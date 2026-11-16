<?php
declare(strict_types=1);

require_once __DIR__ . '/import_theme_assignments.php';

/*
 * Pathways to Scholarships
 * Iteration 3 — Import Staging Service
 *
 * Stores imported rows in MySQL without adding them to the
 * live scholarships table until a later commit step.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/import_reader.php';
require_once __DIR__ . '/import_validator.php';

// Extract the contributing student's name from a filename.
// Example: Ogega_Bryan_Scholarships.csv becomes Ogega_Bryan.
function importStudentFromFilename(string $filename): ?string
{
    $base = pathinfo(basename($filename), PATHINFO_FILENAME);

    if (preg_match('/^([A-Za-z-]+)_([A-Za-z-]+)/', $base, $matches)) {
        return $matches[1] . '_' . $matches[2];
    }

    return null;
}

// Check whether a normalized URL already exists in live scholarships.
function findExistingScholarship(PDO $pdo, string $url): ?int
{
    $stmt = $pdo->prepare(
        'SELECT scholarship_id
         FROM scholarships
         WHERE normalized_url = :url
           AND status = :status
         LIMIT 1'
    );

    $stmt->execute([
        ':url' => $url,
        ':status' => 'active',
    ]);

    $id = $stmt->fetchColumn();

    return $id === false ? null : (int) $id;
}

// Stage one CSV file and return its batch ID.
function stageScholarshipCSV(string $filePath, string $originalFilename): int
{
    $rows = readScholarshipCSV($filePath);
    $pdo = getDBConnection();

    // One transaction ensures a failed staging operation
    // does not leave a partially imported batch.
    $pdo->beginTransaction();

    try {
        $student = importStudentFromFilename($originalFilename);

        $batchStmt = $pdo->prepare(
            'INSERT INTO import_batches
             (file_name, contributing_student, batch_status, total_rows)
             VALUES (:file_name, :student, :status, :total_rows)'
        );

        $batchStmt->execute([
            ':file_name' => basename($originalFilename),
            ':student' => $student,
            ':status' => 'staged',
            ':total_rows' => count($rows),
        ]);

        $batchId = (int) $pdo->lastInsertId();

        // Track duplicates within the uploaded file.
        $seenUrls = [];

        // Check duplicates against previously staged files.
        $stagedDuplicateStmt = $pdo->prepare(
            'SELECT sr.staging_row_id
             FROM import_staging_rows sr
             JOIN import_batches ib
               ON ib.import_batch_id = sr.import_batch_id
             WHERE JSON_UNQUOTE(
                 JSON_EXTRACT(sr.cleaned_data, "$.normalized_url")
             ) = :url
               AND ib.batch_status IN ("staged", "validated")
               AND sr.validation_status IN ("Ready", "Warning")
             LIMIT 1'
        );

        $rowStmt = $pdo->prepare(
            'INSERT INTO import_staging_rows
             (import_batch_id, `row_number`, raw_data, cleaned_data,
              validation_status, validation_message, duplicate_scholarship_id)
             VALUES
             (:batch_id, :row_number, :raw_data, :cleaned_data,
              :status, :message, :duplicate_id)'
        );

        $counts = [
            'Ready' => 0,
            'Warning' => 0,
            'Duplicate' => 0,
            'Error' => 0,
        ];

        foreach ($rows as $row) {
            $result = validateScholarshipRow($row['data']);

            $cleaned = $result['cleaned_data'];
            $status = $result['status'];
            $message = $result['message'];
            $duplicateId = null;

            $url = $cleaned['normalized_url'];

            // Only perform duplicate checks on valid URLs.
            if ($url !== null && $status !== 'Error') {
                $duplicateId = findExistingScholarship($pdo, $url);

                $stagedDuplicateStmt->execute([':url' => $url]);
                $stagedId = $stagedDuplicateStmt->fetchColumn();

                if (isset($seenUrls[$url]) ||
                    $duplicateId !== null ||
                    $stagedId !== false) {
                    $status = 'Duplicate';
                    $message = trim(
                        $message . ' Duplicate official scholarship URL.'
                    );
                }

                $seenUrls[$url] = true;
            }

            $rowStmt->execute([
                ':batch_id' => $batchId,
                ':row_number' => $row['row_number'],
                ':raw_data' => json_encode(
                    $row['data'],
                    JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE
                ),
                ':cleaned_data' => json_encode(
                    $cleaned,
                    JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE
                ),
                ':status' => $status,
                ':message' => $message,
                ':duplicate_id' => $duplicateId,
            ]);

            $counts[$status]++;
        }

        $updateStmt = $pdo->prepare(
            'UPDATE import_batches
             SET batch_status = :status,
                 ready_rows = :ready,
                 warning_rows = :warning,
                 duplicate_rows = :duplicates,
                 error_rows = :errors
             WHERE import_batch_id = :batch_id'
        );

        $updateStmt->execute([
            ':status' => 'validated',
            ':ready' => $counts['Ready'],
            ':warning' => $counts['Warning'],
            ':duplicates' => $counts['Duplicate'],
            ':errors' => $counts['Error'],
            ':batch_id' => $batchId,
        ]);

        $pdo->commit();

        return $batchId;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

// Convert a scholarship's school-level description to our database enum.
function importSchoolLevel(string $value): ?string
{
    $text = strtolower(cleanImportText($value));

    if (str_contains($text, 'both')) {
        return 'Both';
    }

    if (str_contains($text, 'middle') && str_contains($text, 'high')) {
        return 'Both';
    }

    if (str_contains($text, 'middle')) {
        return 'Middle School';
    }

    if (str_contains($text, 'high')) {
        return 'High School';
    }

    return null;
}

// Commit all eligible records from one validated import batch.
function commitScholarshipBatch(int $batchId): int
{
    $pdo = getDBConnection();
    $pdo->beginTransaction();

    try {
        // Lock the batch to prevent two simultaneous commits.
        $batchStmt = $pdo->prepare(
            'SELECT batch_status, contributing_student
             FROM import_batches
             WHERE import_batch_id = ?
             FOR UPDATE'
        );

        $batchStmt->execute([$batchId]);
        $batch = $batchStmt->fetch();

        if (!$batch || $batch['batch_status'] !== 'validated') {
            throw new RuntimeException('This batch is not ready to commit.');
        }

        $rowStmt = $pdo->prepare(
            'SELECT cleaned_data
             FROM import_staging_rows
             WHERE import_batch_id = ?
               AND validation_status IN ("Ready", "Warning")
             ORDER BY `row_number`'
        );

        $rowStmt->execute([$batchId]);
        $rows = $rowStmt->fetchAll();

        $providerTypeStmt = $pdo->prepare(
            'SELECT provider_type_id
             FROM provider_types
             WHERE type_name = ?
             LIMIT 1'
        );

        $providerStmt = $pdo->prepare(
            'SELECT provider_id
             FROM providers
             WHERE provider_name = ?
               AND provider_type_id = ?
             LIMIT 1'
        );

        $insertProviderStmt = $pdo->prepare(
            'INSERT INTO providers (provider_name, provider_type_id)
             VALUES (?, ?)'
        );

        $stateStmt = $pdo->prepare(
            'SELECT state_id FROM states WHERE state_code = ?'
        );

        $insertStmt = $pdo->prepare(
            'INSERT INTO scholarships (
                provider_id,
                state_id,
                import_batch_id,
                scholarship_name,
                official_url,
                normalized_url,
                discovery_source,
                national_or_state_local,
                school_level,
                award_amount_min,
                award_amount_max,
                theme_id,
                number_of_awards,
                minimum_gpa,
                application_method,
                application_open_date,
                application_deadline,
                renewable,
                maximum_potential_value,
                residency_requirement,
                financial_need_required,
                essay_required,
                recommendation_required,
                transcript_test_required,
                community_service_required,
                leadership_required,
                other_eligibility,
                verification_date,
                notes,
                contributing_student
             ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
             )'
        );

        $gradeStmt = $pdo->prepare(
            'INSERT INTO scholarship_grades (scholarship_id, grade_id)
             SELECT ?, grade_id
             FROM grade_levels
             WHERE grade_number = ?'
        );

        $committed = 0;

        foreach ($rows as $row) {
            $data = json_decode(
                $row['cleaned_data'],
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            // Assign national themes only to national scholarships.
            $themeId = null;

            if (($data['classification'] ?? '') === 'National') {
                $assignedTheme = importAssignedNationalTheme(
                    $batch['contributing_student'] ?? null
                );

                $themeId = importNationalThemeId($pdo, $assignedTheme);
            }

            $url = $data['normalized_url'];

            // Check again because other batches may have committed since staging.
            if (findExistingScholarship($pdo, $url) !== null) {
                throw new RuntimeException(
                    'A scholarship URL was already committed. Revalidate this batch.'
                );
            }

            $providerName = $data['Scholarship provider/organization'];
            $providerTypeName = $data['Provider type'] ?: 'Other';

            $providerTypeStmt->execute([$providerTypeName]);
            $providerTypeId = $providerTypeStmt->fetchColumn();

            if ($providerTypeId === false) {
                $providerTypeStmt->execute(['Other']);
                $providerTypeId = $providerTypeStmt->fetchColumn();
            }

            if ($providerTypeId === false) {
                throw new RuntimeException('Provider type could not be resolved.');
            }

            $providerStmt->execute([$providerName, $providerTypeId]);
            $providerId = $providerStmt->fetchColumn();

            if ($providerId === false) {
                $insertProviderStmt->execute([$providerName, $providerTypeId]);
                $providerId = $pdo->lastInsertId();
            }

            $stateCode = $data['state_code'];

            // National scholarships use the National state reference.
            if ($data['classification'] === 'National') {
                $stateCode = 'NA';
            }

            $stateId = null;

            if ($stateCode !== null) {
                $stateStmt->execute([$stateCode]);
                $result = $stateStmt->fetchColumn();
                $stateId = $result === false ? null : (int) $result;
            }

            $award = $data['Award amount_numeric'];
            $schoolLevel = importSchoolLevel(
                $data['Middle school / high school / both']
            );

            $insertStmt->execute([
                $providerId,
                $stateId,
                $batchId,
                $data['Scholarship name'],
                $data['Official scholarship URL'],
                $url,
                $data['Discovery source'],
                $data['classification'] ?? 'State/Local',
                $schoolLevel,
                $award,
                $award,
                $themeId,
                importNumberOfAwards($data['Number of awards (if known)']),
                importMinimumGpa($data['Minimum GPA (if any)']),
                importApplicationMethod($data['Application method']),
                $data['Application open date (if known)_date'],
                $data['Application deadline_date'],
                $data['Renewable (Yes/No/Unknown)_normalized'],
                $data['Maximum potential value_numeric'],
                $data['Residency requirement'],
                $data['Financial need required (Yes/No/Unknown)_normalized'],
                $data['Essay required_normalized'],
                $data['Recommendation required_normalized'],
                $data['Transcript/test score required_normalized'],
                $data['Community service requirement_normalized'],
                $data['Leadership requirement_normalized'],
                $data['Other major eligibility requirements'],
                $data['Verification date_date'],
                importPreservedNotes($data),
                $batch['contributing_student'],
            ]);

            $scholarshipId = (int) $pdo->lastInsertId();

            // Save eligible grade levels in the many-to-many table.
            foreach ($data['grades'] as $grade) {
                $gradeStmt->execute([$scholarshipId, $grade]);
            }

            // Link the scholarship to its categories.
            foreach (importReferenceValues(
                $data['Scholarship category']
            ) as $categoryName) {

                $categoryId = importReferenceId(
                    $pdo,
                    'categories',
                    $categoryName
                );

                if ($categoryId !== null) {
                    $linkStmt = $pdo->prepare(
                        'INSERT IGNORE INTO scholarship_categories
                         (scholarship_id, category_id)
                         VALUES (?, ?)'
                    );

                    $linkStmt->execute([$scholarshipId, $categoryId]);
                }
            }

            // Link the scholarship to its fields of study.
            foreach (importReferenceValues(
                $data['Field/area of interest']
            ) as $fieldName) {

                $fieldId = importReferenceId(
                    $pdo,
                    'fields_of_study',
                    $fieldName
                );

                if ($fieldId !== null) {
                    $linkStmt = $pdo->prepare(
                        'INSERT IGNORE INTO scholarship_fields
                         (scholarship_id, field_id)
                         VALUES (?, ?)'
                    );

                    $linkStmt->execute([$scholarshipId, $fieldId]);
                }
            }

            $committed++;
        }

        // Mark the batch committed only after all inserts succeed.
        $updateStmt = $pdo->prepare(
            'UPDATE import_batches
             SET batch_status = "committed",
                 committed_at = NOW()
             WHERE import_batch_id = ?'
        );

        $updateStmt->execute([$batchId]);

        $pdo->commit();

        return $committed;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

// Roll back all scholarships imported by one committed batch.
// This operation does not affect scholarships from other batches.
function rollbackScholarshipBatch(int $batchId): int
{
    $pdo = getDBConnection();
    $pdo->beginTransaction();

    try {
        // Lock the batch so its status cannot change during rollback.
        $batchStmt = $pdo->prepare(
            'SELECT batch_status
             FROM import_batches
             WHERE import_batch_id = ?
             FOR UPDATE'
        );

        $batchStmt->execute([$batchId]);
        $batch = $batchStmt->fetch();

        if (!$batch || $batch['batch_status'] !== 'committed') {
            throw new RuntimeException(
                'Only committed batches can be rolled back.'
            );
        }

        // Identify scholarships belonging to this import batch.
        $idsStmt = $pdo->prepare(
            'SELECT scholarship_id
             FROM scholarships
             WHERE import_batch_id = ?
             FOR UPDATE'
        );

        $idsStmt->execute([$batchId]);
        $scholarshipIds = array_map(
            'intval',
            $idsStmt->fetchAll(PDO::FETCH_COLUMN)
        );

        // Delete related records before deleting scholarships.
        // Foreign keys require dependent records to be removed first.
        foreach ([
            'scholarship_grades',
            'scholarship_categories',
            'scholarship_fields',
            'saved_scholarships'
        ] as $table) {
            $stmt = $pdo->prepare(
                "DELETE FROM {$table} WHERE scholarship_id = ?"
            );

            foreach ($scholarshipIds as $id) {
                $stmt->execute([$id]);
            }
        }

        // Remove the imported scholarships.
        $deleteStmt = $pdo->prepare(
            'DELETE FROM scholarships WHERE import_batch_id = ?'
        );

        $deleteStmt->execute([$batchId]);
        $deletedCount = $deleteStmt->rowCount();

        // Preserve the batch and its staging rows for import history.
        $updateStmt = $pdo->prepare(
            'UPDATE import_batches
             SET batch_status = "rolled_back",
                 rolled_back_at = NOW()
             WHERE import_batch_id = ?'
        );

        $updateStmt->execute([$batchId]);

        $pdo->commit();

        return $deletedCount;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

// Preserve descriptive scholarship values that cannot be stored
// in numeric or date columns without losing information.
function importPreservedNotes(array $data): string
{
    $notes = cleanImportText($data['Notes'] ?? '');
    $additional = [];

    foreach ([
        'Award amount' => 'Award amount_numeric',
        'Maximum potential value' => 'Maximum potential value_numeric',
        'Application open date (if known)' => 'Application open date (if known)_date',
        'Application deadline' => 'Application deadline_date'
    ] as $originalField => $normalizedField) {

        $original = cleanImportText($data[$originalField] ?? '');
        $normalized = $data[$normalizedField] ?? null;

        // Preserve descriptions that cannot be converted.
        if ($original !== '' && $normalized === null) {
            $additional[] = $originalField . ': ' . $original;
        }
    }

    if ($additional !== []) {
        $notes .= ($notes !== '' ? "\n\n" : '')
            . 'Original spreadsheet values:' . "\n"
            . implode("\n", $additional);
    }

    return $notes;
}

// Convert a number of awards into an integer.
// Descriptions such as "Varies" remain null.
function importNumberOfAwards(mixed $value): ?int
{
    $text = cleanImportText($value);

    if ($text === '') {
        return null;
    }

    $number = str_replace(',', '', $text);

    if (!ctype_digit($number)) {
        return null;
    }

    $result = filter_var($number, FILTER_VALIDATE_INT);

    return $result === false ? null : $result;
}

// Convert a minimum GPA into a numeric value.
// Unknown or descriptive requirements remain null.
function importMinimumGpa(mixed $value): ?float
{
    $text = cleanImportText($value);

    if ($text === '' || !is_numeric($text)) {
        return null;
    }

    $gpa = (float) $text;

    if ($gpa < 0 || $gpa > 4.0) {
        return null;
    }

    return $gpa;
}

// Convert application instructions into the database enum.
// The full original instructions will also be preserved in notes.
function importApplicationMethod(mixed $value): ?string
{
    $text = strtolower(cleanImportText($value));

    if ($text === '') {
        return null;
    }

    if (str_contains($text, 'online') ||
        str_contains($text, 'website') ||
        str_contains($text, 'portal')) {
        return 'Online Form';
    }

    if (str_contains($text, 'email')) {
        return 'Email';
    }

    if (str_contains($text, 'mail') ||
        str_contains($text, 'postal')) {
        return 'Mail';
    }

    return 'Other';
}

// Find an existing reference value or create it when missing.
// Only the two approved lookup tables may be used here.
function importReferenceId(PDO $pdo, string $table, string $value): ?int
{
    $value = cleanImportText($value);

    if ($value === '') {
        return null;
    }

    $tables = [
        'categories' => ['category_id', 'category_name'],
        'fields_of_study' => ['field_id', 'field_name'],
    ];

    if (!isset($tables[$table])) {
        throw new InvalidArgumentException('Unsupported reference table.');
    }

    [$idColumn, $nameColumn] = $tables[$table];

    if (mb_strlen($value) > 150) {
        throw new RuntimeException('Reference value exceeds 150 characters.');
    }

    $select = $pdo->prepare(
        "SELECT {$idColumn}
         FROM {$table}
         WHERE {$nameColumn} = ?
         LIMIT 1"
    );

    $select->execute([$value]);
    $existingId = $select->fetchColumn();

    if ($existingId !== false) {
        return (int) $existingId;
    }

    $insert = $pdo->prepare(
        "INSERT INTO {$table} ({$nameColumn}) VALUES (?)"
    );

    $insert->execute([$value]);

    return (int) $pdo->lastInsertId();
}

// Split multiple categories or fields separated by semicolons.
// Commas are preserved because some official category names contain them.
function importReferenceValues(string $value): array
{
    $parts = explode(';', $value);
    $values = [];

    foreach ($parts as $part) {
        $part = cleanImportText($part);

        if ($part !== '') {
            $values[] = $part;
        }
    }

    return array_values(array_unique($values));
}

// Resolve a national theme by its official name.
// The theme name must already exist in the seeded reference table.
function importNationalThemeId(PDO $pdo, ?string $themeName): ?int
{
    $themeName = cleanImportText($themeName);

    if ($themeName === '') {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT theme_id
         FROM national_themes
         WHERE theme_name = ?
         LIMIT 1'
    );

    $stmt->execute([$themeName]);
    $themeId = $stmt->fetchColumn();

    if ($themeId === false) {
        throw new RuntimeException(
            'National scholarship theme was not found.'
        );
    }

    return (int) $themeId;
}
