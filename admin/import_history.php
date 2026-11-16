<?php
declare(strict_types=1);

/*
 * Pathways to Scholarships
 * Iteration 3 — Import History
 *
 * Displays previously uploaded scholarship batches,
 * including validation counts and commit/rollback status.
 */

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/includes/db.php';
require_once $projectRoot . '/includes/security.php';
require_once $projectRoot . '/includes/config.php';

// Temporary local-development restriction.
// Authentication and role-based access arrive in Iteration 7.
$remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';

if (!APP_DEBUG ||
    !in_array($remoteAddress, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Access denied.');
}

$pdo = getDBConnection();

// Retrieve newest import batches first.
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
$batches = $stmt->fetchAll();

$pageTitle = 'Import History | Pathways to Scholarships';

require_once $projectRoot . '/includes/header.php';
require_once $projectRoot . '/includes/nav.php';
require_once $projectRoot . '/includes/dev_banner.php';
?>

<main class="container py-5">

    <h1 class="mb-3">Scholarship Import History</h1>

    <p class="lead">
        Review uploaded scholarship files, validation results,
        and previous import activity.
    </p>

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
                        $status = $batch['batch_status'];

                        $badgeColor = match ($status) {
                            'committed' => 'success',
                            'validated' => 'primary',
                            'rolled_back' => 'warning',
                            'failed' => 'danger',
                            default => 'secondary',
                        };
                        ?>

                        <tr>
                            <td>
                                <?= (int) $batch['import_batch_id'] ?>
                            </td>

                            <td>
                                <?= e($batch['file_name']) ?>
                            </td>

                            <td>
                                <?= e($batch['contributing_student'] ?? 'Unknown') ?>
                            </td>

                            <td>
                                <?= e($batch['imported_at']) ?>
                            </td>

                            <td>
                                <span class="badge bg-<?= e($badgeColor) ?>">
                                    <?= e($status) ?>
                                </span>
                            </td>

                            <td><?= (int) $batch['ready_rows'] ?></td>
                            <td><?= (int) $batch['warning_rows'] ?></td>
                            <td><?= (int) $batch['duplicate_rows'] ?></td>
                            <td><?= (int) $batch['error_rows'] ?></td>

                            <td>
                                <a
                                    class="btn btn-sm btn-outline-primary"
                                    href="/admin/import_validation.php?batch_id=<?= (int) $batch['import_batch_id'] ?>"
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
