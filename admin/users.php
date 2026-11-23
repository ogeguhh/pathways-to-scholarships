<?php
declare(strict_types=1);

// Pathways to Scholarships
// Iteration 7: Super-admin User Management Dashboard

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/includes/db.php';
require_once $projectRoot . '/includes/security.php';

// Only Super-admins can manage user accounts.
require_role('Super-admin');

$pdo = getDBConnection();

$pageTitle = 'User Management | Pathways to Scholarships';

// Retrieve the selected search and filter values.
$search = trim((string) ($_GET['search'] ?? ''));
$role = (string) ($_GET['role'] ?? '');
$status = (string) ($_GET['status'] ?? '');

$allowedRoles = [
    'Student',
    'Parent',
    'Administrator',
    'Super-admin'
];

$allowedStatuses = ['active', 'inactive'];

// Build the database query using optional filters.
$sql = "
    SELECT
        u.user_id,
        u.full_name,
        u.email,
        u.account_status,
        u.must_change_password,
        u.created_at,
        r.role_name
    FROM users u
    JOIN roles r ON u.role_id = r.role_id
    WHERE 1 = 1
";

$params = [];

// Search by name or email.
if ($search !== '') {

    $sql .= ' AND (u.full_name LIKE ? OR u.email LIKE ?)';

    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

// Filter by role.
if (in_array($role, $allowedRoles, true)) {

    $sql .= ' AND r.role_name = ?';
    $params[] = $role;
}

// Filter by account status.
if (in_array($status, $allowedStatuses, true)) {

    $sql .= ' AND u.account_status = ?';
    $params[] = $status;
}

$sql .= ' ORDER BY u.user_id ASC';

// Prepared statements prevent SQL injection.
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Retrieve account statistics for the summary cards.
$stats = $pdo->query("
    SELECT
        COUNT(*) AS total_users,
        SUM(u.account_status = 'active') AS active_users,
        SUM(u.account_status = 'inactive') AS inactive_users,
        SUM(
            r.role_name = 'Super-admin'
            AND u.account_status = 'active'
        ) AS active_superadmins
    FROM users u
    JOIN roles r ON u.role_id = r.role_id
")->fetch(PDO::FETCH_ASSOC);

$currentUserId = current_user_id();

// Display feedback from account management actions.
$successMessage = $_SESSION['user_success'] ?? '';
$errorMessage = $_SESSION['user_error'] ?? '';

unset($_SESSION['user_success']);
unset($_SESSION['user_error']);

require_once $projectRoot . '/includes/header.php';
require_once $projectRoot . '/includes/nav.php';
?>

<main class="container py-4">

    <div class="d-flex flex-wrap justify-content-between
                align-items-center gap-3 mb-4">

        <div>
            <h1 class="h2">User Management</h1>

            <p class="text-muted mb-0">
                Manage user accounts, roles, and access permissions.
            </p>
        </div>

        <a href="/admin/user_form.php" class="btn btn-primary">
            Create User
        </a>

    </div>

    <?php if ($successMessage !== ''): ?>

        <div class="alert alert-success" role="status">
            <?= e($successMessage) ?>
        </div>

    <?php endif; ?>

    <?php if ($errorMessage !== ''): ?>

        <div class="alert alert-danger" role="alert">
            <?= e($errorMessage) ?>
        </div>

    <?php endif; ?>

    <!-- Summary cards display account statistics. -->
    <div class="row g-3 mb-4">

        <div class="col-6 col-lg-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <p class="text-muted mb-1">Total Users</p>
                    <h2 class="h3 mb-0">
                        <?= (int) $stats['total_users'] ?>
                    </h2>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <p class="text-muted mb-1">Active Users</p>
                    <h2 class="h3 text-success mb-0">
                        <?= (int) $stats['active_users'] ?>
                    </h2>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <p class="text-muted mb-1">Inactive Users</p>
                    <h2 class="h3 text-secondary mb-0">
                        <?= (int) $stats['inactive_users'] ?>
                    </h2>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <p class="text-muted mb-1">Active Super-admins</p>
                    <h2 class="h3 text-primary mb-0">
                        <?= (int) $stats['active_superadmins'] ?>
                    </h2>
                </div>
            </div>
        </div>

    </div>

    <!-- GET filters allow users to search without changing data. -->
    <div class="card shadow-sm mb-4">

        <div class="card-body">

            <form method="GET" class="row g-3 align-items-end">

                <div class="col-md-5">

                    <label for="search" class="form-label">
                        Search Users
                    </label>

                    <input
                        type="search"
                        class="form-control"
                        id="search"
                        name="search"
                        placeholder="Name or email"
                        value="<?= e($search) ?>"
                    >

                </div>

                <div class="col-md-3">

                    <label for="role" class="form-label">
                        Role
                    </label>

                    <select
                        class="form-select"
                        id="role"
                        name="role"
                    >

                        <option value="">All Roles</option>

                        <?php foreach ($allowedRoles as $roleName): ?>

                            <option
                                value="<?= e($roleName) ?>"
                                <?= $role === $roleName ? 'selected' : '' ?>
                            >
                                <?= e($roleName) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="col-md-2">

                    <label for="status" class="form-label">
                        Status
                    </label>

                    <select
                        class="form-select"
                        id="status"
                        name="status"
                    >

                        <option value="">All Statuses</option>

                        <option value="active"
                            <?= $status === 'active' ? 'selected' : '' ?>>
                            Active
                        </option>

                        <option value="inactive"
                            <?= $status === 'inactive' ? 'selected' : '' ?>>
                            Inactive
                        </option>

                    </select>

                </div>

                <div class="col-md-2">

                    <button type="submit" class="btn btn-primary">
                        Filter
                    </button>

                    <a href="/admin/users.php"
                       class="btn btn-outline-secondary">
                        Clear
                    </a>

                </div>

            </form>

        </div>

    </div>

    <div class="card shadow-sm">

        <div class="card-header bg-white">

            <strong>Registered Users</strong>

            <span class="text-muted">
                (<?= count($users) ?> results)
            </span>

        </div>

        <div class="table-responsive">

            <table class="table table-striped table-hover
                          align-middle mb-0">

                <thead class="table-light">

                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">Name</th>
                        <th scope="col">Email</th>
                        <th scope="col">Role</th>
                        <th scope="col">Status</th>
                        <th scope="col">Created</th>
                        <th scope="col">Actions</th>
                    </tr>

                </thead>

                <tbody>

                    <?php if (empty($users)): ?>

                        <tr>
                            <td colspan="7"
                                class="text-center text-muted py-4">
                                No users match your search.
                            </td>
                        </tr>

                    <?php endif; ?>

                    <?php foreach ($users as $user): ?>

                        <?php
                        $userId = (int) $user['user_id'];

                        $isActive =
                            $user['account_status'] === 'active';

                        $isCurrentUser =
                            $userId === $currentUserId;

                        $isLastSuperAdmin =
                            $user['role_name'] === 'Super-admin'
                            && $isActive
                            && (int) $stats['active_superadmins'] <= 1;
                        ?>

                        <tr>

                            <td><?= $userId ?></td>

                            <td>

                                <?= e($user['full_name']) ?>

                                <?php if ($isCurrentUser): ?>

                                    <span class="badge bg-info text-dark">
                                        You
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td><?= e($user['email']) ?></td>

                            <td><?= e($user['role_name']) ?></td>

                            <td>

                                <span class="badge
                                    <?= $isActive
                                        ? 'bg-success'
                                        : 'bg-secondary' ?>">

                                    <?= e(ucfirst($user['account_status'])) ?>

                                </span>

                                <?php if ($user['must_change_password']): ?>

                                    <div class="small text-muted mt-1">
                                        Password change required
                                    </div>

                                <?php endif; ?>

                            </td>

                            <td><?= e($user['created_at']) ?></td>

                            <td>

                                <div class="d-flex flex-wrap gap-1">

                                    <a
                                        href="/admin/user_form.php?id=<?= $userId ?>"
                                        class="btn btn-sm btn-outline-primary"
                                    >
                                        Edit
                                    </a>

                                    <a
                                        href="/admin/user_reset_password.php?id=<?= $userId ?>"
                                        class="btn btn-sm btn-outline-secondary"
                                    >
                                        Reset Password
                                    </a>

                                    <!-- Status changes use POST and CSRF. -->
                                    <form
                                        method="POST"
                                        action="/admin/user_status.php"
                                        onsubmit="return confirm('Change this account status?');"
                                    >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= e(csrf_token()) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="user_id"
                                            value="<?= $userId ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="status"
                                            value="<?= $isActive
                                                ? 'inactive'
                                                : 'active' ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="btn btn-sm
                                                <?= $isActive
                                                    ? 'btn-outline-danger'
                                                    : 'btn-outline-success' ?>"
                                            <?= $isLastSuperAdmin
                                                || $isCurrentUser
                                                ? 'disabled'
                                                : '' ?>
                                        >

                                            <?= $isActive
                                                ? 'Deactivate'
                                                : 'Activate' ?>

                                        </button>

                                    </form>

                                </div>

                                <?php if ($isLastSuperAdmin): ?>

                                    <div class="small text-muted mt-1">
                                        Last active Super-admin
                                    </div>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    </div>

</main>

<?php
require_once $projectRoot . '/includes/footer.php';
?>
