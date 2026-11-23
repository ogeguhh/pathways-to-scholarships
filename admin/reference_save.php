<?php
declare(strict_types=1);

// Pathways to Scholarships
// Iteration 7: Save Reference Data

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/includes/db.php';
require_once $projectRoot . '/includes/security.php';
require_once $projectRoot . '/includes/reference_service.php';

// Only Super-admins can modify reference data.
require_role('Super-admin');

$pdo = getDBConnection();

// Only POST requests can modify database records.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

// Protect against forged form submissions.
if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Invalid security token.');
}

$actorId = current_user_id();

$table = $_POST['table'] ?? '';

$definitions = referenceDefinitions();

// Validate the table before using it in SQL statements.
if (!is_string($table) || !isset($definitions[$table])) {
    http_response_code(400);
    exit('Invalid reference data table.');
}

$definition = referenceDefinition($table);

$rawId = $_POST['id'] ?? '';

$isEdit = $rawId !== '';

$id = filter_var($rawId, FILTER_VALIDATE_INT);

// Return to the selected reference table with feedback.
function returnToReferenceData(
    string $table,
    string $message,
    bool $success
): void {
    $_SESSION[
        $success ? 'reference_success' : 'reference_error'
    ] = $message;

    header(
        'Location: /admin/reference_data.php?table='
        . urlencode($table)
    );

    exit;
}

try {

    if ($isEdit && (!$id || $id < 1)) {
        throw new InvalidArgumentException(
            'Invalid reference ID.'
        );
    }

    // Reuse the validation rules from our shared service.
    $values = referenceValidate($table, $_POST);

    $pdo->beginTransaction();

    // Recheck the acting user's permissions before modifying data.
    $stmt = $pdo->prepare(
        'SELECT
            u.account_status,
            u.must_change_password,
            r.role_name
         FROM users u
         JOIN roles r ON u.role_id = r.role_id
         WHERE u.user_id = ?
         FOR UPDATE'
    );

    $stmt->execute([$actorId]);

    $actor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (
        !$actor ||
        $actor['role_name'] !== 'Super-admin' ||
        $actor['account_status'] !== 'active' ||
        $actor['must_change_password']
    ) {
        throw new RuntimeException(
            'You are not authorized to modify reference data.'
        );
    }

    $existingRecord = null;

    if ($isEdit) {

        // Lock the existing record while it is being updated.
        $stmt = $pdo->prepare(
            "SELECT * FROM `$table`
             WHERE `{$definition['id']}` = ?
             FOR UPDATE"
        );

        $stmt->execute([$id]);

        $existingRecord = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existingRecord) {
            throw new RuntimeException(
                'Reference value not found.'
            );
        }
    }

    // Check each unique reference field for duplicate values.
    foreach ($values as $column => $value) {

        $sql = "SELECT `{$definition['id']}`
                FROM `$table`
                WHERE `$column` = ?";

        $parameters = [$value];

        // Exclude the current record when editing.
        if ($isEdit) {

            $sql .= " AND `{$definition['id']}` <> ?";

            $parameters[] = $id;
        }

        $stmt = $pdo->prepare($sql);

        $stmt->execute($parameters);

        if ($stmt->fetchColumn() !== false) {

            $label = $definition['fields'][$column];

            throw new RuntimeException(
                "$label already exists."
            );
        }
    }

    if ($isEdit) {

        // Build the UPDATE statement using approved column names.
        $assignments = [];

        foreach (array_keys($values) as $column) {
            $assignments[] = "`$column` = ?";
        }

        $sql = "UPDATE `$table` SET "
            . implode(', ', $assignments)
            . " WHERE `{$definition['id']}` = ?";

        $parameters = array_values($values);

        $parameters[] = $id;

        $stmt = $pdo->prepare($sql);

        $stmt->execute($parameters);

        // Record the previous and updated reference values.
        referenceAudit(
            $pdo,
            $actorId,
            'update',
            $table,
            $id,
            [
                'before' => $existingRecord,
                'after' => $values
            ]
        );

        $message = 'Reference value updated successfully.';

    } else {

        // Generate the INSERT statement using approved columns.
        $columns = array_map(
            fn($column) => "`$column`",
            array_keys($values)
        );

        $placeholders = array_fill(
            0,
            count($values),
            '?'
        );

        $sql = "INSERT INTO `$table` ("
            . implode(', ', $columns)
            . ') VALUES ('
            . implode(', ', $placeholders)
            . ')';

        $stmt = $pdo->prepare($sql);

        $stmt->execute(array_values($values));

        $id = (int) $pdo->lastInsertId();

        // Record the newly created reference value.
        referenceAudit(
            $pdo,
            $actorId,
            'create',
            $table,
            $id,
            [
                'created' => $values
            ]
        );

        $message = 'Reference value added successfully.';
    }

    // Save the reference change and audit record together.
    $pdo->commit();

    returnToReferenceData(
        $table,
        $message,
        true
    );

} catch (Throwable $exception) {

    // Undo database changes if any operation fails.
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($exception instanceof PDOException) {

        error_log($exception->getMessage());

        // MySQL error 1062 indicates a duplicate unique value.
        $message = (int) ($exception->errorInfo[1] ?? 0) === 1062
            ? 'This reference value already exists.'
            : 'Unable to save the reference value.';

    } elseif (
        $exception instanceof InvalidArgumentException ||
        $exception instanceof RuntimeException
    ) {

        $message = $exception->getMessage();

    } else {

        error_log($exception->getMessage());

        $message = 'Unable to save the reference value.';
    }

    returnToReferenceData(
        $table,
        $message,
        false
    );
}
