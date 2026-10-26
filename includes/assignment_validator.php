<?php
declare(strict_types=1);

/*
 * Student Assignment Validator
 *
 * Reads the official Data Science student roster.
 * Checks scholarship state and sponsor assignments.
 * Does not modify the database.
 */

/**
 * Find a student's assignment using their name.
 *
 * Accepts names such as:
 * Bryan Ogega
 * Ogega_Bryan
 */
function assignment_find_student(string $student): ?array
{
    // Locate the CSV relative to this PHP file.
    $path = __DIR__ . '/../data/student_assignments.csv';

    $file = fopen($path, 'rb');

    if ($file === false) {
        throw new RuntimeException('Student assignment roster is missing.');
    }

    try {
        // Read the column headings.
        $headers = fgetcsv($file, 0, ',', '"', '');
        if ($headers === false) {
            throw new RuntimeException('Student assignment roster is empty.');
        }

        // Remove a UTF-8 BOM if present.
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);

        // Normalize names for comparison.
        $normalize = static function (string $name): string {
            return strtolower(trim(
                preg_replace('/\s+/', ' ', str_replace('_', ' ', $name))
            ));
        };

        // Search each student in the roster.
while (($values = fgetcsv($file, 0, ',', '"', '')) !== false) {
            if (count($values) !== count($headers)) {
                continue;
            }

            $row = array_combine($headers, $values);

            $fullName = trim($row['student_name'] ?? '');

            $parts = preg_split('/\s+/', $fullName);

            // Convert "Bryan Ogega" to "Ogega Bryan".
            $first = array_shift($parts);
            $last = implode(' ', $parts);

            $reversed = trim($last . ' ' . $first);

            if (
                $normalize($student) === $normalize($fullName) ||
                $normalize($student) === $normalize($reversed)
            ) {
                return $row;
            }
        }

        return null;

    } finally {
        fclose($file);
    }
}

/**
 * Check whether a provider belongs to an assigned letter range.
 *
 * Examples:
 * A-M accepts "Test Foundation"? No, T is outside A-M.
 * N-Z accepts "Test Foundation"? Yes.
 */
function assignment_sponsor_in_range(
    string $provider,
    string $range
): bool {

    // Ignore a leading "The" when identifying the provider.
    $provider = preg_replace('/^the\s+/i', '', trim($provider));

    // Get the first character of the provider name.
    $first = strtoupper(substr($provider, 0, 1));

    if ($first === '') {
        return false;
    }

    if (strcasecmp($range, 'All') === 0) {
        return true;
    }

    // Numeric provider names belong to A-M.
    if (ctype_digit($first)) {
        $first = 'A';
    }

    // Read ranges such as A-M, N-Z, A-H, or I-P.
    if (!preg_match('/^([A-Z])-([A-Z])$/i', trim($range), $matches)) {
        return false;
    }

    return $first >= strtoupper($matches[1])
        && $first <= strtoupper($matches[2]);
}

/**
 * Check a scholarship against the student's assignment.
 *
 * Returns a status and explanation.
 */
function assignment_check_scholarship(
    array $scholarship,
    array $assignment
): array {

    $messages = [];

    $scope = strtolower(trim(
        (string)($scholarship['National vs. state/local'] ?? '')
    ));

    $provider = trim(
        (string)($scholarship['Scholarship provider/organization'] ?? '')
    );

    // Missing provider names cannot be verified.
    if ($provider === '') {
        $messages[] = 'Scholarship provider is missing.';
    }

    if ($scope === 'state/local' || $scope === 'state') {

        // Check the assigned state.
        $state = strtoupper(trim(
            (string)($scholarship['State/region'] ?? '')
        ));

        $expectedState = strtoupper(
            trim($assignment['state_code'] ?? '')
        );

        if ($state !== $expectedState) {
            $messages[] = 'Scholarship does not match the assigned state.';
        }

        // Check the state sponsor-letter range.
        if (
            $provider !== '' &&
            !assignment_sponsor_in_range(
                $provider,
                $assignment['state_sponsor_range']
            )
        ) {
            $messages[] = 'Provider is outside the assigned state sponsor range.';
        }

    } elseif ($scope === 'national') {

        // Check the national sponsor-letter range.
        if (
            $provider !== '' &&
            !assignment_sponsor_in_range(
                $provider,
                $assignment['national_sponsor_range']
            )
        ) {
            $messages[] = 'Provider is outside the assigned national sponsor range.';
        }

        // National theme matching requires additional review.
        // We do not automatically guess a scholarship's theme.

    } else {
        $messages[] = 'Scholarship classification needs review.';
    }

    return [
        'status' => empty($messages) ? 'Ready' : 'Warning',
        'messages' => $messages
    ];
}
