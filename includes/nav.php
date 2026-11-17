<?php
declare(strict_types=1);

/*
 * Pathways to Scholarships
 * Iteration 7 — Shared Navigation
 *
 * Visitor: Public navigation and Login/Register
 * Student: Public navigation and Logout
 * Parent: Public navigation and Logout
 * Administrator: Public navigation, Admin and Logout
 * Super-admin: Public navigation, Admin and Logout
 */

require_once __DIR__ . '/security.php';

// Start the session if needed.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Apply session expiration.
if (function_exists('check_session_timeout')) {
    check_session_timeout();
}

// Determine the project's base URL.
$scriptName = str_replace(
    '\\',
    '/',
    $_SERVER['SCRIPT_NAME'] ?? ''
);

$projectFolder = 'pathways-to-scholarships-project-rebuild-clean';

$position = strpos(
    $scriptName,
    '/' . $projectFolder . '/'
);

if ($position !== false) {
    $baseUrl = substr(
        $scriptName,
        0,
        $position + strlen('/' . $projectFolder)
    );
} else {
    $baseUrl = rtrim(dirname($scriptName), '/');
}

$baseUrl = rtrim($baseUrl, '/');

// Determine current page.
$currentPage = basename(
    parse_url(
        $_SERVER['REQUEST_URI'] ?? '/',
        PHP_URL_PATH
    ) ?: ''
);

// Check login status using the existing session.
$isLoggedIn = !empty($_SESSION['user_id']);

// Display name.
$userName = trim(
    (string) ($_SESSION['full_name'] ?? '')
);

if ($userName === '') {
    $userName = (string) (
        $_SESSION['email'] ?? 'Account'
    );
}

// Read the current user's role.
$userRole = strtolower(
    trim(
        (string) (
            $_SESSION['role_name']
            ?? $_SESSION['role']
            ?? ''
        )
    )
);

// Administrative navigation.
$isAdministrator = $isLoggedIn && in_array(
    $userRole,
    [
        'administrator',
        'super-admin',
        'super_admin'
    ],
    true
);

// Preserve the existing logout CSRF token.
if ($isLoggedIn && empty($_SESSION['logout_csrf'])) {
    $_SESSION['logout_csrf'] = bin2hex(
        random_bytes(32)
    );
}

// Application URLs.
$homeUrl = $baseUrl . '/index.php';
$loginUrl = $baseUrl . '/login.php';
$registerUrl = $baseUrl . '/register.php';
$logoutUrl = $baseUrl . '/logout.php';

$scholarshipsUrl = $baseUrl . '/pages/scholarships.php';
$factsUrl = $baseUrl . '/pages/scholarship_facts.php';
$aboutUrl = $baseUrl . '/pages/about.php';

$adminUrl = $baseUrl . '/admin/dashboard.php';

// Escape output safely.
if (!function_exists('navEscape')) {
    function navEscape(string $value): string
    {
        return htmlspecialchars(
            $value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }
}
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary">

    <div class="container">

        <!-- Website brand -->
        <a
            class="navbar-brand fw-bold"
            href="<?= navEscape($homeUrl) ?>"
        >
            Pathways to Scholarships
        </a>

        <!-- Mobile navigation toggle -->
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

        <div
            class="collapse navbar-collapse"
            id="mainNavigation"
        >

            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">

                <!-- Home -->
                <li class="nav-item">
                    <a
                        class="nav-link <?= $currentPage === 'index.php' ? 'active' : '' ?>"
                        href="<?= navEscape($homeUrl) ?>"
                    >
                        Home
                    </a>
                </li>

                <!-- Search Scholarships -->
                <li class="nav-item">
                    <a
                        class="nav-link <?= $currentPage === 'scholarships.php' ? 'active' : '' ?>"
                        href="<?= navEscape($scholarshipsUrl) ?>"
                    >
                        Search Scholarships
                    </a>
                </li>

                <!-- Scholarship Facts -->
                <li class="nav-item">
                    <a
                        class="nav-link <?= $currentPage === 'scholarship_facts.php' ? 'active' : '' ?>"
                        href="<?= navEscape($factsUrl) ?>"
                    >
                        Scholarship Facts
                    </a>
                </li>

                <!-- About -->
                <li class="nav-item">
                    <a
                        class="nav-link <?= $currentPage === 'about.php' ? 'active' : '' ?>"
                        href="<?= navEscape($aboutUrl) ?>"
                    >
                        About
                    </a>
                </li>

                <?php if ($isLoggedIn): ?>

                    <!-- Admin link: authorized accounts only -->
                    <?php if ($isAdministrator): ?>

                        <li class="nav-item">
                            <a
                                class="nav-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>"
                                href="<?= navEscape($adminUrl) ?>"
                            >
                                Admin
                            </a>
                        </li>

                    <?php endif; ?>

                    <!-- Signed-in account -->
                    <li class="nav-item">
                        <span class="navbar-text small text-white">
                            <?= navEscape($userName) ?>
                        </span>
                    </li>

                    <!-- Logout -->
                    <li class="nav-item">

                        <form
                            method="POST"
                            action="<?= navEscape($logoutUrl) ?>"
                            class="d-inline"
                        >

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= navEscape(
                                    (string) $_SESSION['logout_csrf']
                                ) ?>"
                            >

                            <button
                                type="submit"
                                class="btn btn-outline-light btn-sm"
                            >
                                Logout
                            </button>

                        </form>

                    </li>

                <?php else: ?>

                    <!-- Login -->
                    <li class="nav-item">
                        <a
                            class="nav-link <?= $currentPage === 'login.php' ? 'active' : '' ?>"
                            href="<?= navEscape($loginUrl) ?>"
                        >
                            Login
                        </a>
                    </li>

                    <!-- Register -->
                    <li class="nav-item">
                        <a
                            class="btn btn-light btn-sm"
                            href="<?= navEscape($registerUrl) ?>"
                        >
                            Register
                        </a>
                    </li>

                <?php endif; ?>

            </ul>

        </div>

    </div>

</nav>
