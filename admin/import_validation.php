<?php
declare(strict_types=1);

/*
 * Pathways to Scholarships
 * Iteration 7 — Import Validation Report
 *
 * Access:
 * - Administrator
 * - Super-admin
 *
 * Displays staged scholarship records,
 * validation results, and batch controls.
 */

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/includes/security.php';

// Require an authenticated administrator.
// Replaces the previous APP_DEBUG / localhost restriction.
require_role(['Administrator', 'Super-admin']);

require_once $projectRoot . '/includes/db.php';

// Require a valid numeric batch ID.
$batchId = filter_input(
    INPUT_GET,
    'batch_id',
    FILTER_VALIDATE_INT
);

if (
    $batchId === false ||
    $batchId === null ||
    $batchId < 1
) {
    http_response_code(400);
    exit('A valid import batch ID is required.');
}

$pdo = getDBConnection();

// Determine the project base URL.
$baseUrl = rtrim(
    str_replace(
        '\\',
        '/',
        dirname(dirname($_SERVER['SCRIPT_NAME'] ?? ''))
    ),
    '/'
);

if ($baseUrl === '/' || $baseUrl === '.') {
    $baseUrl = '';
}

// Retrieve the import batch.
$batchStmt = $pdo->prepare(
    'SELECT *
     FROM import_batches
     WHERE import_batch_id = :batch_id'
);

$batchStmt->execute([
    ':batch_id' => $batchId
]);

$batch = $batchStmt->fetch(PDO::FETCH_ASSOC);

if (!$batch) {
    http_response_code(404);
    exit('Import batch not found.');
}

// Retrieve staged scholarship records.
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

$rowStmt->execute([
    ':batch_id' => $batchId
]);

$rows = $rowStmt->fetchAll(PDO::FETCH_ASSOC);

// Batch status and totals.
$batchStatus = (string) ($batch['batch_status'] ?? '');

$readyRows = (int) ($batch['ready_rows'] ?? 0);
$warningRows = (int) ($batch['warning_rows'] ?? 0);

// Validation summary.
$summary = [
    ['Total Rows', $batch['total_rows'] ?? 0, 'secondary'],
    ['Ready', $readyRows, 'success'],
    ['Warning', $warningRows, 'warning'],
    ['Duplicate', $batch['duplicate_rows'] ?? 0, 'info'],
    ['Error', $batch['error_rows'] ?? 0, 'danger']
];

// Page title.
$pageTitle = 'Import Validation | Pathways to Scholarships';

// Shared layout.
require_once $projectRoot . '/includes/header.php';
require_once $projectRoot . '/includes/nav.php';
?>

<main class="container py-5">

    <!-- Page heading -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">

        <div>

            <h1 class="mb-3">
                Import Validation Report
            </h1>

            <p class="lead mb-0">
                Review scholarship records before committing
                them to the database.
            </p>

        </div>

        <a
            href="<?= e($baseUrl . '/admin/import_history.php') ?>"
            class="btn btn-outline-secondary"
        >
            Back to Import History
        </a>

    </div>

    <!-- Batch information -->
    <div class="card mb-4">

        <div class="card-body">

            <h2 class="h5">
                Batch #<?= (int) $batchId ?>
            </h2>

            <p class="mb-1">

                <strong>File:</strong>

                <?= e((string) ($batch['file_name'] ?? 'Unknown')) ?>

            </p>

            <p class="mb-1">

                <strong>Contributing Student:</strong>

                <?= e(
                    (string) (
                        $batch['contributing_student']
                        ?? 'Not identified'
                    )
                ) ?>

            </p>

            <p class="mb-0">

                <strong>Batch Status:</strong>

                <?= e($batchStatus) ?>

            </p>

        </div>

    </div>

    <!-- Commit or rollback controls -->
    <?php if (
        $batchStatus === 'validated' &&
        ($readyRows + $warningRows) > 0
    ): ?>

        <form
            method="post"
            action="<?= e($baseUrl . '/admin/import_commit.php') ?>"
            class="mb-4"
            onsubmit="return confirm('Commit the valid scholarships in this batch?');"
        >

            <input
                type="hidden"
                name="batch_id"
                value="<?= (int) $batchId ?>"
            >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e(csrf_token()) ?>"
            >

            <button
                type="submit"
                class="btn btn-success"
            >
                Commit Batch
            </button>

        </form>

    <?php elseif ($batchStatus === 'committed'): ?>

        <form
            method="post"
            action="<?= e($baseUrl . '/admin/import_rollback.php') ?>"
            class="mb-4"
            onsubmit="return confirm('Roll back this batch and remove its imported scholarships?');"
        >

            <input
                type="hidden"
                name="batch_id"
                value="<?= (int) $batchId ?>"
            >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e(csrf_token()) ?>"
            >

            <button
                type="submit"
                class="btn btn-danger"
            >
                Rollback Batch
            </button>

        </form>

    <?php endif; ?>

    <!-- Validation summary cards -->
    <div class="row g-3 mb-4">

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

    <!-- Staged scholarship records -->
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

                <?php if ($rows === []): ?>

                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">
                            No staged records found for this batch.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($rows as $row): ?>

                        <?php
                        $data = json_decode(
                            (string) $row['raw_data'],
                            true
                        );

                        if (!is_array($data)) {
                            $data = [];
                        }

                        $status = (string) (
                            $row['validation_status'] ?? ''
                        );

                        $badgeColor = match ($status) {
                            'Ready' => 'success',
                            'Warning' => 'warning',
                            'Duplicate' => 'info',
                            'Error' => 'danger',
                            default => 'secondary'
                        };
                        ?>

                        <tr>

                            <td>
                                <?= (int) $row['row_number'] ?>
                            </td>

                            <td>
                                <?= e(
                                    (string) (
                                        $data['Scholarship name'] ?? ''
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= e(
                                    (string) (
                                        $data['Scholarship provider/organization']
                                        ?? ''
                                    )
                                ) ?>
                            </td>

                            <td>

                                <span class="badge bg-<?= e($badgeColor) ?>">
                                    <?= e($status) ?>
                                </span>

                            </td>

                            <td>
                                <?= e(
                                    (string) (
                                        $row['validation_message']
                                        ?: 'No issues found.'
                                    )
                                ) ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

    <!-- Batch status explanation -->
    <p class="text-muted small mt-3">

        <?php if ($batchStatus === 'committed'): ?>

            This batch has been committed to the scholarship database.

        <?php elseif ($batchStatus === 'rolled_back'): ?>

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
