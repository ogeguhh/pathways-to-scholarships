<?php
declare(strict_types=1);

/*
 * Pathways to Scholarships
 * Iteration 3 — CSV Import Reader
 *
 * Reads CSV data and checks the professor's official 29-column
 * scholarship template before staging any records.
 */

require_once __DIR__ . '/import_fields.php';

// Clean a column heading without changing its intended wording.
function cleanImportHeading(string $heading): string
{
    // Remove a UTF-8 byte-order mark if Excel included one.
    $heading = preg_replace('/^\xEF\xBB\xBF/', '', $heading);

    // Replace nonbreaking spaces with ordinary spaces.
    $heading = str_replace("\xC2\xA0", ' ', $heading);

    // Normalize repeated spaces, tabs, and line breaks.
    $heading = preg_replace('/\s+/u', ' ', $heading);

    return trim($heading);
}

// Read a CSV file and return its scholarship records.
function readScholarshipCSV(string $filePath): array
{
    $handle = fopen($filePath, 'rb');

    if ($handle === false) {
        throw new RuntimeException('Unable to open the uploaded file.');
    }

    try {
        $headers = fgetcsv($handle, 0, ',', '"', '');

        if ($headers === false || $headers === []) {
            throw new RuntimeException('The CSV file is empty.');
        }

        $headers = array_map(
            static fn($heading) => cleanImportHeading((string) $heading),
            $headers
        );

        // Reject blank or repeated headings.
        if (in_array('', $headers, true)) {
            throw new RuntimeException(
                'The CSV contains an empty column heading.'
            );
        }

        if (count(array_unique($headers)) !== count($headers)) {
            throw new RuntimeException(
                'The CSV contains duplicate column headings.'
            );
        }

        // Compare the uploaded headings with the official template.
        $required = array_map(
            'cleanImportHeading',
            scholarshipImportFields()
        );

        $missing = array_values(array_diff($required, $headers));
        $extra = array_values(array_diff($headers, $required));

        if ($missing !== [] || $extra !== []) {
            $messages = [];

            if ($missing !== []) {
                $messages[] = 'Missing columns: ' . implode(', ', $missing);
            }

            if ($extra !== []) {
                $messages[] = 'Unexpected columns: ' . implode(', ', $extra);
            }

            throw new RuntimeException(
                'Invalid scholarship template. ' . implode(' | ', $messages)
            );
        }

        if (count($headers) !== 29) {
            throw new RuntimeException(
                'The scholarship template must contain exactly 29 columns.'
            );
        }

        $rows = [];
        $rowNumber = 1;

        while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $rowNumber++;

            // Ignore completely blank lines.
            if (
                count($values) === 1 &&
                trim((string) $values[0]) === ''
            ) {
                continue;
            }

            if (count($values) !== count($headers)) {
                throw new RuntimeException(
                    "CSV row {$rowNumber} has an incorrect number of columns."
                );
            }

            $rows[] = [
                'row_number' => $rowNumber,
                'data' => array_combine($headers, $values),
            ];
        }

        return $rows;

    } finally {
        fclose($handle);
    }
}
