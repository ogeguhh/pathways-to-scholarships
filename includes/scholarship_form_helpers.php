<?php
declare(strict_types=1);

/*
 * Iteration 4 - Scholarship Form Helpers
 *
 * Shared database options and server-side validation
 * for the Create and Edit scholarship pages.
 *
 * This file does not save anything to MySQL.
 */

/**
 * Load the dropdown and checkbox options from MySQL.
 */
function scholarship_form_options(PDO $pdo): array
{
    $queries = [
        'providers' => 'SELECT provider_id, provider_name FROM providers ORDER BY provider_name',
        'states' => 'SELECT state_id, state_code, state_name FROM states ORDER BY state_name',
        'themes' => 'SELECT theme_id, theme_name FROM national_themes ORDER BY theme_name',
        'grades' => 'SELECT grade_id, grade_level FROM grade_levels ORDER BY grade_level',
        'categories' => 'SELECT category_id, category_name FROM categories ORDER BY category_name',
        'fields' => 'SELECT field_id, field_name FROM fields_of_study ORDER BY field_name'
    ];

    $options = [];

    foreach ($queries as $name => $sql) {
        $stmt = $pdo->query($sql);
        $options[$name] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    return $options;
}

/**
 * Check that a URL uses HTTP or HTTPS.
 */
function scholarship_valid_url(string $url): bool
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }

    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));

    return in_array($scheme, ['http', 'https'], true);
}

/**
 * Normalize URLs to help detect duplicate scholarships.
 *
 * Preserve meaningful query parameters.
 */
function scholarship_normalize_url(string $url): string
{
    if (!scholarship_valid_url($url)) {
        return '';
    }

    $parts = parse_url(trim($url));

    if ($parts === false) {
        return '';
    }

    $scheme = strtolower($parts['scheme']);
    $host = strtolower($parts['host']);
    $path = rtrim($parts['path'] ?? '', '/');

    $normalized = $scheme . '://' . $host;

    if (isset($parts['port'])) {
        $normalized .= ':' . $parts['port'];
    }

    $normalized .= $path;

    if (isset($parts['query'])) {
        parse_str($parts['query'], $query);

        foreach (array_keys($query) as $key) {
            if (
                str_starts_with(strtolower($key), 'utm_') ||
                in_array(strtolower($key), ['fbclid', 'gclid'], true)
            ) {
                unset($query[$key]);
            }
        }

        ksort($query);

        if ($query !== []) {
            $normalized .= '?' . http_build_query($query);
        }
    }

    return $normalized;
}

/**
 * Validate a required text field.
 */
function scholarship_required_text(
    mixed $value,
    string $fieldName,
    array &$errors
): string {
    $text = trim(is_scalar($value) ? (string)$value : '');

    if ($text === '') {
        $errors[$fieldName] = 'This field is required.';
    }

    return $text;
}

/**
 * Validate optional text with a maximum length.
 */
function scholarship_optional_text(
    mixed $value,
    string $field,
    int $maxLength,
    array &$errors
): ?string {
    $text = trim(is_scalar($value) ? (string)$value : '');

    if ($text === '') {
        return null;
    }

    if (mb_strlen($text) > $maxLength) {
        $errors[$field] = 'Maximum length is ' . $maxLength . ' characters.';
    }

    return $text;
}

/**
 * Validate money using the database's DECIMAL(12,2) limit.
 */
function scholarship_validate_amount(
    mixed $value,
    string $fieldName,
    array &$errors
): ?string {
    $text = trim(is_scalar($value) ? (string)$value : '');

    if ($text === '') {
        return null;
    }

    if (
        !preg_match('/^\d+(?:\.\d{1,2})?$/', $text) ||
        (float)$text > 9999999999.99
    ) {
        $errors[$fieldName] = 'Enter a valid amount with up to 2 decimal places.';
        return null;
    }

    return number_format((float)$text, 2, '.', '');
}

/**
 * Validate dates using YYYY-MM-DD.
 */
function scholarship_validate_date(
    mixed $value,
    string $fieldName,
    array &$errors
): ?string {
    $text = trim(is_scalar($value) ? (string)$value : '');

    if ($text === '') {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $text);

    if (
        $date === false ||
        $date->format('Y-m-d') !== $text
    ) {
        $errors[$fieldName] = 'Enter a valid date in YYYY-MM-DD format.';
        return null;
    }

    return $text;
}

/**
 * Check that a dropdown ID exists in the reference data.
 */
function scholarship_validate_id(
    mixed $value,
    string $field,
    array $rows,
    string $idColumn,
    array &$errors,
    bool $required = false
): ?int {
    $raw = is_scalar($value) ? trim((string)$value) : '';

    if ($raw === '') {
        if ($required) {
            $errors[$field] = 'Please select an option.';
        }
        return null;
    }

    if (!ctype_digit($raw) || (int)$raw < 1) {
        $errors[$field] = 'Invalid selection.';
        return null;
    }

    foreach ($rows as $row) {
        if ((int)$row[$idColumn] === (int)$raw) {
            return (int)$raw;
        }
    }

    $errors[$field] = 'Selected option does not exist.';
    return null;
}

/**
 * Validate checkbox IDs for many-to-many relationships.
 */
function scholarship_validate_ids(
    mixed $values,
    string $field,
    array $rows,
    string $idColumn,
    array &$errors
): array {
    if (!is_array($values)) {
        $errors[$field] = 'Invalid selection.';
        return [];
    }

    $allowed = array_map(
        fn($row) => (int)$row[$idColumn],
        $rows
    );

    $selected = [];

    foreach ($values as $value) {
        if (
            !is_scalar($value) ||
            !ctype_digit((string)$value) ||
            !in_array((int)$value, $allowed, true)
        ) {
            $errors[$field] = 'One or more selections are invalid.';
            continue;
        }

        $selected[] = (int)$value;
    }

    return array_values(array_unique($selected));
}

/**
 * Validate a dropdown containing fixed text values.
 */
function scholarship_validate_choice(
    mixed $value,
    string $field,
    array $allowed,
    array &$errors,
    ?string $default = null
): ?string {
    $text = is_scalar($value) ? trim((string)$value) : '';

    if ($text === '' && $default !== null) {
        return $default;
    }

    if ($text === '') {
        return null;
    }

    if (!in_array($text, $allowed, true)) {
        $errors[$field] = 'Select a valid option.';
        return null;
    }

    return $text;
}

/**
 * Validate an optional integer with a specified range.
 */
function scholarship_validate_integer(
    mixed $value,
    string $field,
    int $minimum,
    int $maximum,
    array &$errors
): ?int {
    $text = is_scalar($value) ? trim((string)$value) : '';

    if ($text === '') {
        return null;
    }

    $number = filter_var($text, FILTER_VALIDATE_INT);

    if (
        $number === false ||
        $number < $minimum ||
        $number > $maximum
    ) {
        $errors[$field] = "Enter a whole number between $minimum and $maximum.";
        return null;
    }

    return $number;
}

/**
 * Validate all scholarship form fields.
 *
 * The returned data can later be used by both
 * the Create and Edit database functions.
 */
function scholarship_validate_form(array $input, array $options): array
{
    $errors = [];
    $data = [];

    // Required scholarship name.
    $data['scholarship_name'] = scholarship_required_text(
        $input['scholarship_name'] ?? '',
        'scholarship_name',
        $errors
    );

    if (mb_strlen($data['scholarship_name']) > 255) {
        $errors['scholarship_name'] = 'Maximum 255 characters.';
    }

    // Official URL and normalized URL.
    $data['official_url'] = scholarship_required_text(
        $input['official_url'] ?? '',
        'official_url',
        $errors
    );

    if (
        $data['official_url'] !== '' &&
        !scholarship_valid_url($data['official_url'])
    ) {
        $errors['official_url'] = 'Enter a valid HTTP or HTTPS URL.';
    }

    if (strlen($data['official_url']) > 1000) {
        $errors['official_url'] = 'Maximum 1000 characters.';
    }

    $data['normalized_url'] = scholarship_normalize_url(
        $data['official_url']
    );

    if (strlen($data['normalized_url']) > 1000) {
        $errors['official_url'] = 'Normalized URL is too long.';
    }

    // Provider is required.
    $data['provider_id'] = scholarship_validate_id(
        $input['provider_id'] ?? '',
        'provider_id',
        $options['providers'] ?? [],
        'provider_id',
        $errors,
        true
    );

    // National or State/Local classification.
    $data['national_or_state_local'] = scholarship_validate_choice(
        $input['national_or_state_local'] ?? '',
        'national_or_state_local',
        ['National', 'State/Local'],
        $errors
    );

    if ($data['national_or_state_local'] === null) {
        $errors['national_or_state_local'] = 'Choose National or State/Local.';
    }

    $data['state_id'] = scholarship_validate_id(
        $input['state_id'] ?? '',
        'state_id',
        $options['states'] ?? [],
        'state_id',
        $errors
    );

    $data['theme_id'] = scholarship_validate_id(
        $input['theme_id'] ?? '',
        'theme_id',
        $options['themes'] ?? [],
        'theme_id',
        $errors
    );

    if (
        $data['national_or_state_local'] === 'State/Local' &&
        $data['state_id'] === null
    ) {
        $errors['state_id'] = 'Select the scholarship state.';
    }

    if (
        $data['national_or_state_local'] === 'National' &&
        $data['theme_id'] === null
    ) {
        $errors['theme_id'] = 'Select a national theme.';
    }

    // School-level classification.
    $data['school_level'] = scholarship_validate_choice(
        $input['school_level'] ?? '',
        'school_level',
        ['Middle School', 'High School', 'Both'],
        $errors
    );

    // Award amounts and maximum potential value.
    foreach ([
        'award_amount_min',
        'award_amount_max',
        'maximum_potential_value'
    ] as $field) {
        $data[$field] = scholarship_validate_amount(
            $input[$field] ?? '',
            $field,
            $errors
        );
    }

    if (
        $data['award_amount_min'] !== null &&
        $data['award_amount_max'] !== null &&
        (float)$data['award_amount_max'] < (float)$data['award_amount_min']
    ) {
        $errors['award_amount_max'] =
            'Maximum award cannot be less than minimum award.';
    }

    // Number of awards.
    $data['number_of_awards'] = scholarship_validate_integer(
        $input['number_of_awards'] ?? '',
        'number_of_awards',
        0,
        2147483647,
        $errors
    );

    // Minimum GPA must fit DECIMAL(3,2).
    $gpa = is_scalar($input['minimum_gpa'] ?? null)
        ? trim((string)$input['minimum_gpa'])
        : '';

    $data['minimum_gpa'] = null;

    if ($gpa !== '') {
        if (
            !preg_match('/^\d(?:\.\d{1,2})?$/', $gpa) ||
            (float)$gpa > 4.00
        ) {
            $errors['minimum_gpa'] = 'Enter a GPA between 0.00 and 4.00.';
        } else {
            $data['minimum_gpa'] = number_format(
                (float)$gpa, 2, '.', ''
            );
        }
    }

    // Validate application and verification dates.
    foreach ([
        'application_open_date',
        'application_deadline',
        'verification_date'
    ] as $field) {
        $data[$field] = scholarship_validate_date(
            $input[$field] ?? '',
            $field,
            $errors
        );
    }

    if (
        $data['application_open_date'] !== null &&
        $data['application_deadline'] !== null &&
        $data['application_deadline'] < $data['application_open_date']
    ) {
        $errors['application_deadline'] =
            'Deadline cannot be before the application opening date.';
    }

    // Rolling deadline is stored as 0 or 1.
    $rolling = $input['rolling_deadline'] ?? '0';

    if (!in_array((string)$rolling, ['0', '1'], true)) {
        $errors['rolling_deadline'] = 'Invalid rolling deadline value.';
        $data['rolling_deadline'] = 0;
    } else {
        $data['rolling_deadline'] = (int)$rolling;
    }

    // Standard Yes/No/Unknown database fields.
    $yesNoFields = [
        'renewable',
        'financial_need_required',
        'essay_required',
        'recommendation_required',
        'transcript_test_required',
        'community_service_required',
        'leadership_required'
    ];

    foreach ($yesNoFields as $field) {
        $data[$field] = scholarship_validate_choice(
            $input[$field] ?? '',
            $field,
            ['Yes', 'No', 'Unknown'],
            $errors,
            'Unknown'
        );
    }

    // Application method.
    $data['application_method'] = scholarship_validate_choice(
        $input['application_method'] ?? '',
        'application_method',
        ['Online Form', 'Email', 'Mail', 'Other'],
        $errors
    );

    // Optional text fields.
    $textFields = [
        'discovery_source' => 1000,
        'residency_requirement' => 65535,
        'other_eligibility' => 65535,
        'notes' => 65535,
        'contributing_student' => 150
    ];

    foreach ($textFields as $field => $maxLength) {
        $data[$field] = scholarship_optional_text(
            $input[$field] ?? '',
            $field,
            $maxLength,
            $errors
        );
    }

    // Many-to-many relationship selections.
    $data['grade_ids'] = scholarship_validate_ids(
        $input['grade_ids'] ?? [],
        'grade_ids',
        $options['grades'] ?? [],
        'grade_id',
        $errors
    );

    $data['category_ids'] = scholarship_validate_ids(
        $input['category_ids'] ?? [],
        'category_ids',
        $options['categories'] ?? [],
        'category_id',
        $errors
    );

    $data['field_ids'] = scholarship_validate_ids(
        $input['field_ids'] ?? [],
        'field_ids',
        $options['fields'] ?? [],
        'field_id',
        $errors
    );

    if ($data['grade_ids'] === []) {
        $errors['grade_ids'] = 'Select at least one eligible grade.';
    }

    // No database changes happen in this function.
    return [
        'data' => $data,
        'errors' => $errors
    ];
}
