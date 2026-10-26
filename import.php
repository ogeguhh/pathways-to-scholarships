<?php
declare(strict_types=1);

// Load security functions, file readers, and scholarship validation.
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/import_reader.php';
require_once __DIR__ . '/includes/import_validator.php';
require_once __DIR__ . '/includes/assignment_validator.php';

// Messages and results displayed on the upload page.
$messages = [];
$results = [];

// Allow up to 10 files, with each file limited to 5 MB.
$maxFile = 5 * 1024 * 1024;
$maxFiles = 10;

// Process uploads only when the form is submitted.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Check the security token to protect against unwanted requests.
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        $messages[] = 'Invalid security token. Reload the page and try again.';

    } elseif (
        empty($_FILES['scholarship_files']['name']) ||
        !is_array($_FILES['scholarship_files']['name'])
    ) {
        $messages[] = 'Choose one or more files.';

    } else {

        $files = $_FILES['scholarship_files'];

        if (count($files['name']) > $maxFiles) {
            $messages[] = 'Maximum 10 files per upload.';

        } elseif (!is_file(__DIR__ . '/includes/db.php')) {
            $messages[] = 'Local database connection missing: includes/db.php';

        } else {

            // Load the PDO database connection.
            require_once __DIR__ . '/includes/db.php';

            // Process each uploaded file separately.
            foreach ($files['name'] as $i => $name) {

                $name = basename((string)$name);

                $result = [
                    'file' => $name,
                    'status' => 'Rejected',
                    'count' => 0,
                    'student' => '',
                    'detail' => ''
                ];

                try {

                    // Check whether PHP successfully received the file.
                    if (
                        ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE)
                        !== UPLOAD_ERR_OK
                    ) {
                        throw new RuntimeException(
                            'Upload failed (PHP error code ' .
                            ($files['error'][$i] ?? '?') . ').'
                        );
                    }

                    // Check the file size.
                    $size = (int)$files['size'][$i];

                    if ($size < 1 || $size > $maxFile) {
                        throw new RuntimeException(
                            'File must be between 1 byte and 5 MB.'
                        );
                    }

                    // Only accept CSV and Excel files.
                    $ext = strtolower(
                        pathinfo($name, PATHINFO_EXTENSION)
                    );

                    if (!in_array($ext, ['csv', 'xlsx'], true)) {
                        throw new RuntimeException(
                            'Only .csv and .xlsx files are accepted.'
                        );
                    }

                    // Get the temporary location of the uploaded file.
                    $path = (string)$files['tmp_name'][$i];

                    if (!is_uploaded_file($path)) {
                        throw new RuntimeException('Invalid uploaded file.');
                    }

                    // Check the actual file signature or contents.
                    if ($ext === 'xlsx') {

                        $fh = fopen($path, 'rb');

                        if ($fh === false) {
                            throw new RuntimeException('Cannot read Excel file.');
                        }

                        $signature = fread($fh, 4);
                        fclose($fh);

                        if ($signature !== "PK\x03\x04") {
                            throw new RuntimeException(
                                'Invalid .xlsx file type.'
                            );
                        }

                    } else {

                        $fh = fopen($path, 'rb');

                        if ($fh === false) {
                            throw new RuntimeException('Cannot read CSV file.');
                        }

                        $sample = fread($fh, 2048);
                        fclose($fh);

                        if (str_contains($sample, "\0")) {
                            throw new RuntimeException(
                                'Invalid CSV file type.'
                            );
                        }
                    }

                    // Read scholarship rows from CSV or Excel.
                    $rows = import_read_rows($path, $ext);

                    if (!$rows) {
                        throw new RuntimeException('No rows found.');
                    }

                    // Check the 29 required column headings.
                    import_check_headers(array_shift($rows));

                    // Identify the student from LastName_FirstName.
                    $student = import_student_from_filename($name);

                    if ($student === null) {
                        throw new RuntimeException(
                            'Rename file LastName_FirstName.csv or LastName_FirstName.xlsx.'
                        );
                    }

                    // Find the student's official assignment, if available.
                    // Unknown students are allowed for testing, but flagged for review.
                    $assignment = assignment_find_student($student);

                    // Count nonempty scholarship records.
                    $count = 0;

                    foreach ($rows as $row) {

                        if (
                            count(array_filter(
                                $row,
                                fn($v) => trim((string)$v) !== ''
                            )) === 0
                        ) {
                            continue;
                        }

                        if (count($row) !== 29) {
                            throw new RuntimeException(
                                'A data row does not have 29 columns.'
                            );
                        }

                        $count++;
                    }

                    if ($count === 0) {
                        throw new RuntimeException(
                            'No scholarship records found.'
                        );
                    }

                    // Begin a transaction so the batch saves together.
                    $pdo->beginTransaction();

                    try {

                        // Create an import batch for this file.
                        $stmt = $pdo->prepare("
                            INSERT INTO import_batches
                            (
                                uploaded_by,
                                file_name,
                                contributing_student,
                                batch_status,
                                total_rows,
                                ready_rows,
                                warning_rows,
                                duplicate_rows,
                                error_rows
                            )
                            VALUES (NULL, ?, ?, 'staged', ?, 0, 0, 0, 0)
                        ");

                        $stmt->execute([
                            $name,
                            $student,
                            $count
                        ]);

                        // Get the ID of the newly created batch.
                        $batchId = (int)$pdo->lastInsertId();

                        // Prepare to save each scholarship's validation result.
                        $stmt = $pdo->prepare("
                            INSERT INTO import_staging_rows
                            (
                                import_batch_id,
                                row_number,
                                raw_data,
                                validation_status,
                                validation_message
                            )
                            VALUES (?, ?, ?, ?, ?)
                        ");

                        // Track the validation results.
                        $readyCount = 0;
                        $warningCount = 0;
                        $errorCount = 0;

                        $line = 1;

                        foreach ($rows as $row) {

                            $line++;

                            // Ignore empty scholarship rows.
                            if (
                                count(array_filter(
                                    $row,
                                    fn($v) => trim((string)$v) !== ''
                                )) === 0
                            ) {
                                continue;
                            }

                            // Match each value with its template heading.
                            $data = array_combine(
                                import_expected_headers(),
                                $row
                            );

                            if ($data === false) {
                                throw new RuntimeException(
                                    'Could not match scholarship columns.'
                                );
                            }

                            // Clean and validate this scholarship.
                            $validation = import_validate_row($data, $pdo);

                            // Compare this scholarship with the student's assignment.
                            // Use cleaned values so full state names can be normalized.
                            if ($assignment === null) {
                                $assignmentMessages = [
                                    'Student was not found in the assignment roster; assignment needs review.'
                                ];
                            } else {
                                $assignmentResult = assignment_check_scholarship(
                                    $validation['data'],
                                    $assignment
                                );
                                $assignmentMessages = $assignmentResult['messages'];
                            }

                            // Assignment problems are warnings, not fatal errors.
                            // Preserve any Error status from required-field validation.
                            if ($assignmentMessages !== []) {
                                $validation['messages'] = array_merge(
                                    $validation['messages'],
                                    $assignmentMessages
                                );
                                if ($validation['status'] === 'Ready') {
                                    $validation['status'] = 'Warning';
                                }
                            }

                            // Preserve the original uploaded information.
                            $json = json_encode(
                                $data,
                                JSON_UNESCAPED_UNICODE |
                                JSON_INVALID_UTF8_SUBSTITUTE |
                                JSON_THROW_ON_ERROR
                            );

                            // Combine validation messages.
                            $message = implode(
                                '; ',
                                $validation['messages']
                            );

                            // Save the scholarship and its validation status.
                            $stmt->execute([
                                $batchId,
                                $line,
                                $json,
                                $validation['status'],
                                $message
                            ]);

                            // Count Ready, Warning, and Error records.
                            if ($validation['status'] === 'Ready') {
                                $readyCount++;

                            } elseif ($validation['status'] === 'Warning') {
                                $warningCount++;

                            } else {
                                $errorCount++;
                            }
                        }

                        // Update the totals for the uploaded batch.
                        $update = $pdo->prepare("
                            UPDATE import_batches
                            SET
                                ready_rows = ?,
                                warning_rows = ?,
                                error_rows = ?
                            WHERE import_batch_id = ?
                        ");

                        $update->execute([
                            $readyCount,
                            $warningCount,
                            $errorCount,
                            $batchId
                        ]);

                        // Save the complete staging transaction.
                        $pdo->commit();

                    } catch (Throwable $e) {

                        // Undo partial staging changes if something fails.
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }

                        throw $e;
                    }

                    // Prepare the result shown to the user.
                    $detail = $count === 20
                        ? '20 scholarships received.'
                        : 'Expected 20 scholarships; received ' .
                          $count . '. Review student submission.';

                    if ($assignment === null) {
                        $detail .= ' Student assignment not found in roster; review required.';
                    }

                    $detail .= ' Ready: ' . $readyCount .
                               ', Warning: ' . $warningCount .
                               ', Error: ' . $errorCount . '.';

                    $result = [
                        'file' => $name,
                        'status' => 'Staged',
                        'count' => $count,
                        'student' => $student,
                        'detail' => $detail
                    ];

                } catch (Throwable $e) {

                    // Show the upload error without exposing database details.
                    error_log('Scholarship import error: ' . $e->getMessage());

                    $result['detail'] =
                        'The file could not be imported. Check its format and try again.';
                }

                $results[] = $result;
            }
        }
    }
}
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Scholarship Import | Pathways to Scholarships</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
</head>

<body class="bg-light">

<main class="container py-5" style="max-width:950px">

    <a href="index.php" class="text-decoration-none">
        &larr; Home
    </a>

    <h1 class="mt-3">Import scholarship files</h1>

    <p class="text-muted">
        Iteration 3: Download scholarship templates, upload CSV or Excel
        files, and validate scholarship records. Uploaded records are
        staged for review and are not yet published as scholarships.
    </p>

    <!-- Download scholarship templates. -->
    <section class="card mb-4">
        <div class="card-body">

            <h2 class="h5">1. Download a template</h2>

            <a
                class="btn btn-outline-primary me-2"
                href="templates/scholarship_import_template.xlsx"
                download
            >
                Download Excel template
            </a>

            <a
                class="btn btn-outline-secondary"
                href="templates/scholarship_import_template.csv"
                download
            >
                Download CSV template
            </a>

            <p class="small text-muted mt-2 mb-0">
                Keep all 29 column headings unchanged.
                Use a filename like LastName_FirstName.csv.
            </p>

        </div>
    </section>

    <!-- Scholarship upload form. -->
    <section class="card mb-4">
        <div class="card-body">

            <h2 class="h5">2. Upload files</h2>

            <form method="post" enctype="multipart/form-data">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e(csrf_token()) ?>"
                >

                <label class="form-label" for="scholarship_files">
                    CSV or Excel files (up to 10, max 5 MB each)
                </label>

                <input
                    class="form-control mb-3"
                    type="file"
                    name="scholarship_files[]"
                    id="scholarship_files"
                    accept=".csv,.xlsx"
                    multiple
                    required
                >

                <button class="btn btn-primary" type="submit">
                    Upload to staging
                </button>

            </form>

        </div>
    </section>

    <!-- Display upload warnings. -->
    <?php foreach ($messages as $message): ?>

        <div class="alert alert-warning">
            <?= e($message) ?>
        </div>

    <?php endforeach; ?>

    <!-- Display results after uploading. -->
    <?php if ($results): ?>

        <section class="card">
            <div class="card-body">

                <h2 class="h5">3. Upload results</h2>

                <div class="table-responsive">

                    <table class="table table-striped">

                        <thead>
                            <tr>
                                <th>File</th>
                                <th>Student</th>
                                <th>Rows</th>
                                <th>Status</th>
                                <th>Details</th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php foreach ($results as $r): ?>

                            <tr>
                                <td><?= e($r['file']) ?></td>
                                <td><?= e($r['student']) ?></td>
                                <td><?= e($r['count']) ?></td>
                                <td><?= e($r['status']) ?></td>
                                <td><?= e($r['detail']) ?></td>
                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>
            </div>
        </section>

    <?php endif; ?>

</main>

</body>
</html>
