<?php
declare(strict_types=1);

/*
 * Pathways to Scholarships
 * Iteration 7 — Scholarship Import
 *
 * Authorized roles:
 * - Administrator
 * - Super-admin
 *
 * Supports:
 * - Multiple CSV and XLSX files
 * - Excel-to-CSV conversion using SheetJS
 * - Upload validation
 * - 29-column scholarship template
 * - Import batch staging
 */

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/includes/security.php';

// Protect both the upload page and POST requests.
// This replaces the development-only localhost restriction.
require_role(['Administrator', 'Super-admin']);

require_once $projectRoot . '/includes/db.php';
require_once $projectRoot . '/includes/import_service.php';

// Resolve URLs relative to this project's folder.
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

$errors = [];
$uploadedBatches = [];

$maxFiles = 5;
$maxFileSize = 5 * 1024 * 1024;
$maxTotalSize = 20 * 1024 * 1024;

// Process scholarship uploads.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {

        $errors[] = 'Invalid form submission. Refresh and try again.';

    } elseif (
        !isset($_FILES['scholarship_files']) ||
        !is_array($_FILES['scholarship_files']['name'] ?? null)
    ) {

        $errors[] = 'Please select at least one file.';

    } else {

        $files = $_FILES['scholarship_files'];
        $fileCount = count($files['name']);

        if ($fileCount < 1 || $fileCount > $maxFiles) {

            $errors[] = 'Upload between 1 and 5 files at a time.';

        } elseif (array_sum($files['size']) > $maxTotalSize) {

            $errors[] = 'Combined upload size exceeds 20 MB.';

        } else {

            foreach ($files['name'] as $index => $filename) {

                $uploadError = $files['error'][$index]
                    ?? UPLOAD_ERR_NO_FILE;

                $fileSize = (int) (
                    $files['size'][$index] ?? 0
                );

                $temporaryPath = $files['tmp_name'][$index] ?? '';

                // Validate PHP upload status.
                if ($uploadError !== UPLOAD_ERR_OK) {

                    $errors[] = $filename . ': Upload failed.';
                    continue;
                }

                // Validate file size.
                if (
                    $fileSize <= 0 ||
                    $fileSize > $maxFileSize
                ) {

                    $errors[] = $filename .
                        ': File must be 5 MB or smaller.';

                    continue;
                }

                // The server processes CSV files.
                // XLSX files are converted in the browser.
                if (
                    strtolower(
                        pathinfo(
                            $filename,
                            PATHINFO_EXTENSION
                        )
                    ) !== 'csv'
                ) {

                    $errors[] = $filename .
                        ': Server accepts CSV only.';

                    continue;
                }

                // Confirm the file came through PHP upload.
                if (!is_uploaded_file($temporaryPath)) {

                    $errors[] = $filename .
                        ': Invalid uploaded file.';

                    continue;
                }

                // Inspect CSV contents and header count.
                $handle = fopen($temporaryPath, 'rb');

                if ($handle === false) {

                    $errors[] = $filename .
                        ': Cannot read uploaded file.';

                    continue;
                }

                $sample = fread($handle, 4096);

                rewind($handle);

                $header = fgetcsv(
                    $handle,
                    0,
                    ',',
                    '"',
                    ''
                );

                fclose($handle);

                // Reject binary data and incorrect templates.
                if (
                    $sample === false ||
                    str_contains($sample, "\0") ||
                    $header === false ||
                    count($header) !== 29
                ) {

                    $errors[] = $filename .
                        ': Invalid CSV content or headings.';

                    continue;
                }

                try {

                    // Preserve the original filename for
                    // student attribution and import history.
                    $originalName = $_POST['original_names'][$index]
                        ?? $filename;

                    if (
                        !is_string($originalName) ||
                        strlen($originalName) > 255 ||
                        trim($originalName) === ''
                    ) {

                        throw new RuntimeException(
                            'Invalid original filename.'
                        );
                    }

                    // Existing Iteration 3 import service.
                    $batchId = stageScholarshipCSV(
                        $temporaryPath,
                        $originalName
                    );

                    $uploadedBatches[] = [
                        'id' => $batchId,
                        'name' => $originalName
                    ];

                } catch (Throwable $error) {

                    error_log(
                        'Scholarship upload failed: ' .
                        $error->getMessage()
                    );

                    $errors[] = $filename .
                        ': Could not process this file. ' .
                        'Check its 29-column template.';
                }
            }
        }
    }
}

// Browser title.
$pageTitle = 'Import Scholarships | Pathways to Scholarships';

// Shared layout.
require_once $projectRoot . '/includes/header.php';
require_once $projectRoot . '/includes/nav.php';
?>

<main class="container py-5">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">

        <div>
            <h1 class="mb-3">Import Scholarships</h1>

            <p class="lead mb-0">
                Upload CSV or Excel scholarship files for cleaning,
                validation, and duplicate detection.
            </p>
        </div>

        <a
            href="<?= e($baseUrl . '/admin/dashboard.php') ?>"
            class="btn btn-outline-secondary"
        >
            Back to Admin Dashboard
        </a>

    </div>

    <!-- Import actions -->
    <div class="mb-4">

        <a
            href="<?= e($baseUrl . '/admin/import_template.php') ?>"
            class="btn btn-outline-primary"
        >
            Download 29-Column CSV Template
        </a>

        <a
            href="<?= e($baseUrl . '/admin/import_history.php') ?>"
            class="btn btn-outline-secondary ms-2"
        >
            View Import History
        </a>

    </div>

    <!-- Upload errors -->
    <?php if ($errors !== []): ?>

        <div class="alert alert-danger" role="alert">

            <ul class="mb-0">

                <?php foreach ($errors as $error): ?>

                    <li><?= e($error) ?></li>

                <?php endforeach; ?>

            </ul>

        </div>

    <?php endif; ?>

    <!-- Successful uploads -->
    <?php if ($uploadedBatches !== []): ?>

        <div class="alert alert-success" role="status">

            <strong>
                Files staged successfully:
            </strong>

            <ul class="mb-0 mt-2">

                <?php foreach ($uploadedBatches as $batch): ?>

                    <li>

                        <?= e($batch['name']) ?> —

                        <a
                            href="<?= e(
                                $baseUrl . '/admin/import_validation.php'
                            ) ?>?batch_id=<?= (int) $batch['id'] ?>"
                        >
                            Review Batch #<?= (int) $batch['id'] ?>
                        </a>

                    </li>

                <?php endforeach; ?>

            </ul>

        </div>

    <?php endif; ?>

    <!-- Upload form -->
    <form
        id="scholarshipImportForm"
        method="post"
        enctype="multipart/form-data"
        class="card p-4"
    >

        <input
            type="hidden"
            name="csrf_token"
            value="<?= e(csrf_token()) ?>"
        >

        <div class="mb-3">

            <label
                for="scholarshipFiles"
                class="form-label"
            >
                Select Scholarship Files
            </label>

            <input
                type="file"
                class="form-control"
                id="scholarshipFiles"
                name="scholarship_files[]"
                accept=".csv,.xlsx"
                multiple
                required
            >

            <div class="form-text">
                Maximum 5 files, 5 MB each, 20 MB combined.
                Excel files are converted to CSV before upload.
            </div>

        </div>

        <button
            type="submit"
            class="btn btn-primary"
            id="uploadButton"
        >
            Upload and Validate
        </button>

    </form>

</main>

<!-- Excel conversion library -->
<script src="https://cdn.sheetjs.com/xlsx-0.20.3/package/dist/xlsx.full.min.js"></script>

<script>
document.getElementById('scholarshipImportForm')
    .addEventListener('submit', async function (event) {

    event.preventDefault();

    const input = document.getElementById('scholarshipFiles');
    const button = document.getElementById('uploadButton');
    const files = Array.from(input.files);

    const maxFiles = 5;
    const maxFileSize = 5 * 1024 * 1024;
    const maxTotalSize = 20 * 1024 * 1024;

    if (files.length < 1 || files.length > maxFiles) {
        alert('Select between 1 and 5 files.');
        return;
    }

    const formData = new FormData();

    formData.append(
        'csrf_token',
        document.querySelector('[name="csrf_token"]').value
    );

    let totalSize = 0;

    button.disabled = true;
    button.textContent = 'Processing Files...';

    try {

        for (const file of files) {

            if (file.size <= 0 || file.size > maxFileSize) {
                alert(file.name + ': File must be 5 MB or smaller.');
                return;
            }

            let csvFile;

            if (file.name.toLowerCase().endsWith('.xlsx')) {

                if (typeof XLSX === 'undefined') {
                    alert('Excel conversion library is unavailable.');
                    return;
                }

                const buffer = await file.arrayBuffer();

                const workbook = XLSX.read(buffer, {
                    type: 'array'
                });

                const worksheetName =
                    workbook.SheetNames.includes('Sheet2')
                        ? 'Sheet2'
                        : workbook.SheetNames[0];

                const worksheet = workbook.Sheets[worksheetName];

                const csv = XLSX.utils.sheet_to_csv(worksheet, {
                    blankrows: false
                });

                csvFile = new File(
                    [csv],
                    file.name.replace(/\.xlsx$/i, '.csv'),
                    {type: 'text/csv'}
                );

            } else if (file.name.toLowerCase().endsWith('.csv')) {

                csvFile = file;

            } else {

                alert('Unsupported file type: ' + file.name);
                return;
            }

            // Check converted file size.
            if (csvFile.size <= 0 || csvFile.size > maxFileSize) {
                alert(file.name + ': Converted CSV must be 5 MB or smaller.');
                return;
            }

            totalSize += csvFile.size;

            if (totalSize > maxTotalSize) {
                alert('Combined upload size exceeds 20 MB.');
                return;
            }

            formData.append('scholarship_files[]', csvFile);
            formData.append('original_names[]', file.name);
        }

        const response = await fetch(window.location.pathname, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });

        // Avoid replacing the page with a login or error response.
        if (!response.ok) {
            throw new Error(
                'Server returned HTTP ' + response.status
            );
        }

        const html = await response.text();

        document.open();
        document.write(html);
        document.close();

    } catch (error) {

        alert('Upload failed: ' + error.message);

    } finally {

        button.disabled = false;
        button.textContent = 'Upload and Validate';
    }
});
</script>

<?php
require_once $projectRoot . '/includes/footer.php';
?>
