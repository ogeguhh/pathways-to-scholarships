<?php
declare(strict_types=1);

/*
 * Pathways to Scholarships
 * Iteration 7 — Import History
 *
 * Authorized roles:
 * - Administrator
 * - Super-admin
 *
 * Displays uploaded scholarship batches,
 * validation counts, and commit/rollback status.
 */

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/includes/security.php';

// Only authenticated administrators can access import history.
// Replaces the previous localhost-only restriction.
require_role(['Administrator', 'Super-admin']);

require_once $projectRoot . '/includes/db.php';

// Retrieve newest import batches first.
$pdo = getDBConnection();

$stmt = $pdo->prepare(
    'SELECT
        import_batch_id,
        file_name,
        contributing_student,
        imported_at,
        committed_at,
        rolled_back_at,
        batch_status,
        total_rows,
        ready_rows,
        warning_rows,
        duplicate_rows,
        error_rows
     FROM import_batches
     ORDER BY import_batch_id DESC'
);

$stmt->execute();

$batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Page title.
$pageTitle = 'Import History | Pathways to Scholarships';

// Shared layout.
require_once $projectRoot . '/includes/header.php';
require_once $projectRoot . '/includes/nav.php';
?>

<main class="container py-5">

    <!-- Page heading -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">

        <div>

            <h1 class="mb-3">
                Scholarship Import History
            </h1>

            <p class="lead mb-0">
                Review uploaded scholarship files, validation results,
                and previous import activity.
            </p>

        </div>

        <div class="d-flex flex-wrap gap-2">

            <a
                href="import.php"
                class="btn btn-outline-primary"
            >
                Import Scholarships
            </a>

            <a
                href="dashboard.php"
                class="btn btn-outline-secondary"
            >
                Back to Admin Dashboard
            </a>

        </div>

    </div>

    <!-- Import history table -->
    <?php if (empty($batches)): ?>

        <div class="alert alert-info">
            No scholarship imports have been recorded yet.
        </div>

    <?php else: ?>

        <div class="table-responsive">

            <table class="table table-striped table-bordered align-middle">

                <thead class="table-light">

                    <tr>
                        <th>Batch</th>
                        <th>File</th>
                        <th>Student</th>
                        <th>Uploaded</th>
                        <th>Status</th>
                        <th>Ready</th>
                        <th>Warning</th>
                        <th>Duplicate</th>
                        <th>Error</th>
                        <th>Details</th>
                    </tr>

                </thead>

                <tbody>

                    <?php foreach ($batches as $batch): ?>

                        <?php
                        $status = (string) (
                            $batch['batch_status'] ?? ''
                        );

                        $badgeColor = match ($status) {
                            'committed' => 'success',
                            'validated' => 'primary',
                            'rolled_back' => 'warning',
                            'failed' => 'danger',
                            default => 'secondary'
                        };
                        ?>

                        <tr>

                            <td>
                                <?= (int) $batch['import_batch_id'] ?>
                            </td>

                            <td>
                                <?= e(
                                    (string) (
                                        $batch['file_name'] ?? ''
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= e(
                                    (string) (
                                        $batch['contributing_student']
                                        ?? 'Unknown'
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= e(
                                    (string) (
                                        $batch['imported_at'] ?? ''
                                    )
                                ) ?>
                            </td>

                            <td>

                                <span class="badge bg-<?= e($badgeColor) ?>">
                                    <?= e($status) ?>
                                </span>

                            </td>

                            <td>
                                <?= (int) ($batch['ready_rows'] ?? 0) ?>
                            </td>

                            <td>
                                <?= (int) ($batch['warning_rows'] ?? 0) ?>
                            </td>

                            <td>
                                <?= (int) ($batch['duplicate_rows'] ?? 0) ?>
                            </td>

                            <td>
                                <?= (int) ($batch['error_rows'] ?? 0) ?>
                            </td>

                            <td>

                                <a
                                    class="btn btn-sm btn-outline-primary"
                                    href="import_validation.php?batch_id=<?= (int) $batch['import_batch_id'] ?>"
                                >
                                    View
                                </a>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

</main>

<?php
require_once $projectRoot . '/includes/footer.php';
?>
