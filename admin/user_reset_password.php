<?php
declare(strict_types=1);

// Pathways to Scholarships
// Iteration 7: Super-admin Password Reset

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/includes/db.php';
require_once $projectRoot . '/includes/security.php';

// Only Super-admins can reset other users' passwords.
require_role('Super-admin');

$pdo = getDBConnection();

$actorId = current_user_id();

$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';

$userId = filter_var(
    $isPost
        ? ($_POST['user_id'] ?? null)
        : ($_GET['id'] ?? null),
    FILTER_VALIDATE_INT
);

if (!$userId || $userId < 1) {
    http_response_code(400);
    exit('Invalid user ID.');
}

// Users should change their own password through their profile.
if ($userId === $actorId) {
    $_SESSION['user_error'] =
        'Use your profile page to change your own password.';

    header('Location: /admin/users.php');
    exit;
}

// Retrieve the account whose password will be reset.
$stmt = $pdo->prepare(
    'SELECT
        u.user_id,
        u.full_name,
        u.email,
        r.role_name
     FROM users u
     JOIN roles r ON u.role_id = r.role_id
     WHERE u.user_id = ?'
);

$stmt->execute([$userId]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    http_response_code(404);
    exit('User not found.');
}

$error = '';

if ($isPost) {

    $password = (string) ($_POST['password'] ?? '');
    $confirmation = (string) ($_POST['confirm_password'] ?? '');

    // Validate the security token and password requirements.
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {

        http_response_code(403);
        exit('Invalid security token.');

    } elseif (
        strlen($password) < 8 ||
        strlen($password) > 72 ||
        !preg_match('/[A-Z]/', $password) ||
        !preg_match('/[a-z]/', $password) ||
        !preg_match('/[0-9]/', $password) ||
        !preg_match('/[^a-zA-Z0-9]/', $password)
    ) {

        $error = 'Password does not meet the strength requirements.';

    } elseif ($password !== $confirmation) {

        $error = 'Passwords do not match.';

    } else {

        try {

            $pdo->beginTransaction();

            // Lock the Super-admin role to coordinate account changes.
            $stmt = $pdo->query(
                "SELECT role_id
                 FROM roles
                 WHERE role_name = 'Super-admin'
                 FOR UPDATE"
            );

            $superAdminRoleId = (int) $stmt->fetchColumn();

            // Recheck the acting user's authorization.
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
                    'You are not authorized to reset passwords.'
                );
            }

            // Lock the target account before changing its password.
            $stmt = $pdo->prepare(
                'SELECT user_id
                 FROM users
                 WHERE user_id = ?
                 FOR UPDATE'
            );

            $stmt->execute([$userId]);

            if (!$stmt->fetchColumn()) {
                throw new RuntimeException('User not found.');
            }

            // Hash the temporary password before storing it.
            $passwordHash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $stmt = $pdo->prepare(
                'UPDATE users
                 SET password_hash = ?,
                     must_change_password = 1
                 WHERE user_id = ?'
            );

            $stmt->execute([
                $passwordHash,
                $userId
            ]);

            // Record the reset without exposing the password or hash.
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
                    'operation' => 'password_reset',
                    'must_change_password' => true
                ], JSON_THROW_ON_ERROR)
            ]);

            $pdo->commit();

            $_SESSION['user_success'] =
                'Password reset successfully. '
                . 'The user must change their temporary password.';

            header('Location: /admin/users.php');
            exit;

        } catch (Throwable $exception) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($exception->getMessage());

            $error = 'Unable to reset the password.';
        }
    }
}

$pageTitle = 'Reset Password | Pathways to Scholarships';

require_once $projectRoot . '/includes/header.php';
require_once $projectRoot . '/includes/nav.php';
?>

<main class="container py-4">

    <div class="row justify-content-center">

        <div class="col-lg-7">

            <div class="d-flex justify-content-between
                        align-items-center mb-4">

                <h1 class="h2 mb-0">Reset Password</h1>

                <a href="/admin/users.php"
                   class="btn btn-outline-secondary">
                    Back to Users
                </a>

            </div>

            <?php if ($error !== ''): ?>

                <div class="alert alert-danger" role="alert">
                    <?= e($error) ?>
                </div>

            <?php endif; ?>

            <div class="card shadow-sm">

                <div class="card-body p-4">

                    <h2 class="h5">Account Information</h2>

                    <p class="mb-1">
                        <strong>Name:</strong>
                        <?= e($user['full_name']) ?>
                    </p>

                    <p class="mb-1">
                        <strong>Email:</strong>
                        <?= e($user['email']) ?>
                    </p>

                    <p>
                        <strong>Role:</strong>
                        <?= e($user['role_name']) ?>
                    </p>

                    <hr>

                    <form method="POST" id="resetPasswordForm">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= e(csrf_token()) ?>"
                        >

                        <input
                            type="hidden"
                            name="user_id"
                            value="<?= (int) $user['user_id'] ?>"
                        >

                        <div class="mb-3">

                            <label for="password" class="form-label">
                                New Temporary Password
                            </label>

                            <input
                                type="password"
                                class="form-control"
                                id="password"
                                name="password"
                                minlength="8"
                                maxlength="72"
                                autocomplete="new-password"
                                required
                            >

                            <div class="form-text">
                                At least 8 characters, including uppercase,
                                lowercase, a number, and a special character.
                                Maximum 72 bytes.
                            </div>

                        </div>

                        <div class="mb-3">

                            <label for="confirm_password"
                                   class="form-label">
                                Confirm Password
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

                        <div class="alert alert-warning">
                            This replaces the user's current password.
                            The user must change their temporary password.
                        </div>

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            Reset Password
                        </button>

                        <a href="/admin/users.php"
                           class="btn btn-outline-secondary">
                            Cancel
                        </a>

                    </form>

                </div>

            </div>

        </div>

    </div>

</main>

<script>

// Provide immediate client-side password validation.
const password = document.getElementById('password');
const confirmation = document.getElementById('confirm_password');

function validatePasswords() {

    const value = password.value;
    const byteLength = new TextEncoder().encode(value).length;

    const valid =
        byteLength >= 8 &&
        byteLength <= 72 &&
        /[A-Z]/.test(value) &&
        /[a-z]/.test(value) &&
        /[0-9]/.test(value) &&
        /[^a-zA-Z0-9]/.test(value);

    password.setCustomValidity(
        valid ? '' : 'Password does not meet the requirements.'
    );

    confirmation.setCustomValidity(
        value === confirmation.value
            ? ''
            : 'Passwords do not match.'
    );
}

password.addEventListener('input', validatePasswords);
confirmation.addEventListener('input', validatePasswords);

document.getElementById('resetPasswordForm')
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
