<?php
declare(strict_types=1);

/*
 * Pathways to Scholarships
 * Iteration 4 — Provider Merge Form
 *
 * The administrator chooses which provider to keep.
 * The actual merge happens in provider_merge_save.php.
 */

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/includes/db.php';
require_once $projectRoot . '/includes/security.php';
require_once $projectRoot . '/includes/config.php';

// Restrict administration to local development until Iteration 7.
$remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';

if (!APP_DEBUG ||
    !in_array($remoteAddress, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Access denied.');
}

// Start the session before generating HTML or CSRF tokens.
ensure_session();

$sourceId = filter_input(INPUT_GET, 'source_id', FILTER_VALIDATE_INT);

if (!$sourceId || $sourceId < 1) {
    http_response_code(400);
    exit('Invalid provider ID.');
}

$pdo = getDBConnection();

// Count scholarships for each provider.
$stmt = $pdo->query(
    'SELECT
        p.provider_id,
        p.provider_name,
        pt.type_name,
        COUNT(s.scholarship_id) AS scholarship_count
     FROM providers p
     JOIN provider_types pt
       ON pt.provider_type_id = p.provider_type_id
     LEFT JOIN scholarships s
       ON s.provider_id = p.provider_id
     GROUP BY
        p.provider_id,
        p.provider_name,
        pt.type_name
     ORDER BY p.provider_name'
);

$providers = $stmt->fetchAll();

$source = null;

foreach ($providers as $provider) {
    if ((int) $provider['provider_id'] === $sourceId) {
        $source = $provider;
        break;
    }
}

if (!$source) {
    http_response_code(404);
    exit('Provider not found.');
}

$pageTitle = 'Merge Providers | Pathways to Scholarships';

require_once $projectRoot . '/includes/header.php';
require_once $projectRoot . '/includes/nav.php';
require_once $projectRoot . '/includes/dev_banner.php';
?>

<main class="container py-5">

    <h1 class="mb-3">Merge Providers</h1>

    <p>
        Use this form only when two provider records represent
        the same organization.
    </p>

    <div class="alert alert-warning">
        <strong>Important:</strong>
        The provider selected below will be removed after its
        scholarships are reassigned to the provider you keep.
    </div>

    <h2 class="h5 mt-4">Provider to Remove</h2>

    <table class="table table-bordered">
        <tbody>
            <tr>
                <th>Provider ID</th>
                <td><?= (int) $source['provider_id'] ?></td>
            </tr>
            <tr>
                <th>Provider Name</th>
                <td><?= e($source['provider_name']) ?></td>
            </tr>
            <tr>
                <th>Provider Type</th>
                <td><?= e($source['type_name']) ?></td>
            </tr>
            <tr>
                <th>Associated Scholarships</th>
                <td><?= (int) $source['scholarship_count'] ?></td>
            </tr>
        </tbody>
    </table>

    <form action="/admin/provider_merge_save.php"
          method="post"
          onsubmit="return confirm('Merge these providers? The source provider will be removed.');">

        <input type="hidden"
               name="csrf_token"
               value="<?= e(csrf_token()) ?>">

        <input type="hidden"
               name="source_id"
               value="<?= (int) $source['provider_id'] ?>">

        <div class="mb-3">
            <label for="target_id" class="form-label">
                <strong>Provider to Keep</strong>
            </label>

            <select class="form-select"
                    id="target_id"
                    name="target_id"
                    required>

                <option value="">Select the provider to keep</option>

                <?php foreach ($providers as $provider): ?>
                    <?php if ((int) $provider['provider_id'] === $sourceId) {
                        continue;
                    } ?>

                    <option value="<?= (int) $provider['provider_id'] ?>">
                        <?= e($provider['provider_name']) ?>
                        (ID <?= (int) $provider['provider_id'] ?>,
                        <?= (int) $provider['scholarship_count'] ?> scholarships)
                    </option>
                <?php endforeach; ?>
            </select>

            <div class="form-text">
                The retained provider keeps its existing name and type.
                All scholarships from the removed provider will be
                reassigned to it.
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-danger">
                Confirm Merge
            </button>

            <a href="/admin/providers.php"
               class="btn btn-outline-secondary">
                Cancel
            </a>
        </div>

    </form>

</main>

<?php require_once $projectRoot . '/includes/footer.php'; ?>
