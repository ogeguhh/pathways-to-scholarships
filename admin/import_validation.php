<?php
declare(strict_types=1);

/*
 * Pathways to Scholarships
 * Iteration 3 — Import Validation Report
 *
 * Displays staged scholarship records and validation results.
 * Administrative access is restricted to local development.
 */

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/includes/db.php';
require_once $projectRoot . '/includes/security.php';
require_once $projectRoot . '/includes/config.php';

// Until authentication is implemented, only allow local requests.
// This is a temporary development restriction, not production security.
require_role('Administrator');

// Require a valid numeric batch ID.
$batchId = filter_input(INPUT_GET, 'batch_id', FILTER_VALIDATE_INT);

if ($batchId === false || $batchId === null || $batchId < 1) {
    http_response_code(400);
    exit('A valid import batch ID is required.');
}

$pdo = getDBConnection();

// Retrieve the import batch.
$batchStmt = $pdo->prepare(
    'SELECT *
     FROM import_batches
     WHERE import_batch_id = :batch_id'
);

$batchStmt->execute([':batch_id' => $batchId]);
$batch = $batchStmt->fetch();

if (!$batch) {
    http_response_code(404);
    exit('Import batch not found.');
}

// Retrieve individual staged scholarship records.
$rowStmt = $pdo->prepare(
    'SELECT
        `row_number`,
        raw_data,
        validation_status,
        validation_message
     FROM import_staging_rows
     WHERE import_batch_id = :batch_id
     ORDER BY `row_number`'
);

$rowStmt->execute([':batch_id' => $batchId]);
$rows = $rowStmt->fetchAll();

$pageTitle = 'Import Validation | Pathways to Scholarships';

require_once $projectRoot . '/includes/header.php';
require_once $projectRoot . '/includes/nav.php';
?>

<main class="container py-5">

    <h1 class="mb-3">Import Validation Report</h1>

    <p class="lead">
        Review scholarship records before committing them
        to the database.
    </p>

    <div class="card mb-4">
        <div class="card-body">
            <h2 class="h5">Batch #<?= (int) $batchId ?></h2>

            <p class="mb-1">
                <strong>File:</strong>
                <?= e($batch['file_name']) ?>
            </p>

            <p class="mb-1">
                <strong>Contributing Student:</strong>
                <?= e($batch['contributing_student'] ?? 'Not identified') ?>
            </p>

            <p class="mb-0">
                <strong>Batch Status:</strong>
                <?= e($batch['batch_status']) ?>
            </p>
        </div>
    </div>

    <!-- Commit and rollback controls for local development. -->
    <?php if (
        $batch['batch_status'] === 'validated' &&
        ((int) $batch['ready_rows'] + (int) $batch['warning_rows']) > 0
    ): ?>
        <form method="post"
              action="import_commit.php"
              class="mb-4"
              onsubmit="return confirm('Commit the valid scholarships in this batch?');">

            <input type="hidden"
                   name="batch_id"
                   value="<?= (int) $batchId ?>">

            <input type="hidden"
                   name="csrf_token"
                   value="<?= e(csrf_token()) ?>">

            <button type="submit" class="btn btn-success">
                Commit Batch
            </button>
        </form>

    <?php elseif ($batch['batch_status'] === 'committed'): ?>
        <form method="post"
              action="import_rollback.php"
              class="mb-4"
              onsubmit="return confirm('Roll back this batch and remove its imported scholarships?');">

            <input type="hidden"
                   name="batch_id"
                   value="<?= (int) $batchId ?>">

            <input type="hidden"
                   name="csrf_token"
                   value="<?= e(csrf_token()) ?>">

            <button type="submit" class="btn btn-danger">
                Rollback Batch
            </button>
        </form>
    <?php endif; ?>

    <!-- Summary of validation results -->
    <div class="row g-3 mb-4">

        <?php
        $summary = [
            ['Total Rows', $batch['total_rows'], 'secondary'],
            ['Ready', $batch['ready_rows'], 'success'],
            ['Warning', $batch['warning_rows'], 'warning'],
            ['Duplicate', $batch['duplicate_rows'], 'info'],
            ['Error', $batch['error_rows'], 'danger'],
        ];
        ?>

        <?php foreach ($summary as [$label, $count, $color]): ?>
            <div class="col-6 col-md">
                <div class="card h-100 border-<?= e($color) ?>">
                    <div class="card-body text-center">
                        <div class="fs-3 fw-bold">
                            <?= (int) $count ?>
                        </div>
                        <div class="small">
                            <?= e($label) ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

    </div>

    <!-- Each row shows its validation result and any problems. -->
    <div class="table-responsive">
        <table class="table table-striped table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <th>CSV Row</th>
                    <th>Scholarship Name</th>
                    <th>Provider</th>
                    <th>Status</th>
                    <th>Validation Message</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $data = json_decode($row['raw_data'], true) ?? [];
                    $status = $row['validation_status'];

                    $badgeColor = match ($status) {
                        'Ready' => 'success',
                        'Warning' => 'warning',
                        'Duplicate' => 'info',
                        'Error' => 'danger',
                        default => 'secondary',
                    };
                    ?>

                    <tr>
                        <td><?= (int) $row['row_number'] ?></td>

                        <td>
                            <?= e($data['Scholarship name'] ?? '') ?>
                        </td>

                        <td>
                            <?= e($data['Scholarship provider/organization'] ?? '') ?>
                        </td>

                        <td>
                            <span class="badge bg-<?= e($badgeColor) ?>">
                                <?= e($status) ?>
                            </span>
                        </td>

                        <td>
                            <?= e($row['validation_message'] ?: 'No issues found.') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <p class="text-muted small mt-3">
        <?php if ($batch['batch_status'] === 'committed'): ?>
            This batch has been committed to the scholarship database.
        <?php elseif ($batch['batch_status'] === 'rolled_back'): ?>
            This batch was rolled back. Its staging records remain
            available for review.
        <?php else: ?>
            This report displays staged records.
            This batch has not been committed.
        <?php endif; ?>
    </p>

</main>

<?php
require_once $projectRoot . '/includes/footer.php';
?>
