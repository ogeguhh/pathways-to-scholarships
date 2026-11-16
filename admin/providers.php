<?php
declare(strict_types=1);

/*
 * Pathways to Scholarships
 * Iteration 4 — Provider Management
 *
 * Lists scholarship providers and their associated
 * scholarship counts. Administrators can select providers
 * for editing or merging.
 */

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/includes/db.php';
require_once $projectRoot . '/includes/security.php';
require_once $projectRoot . '/includes/config.php';

// Use the existing local-development access restriction.
$remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';

if (!APP_DEBUG ||
    !in_array($remoteAddress, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Access denied.');
}

$pdo = getDBConnection();

// Load providers and count their related scholarships.
// LEFT JOIN includes providers without scholarships.
$stmt = $pdo->prepare(
    'SELECT
        p.provider_id,
        p.provider_name,
        p.provider_type_id,
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
        p.provider_type_id,
        pt.type_name
     ORDER BY p.provider_name'
);

$stmt->execute();
$providers = $stmt->fetchAll();

$pageTitle = 'Manage Providers | Pathways to Scholarships';

require_once $projectRoot . '/includes/header.php';
require_once $projectRoot . '/includes/nav.php';
require_once $projectRoot . '/includes/dev_banner.php';
?>

<main class="container py-5">

    <h1 class="mb-3">Manage Providers</h1>

    <p class="lead">
        Review scholarship providers, correct organization names,
        and merge duplicate provider records.
    </p>

    <div class="table-responsive">
        <table class="table table-striped table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <th>Provider ID</th>
                    <th>Provider Name</th>
                    <th>Provider Type</th>
                    <th>Scholarships</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($providers as $provider): ?>
                    <tr>
                        <td><?= (int) $provider['provider_id'] ?></td>

                        <td><?= e($provider['provider_name']) ?></td>

                        <td><?= e($provider['type_name']) ?></td>

                        <td><?= (int) $provider['scholarship_count'] ?></td>

                        <td>
                            <a href="/admin/provider_form.php?id=<?= (int) $provider['provider_id'] ?>"
                               class="btn btn-sm btn-outline-primary">
                                Edit
                            </a>

                            <a href="/admin/provider_merge.php?source_id=<?= (int) $provider['provider_id'] ?>"
                               class="btn btn-sm btn-outline-secondary">
                                Merge
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</main>

<?php require_once $projectRoot . '/includes/footer.php'; ?>
