<?php
declare(strict_types=1);

/*
 * Pathways to Scholarships
 * Iteration 6 — Scholarship CSV Export
 *
 * Exports all or filtered scholarships using the professor's
 * original 29-column spreadsheet template.
 */

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/includes/config.php';
require_once $projectRoot . '/includes/db.php';
require_once $projectRoot . '/includes/security.php';
require_once $projectRoot . '/includes/import_fields.php';

// Restrict this administrative export to local development
// until role-based authentication is implemented.
$remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';

if (!APP_DEBUG ||
    !in_array($remoteAddress, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Access denied.');
}

$pdo = getDBConnection();

// Optional filters. Empty values export all matching records.
$stateId = filter_input(INPUT_GET, 'state_id', FILTER_VALIDATE_INT);
$gradeId = filter_input(INPUT_GET, 'grade_id', FILTER_VALIDATE_INT);
$fieldId = filter_input(INPUT_GET, 'field_id', FILTER_VALIDATE_INT);

$stateId = $stateId ?: null;
$gradeId = $gradeId ?: null;
$fieldId = $fieldId ?: null;

$type = $_GET['type'] ?? '';

if (!is_string($type) ||
    !in_array($type, ['', 'National', 'State/Local'], true)) {
    $type = '';
}

// Export all stored scholarship records, including archived ones.
// Optional filters narrow the exported dataset.
$sql = "
    SELECT
        s.*,
        p.provider_name,
        pt.type_name AS provider_type,
        st.state_name
    FROM scholarships s
    JOIN providers p
      ON p.provider_id = s.provider_id
    LEFT JOIN provider_types pt
      ON pt.provider_type_id = p.provider_type_id
    LEFT JOIN states st
      ON st.state_id = s.state_id
    WHERE 1 = 1
";

$parameters = [];

if ($stateId !== null) {
    $sql .= ' AND s.state_id = :state_id';
    $parameters[':state_id'] = $stateId;
}

if ($type !== '') {
    $sql .= ' AND s.national_or_state_local = :type';
    $parameters[':type'] = $type;
}

if ($gradeId !== null) {
    $sql .= "
        AND EXISTS (
            SELECT 1
            FROM scholarship_grades sg
            WHERE sg.scholarship_id = s.scholarship_id
              AND sg.grade_id = :grade_id
        )
    ";
    $parameters[':grade_id'] = $gradeId;
}

if ($fieldId !== null) {
    $sql .= "
        AND EXISTS (
            SELECT 1
            FROM scholarship_fields sf
            WHERE sf.scholarship_id = s.scholarship_id
              AND sf.field_id = :field_id
        )
    ";
    $parameters[':field_id'] = $fieldId;
}

$sql .= ' ORDER BY s.scholarship_id';

$stmt = $pdo->prepare($sql);
$stmt->execute($parameters);

$scholarships = $stmt->fetchAll();

// Fetch related values for a scholarship and combine them
// into one spreadsheet cell, separated by semicolons.
function exportRelatedValues(
    PDO $pdo,
    string $sql,
    int $scholarshipId
): string {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$scholarshipId]);

    return implode('; ', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

$gradeSql = "
    SELECT CAST(gl.grade_number AS CHAR)
    FROM scholarship_grades sg
    JOIN grade_levels gl ON gl.grade_id = sg.grade_id
    WHERE sg.scholarship_id = ?
    ORDER BY gl.grade_number
";

$categorySql = "
    SELECT c.category_name
    FROM scholarship_categories sc
    JOIN categories c ON c.category_id = sc.category_id
    WHERE sc.scholarship_id = ?
    ORDER BY c.category_name
";

$fieldSql = "
    SELECT f.field_name
    FROM scholarship_fields sf
    JOIN fields_of_study f ON f.field_id = sf.field_id
    WHERE sf.scholarship_id = ?
    ORDER BY f.field_name
";

// Return an empty cell for unavailable optional values.
function exportValue(mixed $value): string
{
    return $value === null ? '' : (string) $value;
}

// Write one award value when the minimum and maximum match.
// Otherwise represent the recorded award range.
function exportAward(mixed $minimum, mixed $maximum): string
{
    if ($minimum === null && $maximum === null) {
        return '';
    }

    if ($minimum === null) {
        return exportValue($maximum);
    }

    if ($maximum === null || (float) $minimum === (float) $maximum) {
        return exportValue($minimum);
    }

    return exportValue($minimum) . '-' . exportValue($maximum);
}

// Format the Yes/No columns stored as tiny integers.
function exportYesNo(mixed $value): string
{
    if ($value === null) {
        return '';
    }

    return (int) $value === 1 ? 'Yes' : 'No';
}

// Download the generated CSV directly in the browser.
header('Content-Type: text/csv; charset=UTF-8');
header(
    'Content-Disposition: attachment; filename="scholarships_export.csv"'
);
header('Cache-Control: no-store');

$output = fopen('php://output', 'wb');

if ($output === false) {
    http_response_code(500);
    exit('Unable to generate CSV export.');
}

// UTF-8 BOM improves compatibility with Microsoft Excel.
fwrite($output, "\xEF\xBB\xBF");

// Use the exact 29 headings from the import template.
fputcsv($output, scholarshipImportFields(), ',', '"', '');

foreach ($scholarships as $scholarship) {
    $id = (int) $scholarship['scholarship_id'];

    $grades = exportRelatedValues($pdo, $gradeSql, $id);
    $categories = exportRelatedValues($pdo, $categorySql, $id);
    $fields = exportRelatedValues($pdo, $fieldSql, $id);

    $row = [
        exportValue($scholarship['scholarship_name']),
        exportValue($scholarship['provider_name']),
        exportValue($scholarship['provider_type']),
        exportValue($scholarship['official_url']),
        exportValue($scholarship['discovery_source']),
        exportValue($scholarship['state_name'] ?? (
            $scholarship['national_or_state_local'] === 'National'
                ? 'National' : ''
        )),
        exportValue($scholarship['national_or_state_local']),
        $grades,
        exportValue($scholarship['school_level']),
        $categories,
        $fields,
        exportAward(
            $scholarship['award_amount_min'],
            $scholarship['award_amount_max']
        ),
        exportValue($scholarship['number_of_awards']),
        exportValue($scholarship['renewable']),
        exportValue($scholarship['maximum_potential_value']),
        exportValue($scholarship['application_open_date']),
        exportValue($scholarship['application_deadline']),
        exportValue($scholarship['residency_requirement']),
        exportValue($scholarship['minimum_gpa']),
        exportValue($scholarship['financial_need_required']),
        exportValue($scholarship['essay_required']),
        exportValue($scholarship['recommendation_required']),
        exportValue($scholarship['transcript_test_required']),
        exportValue($scholarship['community_service_required']),
        exportValue($scholarship['leadership_required']),
        exportValue($scholarship['other_eligibility']),
        exportValue($scholarship['application_method']),
        exportValue($scholarship['verification_date']),
        exportValue($scholarship['notes'])
    ];

    fputcsv($output, $row, ',', '"', '');
}

fclose($output);
exit;
