<?php
declare(strict_types=1);

/*
 * Pathways to Scholarships
 * Iteration 3 — Import Validation
 *
 * Cleans spreadsheet values and identifies missing or invalid data.
 * Duplicate detection is handled separately by import_service.php.
 */

require_once __DIR__ . '/import_fields.php';

// Remove extra whitespace, including non-breaking spaces from Excel.
function cleanImportText(mixed $value): string
{
    if ($value === null) {
        return '';
    }

    $text = trim((string) $value);
    $text = str_replace("\xC2\xA0", ' ', $text);

    return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
}

// Convert common Yes/No/Unknown answers to consistent values.
// Ambiguous answers remain Unknown rather than being guessed.
function cleanImportYesNo(mixed $value): string
{
    $text = strtolower(cleanImportText($value));

    if (in_array($text, ['yes', 'y', 'true', '1'], true)) {
        return 'Yes';
    }

    if (in_array($text, ['no', 'n', 'false', '0'], true)) {
        return 'No';
    }

    return 'Unknown';
}

// Convert amounts such as "$1,000" into 1000.00.
// Descriptions such as "Varies" remain null.
function cleanImportMoney(mixed $value): ?float
{
    $text = cleanImportText($value);

    if ($text === '') {
        return null;
    }

    if (!preg_match('/^\$?\s*([\d,]+(?:\.\d{1,2})?)$/', $text, $matches)) {
        return null;
    }

    return (float) str_replace(',', '', $matches[1]);
}

// Accept real spreadsheet dates and common date formats.
// Uncertain or descriptive dates remain null.
function cleanImportDate(mixed $value): ?string
{
    $text = cleanImportText($value);

    if ($text === '' || strtolower($text) === 'unknown') {
        return null;
    }

    foreach (['!Y-m-d', '!Y-m-d H:i:s', '!m/d/Y', '!n/j/Y', '!F j, Y', '!M j, Y'] as $format) {
        $date = DateTimeImmutable::createFromFormat($format, $text);
        $errors = DateTimeImmutable::getLastErrors();

        if ($date !== false && ($errors === false ||
            ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
            return $date->format('Y-m-d');
        }
    }

    return null;
}

// Extract grades 6 through 12 from common spreadsheet formats.
function cleanImportGrades(mixed $value): array
{
    $text = strtolower(cleanImportText($value));
    $grades = [];

    if (preg_match('/\b(?:grades?|gr)\s*(\d{1,2})\s*(?:-|–|to)\s*(\d{1,2})\b/u', $text, $match)
        || preg_match('/^(\d{1,2})\s*(?:-|–|to)\s*(\d{1,2})$/u', $text, $match)) {
        $start = (int) $match[1];
        $end = (int) $match[2];

        if ($start <= $end) {
            for ($grade = max(6, $start); $grade <= min(12, $end); $grade++) {
                $grades[] = $grade;
            }
        }
    } else {
        preg_match_all('/(?<!\d)(?:6|7|8|9|10|11|12)(?!\d)/', $text, $matches);
        $grades = array_map('intval', $matches[0]);
    }

    return array_values(array_unique($grades));
}

// Recognize a state abbreviation or full state name.
// Descriptions such as "Tennessee (South)" are supported.
function cleanImportState(mixed $value): ?string
{
    static $states = [
        'AL'=>'Alabama','AK'=>'Alaska','AZ'=>'Arizona','AR'=>'Arkansas',
        'CA'=>'California','CO'=>'Colorado','CT'=>'Connecticut',
        'DE'=>'Delaware','FL'=>'Florida','GA'=>'Georgia','HI'=>'Hawaii',
        'ID'=>'Idaho','IL'=>'Illinois','IN'=>'Indiana','IA'=>'Iowa',
        'KS'=>'Kansas','KY'=>'Kentucky','LA'=>'Louisiana','ME'=>'Maine',
        'MD'=>'Maryland','MA'=>'Massachusetts','MI'=>'Michigan',
        'MN'=>'Minnesota','MS'=>'Mississippi','MO'=>'Missouri',
        'MT'=>'Montana','NE'=>'Nebraska','NV'=>'Nevada',
        'NH'=>'New Hampshire','NJ'=>'New Jersey','NM'=>'New Mexico',
        'NY'=>'New York','NC'=>'North Carolina','ND'=>'North Dakota',
        'OH'=>'Ohio','OK'=>'Oklahoma','OR'=>'Oregon','PA'=>'Pennsylvania',
        'RI'=>'Rhode Island','SC'=>'South Carolina','SD'=>'South Dakota',
        'TN'=>'Tennessee','TX'=>'Texas','UT'=>'Utah','VT'=>'Vermont',
        'VA'=>'Virginia','WA'=>'Washington','WV'=>'West Virginia',
        'WI'=>'Wisconsin','WY'=>'Wyoming'
    ];

    $text = cleanImportText($value);
    $upper = strtoupper($text);

    if ($upper === 'NATIONAL') {
        return 'NA';
    }

    if (isset($states[$upper])) {
        return $upper;
    }

    foreach ($states as $code => $name) {
        if (strcasecmp($text, $name) === 0 ||
            preg_match('/^' . preg_quote($name, '/') . '\s*\(/i', $text)) {
            return $code;
        }
    }

    return null;
}

// Normalize official URLs for duplicate comparisons.
// Remove fragments, default ports, and trailing slashes.
function normalizeImportUrl(mixed $value): ?string
{
    $url = cleanImportText($value);

    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return null;
    }

    $parts = parse_url($url);

    if (!is_array($parts) ||
        !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) ||
        empty($parts['host'])) {
        return null;
    }

    $scheme = strtolower($parts['scheme']);
    $host = strtolower($parts['host']);
    $port = $parts['port'] ?? null;

    $portText = '';
    if ($port !== null &&
        !(($scheme === 'http' && $port === 80) ||
          ($scheme === 'https' && $port === 443))) {
        $portText = ':' . $port;
    }

    $path = rtrim($parts['path'] ?? '', '/');

    // Remove tracking parameters while preserving scholarship identifiers.
    $query = '';

    if (isset($parts['query'])) {
        parse_str($parts['query'], $parameters);

        foreach (array_keys($parameters) as $key) {
            $lowerKey = strtolower($key);

            if (str_starts_with($lowerKey, 'utm_') ||
                in_array($lowerKey, [
                    'fbclid',
                    'gclid',
                    'msclkid',
                    'mc_cid',
                    'mc_eid'
                ], true)) {
                unset($parameters[$key]);
            }
        }

        if ($parameters !== []) {
            $query = '?' . http_build_query($parameters);
        }
    }

    return $scheme . '://' . $host . $portText . $path . $query;
}

// Clean one scholarship row and return its validation result.
function validateScholarshipRow(array $row): array
{
    $cleaned = [];
    $errors = [];
    $warnings = [];

    foreach (scholarshipImportFields() as $field) {
        $cleaned[$field] = cleanImportText($row[$field] ?? '');
    }

    $name = $cleaned['Scholarship name'];
    $provider = $cleaned['Scholarship provider/organization'];
    $url = $cleaned['Official scholarship URL'];

    if ($name === '') {
        $errors[] = 'Scholarship name is missing.';
    }

    if ($provider === '') {
        $errors[] = 'Scholarship provider is missing.';
    }

    if (mb_strlen($name) > 255 || mb_strlen($provider) > 255) {
        $errors[] = 'Scholarship name or provider exceeds 255 characters.';
    }

    $normalizedUrl = normalizeImportUrl($url);

    if ($normalizedUrl === null) {
        $errors[] = 'Official scholarship URL is missing or invalid.';
    } elseif (strlen($normalizedUrl) > 1000) {
        $errors[] = 'Official scholarship URL is too long.';
    }

    $cleaned['normalized_url'] = $normalizedUrl;

    $state = cleanImportState($cleaned['State/region']);
    $cleaned['state_code'] = $state;

    $classification = strtolower($cleaned['National vs. state/local']);

    if (str_contains($classification, 'regional')) {
        // Regional eligibility may span several states.
        // Keep it out of the national category until reviewed.
        $cleaned['classification'] = 'State/Local';
        $warnings[] = 'Regional scholarship: verify eligible states.';
    } elseif (str_contains($classification, 'national')) {
        $cleaned['classification'] = 'National';
    } elseif (str_contains($classification, 'state') ||
              str_contains($classification, 'local')) {
        $cleaned['classification'] = 'State/Local';
    } else {
        $cleaned['classification'] = null;
        $warnings[] = 'National/state classification needs review.';
    }

    if ($state === null) {
        $warnings[] = 'State or region needs review.';
    }

    if ($state === 'NA' && $cleaned['classification'] === 'State/Local') {
        $warnings[] = 'National state conflicts with State/Local classification.';
    }

    $grades = cleanImportGrades($cleaned['Eligible grade levels']);
    $cleaned['grades'] = $grades;

    if ($grades === []) {
        $warnings[] = 'Eligible grade levels need review.';
    }

    foreach ([
        'Renewable (Yes/No/Unknown)',
        'Financial need required (Yes/No/Unknown)',
        'Essay required',
        'Recommendation required',
        'Transcript/test score required',
        'Community service requirement',
        'Leadership requirement'
    ] as $field) {
        $cleaned[$field . '_normalized'] = cleanImportYesNo($cleaned[$field]);
    }

    foreach (['Award amount', 'Maximum potential value'] as $field) {
        $amount = cleanImportMoney($cleaned[$field]);
        $cleaned[$field . '_numeric'] = $amount;

        if ($amount === null && $cleaned[$field] !== '') {
            $warnings[] = $field . ' is descriptive or needs review.';
        }
    }

    foreach ([
        'Application open date (if known)',
        'Application deadline',
        'Verification date'
    ] as $field) {
        $date = cleanImportDate($cleaned[$field]);
        $cleaned[$field . '_date'] = $date;

        if ($date === null && $cleaned[$field] !== '') {
            $warnings[] = $field . ' is unknown or needs review.';
        }
    }

    $status = 'Ready';

    if ($errors !== []) {
        $status = 'Error';
    } elseif ($warnings !== []) {
        $status = 'Warning';
    }

    return [
        'status' => $status,
        'errors' => $errors,
        'warnings' => $warnings,
        'message' => implode(' ', array_merge($errors, $warnings)),
        'cleaned_data' => $cleaned,
    ];
}
