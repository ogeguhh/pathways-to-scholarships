<?php
declare(strict_types=1);

/*
 * Pathways to Scholarships
 * Iteration 4 — Scholarship Save Handler
 *
 * Validates submitted scholarship information and saves
 * the scholarship and its relationships in one transaction.
 */

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/includes/db.php';
require_once $projectRoot . '/includes/security.php';
require_once $projectRoot . '/includes/config.php';
require_once $projectRoot . '/includes/import_validator.php';

// Authentication is scheduled for Iteration 7.
// Until then, allow administration only on the local server.
require_role('Administrator');

// Saving requires a POST request and a valid CSRF token.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Invalid CSRF token.');
}

$pdo = getDBConnection();

// Read a submitted text value and remove surrounding whitespace.
function submittedText(string $name): string
{
    $value = $_POST[$name] ?? '';

    return is_string($value) ? trim($value) : '';
}

// Convert optional form values to NULL for database storage.
function optionalText(string $name): ?string
{
    $value = submittedText($name);

    return $value === '' ? null : $value;
}

// Validate an optional decimal number.
function decimalValue(
    string $name,
    float $minimum,
    float $maximum,
    array &$errors
): ?string {
    $value = submittedText($name);

    if ($value === '') {
        return null;
    }

    if (!is_numeric($value) ||
        !is_finite((float) $value) ||
        (float) $value < $minimum ||
        (float) $value > $maximum) {
        $errors[] = "$name must be between $minimum and $maximum.";
        return null;
    }

    // Database amounts use two decimal places.
    if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
        $errors[] = "$name must have at most two decimal places.";
        return null;
    }

    return $value;
}

// Check that a date uses YYYY-MM-DD and represents a real date.
function dateValue(string $name, array &$errors): ?string
{
    $value = submittedText($name);

    if ($value === '') {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

    if (!$date || $date->format('Y-m-d') !== $value) {
        $errors[] = "$name must be a valid date.";
        return null;
    }

    return $value;
}

// Confirm that a selected reference ID exists in its table.
function validReference(
    PDO $pdo,
    string $table,
    string $column,
    string $value
): bool {
    if (!ctype_digit($value) || (int) $value < 1) {
        return false;
    }

    // Table and column names are fixed by our own code.
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM `$table` WHERE `$column` = ?"
    );

    $stmt->execute([(int) $value]);

    return (int) $stmt->fetchColumn() === 1;
}

// Validate checkbox IDs and reject unknown reference values.
function selectedIds(
    PDO $pdo,
    string $input,
    string $table,
    string $column,
    array &$errors
): array {
    $submitted = $_POST[$input] ?? [];

    if (!is_array($submitted)) {
        $errors[] = "Invalid $input selection.";
        return [];
    }

    $ids = [];

    foreach ($submitted as $value) {
        if (!is_string($value) ||
            !validReference($pdo, $table, $column, $value)) {
            $errors[] = "Invalid $input selection.";
            continue;
        }

        $ids[] = (int) $value;
    }

    return array_values(array_unique($ids));
}

// Verify dropdown values against their permitted choices.
function enumValue(
    string $name,
    array $allowed,
    array &$errors,
    bool $optional = false
): ?string {
    $value = submittedText($name);

    if ($optional && $value === '') {
        return null;
    }

    if (!in_array($value, $allowed, true)) {
        $errors[] = "Invalid $name value.";
        return null;
    }

    return $value;
}

$errors = [];

// Determine whether this request creates or edits a record.
$idText = submittedText('scholarship_id');
$id = null;

if ($idText !== '') {
    if (!ctype_digit($idText) || (int) $idText < 1) {
        $errors[] = 'Invalid scholarship ID.';
    } else {
        $id = (int) $idText;
    }
}

$name = submittedText('scholarship_name');
$providerId = submittedText('provider_id');
$officialUrl = submittedText('official_url');

if ($name === '' || mb_strlen($name) > 255) {
    $errors[] = 'Scholarship name is required (maximum 255 characters).';
}

if (!validReference($pdo, 'providers', 'provider_id', $providerId)) {
    $errors[] = 'Select a valid scholarship provider.';
}

if ($officialUrl === '' || strlen($officialUrl) > 1000) {
    $errors[] = 'A valid official scholarship URL is required.';
}

$normalizedUrl = normalizeImportUrl($officialUrl);

if ($normalizedUrl === null) {
    $errors[] = 'Official URL must be a valid HTTP or HTTPS URL.';
}

$type = enumValue(
    'national_or_state_local',
    ['National', 'State/Local'],
    $errors
);

$stateId = submittedText('state_id');
$themeId = submittedText('theme_id');

foreach ([
    ['state_id', $stateId, 'states', 'state_id'],
    ['theme_id', $themeId, 'national_themes', 'theme_id']
] as [$label, $value, $table, $column]) {
    if ($value !== '' &&
        !validReference($pdo, $table, $column, $value)) {
        $errors[] = "Invalid $label selection.";
    }
}

$schoolLevel = enumValue(
    'school_level',
    ['Middle School', 'High School', 'Both'],
    $errors,
    true
);

$renewable = enumValue(
    'renewable',
    ['Yes', 'No', 'Unknown'],
    $errors
);

$applicationMethod = enumValue(
    'application_method',
    ['Online Form', 'Email', 'Mail', 'Other'],
    $errors,
    true
);

$yesNoFields = [
    'financial_need_required',
    'essay_required',
    'recommendation_required',
    'transcript_test_required',
    'community_service_required',
    'leadership_required'
];

$yesNoValues = [];

foreach ($yesNoFields as $field) {
    $yesNoValues[$field] = enumValue(
        $field,
        ['Yes', 'No', 'Unknown'],
        $errors
    );
}

$minimumAward = decimalValue(
    'award_amount_min', 0, 9999999999.99, $errors
);

$maximumAward = decimalValue(
    'award_amount_max', 0, 9999999999.99, $errors
);

$maximumPotential = decimalValue(
    'maximum_potential_value', 0, 9999999999.99, $errors
);

$minimumGpa = decimalValue(
    'minimum_gpa', 0, 4, $errors
);

if ($minimumAward !== null &&
    $maximumAward !== null &&
    (float) $maximumAward < (float) $minimumAward) {
    $errors[] = 'Maximum award cannot be less than minimum award.';
}

$numberOfAwards = optionalText('number_of_awards');

if ($numberOfAwards !== null &&
    (!ctype_digit($numberOfAwards) ||
     (float) $numberOfAwards > 2147483647)) {
    $errors[] = 'Number of awards must be a nonnegative whole number.';
}

$openDate = dateValue('application_open_date', $errors);
$deadline = dateValue('application_deadline', $errors);
$verificationDate = dateValue('verification_date', $errors);

$grades = selectedIds(
    $pdo, 'grades', 'grade_levels', 'grade_id', $errors
);

$categories = selectedIds(
    $pdo, 'categories', 'categories', 'category_id', $errors
);

$fields = selectedIds(
    $pdo, 'fields', 'fields_of_study', 'field_id', $errors
);

// Prepare the complete scholarship record for saving.
$data = [
    'provider_id' => ctype_digit($providerId) ? (int) $providerId : null,
    'state_id' => $stateId !== '' && ctype_digit($stateId)
        ? (int) $stateId : null,
    'theme_id' => $themeId !== '' && ctype_digit($themeId)
        ? (int) $themeId : null,
    'scholarship_name' => $name,
    'official_url' => $officialUrl,
    'normalized_url' => $normalizedUrl,
    'discovery_source' => optionalText('discovery_source'),
    'national_or_state_local' => $type,
    'school_level' => $schoolLevel,
    'award_amount_min' => $minimumAward,
    'award_amount_max' => $maximumAward,
    'number_of_awards' => $numberOfAwards,
    'renewable' => $renewable,
    'maximum_potential_value' => $maximumPotential,
    'application_open_date' => $openDate,
    'application_deadline' => $deadline,
    'rolling_deadline' => isset($_POST['rolling_deadline']) ? 1 : 0,
    'residency_requirement' => optionalText('residency_requirement'),
    'minimum_gpa' => $minimumGpa,
    'financial_need_required' => $yesNoValues['financial_need_required'],
    'essay_required' => $yesNoValues['essay_required'],
    'recommendation_required' => $yesNoValues['recommendation_required'],
    'transcript_test_required' => $yesNoValues['transcript_test_required'],
    'community_service_required' => $yesNoValues['community_service_required'],
    'leadership_required' => $yesNoValues['leadership_required'],
    'other_eligibility' => optionalText('other_eligibility'),
    'application_method' => $applicationMethod,
    'verification_date' => $verificationDate,
    'notes' => optionalText('notes'),
    'contributing_student' => optionalText('contributing_student')
];

if (strlen($data['contributing_student'] ?? '') > 150) {
    $errors[] = 'Contributing student must be 150 characters or fewer.';
}

// Display validation errors without modifying the database.
if ($errors !== []) {
    http_response_code(422);

    echo '<h1>Scholarship Validation Errors</h1><ul>';

    foreach (array_unique($errors) as $error) {
        echo '<li>' . e($error) . '</li>';
    }

    echo '</ul><p>Please go back and correct the form.</p>';
    exit;
}

try {
    $pdo->beginTransaction();

    if ($id !== null) {
        // Lock the existing row while it is being edited.
        $stmt = $pdo->prepare(
            'SELECT scholarship_id
             FROM scholarships
             WHERE scholarship_id = ?
             FOR UPDATE'
        );

        $stmt->execute([$id]);

        if (!$stmt->fetch()) {
            throw new RuntimeException('Scholarship not found.');
        }
    }

    // FR-IMP-06: the normalized official URL is the duplicate key.
    // An edited scholarship may retain its own existing URL.
    $stmt = $pdo->prepare(
        'SELECT scholarship_id
         FROM scholarships
         WHERE normalized_url = ?
           AND status = ?
           AND (? IS NULL OR scholarship_id <> ?)
         LIMIT 1'
    );

    $stmt->execute([$normalizedUrl, 'active', $id, $id]);

    if ($stmt->fetch()) {
        throw new RuntimeException(
            'An active scholarship already uses this official URL.'
        );
    }

    if ($id === null) {
        // Insert a new scholarship using validated form values.
        $columns = array_keys($data);

        $columnSql = implode(
            ', ',
            array_map(fn($column) => "`$column`", $columns)
        );

        $placeholders = implode(
            ', ',
            array_fill(0, count($columns), '?')
        );

        $stmt = $pdo->prepare(
            "INSERT INTO scholarships ($columnSql)
             VALUES ($placeholders)"
        );

        $stmt->execute(array_values($data));

        $id = (int) $pdo->lastInsertId();
        $action = 'create';
    } else {
        // Update the existing record without changing its
        // original import batch, creation time, or status.
        $assignments = implode(
            ', ',
            array_map(
                fn($column) => "`$column` = ?",
                array_keys($data)
            )
        );

        $stmt = $pdo->prepare(
            "UPDATE scholarships
             SET $assignments
             WHERE scholarship_id = ?"
        );

        $stmt->execute([...array_values($data), $id]);

        $action = 'edit';
    }

    // Replace many-to-many selections inside the same transaction.
    $relationships = [
        ['scholarship_grades', 'grade_id', $grades],
        ['scholarship_categories', 'category_id', $categories],
        ['scholarship_fields', 'field_id', $fields]
    ];

    foreach ($relationships as [$table, $column, $selected]) {
        $delete = $pdo->prepare(
            "DELETE FROM `$table` WHERE scholarship_id = ?"
        );

        $delete->execute([$id]);

        $insert = $pdo->prepare(
            "INSERT INTO `$table` (scholarship_id, `$column`)
             VALUES (?, ?)"
        );

        foreach ($selected as $relatedId) {
            $insert->execute([$id, $relatedId]);
        }
    }

    // Record the administrative action.
    // user_id is NULL until Iteration 7 authentication exists.
    $audit = $pdo->prepare(
        'INSERT INTO audit_log
            (user_id, action, entity_type, entity_id, details)
         VALUES (?, ?, ?, ?, ?)'
    );

    $audit->execute([
        current_user_id(),
        $action,
        'scholarship',
        $id,
        json_encode(
            [
                'scholarship_name' => $name,
                'development_mode' => true
            ],
            JSON_THROW_ON_ERROR
        )
    ]);

    $pdo->commit();

    // Redirect after saving to prevent accidental resubmission.
    header(
        'Location: /admin/scholarship_form.php?id=' . $id
    );
    exit;

} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Scholarship save failed: ' . $error->getMessage());

    http_response_code(400);

    // Show only a safe error message to the browser.
    if ($error instanceof RuntimeException) {
        exit(e($error->getMessage()));
    }

    exit('Scholarship could not be saved. Check the server log.');
}
