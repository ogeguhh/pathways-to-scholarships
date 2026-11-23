<?php
declare(strict_types=1);

// Pathways to Scholarships
// Iteration 7: Activate and Deactivate User Accounts

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/includes/db.php';
require_once $projectRoot . '/includes/security.php';

// Only Super-admins can change account statuses.
require_role('Super-admin');

$pdo = getDBConnection();

// Database changes must use POST requests.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

// Verify the submitted security token.
if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Invalid security token.');
}

$actorId = current_user_id();

$userId = filter_var(
    $_POST['user_id'] ?? null,
    FILTER_VALIDATE_INT
);

$status = (string) ($_POST['status'] ?? '');

// Return to the dashboard with a success or error message.
function returnToUsers(string $message, bool $success): void
{
    $_SESSION[$success ? 'user_success' : 'user_error'] = $message;

    header('Location: /admin/users.php');
    exit;
}

try {

    // Validate the submitted account ID and status.
    if (!$userId || $userId < 1) {
        throw new RuntimeException('Invalid user ID.');
    }

    if (!in_array($status, ['active', 'inactive'], true)) {
        throw new RuntimeException('Invalid account status.');
    }

    // Prevent administrators from deactivating themselves.
    if ($userId === $actorId && $status === 'inactive') {
        throw new RuntimeException(
            'You cannot deactivate your own account.'
        );
    }

    $pdo->beginTransaction();

    // Lock the Super-admin role to prevent simultaneous changes
    // from accidentally removing every active Super-admin.
    $stmt = $pdo->query(
        "SELECT role_id
         FROM roles
         WHERE role_name = 'Super-admin'
         FOR UPDATE"
    );

    $superAdminRoleId = (int) $stmt->fetchColumn();

    if (!$superAdminRoleId) {
        throw new RuntimeException('Super-admin role not found.');
    }

    // Recheck the acting user's permissions inside the transaction.
    $stmt = $pdo->prepare(
        'SELECT role_id, account_status, must_change_password
         FROM users
         WHERE user_id = ?
         FOR UPDATE'
    );

    $stmt->execute([$actorId]);

    $actor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (
        !$actor ||
        (int) $actor['role_id'] !== $superAdminRoleId ||
        $actor['account_status'] !== 'active' ||
        $actor['must_change_password']
    ) {
        throw new RuntimeException(
            'Your account is not authorized to manage users.'
        );
    }

    // Retrieve and lock the target account.
    $stmt = $pdo->prepare(
        'SELECT
            user_id,
            full_name,
            role_id,
            account_status,
            password_hash
         FROM users
         WHERE user_id = ?
         FOR UPDATE'
    );

    $stmt->execute([$userId]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new RuntimeException('User not found.');
    }

    // An account must have a password before it can be activated.
    if ($status === 'active' && empty($user['password_hash'])) {
        throw new RuntimeException(
            'Reset this account password before activating it.'
        );
    }

    // Avoid unnecessary database changes.
    if ($user['account_status'] === $status) {

        $pdo->commit();

        returnToUsers(
            'The account already has the requested status.',
            true
        );
    }

    // Protect the last active Super-admin account.
    if (
        (int) $user['role_id'] === $superAdminRoleId &&
        $user['account_status'] === 'active' &&
        $status === 'inactive'
    ) {

        $stmt = $pdo->prepare(
            "SELECT user_id
             FROM users
             WHERE role_id = ?
               AND account_status = 'active'
             FOR UPDATE"
        );

        $stmt->execute([$superAdminRoleId]);

        $activeSuperAdmins = $stmt->fetchAll(
            PDO::FETCH_COLUMN
        );

        if (count($activeSuperAdmins) <= 1) {
            throw new RuntimeException(
                'The last active Super-admin cannot be deactivated.'
            );
        }
    }

    // Update the account status without deleting the account.
    $stmt = $pdo->prepare(
        'UPDATE users
         SET account_status = ?
         WHERE user_id = ?'
    );

    $stmt->execute([
        $status,
        $userId
    ]);

    // Record the status change in the audit log.
    $stmt = $pdo->prepare(
        'INSERT INTO audit_log
            (user_id, action, entity_type, entity_id, details)
         VALUES (?, ?, ?, ?, ?)'
    );

    $stmt->execute([
        $actorId,
        'update',
        'user',
        $userId,
        json_encode([
            'operation' => 'account_status_change',
            'full_name' => $user['full_name'],
            'previous_status' => $user['account_status'],
            'new_status' => $status
        ], JSON_THROW_ON_ERROR)
    ]);

    $pdo->commit();

    $message = $status === 'active'
        ? 'User account activated successfully.'
        : 'User account deactivated successfully.';

    returnToUsers($message, true);

} catch (Throwable $exception) {

    // Roll back changes if any part of the operation fails.
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($exception instanceof PDOException) {

        error_log($exception->getMessage());

        $message = 'Unable to update the account status.';

    } elseif ($exception instanceof RuntimeException) {

        $message = $exception->getMessage();

    } else {

        error_log($exception->getMessage());

        $message = 'Unable to update the account status.';
    }

    returnToUsers($message, false);
}
