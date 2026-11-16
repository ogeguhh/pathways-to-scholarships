<?php
declare(strict_types=1);

/*
 * Pathways to Scholarships
 * Iteration 3 — Scholarship Upload Page
 *
 * Accepts multiple CSV/XLSX files.
 * XLSX files are converted to CSV in the browser using SheetJS.
 * The PHP server validates uploads before staging scholarship rows.
 */

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/includes/config.php';
require_once $projectRoot . '/includes/security.php';
require_once $projectRoot . '/includes/import_service.php';

// Temporary development-only access restriction.
// Replace with administrator authentication before deployment.
$remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';

if (!APP_DEBUG ||
    !in_array($remoteAddress, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Access denied.');
}

$errors = [];
$uploadedBatches = [];

$maxFiles = 5;
$maxFileSize = 5 * 1024 * 1024;
$maxTotalSize = 20 * 1024 * 1024;

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

                $uploadError = $files['error'][$index] ?? UPLOAD_ERR_NO_FILE;
                $fileSize = (int) ($files['size'][$index] ?? 0);
                $temporaryPath = $files['tmp_name'][$index] ?? '';

                if ($uploadError !== UPLOAD_ERR_OK) {
                    $errors[] = $filename . ': Upload failed.';
                    continue;
                }

                if ($fileSize <= 0 || $fileSize > $maxFileSize) {
                    $errors[] = $filename . ': File must be 5 MB or smaller.';
                    continue;
                }

                if (
                    strtolower(pathinfo($filename, PATHINFO_EXTENSION))
                    !== 'csv'
                ) {
                    $errors[] = $filename . ': Server accepts CSV only.';
                    continue;
                }

                if (!is_uploaded_file($temporaryPath)) {
                    $errors[] = $filename . ': Invalid uploaded file.';
                    continue;
                }

                // Reject binary files and content without CSV headings.
                $handle = fopen($temporaryPath, 'rb');

                if ($handle === false) {
                    $errors[] = $filename . ': Cannot read uploaded file.';
                    continue;
                }

                $sample = fread($handle, 4096);
                rewind($handle);
                $header = fgetcsv($handle, 0, ',', '"', '');
                fclose($handle);

                if (
                    $sample === false ||
                    str_contains($sample, "\0") ||
                    $header === false ||
                    count($header) !== 29
                ) {
                    $errors[] = $filename . ': Invalid CSV content or headings.';
                    continue;
                }

                try {
                    // Original name is used to identify the student.
                    $originalName = $_POST['original_names'][$index]
                        ?? $filename;

                    // Reject unexpected filename metadata.
                    if (!is_string($originalName) ||
                        strlen($originalName) > 255) {
                        throw new RuntimeException(
                            'Invalid original filename.'
                        );
                    }

                    $batchId = stageScholarshipCSV(
                        $temporaryPath,
                        $originalName
                    );

                    $uploadedBatches[] = [
                        'id' => $batchId,
                        'name' => $originalName,
                    ];

                } catch (Throwable $error) {
                    error_log(
                        'Scholarship upload failed: ' .
                        $error->getMessage()
                    );

                    $errors[] = $filename .
                        ': Could not process this file. Check its 29-column template.';
                }
            }
        }
    }
}

$pageTitle = 'Import Scholarships | Pathways to Scholarships';

require_once $projectRoot . '/includes/header.php';
require_once $projectRoot . '/includes/nav.php';
require_once $projectRoot . '/includes/dev_banner.php';
?>

<main class="container py-5">

    <h1 class="mb-3">Import Scholarships</h1>

    <p class="lead">
        Upload CSV or Excel scholarship files for cleaning,
        validation, and duplicate detection.
    </p>

    <div class="mb-4">
        <a href="/admin/import_template.php"
           class="btn btn-outline-primary">
            Download 29-Column CSV Template
        </a>

        <a href="/admin/import_history.php"
           class="btn btn-outline-secondary ms-2">
            View Import History
        </a>
    </div>

    <?php if ($errors !== []): ?>
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($uploadedBatches !== []): ?>
        <div class="alert alert-success" role="status">
            <strong>Files staged successfully:</strong>

            <ul class="mb-0 mt-2">
                <?php foreach ($uploadedBatches as $batch): ?>
                    <li>
                        <?= e($batch['name']) ?> —
                        <a href="/admin/import_validation.php?batch_id=<?= (int) $batch['id'] ?>">
                            Review Batch #<?= (int) $batch['id'] ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form
        id="scholarshipImportForm"
        method="post"
        enctype="multipart/form-data"
        class="card p-4"
    >
        <input type="hidden"
               name="csrf_token"
               value="<?= e(csrf_token()) ?>">

        <div class="mb-3">
            <label for="scholarshipFiles" class="form-label">
                Select Scholarship Files
            </label>

            <input
                type="file"
                class="form-control"
                id="scholarshipFiles"
                accept=".csv,.xlsx"
                multiple
                required
            >

            <div class="form-text">
                Maximum 5 files, 5 MB each, 20 MB combined.
                Excel files are converted to CSV before upload.
            </div>
        </div>

        <button type="submit" class="btn btn-primary">
            Upload and Validate
        </button>
    </form>

</main>

<script src="https://cdn.sheetjs.com/xlsx-0.20.3/package/dist/xlsx.full.min.js"></script>

<script>
document.getElementById('scholarshipImportForm')
    .addEventListener('submit', async function (event) {

    event.preventDefault();

    const input = document.getElementById('scholarshipFiles');
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
                const workbook = XLSX.read(buffer, {type: 'array'});

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

            // Check the converted size, not only the original size.
            if (csvFile.size > maxFileSize) {
                alert(file.name + ': Converted CSV exceeds 5 MB.');
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
            body: formData
        });

        const html = await response.text();

        document.open();
        document.write(html);
        document.close();

    } catch (error) {
        alert('Upload failed: ' + error.message);
    }
});
</script>

<?php
require_once $projectRoot . '/includes/footer.php';
?>
