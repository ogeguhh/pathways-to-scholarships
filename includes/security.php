<?php
declare(strict_types=1);

// Pathways to Scholarships
// Iteration 7: Authentication and Role-Based Security

const SESSION_TIMEOUT = 1800;

// --------------------------------------------------
// 1. HTML ESCAPING
// --------------------------------------------------

function e(?string $value): string
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

// --------------------------------------------------
// 2. APPLICATION URL HELPER
// --------------------------------------------------

function app_url(string $path = ''): string
{
    $script = str_replace(
        '\\',
        '/',
        $_SERVER['SCRIPT_NAME'] ?? '/index.php'
    );

    $directory = dirname($script);

    // Admin pages live one directory below the root.
    if (basename($directory) === 'admin') {
        $directory = dirname($directory);
    }

    // Public pages also live one directory below.
    if (basename($directory) === 'pages') {
        $directory = dirname($directory);
    }

    $base = rtrim(
        $directory === '.' ? '' : $directory,
        '/'
    );

    return $base . '/' . ltrim($path, '/');
}

// --------------------------------------------------
// 3. SESSION MANAGEMENT
// --------------------------------------------------

function ensure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    if (headers_sent()) {
        throw new RuntimeException(
            'Cannot start session after output.'
        );
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    $secure = (
        !empty($_SERVER['HTTPS']) &&
        $_SERVER['HTTPS'] !== 'off'
    );

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

// --------------------------------------------------
// 4. CSRF PROTECTION
// --------------------------------------------------

function csrf_token(): string
{
    ensure_session();

    if (
        !isset($_SESSION['csrf_token']) ||
        !is_string($_SESSION['csrf_token'])
    ) {
        $_SESSION['csrf_token'] =
            bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf_token(?string $submittedToken): bool
{
    ensure_session();

    return is_string($submittedToken)
        && isset($_SESSION['csrf_token'])
        && is_string($_SESSION['csrf_token'])
        && hash_equals(
            $_SESSION['csrf_token'],
            $submittedToken
        );
}

// Compatibility with our Iteration 7 forms.
function verify_csrf(?string $submittedToken): bool
{
    return verify_csrf_token($submittedToken);
}

// --------------------------------------------------
// 5. CLEAR AUTHENTICATION SESSION
// --------------------------------------------------

function clear_auth_session(): void
{
    ensure_session();

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {

        $params = session_get_cookie_params();

        setcookie(session_name(), '', [
            'expires' => time() - 3600,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax'
        ]);
    }

    session_destroy();
}

// --------------------------------------------------
// 6. SESSION TIMEOUT
// --------------------------------------------------

function check_session_timeout(): void
{
    ensure_session();

    if (!isset($_SESSION['user_id'])) {
        return;
    }

    $lastActivity = (int)(
        $_SESSION['last_activity'] ?? 0
    );

    if (
        $lastActivity <= 0 ||
        time() - $lastActivity > SESSION_TIMEOUT
    ) {

        clear_auth_session();

        header(
            'Location: ' .
            app_url('login.php?expired=1'),
            true,
            303
        );

        exit;
    }

    $_SESSION['last_activity'] = time();
}

// --------------------------------------------------
// 7. CURRENT AUTHENTICATED USER
// --------------------------------------------------

function current_user(): ?array
{
    ensure_session();
    check_session_timeout();

    $userId = filter_var(
        $_SESSION['user_id'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($userId === false || $userId === null) {
        return null;
    }

    // Use the existing Iteration 6 database helper.
    require_once __DIR__ . '/db.php';

    $pdo = getDBConnection();

    $stmt = $pdo->prepare("
        SELECT
            u.user_id,
            u.full_name,
            u.email,
            u.account_status,
            u.must_change_password,
            r.role_name
        FROM users AS u
        INNER JOIN roles AS r
            ON u.role_id = r.role_id
        WHERE u.user_id = ?
        LIMIT 1
    ");

    $stmt->execute([(int)$userId]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (
        !$user ||
        strtolower((string)$user['account_status'])
            !== 'active'
    ) {

        clear_auth_session();

        header(
            'Location: ' .
            app_url('login.php?expired=1'),
            true,
            303
        );

        exit;
    }

    // Keep session role information synchronized
    // with the database.
    $_SESSION['role'] = $user['role_name'];
    $_SESSION['role_name'] = $user['role_name'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email'] = $user['email'];

    return $user;
}

// --------------------------------------------------
// 8. CURRENT USER ID
// --------------------------------------------------

function current_user_id(): ?int
{
    $user = current_user();

    return $user !== null
        ? (int)$user['user_id']
        : null;
}

// --------------------------------------------------
// 9. CHECK USER ROLE
// --------------------------------------------------

function has_role(string $requiredRole): bool
{
    $user = current_user();

    if ($user === null) {
        return false;
    }

    return strcasecmp(
        (string)$user['role_name'],
        $requiredRole
    ) === 0;
}

// --------------------------------------------------
// 10. REQUIRE AUTHENTICATION
// --------------------------------------------------

function require_login(): void
{
    if (current_user() === null) {

        header(
            'Location: ' . app_url('login.php'),
            true,
            303
        );

        exit;
    }
}

// --------------------------------------------------
// 11. REQUIRE SPECIFIC ROLE
// --------------------------------------------------

function require_role(string|array $requiredRoles): void
{
    $user = current_user();

    if ($user === null) {

        header(
            'Location: ' . app_url('login.php'),
            true,
            303
        );

        exit;
    }

    $roles = (array)$requiredRoles;

    foreach ($roles as $role) {

        if (
            strcasecmp(
                (string)$user['role_name'],
                (string)$role
            ) === 0
        ) {
            return;
        }
    }

    http_response_code(403);

    exit(
        '403 Forbidden: You do not have ' .
        'permission to access this page.'
    );
}

// --------------------------------------------------
// 12. ADMINISTRATOR ACCESS
// --------------------------------------------------

function require_admin_access(): void
{
    require_role([
        'Administrator',
        'Super-admin'
    ]);
}

function is_admin(): bool
{
    return has_role('Administrator')
        || has_role('Super-admin');
}

// --------------------------------------------------
// 13. DEVELOPMENT MODE
// --------------------------------------------------

function development_mode(): bool
{
    return false;
}

// --------------------------------------------------
// 14. INITIALIZE SESSION
// --------------------------------------------------

ensure_session();
check_session_timeout();
