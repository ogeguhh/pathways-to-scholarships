<?php
declare(strict_types=1);

// Pathways to Scholarships
// Iteration 7: Create and Edit User Accounts

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/includes/db.php';
require_once $projectRoot . '/includes/security.php';

// Restrict account management to Super-admins.
require_role('Super-admin');

$pdo = getDBConnection();

// Determine whether we are creating or editing an account.
$userId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

$isEdit = isset($_GET['id']);

if ($isEdit && (!$userId || $userId < 1)) {
    http_response_code(400);
    exit('Invalid user ID.');
}

$user = [
    'user_id' => '',
    'full_name' => '',
    'email' => '',
    'role_id' => 2,
    'account_status' => 'active'
];

// Visitor represents an unauthenticated person,
// so it is not a registered account type.
$roles = $pdo->query(
    "SELECT role_id, role_name
     FROM roles
     WHERE role_name IN (
         'Student',
         'Parent',
         'Administrator',
         'Super-admin'
     )
     ORDER BY role_id"
)->fetchAll(PDO::FETCH_ASSOC);

$isLastSuperAdmin = false;

if ($isEdit) {

    // Retrieve the account being edited.
    $stmt = $pdo->prepare(
        'SELECT
            u.user_id,
            u.full_name,
            u.email,
            u.role_id,
            u.account_status,
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

    // Count active Super-admins to prevent accidental lockout.
    $activeSuperAdmins = (int) $pdo->query(
        "SELECT COUNT(*)
         FROM users u
         JOIN roles r ON u.role_id = r.role_id
         WHERE r.role_name = 'Super-admin'
           AND u.account_status = 'active'"
    )->fetchColumn();

    $isLastSuperAdmin =
        $user['role_name'] === 'Super-admin'
        && $user['account_status'] === 'active'
        && $activeSuperAdmins <= 1;
}

$isCurrentUser = $isEdit
    && (int) $userId === current_user_id();

$protectRole = $isLastSuperAdmin || $isCurrentUser;

$pageTitle = $isEdit
    ? 'Edit User | Pathways to Scholarships'
    : 'Create User | Pathways to Scholarships';

require_once $projectRoot . '/includes/header.php';
require_once $projectRoot . '/includes/nav.php';
?>

<main class="container py-4">

    <div class="row justify-content-center">

        <div class="col-lg-8">

            <div class="d-flex justify-content-between
                        align-items-center mb-4">

                <h1 class="h2 mb-0">
                    <?= $isEdit ? 'Edit User' : 'Create User' ?>
                </h1>

                <a href="/admin/users.php"
                   class="btn btn-outline-secondary">
                    Back to Users
                </a>

            </div>

            <?php if ($protectRole): ?>

                <div class="alert alert-warning">

                    <?php if ($isLastSuperAdmin): ?>

                        This is the last active Super-admin.
                        Its role and status cannot be changed.

                    <?php else: ?>

                        You cannot change your own role
                        or deactivate your own account.

                    <?php endif; ?>

                </div>

            <?php endif; ?>

            <div class="card shadow-sm">

                <div class="card-body p-4">

                    <form
                        method="POST"
                        action="/admin/user_save.php"
                        id="userForm"
                    >

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= e(csrf_token()) ?>"
                        >

                        <input
                            type="hidden"
                            name="user_id"
                            value="<?= e((string) $user['user_id']) ?>"
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
                                maxlength="150"
                                value="<?= e($user['full_name']) ?>"
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
                                maxlength="255"
                                value="<?= e($user['email']) ?>"
                                required
                            >

                        </div>

                        <div class="mb-3">

                            <label for="role_id" class="form-label">
                                Account Role
                            </label>

                            <select
                                class="form-select"
                                id="role_id"
                                name="role_id"
                                <?= $protectRole ? 'disabled' : '' ?>
                                required
                            >

                                <?php foreach ($roles as $role): ?>

                                    <option
                                        value="<?= (int) $role['role_id'] ?>"
                                        <?= (int) $user['role_id']
                                            === (int) $role['role_id']
                                            ? 'selected' : '' ?>
                                    >
                                        <?= e($role['role_name']) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                            <?php if ($protectRole): ?>

                                <input
                                    type="hidden"
                                    name="role_id"
                                    value="<?= (int) $user['role_id'] ?>"
                                >

                            <?php endif; ?>

                        </div>

                        <div class="mb-3">

                            <label for="account_status"
                                   class="form-label">
                                Account Status
                            </label>

                            <select
                                class="form-select"
                                id="account_status"
                                name="account_status"
                                <?= $protectRole ? 'disabled' : '' ?>
                                required
                            >

                                <option value="active"
                                    <?= $user['account_status']
                                        === 'active' ? 'selected' : '' ?>>
                                    Active
                                </option>

                                <option value="inactive"
                                    <?= $user['account_status']
                                        === 'inactive' ? 'selected' : '' ?>>
                                    Inactive
                                </option>

                            </select>

                            <?php if ($protectRole): ?>

                                <input
                                    type="hidden"
                                    name="account_status"
                                    value="<?= e($user['account_status']) ?>"
                                >

                            <?php endif; ?>

                        </div>

                        <?php if (!$isEdit): ?>

                            <!-- New accounts require an initial password. -->

                            <div class="mb-3">

                                <label for="password"
                                       class="form-label">
                                    Temporary Password
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
                                    Use at least 8 characters, including
                                    uppercase, lowercase, a number,
                                    and a special character.
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

                            <div class="alert alert-info">
                                The user will be required to change
                                their temporary password.
                            </div>

                        <?php endif; ?>

                        <div class="d-flex gap-2 mt-4">

                            <button
                                type="submit"
                                class="btn btn-primary"
                            >
                                <?= $isEdit
                                    ? 'Save Changes'
                                    : 'Create User' ?>
                            </button>

                            <a
                                href="/admin/users.php"
                                class="btn btn-outline-secondary"
                            >
                                Cancel
                            </a>

                        </div>

                    </form>

                </div>

            </div>

        </div>

    </div>

</main>

<script>

// Validate temporary passwords before submitting the form.
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

if (password && confirmation) {

    password.addEventListener('input', validatePasswords);

    confirmation.addEventListener('input', validatePasswords);

    document.getElementById('userForm')
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
