<?php
declare(strict_types=1);

/*
 * Pathways to Scholarships
 * Iteration 7 — Provider Management
 *
 * Features:
 * - Administrator/Super-admin authorization
 * - Display all scholarship providers
 * - Show provider types
 * - Count scholarships for each provider
 * - Edit provider information
 * - Merge duplicate providers
 */

$projectRoot = dirname(__DIR__);

// Required application files.
require_once $projectRoot . '/includes/db.php';
require_once $projectRoot . '/includes/security.php';
require_once $projectRoot . '/includes/config.php';

// Only administrators and super-admins can access this page.
require_role(['Administrator', 'Super-admin']);

// Database connection.
$pdo = getDBConnection();

// Retrieve providers and their scholarship counts.
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

// Page title.
$pageTitle = 'Manage Providers | Pathways to Scholarships';

// Shared layout.
require_once $projectRoot . '/includes/header.php';
require_once $projectRoot . '/includes/nav.php';
?>

<main class="container py-5">

    <div class="mb-4">

        <h1 class="mb-3">
            Manage Providers
        </h1>

        <p class="lead">
            Review scholarship providers, correct organization
            names, and merge duplicate provider records.
        </p>

    </div>

    <div class="card shadow-sm">

        <div class="card-header bg-light">

            <h2 class="h5 mb-0">
                Scholarship Providers
            </h2>

        </div>

        <div class="card-body">

            <p class="text-muted">
                Total Providers:
                <strong>
                    <?= count($providers) ?>
                </strong>
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

                        <?php if (empty($providers)): ?>

                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    No providers found.
                                </td>
                            </tr>

                        <?php else: ?>

                            <?php foreach ($providers as $provider): ?>

                                <tr>

                                    <td>
                                        <?= (int) $provider['provider_id'] ?>
                                    </td>

                                    <td>
                                        <?= e($provider['provider_name']) ?>
                                    </td>

                                    <td>
                                        <?= e($provider['type_name'] ?? 'Unknown') ?>
                                    </td>

                                    <td>
                                        <?= (int) $provider['scholarship_count'] ?>
                                    </td>

                                    <td>

                                        <div class="d-flex gap-2">

                                            <a
                                                href="provider_form.php?id=<?= (int) $provider['provider_id'] ?>"
                                                class="btn btn-sm btn-outline-primary"
                                            >
                                                Edit
                                            </a>

                                            <a
                                                href="provider_merge.php?source_id=<?= (int) $provider['provider_id'] ?>"
                                                class="btn btn-sm btn-outline-secondary"
                                            >
                                                Merge
                                            </a>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

    <div class="mt-4">

        <a
            href="dashboard.php"
            class="btn btn-outline-secondary"
        >
            Back to Dashboard
        </a>

    </div>

</main>

<?php
require_once $projectRoot . '/includes/footer.php';
?>
