<?php
declare(strict_types=1);

// Pathways to Scholarships
// Iteration 7: Safe Reference Data Deletion

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/includes/db.php';
require_once $projectRoot . '/includes/security.php';
require_once $projectRoot . '/includes/reference_service.php';

// Only Super-admins can delete reference values.
require_role('Super-admin');

$pdo = getDBConnection();

// Deletion must use a POST request.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

// Prevent forged deletion requests.
if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Invalid security token.');
}

$actorId = current_user_id();

$table = $_POST['table'] ?? '';

$definitions = referenceDefinitions();

// Reject tables outside our approved reference data list.
if (!is_string($table) || !isset($definitions[$table])) {
    http_response_code(400);
    exit('Invalid reference data table.');
}

$definition = referenceDefinition($table);

$id = filter_var(
    $_POST['id'] ?? null,
    FILTER_VALIDATE_INT
);

// Redirect back to the selected reference table.
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

    if (!$id || $id < 1) {
        throw new InvalidArgumentException(
            'Invalid reference ID.'
        );
    }

    $pdo->beginTransaction();

    // Recheck the acting user's authorization.
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
            'You are not authorized to delete reference data.'
        );
    }

    // Lock the selected record until the transaction finishes.
    $stmt = $pdo->prepare(
        "SELECT * FROM `$table`
         WHERE `{$definition['id']}` = ?
         FOR UPDATE"
    );

    $stmt->execute([$id]);

    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        throw new RuntimeException(
            'Reference value not found.'
        );
    }

    // Check whether other database records depend on this value.
    $usageCount = referenceUsageCount(
        $pdo,
        $table,
        $id
    );

    if ($usageCount > 0) {
        throw new RuntimeException(
            "This reference value cannot be deleted because "
            . "it is used by $usageCount related record(s)."
        );
    }

    // Delete only the selected reference record.
    $stmt = $pdo->prepare(
        "DELETE FROM `$table`
         WHERE `{$definition['id']}` = ?"
    );

    $stmt->execute([$id]);

    // Preserve information about the deleted record in the audit log.
    referenceAudit(
        $pdo,
        $actorId,
        'delete',
        $table,
        $id,
        [
            'deleted' => $record
        ]
    );

    // Commit the deletion and audit record together.
    $pdo->commit();

    returnToReferenceData(
        $table,
        'Reference value deleted successfully.',
        true
    );

} catch (Throwable $exception) {

    // Undo the deletion if any operation fails.
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($exception instanceof PDOException) {

        error_log($exception->getMessage());

        // MySQL error 1451 means another record references this value.
        // This provides additional protection against unsafe deletion.
        if ((int) ($exception->errorInfo[1] ?? 0) === 1451) {

            $message =
                'This reference value cannot be deleted '
                . 'because it is currently in use.';

        } else {

            $message = 'Unable to delete the reference value.';
        }

    } elseif (
        $exception instanceof InvalidArgumentException ||
        $exception instanceof RuntimeException
    ) {

        $message = $exception->getMessage();

    } else {

        error_log($exception->getMessage());

        $message = 'Unable to delete the reference value.';
    }

    returnToReferenceData(
        $table,
        $message,
        false
    );
}
