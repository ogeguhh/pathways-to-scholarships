<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$errors = [];
$success = '';

$fullName = '';
$email = '';
$selectedRole = 'Student';

function escape_html(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

if (empty($_SESSION['registration_csrf'])) {
    $_SESSION['registration_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $selectedRole = (string) ($_POST['role'] ?? '');

    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
    $csrf = (string) ($_POST['csrf_token'] ?? '');

    if (!hash_equals($_SESSION['registration_csrf'], $csrf)) {
        $errors[] = 'Invalid form submission. Please try again.';
    }

    if (strlen($fullName) < 2 || strlen($fullName) > 200) {
        $errors[] = 'Enter a valid full name.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }

    if (!in_array($selectedRole, ['Student', 'Parent'], true)) {
        $errors[] = 'Select Student or Parent.';
    }

    if (
        strlen($password) < 12 ||
        !preg_match('/[A-Z]/', $password) ||
        !preg_match('/[a-z]/', $password) ||
        !preg_match('/[0-9]/', $password) ||
        !preg_match('/[^a-zA-Z0-9]/', $password)
    ) {
        $errors[] = 'Password must contain at least 12 characters, uppercase and lowercase letters, a number, and a special character.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        try {
            $pdo = getDBConnection();

            $roleQuery = $pdo->prepare(
                'SELECT role_id FROM roles WHERE role_name = ? LIMIT 1'
            );
            $roleQuery->execute([$selectedRole]);
            $roleId = $roleQuery->fetchColumn();

            if (!$roleId) {
                $errors[] = 'Selected role is not available.';
            } else {
                $check = $pdo->prepare(
                    'SELECT user_id FROM users WHERE email = ? LIMIT 1'
                );
                $check->execute([$email]);

                if ($check->fetchColumn()) {
                    $errors[] = 'An account with this email already exists.';
                } else {
                    $nameParts = preg_split('/\s+/', $fullName, 2);

                    $firstName = $nameParts[0] ?? '';
                    $lastName = $nameParts[1] ?? '';

                    $passwordHash = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                    $insert = $pdo->prepare(
                        'INSERT INTO users (
                            role_id,
                            email,
                            password_hash,
                            first_name,
                            last_name,
                            full_name,
                            account_status,
                            must_change_password
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                    );

                    $insert->execute([
                        $roleId,
                        $email,
                        $passwordHash,
                        $firstName,
                        $lastName,
                        $fullName,
                        'active',
                        0
                    ]);

                    $success = 'Account created successfully! You can now log in.';

                    $fullName = '';
                    $email = '';
                    $selectedRole = 'Student';

                    $_SESSION['registration_csrf'] = bin2hex(
                        random_bytes(32)
                    );
                }
            }
        } catch (PDOException $e) {
            error_log('Registration database error: ' . $e->getMessage());
            $errors[] = 'Unable to create your account right now.';
        } catch (Throwable $e) {
            error_log('Registration error: ' . $e->getMessage());
            $errors[] = 'An unexpected error occurred.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Register | Pathways to Scholarships</title>

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

        <div>
            <a href="index.php" class="btn btn-outline-light btn-sm">
                Home
            </a>

            <a href="login.php" class="btn btn-light btn-sm">
                Login
            </a>
        </div>
    </div>
</nav>

<main class="container py-5">

    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">

            <div class="card shadow-sm border-0">

                <div class="card-body p-4">

                    <h2 class="text-center mb-2">
                        Create an Account
                    </h2>

                    <p class="text-muted text-center mb-4">
                        Register as a Student or Parent to access
                        scholarship opportunities.
                    </p>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li>
                                        <?= escape_html($error) ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if ($success !== ''): ?>
                        <div class="alert alert-success">
                            <?= escape_html($success) ?>

                            <div class="mt-2">
                                <a href="login.php" class="btn btn-success btn-sm">
                                    Go to Login
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="register.php">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= escape_html($_SESSION['registration_csrf']) ?>"
                        >

                        <div class="mb-3">
                            <label class="form-label">
                                Full Name
                            </label>

                            <input
                                type="text"
                                name="full_name"
                                class="form-control"
                                maxlength="200"
                                value="<?= escape_html($fullName) ?>"
                                required
                            >
                        </div>

                        <div class="mb-3">
                            <label class="form-label">
                                Email Address
                            </label>

                            <input
                                type="email"
                                name="email"
                                class="form-control"
                                value="<?= escape_html($email) ?>"
                                required
                            >
                        </div>

                        <div class="mb-3">
                            <label class="form-label">
                                Account Type
                            </label>

                            <select name="role" class="form-select" required>
                                <option
                                    value="Student"
                                    <?= $selectedRole === 'Student' ? 'selected' : '' ?>
                                >
                                    Student
                                </option>

                                <option
                                    value="Parent"
                                    <?= $selectedRole === 'Parent' ? 'selected' : '' ?>
                                >
                                    Parent
                                </option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">
                                Password
                            </label>

                            <input
                                type="password"
                                name="password"
                                class="form-control"
                                minlength="12"
                                required
                            >

                            <div class="form-text">
                                At least 12 characters, including uppercase,
                                lowercase, number, and special character.
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">
                                Confirm Password
                            </label>

                            <input
                                type="password"
                                name="confirm_password"
                                class="form-control"
                                required
                            >
                        </div>

                        <button
                            type="submit"
                            class="btn btn-primary w-100"
                        >
                            Create Account
                        </button>

                    </form>

                    <p class="text-center mt-4 mb-0">
                        Already have an account?
                        <a href="login.php">Login here</a>
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
