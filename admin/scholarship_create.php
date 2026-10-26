<?php
declare(strict_types=1);

/*
 * Iteration 4 - Create Scholarship
 *
 * Allows an administrator to create a scholarship.
 * Validates form data before saving to MySQL.
 * Saves scholarship relationships in one transaction.
 *
 * Development mode only until authentication is implemented.
 */

// Load existing project helpers.
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/scholarship_form_helpers.php';

// Use the project's development role placeholder.
require_role('Administrator');

// Load dropdown and checkbox options from MySQL.
$options = scholarship_form_options($pdo);

// Store submitted values and validation errors.
$values = [];
$errors = [];
$success = '';
$databaseError = '';

// This page submits the form to itself.
$action = 'scholarship_create.php';

// Display a success message after redirecting.
if (isset($_GET['created']) && ctype_digit((string)$_GET['created'])) {
    $success = 'Scholarship created successfully! ID: '
        . (int)$_GET['created'];
}

// Process the form only when submitted.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Preserve submitted values if validation fails.
    $values = $_POST;

    // Verify the CSRF token before changing data.
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        $databaseError = 'Invalid security token. Reload and try again.';

    } else {

        // Run the shared PHP validation.
        $validation = scholarship_validate_form($_POST, $options);

        $data = $validation['data'];
        $errors = $validation['errors'];

        // Only save when every validation check passes.
        if ($errors === []) {

            try {

                // Check for an existing active scholarship URL.
                $duplicate = $pdo->prepare("
                    SELECT scholarship_id
                    FROM scholarships
                    WHERE normalized_url = ?
                      AND status = 'active'
                    LIMIT 1
                ");

                $duplicate->execute([$data['normalized_url']]);

                if ($duplicate->fetchColumn() !== false) {
                    $errors['official_url'] =
                        'An active scholarship already uses this URL.';
                } else {

                    // Begin a transaction before saving.
                    $pdo->beginTransaction();

                    // These are the approved database columns.
                    // Do not accept column names from the user.
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

                    // Build the placeholders for PDO.
                    $placeholders = implode(
                        ', ',
                        array_fill(0, count($columns), '?')
                    );

                    $columnNames = implode(', ', $columns);

                    // created_by and updated_by use the
                    // current development user.
                    $sql = "
                        INSERT INTO scholarships
                        ($columnNames, created_by, updated_by)
                        VALUES ($placeholders, ?, ?)
                    ";

                    $stmt = $pdo->prepare($sql);

                    // Collect validated values in column order.
                    $parameters = [];

                    foreach ($columns as $column) {
                        $parameters[] = $data[$column] ?? null;
                    }

                    $parameters[] = current_user_id();
                    $parameters[] = current_user_id();

                    // Insert the main scholarship record.
                    $stmt->execute($parameters);

                    // Get the new scholarship's database ID.
                    $scholarshipId = (int)$pdo->lastInsertId();

                    /*
                     * Save many-to-many relationships.
                     *
                     * One scholarship can have several grades,
                     * categories, and fields of study.
                     */
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

                    foreach ($relationships as $field => $config) {

                        // Table and column names are fixed in code.
                        $relationSql = "
                            INSERT INTO {$config['table']}
                            (scholarship_id, {$config['column']})
                            VALUES (?, ?)
                        ";

                        $relationStmt = $pdo->prepare($relationSql);

                        // Insert each selected reference ID.
                        foreach ($data[$field] as $selectedId) {
                            $relationStmt->execute([
                                $scholarshipId,
                                $selectedId
                            ]);
                        }
                    }

                    // Commit only after every insert succeeds.
                    $pdo->commit();

                    // Redirect to prevent duplicate form submissions.
                    header(
                        'Location: scholarship_create.php?created='
                        . $scholarshipId,
                        true,
                        303
                    );
                    exit;
                }

            } catch (PDOException $e) {

                // Undo partial database changes.
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                // MySQL error 1062 means a duplicate unique key.
                $mysqlCode = (int)($e->errorInfo[1] ?? 0);

                if ($mysqlCode === 1062) {
                    $errors['official_url'] =
                        'An active scholarship already uses this URL.';
                } else {
                    // Record details in the server log, not the page.
                    error_log(
                        'Scholarship creation failed: ' . $e->getMessage()
                    );

                    $databaseError =
                        'Unable to save the scholarship. Please try again.';
                }

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log(
                    'Scholarship creation failed: ' . $e->getMessage()
                );

                $databaseError =
                    'An unexpected error occurred while saving.';
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

    <title>Create Scholarship | Pathways to Scholarships</title>

    <!-- Bootstrap styling -->
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

    <h1 class="mt-3">Create Scholarship</h1>

    <p class="text-muted">
        Add a new scholarship to the Pathways to Scholarships database.
    </p>

    <!-- Development mode warning -->
    <div class="alert alert-warning">
        <strong>Development mode — no login required.</strong>
        This administrator page is intended for local testing only.
    </div>

    <!-- Successful database insert -->
    <?php if ($success !== ''): ?>
        <div class="alert alert-success">
            <?= e($success) ?>
        </div>
    <?php endif; ?>

    <!-- General database or security errors -->
    <?php if ($databaseError !== ''): ?>
        <div class="alert alert-danger">
            <?= e($databaseError) ?>
        </div>
    <?php endif; ?>

    <!-- Show validation summary -->
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
    // Display our reusable Bootstrap and jQuery form.
    require __DIR__ . '/../includes/scholarship_form.php';
    ?>

</main>

</body>
</html>
