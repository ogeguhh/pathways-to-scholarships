<?php
// Pathways to Scholarships
// Iteration 7: Role-Based Navigation

require_once __DIR__ . '/security.php';

// Retrieve the authenticated user's current information.
$navUser = current_user();

$navRole = $navUser['role_name'] ?? 'Visitor';

$navLoggedIn = $navUser !== null;

// Administrators and Super-admins share administrative permissions.
$navIsAdmin = in_array(
    $navRole,
    ['Administrator', 'Super-admin'],
    true
);

$navIsSuperAdmin = $navRole === 'Super-admin';

$navMustChangePassword =
    (bool) ($navUser['must_change_password'] ?? false);

if ($navMustChangePassword) {
    $navIsAdmin = false;
    $navIsSuperAdmin = false;
}

// Identify the current URL to highlight the correct navigation link.
$currentPath = parse_url(
    $_SERVER['REQUEST_URI'] ?? '/',
    PHP_URL_PATH
);
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary">

    <div class="container">

        <a class="navbar-brand fw-bold" href="/">
            Pathways to Scholarships
        </a>

        <!-- Responsive navigation button for smaller screens. -->
        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#mainNavigation"
            aria-controls="mainNavigation"
            aria-expanded="false"
            aria-label="Toggle navigation"
        >
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNavigation">

            <ul class="navbar-nav ms-auto">

                <!-- Public links are available to every role. -->

                <li class="nav-item">
                    <a class="nav-link <?= in_array($currentPath, ['/', '/index.php'], true) ? 'active' : '' ?>"
                       href="/">
                        Home
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?= $currentPath === '/pages/scholarships.php' ? 'active' : '' ?>"
                       href="/pages/scholarships.php">
                        Search Scholarships
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?= $currentPath === '/pages/scholarship_facts.php' ? 'active' : '' ?>"
                       href="/pages/scholarship_facts.php">
                        Scholarship Facts
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?= $currentPath === '/pages/about.php' ? 'active' : '' ?>"
                       href="/pages/about.php">
                        About
                    </a>
                </li>

                <!-- Administrative links are restricted by role. -->

                <?php if ($navIsAdmin): ?>

                    <li class="nav-item dropdown">

                        <a
                            class="nav-link dropdown-toggle"
                            href="#"
                            role="button"
                            data-bs-toggle="dropdown"
                            aria-expanded="false"
                        >
                            Administration
                        </a>

                        <ul class="dropdown-menu">

                            <li>
                                <a class="dropdown-item"
                                   href="/admin/dashboard.php">
                                    Admin Dashboard
                                </a>
                            </li>

                            <li>
                                <a class="dropdown-item"
                                   href="/admin/scholarships.php">
                                    Manage Scholarships
                                </a>
                            </li>

                            <li>
                                <a class="dropdown-item"
                                   href="/admin/providers.php">
                                    Manage Providers
                                </a>
                            </li>

                            <li>
                                <a class="dropdown-item"
                                   href="/admin/import.php">
                                    Import Data
                                </a>
                            </li>

                            <li>
                                <a class="dropdown-item"
                                   href="/admin/import_history.php">
                                    Import History
                                </a>
                            </li>

                            <li><hr class="dropdown-divider"></li>

                            <li>
                                <a class="dropdown-item"
                                   href="/admin/data_quality.php">
                                    Data Quality
                                </a>
                            </li>

                            <li>
                                <a class="dropdown-item"
                                   href="/admin/coverage_report.php">
                                    Coverage Report
                                </a>
                            </li>

                        </ul>

                    </li>

                <?php endif; ?>

                <!-- Only Super-admins can access this menu. -->

                <?php if ($navIsSuperAdmin): ?>

                    <li class="nav-item dropdown">

                        <a
                            class="nav-link dropdown-toggle"
                            href="#"
                            role="button"
                            data-bs-toggle="dropdown"
                            aria-expanded="false"
                        >
                            Super-admin
                        </a>

                        <ul class="dropdown-menu">

                            <li>
                                <a class="dropdown-item"
                                   href="/admin/users.php">
                                    User Management
                                </a>
                            </li>

                            <li>
                                <a class="dropdown-item"
                                   href="/admin/reference_data.php">
                                    Reference Data Management
                                </a>
                            </li>

                            <li>
                                <a class="dropdown-item"
                                   href="/admin/audit_log.php">
                                    Audit Log
                                </a>
                            </li>

                        </ul>

                    </li>

                <?php endif; ?>

                <!-- Display account options based on login status. -->

                <?php if ($navLoggedIn): ?>

                    <li class="nav-item dropdown">

                        <a
                            class="nav-link dropdown-toggle"
                            href="#"
                            role="button"
                            data-bs-toggle="dropdown"
                            aria-expanded="false"
                        >
                            <?= e($navUser['full_name']) ?>
                        </a>

                        <ul class="dropdown-menu dropdown-menu-end">

                            <li>
                                <span class="dropdown-item-text text-muted">
                                    <?= e($navRole) ?>
                                </span>
                            </li>

                            <li><hr class="dropdown-divider"></li>

                            <li>
                                <a class="dropdown-item"
                                   href="/pages/profile.php">
                                    My Profile
                                </a>
                            </li>

                            <li>
                                <a class="dropdown-item"
                                   href="/pages/change_password.php">
                                    Change Password
                                </a>
                            </li>

                            <li><hr class="dropdown-divider"></li>

                            <li>

                                <!-- Logout uses POST and CSRF protection. -->
                                <form
                                    method="POST"
                                    action="/pages/logout.php"
                                    class="m-0"
                                >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= e(csrf_token()) ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="dropdown-item text-danger"
                                    >
                                        Logout
                                    </button>

                                </form>

                            </li>

                        </ul>

                    </li>

                <?php else: ?>

                    <li class="nav-item">
                        <a class="nav-link <?= $currentPath === '/pages/login.php' ? 'active' : '' ?>"
                           href="/pages/login.php">
                            Login
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link <?= $currentPath === '/pages/register.php' ? 'active' : '' ?>"
                           href="/pages/register.php">
                            Register
                        </a>
                    </li>

                <?php endif; ?>

            </ul>

        </div>

    </div>

</nav>
