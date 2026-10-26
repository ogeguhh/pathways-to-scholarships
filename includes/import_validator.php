<?php
declare(strict_types=1);

/*
 * Iteration 3 - Scholarship Import Validator
 *
 * This file cleans and checks scholarship data.
 * It does not insert anything into the database.
 */

/**
 * Clean text by removing extra spaces and invisible spaces.
 */
function import_clean_text(mixed $value): string
{
    $text = (string)$value;

    // Replace non-breaking spaces with regular spaces.
    $text = str_replace("\xC2\xA0", ' ', $text);

    // Remove spaces at the beginning and end.
    $text = trim($text);

    // Replace repeated whitespace with one space.
    return preg_replace('/\s+/u', ' ', $text) ?? $text;
}

/**
 * Convert Yes/No answers into consistent values.
 */
function import_clean_yes_no(mixed $value): string
{
    $text = strtolower(import_clean_text($value));

    if (in_array($text, ['yes', 'y', 'true', '1'], true)) {
        return 'Yes';
    }

    if (in_array($text, ['no', 'n', 'false', '0'], true)) {
        return 'No';
    }

    return 'Unknown';
}

/**
 * Convert dollar amounts into numbers.
 *
 * Example:
 * "$1,000" becomes 1000.00
 */
function import_clean_money(mixed $value): ?float
{
    $text = import_clean_text($value);

    if ($text === '') {
        return null;
    }

    // Remove dollar signs and commas.
    $text = str_replace(['$', ','], '', $text);

    if (!is_numeric($text)) {
        return null;
    }

    return round((float)$text, 2);
}

/**
 * Convert supported dates into YYYY-MM-DD format.
 */
function import_clean_date(mixed $value): ?string
{
    $text = import_clean_text($value);

    if ($text === '') {
        return null;
    }

    // Excel sometimes stores dates as serial numbers.
    if (is_numeric($text)) {
        $number = (float)$text;

        if ($number >= 1 && $number <= 2958465) {
            $seconds = (int)round(($number - 25569) * 86400);

            return gmdate('Y-m-d', $seconds);
        }

        return null;
    }

    // Try common date formats.
    foreach (['Y-m-d', 'm/d/Y', 'n/j/Y', 'm-d-Y', 'n-j-Y'] as $format) {
        $date = DateTimeImmutable::createFromFormat('!' . $format, $text);

        if ($date !== false && $date->format($format) === $text) {
            return $date->format('Y-m-d');
        }
    }

    return null;
}

/**
 * Standardize national versus state/local classifications.
 */
function import_clean_scope(mixed $value): ?string
{
    $text = strtolower(import_clean_text($value));

    if ($text === 'national') {
        return 'National';
    }

    if (in_array($text, ['state', 'local', 'state/local', 'state or local'], true)) {
        return 'State/Local';
    }

    return null;
}

/**
 * Clean and validate one scholarship record.
 *
 * Returns:
 * - status: Ready, Warning, or Error
 * - data: cleaned scholarship fields
 * - messages: explanations of problems found
 */
function import_validate_row(array $row, ?PDO $pdo = null): array
{
    $cleaned = [];
    $messages = [];
    $errors = [];

    // Clean all 29 fields while preserving their original headings.
    foreach ($row as $field => $value) {
        $cleaned[$field] = import_clean_text($value);
    }

    // Required scholarship name.
    $name = $cleaned['Scholarship name'] ?? '';

    if ($name === '') {
        $errors[] = 'Scholarship name is missing.';
    }

    // Required official scholarship URL.
    $url = $cleaned['Official scholarship URL'] ?? '';

    if ($url === '') {
        $errors[] = 'Official scholarship URL is missing.';
    } elseif (
        !filter_var($url, FILTER_VALIDATE_URL) ||
        !in_array(strtolower((string)parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)
    ) {
        $errors[] = 'Official scholarship URL is invalid.';
    }

    // Standardize scholarship classification.
    $scopeField = 'National vs. state/local';

    $scope = import_clean_scope($cleaned[$scopeField] ?? '');

    if ($scope === null) {
        $messages[] = 'National versus state/local classification needs review.';
    } else {
        $cleaned[$scopeField] = $scope;
    }

    // Clean the award amount.
    $amountField = 'Award amount';

    $originalAmount = $cleaned[$amountField] ?? '';
    $amount = import_clean_money($originalAmount);

    if ($originalAmount !== '' && $amount === null) {
        $messages[] = 'Award amount could not be converted to a number.';
    } elseif ($amount !== null) {
        $cleaned[$amountField] = number_format($amount, 2, '.', '');
    }

    // Clean Yes/No/Unknown fields.
    $yesNoFields = [
        'Renewable (Yes/No/Unknown)',
        'Financial need required (Yes/No/Unknown)',
        'Essay required',
        'Recommendation required',
        'Transcript/test score required',
        'Community service requirement',
        'Leadership requirement'
    ];

    foreach ($yesNoFields as $field) {
        if (!array_key_exists($field, $cleaned)) {
            continue;
        }

        $original = $cleaned[$field];
        $cleaned[$field] = import_clean_yes_no($original);

        if (
            $original !== '' &&
            $cleaned[$field] === 'Unknown' &&
            strtolower($original) !== 'unknown'
        ) {
            $messages[] = $field . ' contains an unrecognized answer.';
        }
    }

    // Validate the scholarship's school level.
$gradeField = 'Middle school / high school / both';

if (isset($cleaned[$gradeField]) && $cleaned[$gradeField] !== '') {

    // Convert the answer to lowercase so capitalization does not matter.
    $grade = strtolower($cleaned[$gradeField]);

    // These are the three school levels our project accepts.
    $validGrades = [
        'middle school' => 'Middle School',
        'high school' => 'High School',
        'both' => 'Both'
    ];

    if (isset($validGrades[$grade])) {
        // Save the standardized school level.
        $cleaned[$gradeField] = $validGrades[$grade];
    } else {
        // Flag an invalid school level for review.
        $messages[] = 'School level must be Middle School, High School, or Both.';
    }
}

    // Clean application and verification dates.
    $dateFields = [
        'Application open date (if known)',
        'Application deadline',
        'Verification date'
    ];

    foreach ($dateFields as $field) {
        if (!array_key_exists($field, $cleaned)) {
            continue;
        }

        $original = $cleaned[$field];

        if ($original === '') {
            continue;
        }

        $date = import_clean_date($original);

        if ($date === null) {
            $messages[] = $field . ' contains an invalid date.';
        } else {
            $cleaned[$field] = $date;
        }
    }

    // Convert full state names into two-letter abbreviations.
$stateField = 'State/region';

if ($pdo !== null && !empty($cleaned[$stateField])) {

    // Look up the state in our database.
    $stateCode = import_normalize_state($pdo, $cleaned[$stateField]);

    if ($stateCode !== null) {
        // Example: Minnesota becomes MN.
        $cleaned[$stateField] = $stateCode;
    } else {
        // Flag unknown states for review.
        $messages[] = 'State/region could not be normalized.';
    }
}

    // Decide the final validation status.
    if ($errors !== []) {
        $status = 'Error';
    } elseif ($messages !== []) {
        $status = 'Warning';
    } else {
        $status = 'Ready';
    }

    return [
        'status' => $status,
        'data' => $cleaned,
        'messages' => array_merge($errors, $messages)
    ];
}

/**
 * Convert a state name or abbreviation into its official state code.
 *
 * Examples:
 * Minnesota -> MN
 * California -> CA
 * MN -> MN
 *
 * Returns null if the state cannot be found.
 */
function import_normalize_state(PDO $pdo, mixed $value): ?string
{
    // Clean extra spaces and invisible characters.
    $state = import_clean_text($value);

    // An empty state cannot be converted.
    if ($state === '') {
        return null;
    }

    // Look for either a state name or a two-letter code.
    $sql = "
        SELECT state_code
        FROM states
        WHERE LOWER(state_name) = LOWER(?)
           OR UPPER(state_code) = UPPER(?)
        LIMIT 1
    ";

    // Prepared statements safely pass values to the database.
    $stmt = $pdo->prepare($sql);

    $stmt->execute([$state, $state]);

    // Fetch the matching state abbreviation.
    $code = $stmt->fetchColumn();

    // Return null if no matching state exists.
    return $code === false ? null : (string)$code;
}