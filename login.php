<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';

// Start a secure session.
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');

    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', '1');
    }

    session_start();
}

function loginEscape(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

$error = '';
$email = '';

// Expire inactive authenticated sessions after 30 minutes.
if (
    !empty($_SESSION['user_id']) &&
    isset($_SESSION['last_activity']) &&
    time() - (int) $_SESSION['last_activity'] > 1800
) {
    $_SESSION = [];
    session_regenerate_id(true);
}

// Do not automatically redirect existing sessions.
// Always allow the login form to display.

if (empty($_SESSION['login_csrf'])) {
    $_SESSION['login_csrf'] = bin2hex(random_bytes(32));
}

// Process login submission.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $csrfToken = (string) ($_POST['csrf_token'] ?? '');

    if (
        !hash_equals(
            (string) $_SESSION['login_csrf'],
            $csrfToken
        )
    ) {
        $error = 'Invalid form submission. Please refresh and try again.';

    } elseif (
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        $password === ''
    ) {
        $error = 'Please enter a valid email address and password.';

    } else {

        try {
            $pdo = getDBConnection();

            $stmt = $pdo->prepare(
                "SELECT
                    u.user_id,
                    u.role_id,
                    u.email,
                    u.full_name,
                    u.first_name,
                    u.last_name,
                    u.password_hash,
                    u.account_status,
                    u.must_change_password,
                    r.role_name
                 FROM users AS u
                 INNER JOIN roles AS r
                    ON r.role_id = u.role_id
                 WHERE u.email = ?
                 LIMIT 1"
            );

            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            $validLogin =
                $user !== false &&
                password_verify(
                    $password,
                    (string) $user['password_hash']
                ) &&
                strtolower((string) $user['account_status']) === 'active';

            if (!$validLogin) {
                $error = 'Invalid email or password.';

            } else {

                // Replace any previous authentication session.
                $_SESSION = [];
                session_regenerate_id(true);

                $fullName = trim((string) ($user['full_name'] ?? ''));

                if ($fullName === '') {
                    $fullName = trim(
                        (string) ($user['first_name'] ?? '') .
                        ' ' .
                        (string) ($user['last_name'] ?? '')
                    );
                }

                $_SESSION['user_id'] = (int) $user['user_id'];
                $_SESSION['role_id'] = (int) $user['role_id'];
                $_SESSION['role'] = (string) $user['role_name'];
                $_SESSION['role_name'] = (string) $user['role_name'];
                $_SESSION['full_name'] = $fullName;
                $_SESSION['email'] = (string) $user['email'];
                $_SESSION['last_activity'] = time();
                $_SESSION['logged_in_at'] = time();

                $_SESSION['must_change_password'] =
                    (int) ($user['must_change_password'] ?? 0);

                // Redirect only after successful authentication.
                header('Location: index.php');
                exit;
            }

        } catch (Throwable $e) {
            error_log('Login error: ' . $e->getMessage());
            $error = 'Unable to log in right now. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Login | Pathways to Scholarships</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
</head>

<body class="bg-light">

<nav class="navbar navbar-dark bg-primary">
    <div class="container">

        <a class="navbar-brand fw-bold" href="index.php">
            Pathways to Scholarships
        </a>

        <div class="d-flex gap-2">
            <a href="index.php" class="btn btn-outline-light btn-sm">
                Home
            </a>

            <a href="register.php" class="btn btn-light btn-sm">
                Register
            </a>
        </div>

    </div>
</nav>

<main class="container py-5">

    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">

            <div class="card border-0 shadow-sm">

                <div class="card-body p-4">

                    <h2 class="text-center mb-3">
                        Login
                    </h2>

                    <p class="text-muted text-center mb-4">
                        Sign in to your Pathways to Scholarships account.
                    </p>

                    <?php if ($error !== ''): ?>
                        <div class="alert alert-danger" role="alert">
                            <?= loginEscape($error) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_GET['registered'])): ?>
                        <div class="alert alert-success" role="status">
                            Your account was created successfully.
                            Please log in.
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="login.php">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= loginEscape(
                                (string) $_SESSION['login_csrf']
                            ) ?>"
                        >

                        <div class="mb-3">

                            <label for="email" class="form-label">
                                Email Address
                            </label>

                            <input
                                type="email"
                                id="email"
                                name="email"
                                class="form-control"
                                value="<?= loginEscape($email) ?>"
                                autocomplete="username"
                                required
                            >

                        </div>

                        <div class="mb-4">

                            <label for="password" class="form-label">
                                Password
                            </label>

                            <input
                                type="password"
                                id="password"
                                name="password"
                                class="form-control"
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

                    <p class="text-center mt-4 mb-0">
                        Don't have an account?
                        <a href="register.php">Register here</a>
                    </p>

                </div>
            </div>

        </div>
    </div>

</main>

<footer class="text-center text-muted py-4">
    &copy; <?= date('Y') ?> Pathways to Scholarships
</footer>

</body>
</html>
