<?php
declare(strict_types=1);

// Pathways to Scholarships
// Iteration 7 — Student and Parent Registration

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/includes/db.php';
require_once $projectRoot . '/includes/security.php';

ensure_session();

$pdo = getDBConnection();

$pageTitle = 'Register | Pathways to Scholarships';

$errors = [];
$success = false;

$fullName = '';
$email = '';
$role = 'Student';

// Process the form only when it is submitted.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $role = (string) ($_POST['role'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    // Verify the security token before processing the form.
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid form submission. Please try again.';
    }

    // Server-side validation cannot be bypassed by disabling JavaScript.
    if ($fullName === '' || mb_strlen($fullName) > 150) {
        $errors[] = 'Enter a valid full name of up to 150 characters.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)
        || strlen($email) > 255) {
        $errors[] = 'Enter a valid email address.';
    }

    // Only these two roles are allowed to register publicly.
    if (!in_array($role, ['Student', 'Parent'], true)) {
        $errors[] = 'Select either Student or Parent.';
    }

    // Require a strong password.
    if (
        strlen($password) < 8 ||
        strlen($password) > 72 ||
        !preg_match('/[A-Z]/', $password) ||
        !preg_match('/[a-z]/', $password) ||
        !preg_match('/[0-9]/', $password) ||
        !preg_match('/[^a-zA-Z0-9]/', $password)
    ) {
        $errors[] = 'Password must contain 8–72 bytes, including uppercase, lowercase, a number, and a special character.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {

        // Prepared statements protect database queries from SQL injection.
        $stmt = $pdo->prepare(
            'SELECT user_id FROM users WHERE email = ?'
        );

        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            $errors[] = 'An account with this email already exists.';
        }
    }

    if (empty($errors)) {

        // Retrieve the role ID from the existing roles table.
        $stmt = $pdo->prepare(
            'SELECT role_id FROM roles WHERE role_name = ?'
        );

        $stmt->execute([$role]);

        $roleId = $stmt->fetchColumn();

        if ($roleId === false) {
            $errors[] = 'The selected role is unavailable.';
        } else {

            // Store a secure password hash instead of the actual password.
            $passwordHash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            try {

                $stmt = $pdo->prepare(
                    "INSERT INTO users
                    (
                        role_id,
                        full_name,
                        email,
                        password_hash,
                        account_status,
                        must_change_password
                    )
                    VALUES (?, ?, ?, ?, 'active', 0)"
                );

                $stmt->execute([
                    $roleId,
                    $fullName,
                    $email,
                    $passwordHash
                ]);

                $success = true;

                $fullName = '';
                $email = '';
                $role = 'Student';

            } catch (PDOException $exception) {

                // The unique database constraint also prevents duplicate
                // accounts if two requests arrive at the same time.
                if (($exception->errorInfo[1] ?? null) === 1062) {
                    $errors[] = 'An account with this email already exists.';
                } else {
                    error_log($exception->getMessage());
                    $errors[] = 'Registration failed. Please try again.';
                }
            }
        }
    }
}

require_once $projectRoot . '/includes/header.php';
require_once $projectRoot . '/includes/nav.php';
?>

<main class="container py-5">

    <div class="row justify-content-center">

        <div class="col-md-8 col-lg-6">

            <div class="card shadow-sm">

                <div class="card-body p-4">

                    <h1 class="h3 mb-3">Create an Account</h1>

                    <p class="text-muted">
                        Register as a student or parent to get started
                        with Pathways to Scholarships.
                    </p>

                    <?php if ($success): ?>

                        <div class="alert alert-success" role="status">
                            Your account was created successfully.
                            <a href="/pages/login.php">Continue to login</a>.
                        </div>

                    <?php endif; ?>

                    <?php if (!empty($errors)): ?>

                        <div class="alert alert-danger" role="alert">

                            <ul class="mb-0">

                                <?php foreach ($errors as $error): ?>

                                    <li><?= e($error) ?></li>

                                <?php endforeach; ?>

                            </ul>

                        </div>

                    <?php endif; ?>

                    <?php if (!$success): ?>

                    <form method="POST" id="registrationForm">

                        <!-- Protect the form against forged requests. -->
                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= e(csrf_token()) ?>"
                        >

                        <div class="mb-3">

                            <label for="full_name" class="form-label">
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

                        <div class="mb-3">

                            <label for="role" class="form-label">
                                Account Type
                            </label>

                            <select
                                class="form-select"
                                id="role"
                                name="role"
                                required
                            >

                                <option value="Student"
                                    <?= $role === 'Student' ? 'selected' : '' ?>>
                                    Student
                                </option>

                                <option value="Parent"
                                    <?= $role === 'Parent' ? 'selected' : '' ?>>
                                    Parent
                                </option>

                            </select>

                        </div>

                        <div class="mb-3">

                            <label for="password" class="form-label">
                                Password
                            </label>

                            <input
                                type="password"
                                class="form-control"
                                id="password"
                                name="password"
                                minlength="8"
                                maxlength="72"
                                autocomplete="new-password"
                                aria-describedby="passwordHelp"
                                required
                            >

                            <div id="passwordHelp" class="form-text">
                                At least 8 characters, including an
                                uppercase letter, lowercase letter,
                                number, and special character.
                                Maximum 72 bytes.
                            </div>

                        </div>

                        <div class="mb-4">

                            <label for="confirm_password" class="form-label">
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

                        <button
                            type="submit"
                            class="btn btn-primary w-100"
                        >
                            Create Account
                        </button>

                    </form>

                    <p class="text-center mt-3 mb-0">

                        Already have an account?

                        <a href="/pages/login.php">Login</a>

                    </p>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </div>

</main>

<script>

// Client-side validation provides immediate feedback to the user.
const password = document.getElementById('password');
const confirmPassword = document.getElementById('confirm_password');

function validatePasswords() {

    const value = password.value;

    const byteLength = new TextEncoder().encode(value).length;

    const strongPassword =
        byteLength >= 8 &&
        byteLength <= 72 &&
        /[A-Z]/.test(value) &&
        /[a-z]/.test(value) &&
        /[0-9]/.test(value) &&
        /[^a-zA-Z0-9]/.test(value);

    password.setCustomValidity(
        strongPassword ? '' : 'Please meet the password requirements.'
    );

    confirmPassword.setCustomValidity(
        password.value === confirmPassword.value
            ? ''
            : 'Passwords do not match.'
    );
}

if (password && confirmPassword) {

    password.addEventListener('input', validatePasswords);

    confirmPassword.addEventListener('input', validatePasswords);

    document.getElementById('registrationForm')
        .addEventListener('submit', function (event) {

            validatePasswords();

            if (!this.checkValidity()) {
                event.preventDefault();
                this.reportValidity();
            }
        });
}

</script>

<?php
require_once $projectRoot . '/includes/footer.php';
?>
