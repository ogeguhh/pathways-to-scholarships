<?php
declare(strict_types=1);

/*
 * Iteration 4 - Edit Scholarship
 *
 * Loads an existing scholarship and its selections.
 * Validates changes with the shared PHP validator.
 * Updates the scholarship and relationships together.
 *
 * Development mode only until login is implemented.
 */

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/scholarship_form_helpers.php';
// Load the team's shared audit logging function.
require_once __DIR__ . '/../includes/audit_logger.php';

// Use the development role placeholder.
require_role('Administrator');

// Load dropdown and checkbox choices.
$options = scholarship_form_options($pdo);

$errors = [];
$databaseError = '';
$success = '';
$values = [];

// Get the scholarship ID from the URL.
$id = filter_var(
    $_GET['id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($id === false || $id === null) {
    http_response_code(400);
    exit('A valid scholarship ID is required.');
}

// Load an existing scholarship.
$stmt = $pdo->prepare("
    SELECT *
    FROM scholarships
    WHERE scholarship_id = ?
    LIMIT 1
");

$stmt->execute([$id]);
$scholarship = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$scholarship) {
    http_response_code(404);
    exit('Scholarship not found.');
}

// Load existing checkbox selections.
$relationships = [
    'grade_ids' => [
        'table' => 'scholarship_grades',
        'column' => 'grade_id'
    ],
    'category_ids' => [
        'table' => 'scholarship_categories',
        'column' => 'category_id'
    ],
    'field_ids' => [
        'table' => 'scholarship_fields',
        'column' => 'field_id'
    ]
];

// Start with existing scholarship values.
$values = $scholarship;

foreach ($relationships as $field => $config) {
    $sql = "
        SELECT {$config['column']}
        FROM {$config['table']}
        WHERE scholarship_id = ?
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);

    $values[$field] = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// This page submits changes back to itself.
$action = 'scholarship_edit.php?id=' . $id;

if (isset($_GET['updated'])) {
    $success = 'Scholarship updated successfully!';
}

// Process a submitted edit.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Keep submitted values when errors occur.
    $values = $_POST;

    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        $databaseError = 'Invalid security token. Reload and try again.';

    } else {

        // Validate using the same rules as Create.
        $validation = scholarship_validate_form($_POST, $options);

        $data = $validation['data'];
        $errors = $validation['errors'];

        if ($errors === []) {

            try {

                // Start one transaction for all changes.
                $pdo->beginTransaction();

                // Lock this scholarship during the update.
                $lock = $pdo->prepare("
                    SELECT scholarship_id
                    FROM scholarships
                    WHERE scholarship_id = ?
                    FOR UPDATE
                ");

                $lock->execute([$id]);

                if ($lock->fetchColumn() === false) {
                    throw new RuntimeException(
                        'Scholarship no longer exists.'
                    );
                }

                // Check duplicate URLs, excluding this record.
                $duplicate = $pdo->prepare("
                    SELECT scholarship_id
                    FROM scholarships
                    WHERE normalized_url = ?
                      AND status = 'active'
                      AND scholarship_id <> ?
                    LIMIT 1
                ");

                $duplicate->execute([
                    $data['normalized_url'],
                    $id
                ]);

                if ($duplicate->fetchColumn() !== false) {
                    $errors['official_url'] =
                        'Another active scholarship already uses this URL.';

                    $pdo->rollBack();

                } else {

                    /*
                     * Only update approved scholarship columns.
                     *
                     * Keep scholarship_id, import_batch_id,
                     * created_by, created_at, status, and the
                     * generated active_url_hash unchanged.
                     */
                    $columns = [
                        'provider_id',
                        'state_id',
                        'theme_id',
                        'scholarship_name',
                        'official_url',
                        'normalized_url',
                        'discovery_source',
                        'national_or_state_local',
                        'school_level',
                        'award_amount_min',
                        'award_amount_max',
                        'number_of_awards',
                        'renewable',
                        'maximum_potential_value',
                        'application_open_date',
                        'application_deadline',
                        'rolling_deadline',
                        'residency_requirement',
                        'minimum_gpa',
                        'financial_need_required',
                        'essay_required',
                        'recommendation_required',
                        'transcript_test_required',
                        'community_service_required',
                        'leadership_required',
                        'other_eligibility',
                        'application_method',
                        'verification_date',
                        'notes',
                        'contributing_student'
                    ];

                    // Build the SQL assignments.
                    $assignments = [];

                    foreach ($columns as $column) {
                        $assignments[] = "$column = ?";
                    }

                    $assignments[] = 'updated_by = ?';

                    $sql = "
                        UPDATE scholarships
                        SET " . implode(', ', $assignments) . "
                        WHERE scholarship_id = ?
                    ";

                    $stmt = $pdo->prepare($sql);

                    // Supply values in the same column order.
                    $parameters = [];

                    foreach ($columns as $column) {
                        $parameters[] = $data[$column] ?? null;
                    }

                    $parameters[] = current_user_id();
                    $parameters[] = $id;

                    // Update the main scholarship.
                    $stmt->execute($parameters);

                    /*
                     * Replace relationship selections.
                     *
                     * Only rows belonging to this scholarship
                     * are deleted and recreated.
                     */
                    foreach ($relationships as $field => $config) {

                        $deleteSql = "
                            DELETE FROM {$config['table']}
                            WHERE scholarship_id = ?
                        ";

                        $deleteStmt = $pdo->prepare($deleteSql);
                        $deleteStmt->execute([$id]);

                        $insertSql = "
                            INSERT INTO {$config['table']}
                            (scholarship_id, {$config['column']})
                            VALUES (?, ?)
                        ";

                        $insertStmt = $pdo->prepare($insertSql);

                        foreach ($data[$field] as $selectedId) {
                            $insertStmt->execute([
                                $id,
                                $selectedId
                            ]);
                        }
                    }

                        // Record the scholarship update in the audit log.
                        // This runs inside the same transaction as the database update.
                        log_audit(
                            $pdo,
                            'edit',
                            'scholarship',
                            $id,
                            [
                                'scholarship_name' => $data['scholarship_name'],
                                'provider_id' => $data['provider_id'],
                                'normalized_url' => $data['normalized_url']
                            ]
                        );

                        // Save both the scholarship changes and audit entry.
                        $pdo->commit();
                    
                        // Redirect to prevent duplicate submissions.
                    header(
                        'Location: scholarship_edit.php?id='
                        . $id . '&updated=1',
                        true,
                        303
                    );
                    exit;
                }

            } catch (PDOException $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $mysqlCode = (int)($e->errorInfo[1] ?? 0);

                if ($mysqlCode === 1062) {
                    $errors['official_url'] =
                        'Another active scholarship already uses this URL.';
                } else {
                    error_log('Scholarship edit failed: ' . $e->getMessage());

                    $databaseError =
                        'Unable to update the scholarship.';
                }

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log('Scholarship edit failed: ' . $e->getMessage());

                $databaseError =
                    'An unexpected error occurred while updating.';
            }
        }
    }
}
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <title>Edit Scholarship | Pathways to Scholarships</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
</head>

<body class="bg-light">

<main class="container py-5" style="max-width:950px">

    <a href="../index.php" class="text-decoration-none">
        &larr; Home
    </a>

    <h1 class="mt-3">Edit Scholarship</h1>

    <p class="text-muted">
        Editing scholarship #<?= e($id) ?>.
        Update the information and save your changes.
    </p>

    <div class="alert alert-warning">
        <strong>Development mode — no login required.</strong>
        This page is for local testing only.
    </div>

    <?php if ($success !== ''): ?>
        <div class="alert alert-success">
            <?= e($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($databaseError !== ''): ?>
        <div class="alert alert-danger">
            <?= e($databaseError) ?>
        </div>
    <?php endif; ?>

    <?php if ($errors !== []): ?>
        <div class="alert alert-danger">
            <strong>Please correct the following errors:</strong>

            <ul class="mb-0 mt-2">
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php
    // Reuse the same Bootstrap and jQuery form as Create.
    require __DIR__ . '/../includes/scholarship_form.php';
    ?>

</main>

</body>
</html>
