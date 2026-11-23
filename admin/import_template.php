<?php
declare(strict_types=1);

/*
 * Pathways to Scholarships
 * Iteration 3 — Downloadable Import Template
 *
 * Generates a CSV containing the professor's
 * 29 required scholarship column headings.
 */

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/includes/config.php';
require_once $projectRoot . '/includes/import_fields.php';

// Temporary development-only restriction.
require_once $projectRoot . '/includes/security.php';

require_role('Administrator');

// Tell the browser to download a CSV file.
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="scholarships_import_template.csv"');
header('Cache-Control: no-store');

// Write CSV content directly to the browser response.
$output = fopen('php://output', 'wb');

if ($output === false) {
    http_response_code(500);
    exit('Unable to generate the template.');
}

// UTF-8 BOM helps Excel recognize special characters.
fwrite($output, "\xEF\xBB\xBF");

// Write the 29 headings as the first row.
fputcsv($output, scholarshipImportFields(), ',', '"', '');

fclose($output);
exit;
