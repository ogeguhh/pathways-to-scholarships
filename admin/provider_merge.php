<?php
declare(strict_types=1);

/*
 * Pathways to Scholarships
 * Iteration 7 — Provider Merge
 *
 * Allows administrators to select a source provider
 * and a destination provider for merging.
 *
 * The actual merge is handled by:
 * provider_merge_save.php
 */

$projectRoot = dirname(__DIR__);

// Load application files.
require_once $projectRoot . '/includes/db.php';
require_once $projectRoot . '/includes/security.php';
require_once $projectRoot . '/includes/config.php';

// Restrict access to administrators.
require_role(['Administrator', 'Super-admin']);

// Database connection.
$pdo = getDBConnection();

// Selected source provider.
$sourceId = filter_input(
    INPUT_GET,
    'source_id',
    FILTER_VALIDATE_INT
);

$sourceId = $sourceId ?: 0;

// Load all providers and scholarship counts.
$sql = "
    SELECT
        p.provider_id,
        p.provider_name,
        p.provider_type_id,
        pt.provider_type_name AS type_name,
        COUNT(s.scholarship_id) AS scholarship_count

    FROM providers AS p

    LEFT JOIN provider_types AS pt
        ON pt.provider_type_id = p.provider_type_id

    LEFT JOIN scholarships AS s
        ON s.provider_id = p.provider_id

    GROUP BY
        p.provider_id,
        p.provider_name,
        p.provider_type_id,
        pt.provider_type_name

    ORDER BY
        p.provider_name ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute();

$providers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Identify selected source provider.
$sourceProvider = null;

foreach ($providers as $provider) {
    if ((int) $provider['provider_id'] === $sourceId) {
        $sourceProvider = $provider;
        break;
    }
}

// Page title.
$pageTitle = 'Merge Providers | Pathways to Scholarships';

// Shared layout.
require_once $projectRoot . '/includes/header.php';
require_once $projectRoot . '/includes/nav.php';
?>

<main class="container py-5">

    <h1 class="mb-3">
        Merge Providers
    </h1>

    <p class="lead">
        Merge duplicate scholarship providers into one
        existing provider record.
    </p>

    <div class="alert alert-warning">
        <strong>Important:</strong>
        Merging providers may update scholarship records
        and remove the duplicate provider.
        Confirm your selections carefully before proceeding.
    </div>

    <?php if (count($providers) < 2): ?>

        <div class="alert alert-info">
            At least two providers are required to perform a merge.
        </div>

    <?php else: ?>

        <div class="card shadow-sm">

            <div class="card-header bg-light">
                <h2 class="h5 mb-0">
                    Select Providers
                </h2>
            </div>

            <div class="card-body">

                <form
                    method="POST"
                    action="provider_merge_save.php"
                >

                    <!-- CSRF protection -->
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e(csrf_token()) ?>"
                    >

                    <!-- Source provider -->
                    <div class="mb-4">

                        <label
                            for="source_provider_id"
                            class="form-label fw-semibold"
                        >
                            Source Provider (Duplicate)
                        </label>

                        <select
                            name="source_provider_id"
                            id="source_provider_id"
                            class="form-select"
                            required
                        >

                            <option value="">
                                Select the provider to merge
                            </option>

                            <?php foreach ($providers as $provider): ?>

                                <option
                                    value="<?= (int) $provider['provider_id'] ?>"
                                    <?= (int) $provider['provider_id'] === $sourceId
                                        ? 'selected'
                                        : '' ?>
                                >
                                    <?= e($provider['provider_name']) ?>
                                    (<?= (int) $provider['scholarship_count'] ?> scholarships)
                                </option>

                            <?php endforeach; ?>

                        </select>

                        <div class="form-text">
                            This provider will be merged into the destination provider.
                        </div>

                    </div>

                    <!-- Destination provider -->
                    <div class="mb-4">

                        <label
                            for="target_provider_id"
                            class="form-label fw-semibold"
                        >
                            Destination Provider (Keep)
                        </label>

                        <select
                            name="target_provider_id"
                            id="target_provider_id"
                            class="form-select"
                            required
                        >

                            <option value="">
                                Select the provider to keep
                            </option>

                            <?php foreach ($providers as $provider): ?>

                                <?php
                                $providerId = (int) $provider['provider_id'];
                                ?>

                                <?php if ($providerId !== $sourceId): ?>

                                    <option value="<?= $providerId ?>">
                                        <?= e($provider['provider_name']) ?>
                                        (<?= (int) $provider['scholarship_count'] ?> scholarships)
                                    </option>

                                <?php endif; ?>

                            <?php endforeach; ?>

                        </select>

                        <div class="form-text">
                            Scholarships should be reassigned to this provider.
                        </div>

                    </div>

                    <!-- Actions -->
                    <div class="d-flex gap-2">

                        <button
                            type="submit"
                            class="btn btn-danger"
                            onclick="return confirm('Are you sure you want to merge these providers? This action may not be reversible.');"
                        >
                            Merge Providers
                        </button>

                        <a
                            href="providers.php"
                            class="btn btn-outline-secondary"
                        >
                            Cancel
                        </a>

                    </div>

                </form>

            </div>

        </div>

    <?php endif; ?>

    <!-- Back to provider management -->
    <div class="mt-4">

        <a
            href="providers.php"
            class="btn btn-outline-primary"
        >
            Back to Provider Management
        </a>

    </div>

</main>

<?php
require_once $projectRoot . '/includes/footer.php';
?>
