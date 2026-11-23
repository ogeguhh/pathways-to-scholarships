<?php
declare(strict_types=1);

// Pathways to Scholarships
// Iteration 7: User Login

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/includes/db.php';
require_once $projectRoot . '/includes/security.php';

ensure_session();

$pdo = getDBConnection();

$pageTitle = 'Login | Pathways to Scholarships';

$error = '';
$email = '';

// Redirect users who are already authenticated.
$authenticatedUser = current_user();

if ($authenticatedUser) {

    enforce_password_change($authenticatedUser);

    header('Location: /');
    exit;
}

// Process submitted login credentials.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {

        $error = 'Invalid form submission. Please try again.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)
        || $password === '') {

        $error = 'Enter a valid email address and password.';

    } else {

        // Join users and roles to retrieve the account's permissions.
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
             WHERE u.email = ?
             LIMIT 1'
        );

        $stmt->execute([$email]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Verify the password against its stored hash.
        if (
            $user &&
            $user['account_status'] === 'active' &&
            $user['role_name'] !== 'Visitor' &&
            !empty($user['password_hash']) &&
            password_verify($password, $user['password_hash'])
        ) {

            login_user($user);

            if ($user['must_change_password']) {
                header('Location: /pages/change_password.php');
            } else {
                header('Location: /');
            }

            exit;

        } else {

            // A generic message avoids revealing which accounts exist.
            $error = 'Invalid email or password, or account unavailable.';
        }
    }
}

require_once $projectRoot . '/includes/header.php';
require_once $projectRoot . '/includes/nav.php';
?>

<main class="container py-5">

    <div class="row justify-content-center">

        <div class="col-md-8 col-lg-5">

            <div class="card shadow-sm">

                <div class="card-body p-4">

                    <h1 class="h3 mb-3">Login</h1>

                    <p class="text-muted">
                        Sign in to your Pathways to Scholarships account.
                    </p>

                    <?php if (isset($_GET['logged_out'])): ?>

                        <div class="alert alert-success" role="status">
                            You have been logged out successfully.
                        </div>

                    <?php endif; ?>

                    <?php if ($error !== ''): ?>

                        <div class="alert alert-danger" role="alert">
                            <?= e($error) ?>
                        </div>

                    <?php endif; ?>

                    <form method="POST">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= e(csrf_token()) ?>"
                        >

                        <div class="mb-3">

                            <label for="email" class="form-label">
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

                        </div>

                        <div class="mb-4">

                            <label for="password" class="form-label">
                                Password
                            </label>

                            <input
                                type="password"
                                class="form-control"
                                id="password"
                                name="password"
                                autocomplete="current-password"
                                required
                            >

                        </div>

                        <button
                            type="submit"
                            class="btn btn-primary w-100"
                        >
                            Login
                        </button>

                    </form>

                    <p class="text-center mt-3 mb-0">

                        Don't have an account?

                        <a href="/pages/register.php">Register</a>

                    </p>

                </div>

            </div>

        </div>

    </div>

</main>

<?php
require_once $projectRoot . '/includes/footer.php';
?>
