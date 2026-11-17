<?php
declare(strict_types=1);

/*
 * Pathways to Scholarships
 * Iteration 7 — Downloadable Import Template
 *
 * Authorized roles:
 * - Administrator
 * - Super-admin
 *
 * Generates an empty CSV template containing
 * the 29 required scholarship column headings.
 */

$projectRoot = dirname(__DIR__);

// Load security functions.
require_once $projectRoot . '/includes/security.php';

// Restrict template downloads to authorized administrators.
require_role(['Administrator', 'Super-admin']);

// Load the existing scholarship field definitions.
require_once $projectRoot . '/includes/import_fields.php';

// Tell the browser to download a CSV file.
header('Content-Type: text/csv; charset=UTF-8');
header(
    'Content-Disposition: attachment; filename="scholarships_import_template.csv"'
);
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

// Open the output stream.
$output = fopen('php://output', 'wb');

if ($output === false) {
    http_response_code(500);
    exit('Unable to generate the template.');
}

// UTF-8 BOM helps Excel recognize special characters.
fwrite($output, "\xEF\xBB\xBF");

// Write the existing 29 scholarship headings.
// No scholarship data is included in the template.
fputcsv(
    $output,
    scholarshipImportFields(),
    ',',
    '"',
    ''
);

// Close the stream.
fclose($output);

exit;
