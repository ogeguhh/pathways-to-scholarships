<?php
declare(strict_types=1);

// Pathways to Scholarships
// Iteration 7: Change Account Password

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/includes/db.php';
require_once $projectRoot . '/includes/security.php';

// Only authenticated users can change their passwords.
require_login();

$pdo = getDBConnection();

$user = current_user();

$userId = (int) $user['user_id'];

$mustChangePassword = (bool) $user['must_change_password'];

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmation = $_POST['confirm_password'] ?? '';

    // Reject malformed form submissions.
    if (
        !is_string($currentPassword) ||
        !is_string($newPassword) ||
        !is_string($confirmation)
    ) {
        http_response_code(400);
        exit('Invalid password information.');
    }

    // Validate the security token.
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {

        http_response_code(403);
        exit('Invalid security token.');

    } elseif ($currentPassword === '') {

        $error = 'Enter your current password.';

    } elseif (
        strlen($newPassword) < 8 ||
        strlen($newPassword) > 72 ||
        str_contains($newPassword, "\0") ||
        !preg_match('/[A-Z]/', $newPassword) ||
        !preg_match('/[a-z]/', $newPassword) ||
        !preg_match('/[0-9]/', $newPassword) ||
        !preg_match('/[^a-zA-Z0-9]/', $newPassword)
    ) {

        $error = 'Your new password does not meet the strength requirements.';

    } elseif ($newPassword !== $confirmation) {

        $error = 'The new passwords do not match.';

    } else {

        try {

            $pdo->beginTransaction();

            // Lock the account to prevent simultaneous password changes.
            $stmt = $pdo->prepare(
                'SELECT
                    u.user_id,
                    u.full_name,
                    u.email,
                    u.password_hash,
                    u.account_status,
                    u.must_change_password,
                    r.role_name
                 FROM users u
                 JOIN roles r ON u.role_id = r.role_id
                 WHERE u.user_id = ?
                 FOR UPDATE'
            );

            $stmt->execute([$userId]);

            $account = $stmt->fetch(PDO::FETCH_ASSOC);

            if (
                !$account ||
                $account['account_status'] !== 'active' ||
                $account['role_name'] === 'Visitor'
            ) {
                throw new RuntimeException(
                    'Your account is unavailable.'
                );
            }

            // Verify the current password against its stored hash.
            if (
                empty($account['password_hash']) ||
                !password_verify(
                    $currentPassword,
                    $account['password_hash']
                )
            ) {
                throw new RuntimeException(
                    'Your current password is incorrect.'
                );
            }

            // Prevent users from reusing their current password.
            if (password_verify(
                $newPassword,
                $account['password_hash']
            )) {
                throw new RuntimeException(
                    'Your new password must be different from your current password.'
                );
            }

            // Store a secure hash instead of the actual password.
            $newPasswordHash = password_hash(
                $newPassword,
                PASSWORD_DEFAULT
            );

            // Clear the mandatory password-change requirement.
            $stmt = $pdo->prepare(
                'UPDATE users
                 SET password_hash = ?,
                     must_change_password = 0
                 WHERE user_id = ?'
            );

            $stmt->execute([
                $newPasswordHash,
                $userId
            ]);

            // Record the password change without exposing credentials.
            $stmt = $pdo->prepare(
                'INSERT INTO audit_log
                    (
                        user_id,
                        action,
                        entity_type,
                        entity_id,
                        details
                    )
                 VALUES (?, ?, ?, ?, ?)'
            );

            $stmt->execute([
                $userId,
                'update',
                'user',
                $userId,
                json_encode([
                    'operation' => 'password_change',
                    'temporary_password_replaced' =>
                        (bool) $account['must_change_password']
                ], JSON_THROW_ON_ERROR)
            ]);

            $pdo->commit();

            // Refresh the authenticated session after the password change.
            $account['password_hash'] = $newPasswordHash;
            $account['must_change_password'] = 0;

            login_user($account);

            $_SESSION['profile_success'] =
                'Your password was changed successfully.';

            header('Location: /pages/profile.php');
            exit;

        } catch (Throwable $exception) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($exception instanceof PDOException) {

                error_log($exception->getMessage());

                $error = 'Unable to change your password.';

            } elseif ($exception instanceof RuntimeException) {

                $error = $exception->getMessage();

            } else {

                error_log($exception->getMessage());

                $error = 'Unable to change your password.';
            }
        }
    }
}

$pageTitle = 'Change Password | Pathways to Scholarships';

require_once $projectRoot . '/includes/header.php';
require_once $projectRoot . '/includes/nav.php';
?>

<main class="container py-4">

    <div class="row justify-content-center">

        <div class="col-lg-7">

            <div class="d-flex flex-wrap
                        justify-content-between
                        align-items-center gap-3 mb-4">

                <h1 class="h2 mb-0">Change Password</h1>

                <?php if (!$mustChangePassword): ?>

                    <a
                        href="/pages/profile.php"
                        class="btn btn-outline-secondary"
                    >
                        Back to Profile
                    </a>

                <?php endif; ?>

            </div>

            <?php if ($mustChangePassword): ?>

                <div class="alert alert-warning">

                    <strong>Password Change Required</strong>

                    <p class="mb-0 mt-2">

                        You are using a temporary password.
                        You must create a new password before
                        continuing to your account.

                    </p>

                </div>

            <?php endif; ?>

            <?php if ($error !== ''): ?>

                <div class="alert alert-danger" role="alert">
                    <?= e($error) ?>
                </div>

            <?php endif; ?>

            <div class="card shadow-sm">

                <div class="card-body p-4">

                    <form method="POST" id="changePasswordForm">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= e(csrf_token()) ?>"
                        >

                        <div class="mb-3">

                            <label
                                for="current_password"
                                class="form-label"
                            >
                                <?= $mustChangePassword
                                    ? 'Current Temporary Password'
                                    : 'Current Password' ?>
                            </label>

                            <input
                                type="password"
                                class="form-control"
                                id="current_password"
                                name="current_password"
                                autocomplete="current-password"
                                required
                            >

                        </div>

                        <div class="mb-3">

                            <label
                                for="new_password"
                                class="form-label"
                            >
                                New Password
                            </label>

                            <input
                                type="password"
                                class="form-control"
                                id="new_password"
                                name="new_password"
                                minlength="8"
                                maxlength="72"
                                autocomplete="new-password"
                                required
                            >

                            <div class="form-text">

                                At least 8 characters, including
                                uppercase, lowercase, a number,
                                and a special character.
                                Maximum 72 bytes.

                            </div>

                        </div>

                        <div class="mb-4">

                            <label
                                for="confirm_password"
                                class="form-label"
                            >
                                Confirm New Password
                            </label>

                            <input
                                type="password"
                                class="form-control"
                                id="confirm_password"
                                name="confirm_password"
                                autocomplete="new-password"
                                required
                            >

                        </div>

                        <div class="d-flex gap-2">

                            <button
                                type="submit"
                                class="btn btn-primary"
                            >
                                Change Password
                            </button>

                            <?php if (!$mustChangePassword): ?>

                                <a
                                    href="/pages/profile.php"
                                    class="btn btn-outline-secondary"
                                >
                                    Cancel
                                </a>

                            <?php endif; ?>

                        </div>

                    </form>

                </div>

            </div>

        </div>

    </div>

</main>

<script>

// Validate password strength and confirmation in the browser.
const newPassword = document.getElementById('new_password');
const confirmation = document.getElementById('confirm_password');

function validatePasswords() {

    const value = newPassword.value;

    const byteLength = new TextEncoder().encode(value).length;

    const valid =
        byteLength >= 8 &&
        byteLength <= 72 &&
        /[A-Z]/.test(value) &&
        /[a-z]/.test(value) &&
        /[0-9]/.test(value) &&
        /[^a-zA-Z0-9]/.test(value);

    newPassword.setCustomValidity(
        valid
            ? ''
            : 'Password does not meet the strength requirements.'
    );

    confirmation.setCustomValidity(
        value === confirmation.value
            ? ''
            : 'Passwords do not match.'
    );
}

newPassword.addEventListener('input', validatePasswords);

confirmation.addEventListener('input', validatePasswords);

document.getElementById('changePasswordForm')
    .addEventListener('submit', function (event) {

        validatePasswords();

        if (!this.checkValidity()) {

            event.preventDefault();

            this.reportValidity();
        }
    });

</script>

<?php
require_once $projectRoot . '/includes/footer.php';
?>
