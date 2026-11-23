<?php
declare(strict_types=1);

// Pathways to Scholarships
// Iteration 7: User Account Profile

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/includes/db.php';
require_once $projectRoot . '/includes/security.php';

// All registered roles can access their own profile.
require_login();

$pdo = getDBConnection();

$user = current_user();

$userId = (int) $user['user_id'];

$fullName = $user['full_name'];
$email = $user['email'];

$error = '';

$success = $_SESSION['profile_success'] ?? '';

unset($_SESSION['profile_success']);

// Process profile updates.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $submittedName = $_POST['full_name'] ?? '';
    $submittedEmail = $_POST['email'] ?? '';

    if (!is_string($submittedName) ||
        !is_string($submittedEmail)) {

        http_response_code(400);
        exit('Invalid profile information.');
    }

    $fullName = trim($submittedName);
    $email = strtolower(trim($submittedEmail));

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {

        http_response_code(403);
        exit('Invalid security token.');

    } elseif ($fullName === '' || mb_strlen($fullName) > 150) {

        $error = 'Enter a valid full name.';

    } elseif (
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        strlen($email) > 255
    ) {

        $error = 'Enter a valid email address.';

    } else {

        try {

            $pdo->beginTransaction();

            // Lock the signed-in user's record before updating it.
            $stmt = $pdo->prepare(
                'SELECT
                    full_name,
                    email,
                    account_status,
                    must_change_password
                 FROM users
                 WHERE user_id = ?
                 FOR UPDATE'
            );

            $stmt->execute([$userId]);

            $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

            if (
                !$existingUser ||
                $existingUser['account_status'] !== 'active'
            ) {
                throw new RuntimeException(
                    'Your account is unavailable.'
                );
            }

            if ($existingUser['must_change_password']) {
                throw new RuntimeException(
                    'Change your temporary password before updating your profile.'
                );
            }

            // Check whether another account uses this email.
            $stmt = $pdo->prepare(
                'SELECT user_id
                 FROM users
                 WHERE email = ?
                   AND user_id <> ?'
            );

            $stmt->execute([
                $email,
                $userId
            ]);

            if ($stmt->fetchColumn() !== false) {
                throw new RuntimeException(
                    'An account with this email already exists.'
                );
            }

            // Use the authenticated user ID rather than accepting
            // an account ID submitted through the form.
            $stmt = $pdo->prepare(
                'UPDATE users
                 SET full_name = ?,
                     email = ?
                 WHERE user_id = ?'
            );

            $stmt->execute([
                $fullName,
                $email,
                $userId
            ]);

            // Record profile changes without including passwords.
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
                    'operation' => 'profile_update',
                    'before' => [
                        'full_name' => $existingUser['full_name'],
                        'email' => $existingUser['email']
                    ],
                    'after' => [
                        'full_name' => $fullName,
                        'email' => $email
                    ]
                ], JSON_THROW_ON_ERROR)
            ]);

            $pdo->commit();

            // Synchronize the session with the updated profile.
            $_SESSION['full_name'] = $fullName;
            $_SESSION['email'] = $email;

            $_SESSION['profile_success'] =
                'Your profile was updated successfully.';

            header('Location: /pages/profile.php');
            exit;

        } catch (Throwable $exception) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($exception instanceof PDOException) {

                error_log($exception->getMessage());

                $error =
                    (int) ($exception->errorInfo[1] ?? 0) === 1062
                    ? 'An account with this email already exists.'
                    : 'Unable to update your profile.';

            } elseif ($exception instanceof RuntimeException) {

                $error = $exception->getMessage();

            } else {

                error_log($exception->getMessage());

                $error = 'Unable to update your profile.';
            }
        }
    }
}

$pageTitle = 'My Profile | Pathways to Scholarships';

require_once $projectRoot . '/includes/header.php';
require_once $projectRoot . '/includes/nav.php';
?>

<main class="container py-4">

    <div class="row justify-content-center">

        <div class="col-lg-8">

            <div class="mb-4">

                <h1 class="h2">My Profile</h1>

                <p class="text-muted">
                    View and manage your account information.
                </p>

            </div>

            <?php if ($success !== ''): ?>

                <div class="alert alert-success" role="status">
                    <?= e($success) ?>
                </div>

            <?php endif; ?>

            <?php if ($error !== ''): ?>

                <div class="alert alert-danger" role="alert">
                    <?= e($error) ?>
                </div>

            <?php endif; ?>

            <div class="card shadow-sm mb-4">

                <div class="card-header bg-white">

                    <strong>Personal Information</strong>

                </div>

                <div class="card-body p-4">

                    <form method="POST">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= e(csrf_token()) ?>"
                        >

                        <div class="mb-3">

                            <label for="full_name"
                                   class="form-label">
                                Full Name
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                id="full_name"
                                name="full_name"
                                value="<?= e($fullName) ?>"
                                maxlength="150"
                                autocomplete="name"
                                required
                            >

                        </div>

                        <div class="mb-3">

                            <label for="email"
                                   class="form-label">
                                Email Address
                            </label>

                            <input
                                type="email"
                                class="form-control"
                                id="email"
                                name="email"
                                value="<?= e($email) ?>"
                                maxlength="255"
                                autocomplete="email"
                                required
                            >

                            <div class="form-text">
                                This email is used to sign in.
                            </div>

                        </div>

                        <!-- Account permissions are read-only. -->

                        <div class="mb-3">

                            <label class="form-label">
                                Account Role
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                value="<?= e($user['role_name']) ?>"
                                readonly
                            >

                        </div>

                        <div class="mb-4">

                            <label class="form-label">
                                Account Status
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                value="<?= e(ucfirst($user['account_status'])) ?>"
                                readonly
                            >

                        </div>

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            Save Changes
                        </button>

                    </form>

                </div>

            </div>

            <div class="card shadow-sm">

                <div class="card-header bg-white">

                    <strong>Account Security</strong>

                </div>

                <div class="card-body p-4">

                    <h2 class="h5">Password</h2>

                    <p class="text-muted">

                        Keep your account secure by using
                        a strong password.

                    </p>

                    <?php if ($user['must_change_password']): ?>

                        <div class="alert alert-warning">

                            You are required to change
                            your temporary password.

                        </div>

                    <?php endif; ?>

                    <a
                        href="/pages/change_password.php"
                        class="btn btn-outline-primary"
                    >
                        Change Password
                    </a>

                </div>

            </div>

        </div>

    </div>

</main>

<?php
require_once $projectRoot . '/includes/footer.php';
?>
