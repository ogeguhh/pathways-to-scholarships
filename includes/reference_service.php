<?php
declare(strict_types=1);

// Pathways to Scholarships
// Iteration 7: Shared Reference Data Service

// Define the only reference tables and columns that can be managed.
// This allowlist prevents arbitrary SQL table or column selection.
function referenceDefinitions(): array
{
    return [
        'states' => [
            'label' => 'States',
            'id' => 'state_id',
            'fields' => [
                'state_code' => 'State Code',
                'state_name' => 'State Name'
            ],
            'order' => 'state_name',
            'dependencies' => [
                ['scholarships', 'state_id'],
                ['student_profiles', 'state_id']
            ]
        ],

        'grade_levels' => [
            'label' => 'Grade Levels',
            'id' => 'grade_id',
            'fields' => [
                'grade_number' => 'Grade Number'
            ],
            'order' => 'grade_number',
            'dependencies' => [
                ['scholarship_grades', 'grade_id'],
                ['student_profiles', 'grade_id']
            ]
        ],

        'fields_of_study' => [
            'label' => 'Fields of Study',
            'id' => 'field_id',
            'fields' => [
                'field_name' => 'Field Name'
            ],
            'order' => 'field_name',
            'dependencies' => [
                ['profile_interests', 'field_id'],
                ['scholarship_fields', 'field_id']
            ]
        ],

        'categories' => [
            'label' => 'Categories',
            'id' => 'category_id',
            'fields' => [
                'category_name' => 'Category Name'
            ],
            'order' => 'category_name',
            'dependencies' => [
                ['scholarship_categories', 'category_id']
            ]
        ],

        'provider_types' => [
            'label' => 'Provider Types',
            'id' => 'provider_type_id',
            'fields' => [
                'type_name' => 'Type Name'
            ],
            'order' => 'type_name',
            'dependencies' => [
                ['providers', 'provider_type_id']
            ]
        ],

        'national_themes' => [
            'label' => 'National Themes',
            'id' => 'theme_id',
            'fields' => [
                'theme_name' => 'Theme Name'
            ],
            'order' => 'theme_name',
            'dependencies' => [
                ['scholarships', 'theme_id']
            ]
        ]
    ];
}

// Retrieve a definition only if the table is allowlisted.
function referenceDefinition(string $table): array
{
    $definitions = referenceDefinitions();

    if (!isset($definitions[$table])) {
        throw new InvalidArgumentException(
            'Invalid reference data table.'
        );
    }

    return $definitions[$table];
}

// Retrieve all records from an approved reference table.
function referenceRows(PDO $pdo, string $table): array
{
    $definition = referenceDefinition($table);

    $sql = "SELECT * FROM `$table`
            ORDER BY `{$definition['order']}`";

    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

// Retrieve one reference record by its primary key.
function referenceFind(
    PDO $pdo,
    string $table,
    int $id
): ?array
{
    $definition = referenceDefinition($table);

    $stmt = $pdo->prepare(
        "SELECT * FROM `$table`
         WHERE `{$definition['id']}` = ?"
    );

    $stmt->execute([$id]);

    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    return $record ?: null;
}

// Validate submitted reference data before saving it.
function referenceValidate(
    string $table,
    array $input
): array
{
    $definition = referenceDefinition($table);

    $values = [];

    foreach ($definition['fields'] as $column => $label) {

        $rawValue = $input[$column] ?? '';

        if (!is_string($rawValue)) {
            throw new InvalidArgumentException(
                "$label is invalid."
            );
        }

        $value = trim($rawValue);

        if ($value === '') {
            throw new InvalidArgumentException(
                "$label is required."
            );
        }

        // State codes must contain exactly two letters.
        if ($column === 'state_code') {

            $value = strtoupper($value);

            if (!preg_match('/^[A-Z]{2}$/', $value)) {
                throw new InvalidArgumentException(
                    'State code must contain exactly two letters.'
                );
            }

        // Our scholarship system supports grades 6 through 12.
        } elseif ($column === 'grade_number') {

            $grade = filter_var(
                $value,
                FILTER_VALIDATE_INT
            );

            if ($grade === false || $grade < 6 || $grade > 12) {
                throw new InvalidArgumentException(
                    'Grade number must be between 6 and 12.'
                );
            }

            $value = $grade;

        } else {

            // Match the maximum lengths in our database schema.
            $maxLength = $column === 'state_name'
                ? 100
                : 150;

            if (mb_strlen($value) > $maxLength) {
                throw new InvalidArgumentException(
                    "$label cannot exceed $maxLength characters."
                );
            }
        }

        $values[$column] = $value;
    }

    return $values;
}

// Count records that depend on a reference value.
// Used to prevent deletion of values that are still in use.
function referenceUsageCount(
    PDO $pdo,
    string $table,
    int $id
): int
{
    $definition = referenceDefinition($table);

    $total = 0;

    foreach ($definition['dependencies'] as $dependency) {

        [$dependentTable, $dependentColumn] = $dependency;

        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM `$dependentTable`
             WHERE `$dependentColumn` = ?"
        );

        $stmt->execute([$id]);

        $total += (int) $stmt->fetchColumn();
    }

    return $total;
}

// Record reference data changes in the administrative audit log.
function referenceAudit(
    PDO $pdo,
    int $actorId,
    string $action,
    string $table,
    int $id,
    array $details
): void
{
    referenceDefinition($table);

    $stmt = $pdo->prepare(
        'INSERT INTO audit_log
            (user_id, action, entity_type, entity_id, details)
         VALUES (?, ?, ?, ?, ?)'
    );

    $stmt->execute([
        $actorId,
        $action,
        'reference_data',
        $id,
        json_encode([
            'table' => $table,
            'changes' => $details
        ], JSON_THROW_ON_ERROR)
    ]);
}
