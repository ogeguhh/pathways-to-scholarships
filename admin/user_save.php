<?php
declare(strict_types=1);

// Pathways to Scholarships
// Iteration 7: Create and Update User Accounts

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/includes/db.php';
require_once $projectRoot . '/includes/security.php';

// Only Super-admins can create or modify user accounts.
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

$rawUserId = $_POST['user_id'] ?? '';

$isEdit = $rawUserId !== '';

$userId = filter_var($rawUserId, FILTER_VALIDATE_INT);

$fullName = trim((string) ($_POST['full_name'] ?? ''));
$email = strtolower(trim((string) ($_POST['email'] ?? '')));

$roleId = filter_var(
    $_POST['role_id'] ?? null,
    FILTER_VALIDATE_INT
);

$status = (string) ($_POST['account_status'] ?? '');

$password = (string) ($_POST['password'] ?? '');
$confirmation = (string) ($_POST['confirm_password'] ?? '');

// Store feedback in the session and return to the dashboard.
function returnToUsers(string $message, bool $success): void
{
    $_SESSION[$success ? 'user_success' : 'user_error'] = $message;

    header('Location: /admin/users.php');
    exit;
}

try {

    // Validate all submitted account information.
    if ($isEdit && (!$userId || $userId < 1)) {
        throw new RuntimeException('Invalid user ID.');
    }

    if ($fullName === '' || mb_strlen($fullName) > 150) {
        throw new RuntimeException('Enter a valid full name.');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)
        || strlen($email) > 255) {
        throw new RuntimeException('Enter a valid email address.');
    }

    if (!in_array($status, ['active', 'inactive'], true)) {
        throw new RuntimeException('Invalid account status.');
    }

    // New accounts must have a strong temporary password.
    if (!$isEdit) {

        if (
            strlen($password) < 8 ||
            strlen($password) > 72 ||
            !preg_match('/[A-Z]/', $password) ||
            !preg_match('/[a-z]/', $password) ||
            !preg_match('/[0-9]/', $password) ||
            !preg_match('/[^a-zA-Z0-9]/', $password)
        ) {
            throw new RuntimeException(
                'Password does not meet the strength requirements.'
            );
        }

        if ($password !== $confirmation) {
            throw new RuntimeException('Passwords do not match.');
        }
    }

    // Transactions keep account changes and audit records together.
    $pdo->beginTransaction();

    // Lock the Super-admin role row to serialize changes that
    // could affect the number of active Super-admin accounts.
    $lock = $pdo->query(
        "SELECT role_id
         FROM roles
         WHERE role_name = 'Super-admin'
         FOR UPDATE"
    );

    $superAdminRoleId = (int) $lock->fetchColumn();

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

    // Confirm that the requested role is a valid account role.
    $stmt = $pdo->prepare(
        "SELECT role_name
         FROM roles
         WHERE role_id = ?
           AND role_name IN (
               'Student',
               'Parent',
               'Administrator',
               'Super-admin'
           )"
    );

    $stmt->execute([$roleId]);

    $roleName = $stmt->fetchColumn();

    if ($roleName === false) {
        throw new RuntimeException('Invalid account role.');
    }

    // Prevent duplicate email addresses.
    $stmt = $pdo->prepare(
        'SELECT user_id
         FROM users
         WHERE email = ?
           AND user_id <> ?'
    );

    $stmt->execute([
        $email,
        $isEdit ? $userId : 0
    ]);

    if ($stmt->fetchColumn() !== false) {
        throw new RuntimeException(
            'An account with this email already exists.'
        );
    }

    if ($isEdit) {

        // Lock the existing account before updating it.
        $stmt = $pdo->prepare(
            'SELECT
                user_id,
                full_name,
                email,
                role_id,
                account_status
             FROM users
             WHERE user_id = ?
             FOR UPDATE'
        );

        $stmt->execute([$userId]);

        $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existingUser) {
            throw new RuntimeException('User not found.');
        }

        // Prevent users from changing their own permissions.
        if (
            (int) $userId === $actorId &&
            (
                (int) $existingUser['role_id'] !== $roleId ||
                $status !== 'active'
            )
        ) {
            throw new RuntimeException(
                'You cannot change your own role or deactivate yourself.'
            );
        }

        // Determine whether this change removes an active Super-admin.
        $removesSuperAdmin =
            (int) $existingUser['role_id'] === $superAdminRoleId
            && $existingUser['account_status'] === 'active'
            && (
                $roleId !== $superAdminRoleId ||
                $status !== 'active'
            );

        if ($removesSuperAdmin) {

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
                    'The last active Super-admin cannot be demoted or deactivated.'
                );
            }
        }

        // Save the updated account information.
        $stmt = $pdo->prepare(
            'UPDATE users
             SET full_name = ?,
                 email = ?,
                 role_id = ?,
                 account_status = ?
             WHERE user_id = ?'
        );

        $stmt->execute([
            $fullName,
            $email,
            $roleId,
            $status,
            $userId
        ]);

        $action = 'update';

        $details = [
            'before' => $existingUser,
            'after' => [
                'full_name' => $fullName,
                'email' => $email,
                'role_id' => $roleId,
                'role_name' => $roleName,
                'account_status' => $status
            ]
        ];

        $message = 'User account updated successfully.';

    } else {

        // Store only a secure password hash.
        $passwordHash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        $stmt = $pdo->prepare(
            'INSERT INTO users
                (
                    role_id,
                    full_name,
                    email,
                    password_hash,
                    account_status,
                    must_change_password
                )
             VALUES (?, ?, ?, ?, ?, 1)'
        );

        $stmt->execute([
            $roleId,
            $fullName,
            $email,
            $passwordHash,
            $status
        ]);

        $userId = (int) $pdo->lastInsertId();

        $action = 'create';

        $details = [
            'full_name' => $fullName,
            'email' => $email,
            'role_name' => $roleName,
            'account_status' => $status
        ];

        $message = 'User account created successfully.';
    }

    // Record who performed the action.
    // Passwords and password hashes are never included in audit logs.
    $stmt = $pdo->prepare(
        'INSERT INTO audit_log
            (user_id, action, entity_type, entity_id, details)
         VALUES (?, ?, ?, ?, ?)'
    );

    $stmt->execute([
        $actorId,
        $action,
        'user',
        $userId,
        json_encode($details, JSON_THROW_ON_ERROR)
    ]);

    $pdo->commit();

    returnToUsers($message, true);

} catch (Throwable $exception) {

    // Undo changes if any part of the operation fails.
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($exception instanceof PDOException) {

        error_log($exception->getMessage());

        $message = ($exception->errorInfo[1] ?? null) === 1062
            ? 'An account with this email already exists.'
            : 'Unable to save the user account.';

    } elseif ($exception instanceof RuntimeException) {

        $message = $exception->getMessage();

    } else {

        error_log($exception->getMessage());
        $message = 'Unable to save the user account.';
    }

    returnToUsers($message, false);
}
