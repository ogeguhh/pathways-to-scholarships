<?php
// Pathways to Scholarships
// Iteration 7: Authentication and Role-Based Access Control

require_once __DIR__ . '/db.php';

define('SESSION_TIMEOUT', 1800);

// Escape HTML output to prevent cross-site scripting.
function e(?string $value): string
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

// Start a secure session and enforce the inactivity timeout.
function ensure_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS'])
                && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        session_start();
    }

    if (isset($_SESSION['user_id'])) {

        $lastActivity = $_SESSION['last_activity'] ?? 0;

        if (time() - $lastActivity >= SESSION_TIMEOUT) {

            $_SESSION = [];

            session_regenerate_id(true);

        } else {

            $_SESSION['last_activity'] = time();
        }
    }
}

// Generate a token that protects forms from forged requests.
function csrf_token(): string
{
    ensure_session();

    if (empty($_SESSION['csrf_token'])) {

        $_SESSION['csrf_token'] =
            bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

// Compare the submitted token with the session token.
function verify_csrf_token($submittedToken): bool
{
    ensure_session();

    return is_string($submittedToken)
        && isset($_SESSION['csrf_token'])
        && hash_equals(
            $_SESSION['csrf_token'],
            $submittedToken
        );
}

// Generate a fingerprint from the stored password hash.
// When the password changes, the fingerprint also changes.
function password_fingerprint(string $passwordHash): string
{
    return hash('sha256', $passwordHash);
}

// Create an authenticated session after successful login.
function login_user(array $user): void
{
    ensure_session();

    // Prevent session fixation attacks.
    session_regenerate_id(true);

    $_SESSION = [
        'user_id' => (int) $user['user_id'],
        'full_name' => $user['full_name'],
        'email' => $user['email'],
        'role' => $user['role_name'],

        'must_change_password' =>
            (bool) $user['must_change_password'],

        'password_fingerprint' =>
            password_fingerprint($user['password_hash']),

        'last_activity' => time()
    ];
}

// Destroy the authenticated session and its cookie.
function logout_user(): void
{
    ensure_session();

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {

        $params = session_get_cookie_params();

        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => 'Lax'
        ]);
    }

    session_destroy();
}

// Clear an invalid authenticated session while keeping
// a fresh anonymous session available for public pages.
function invalidate_authenticated_session(): void
{
    $_SESSION = [];

    session_regenerate_id(true);
}

// Retrieve the signed-in user's current database information.
function current_user(): ?array
{
    ensure_session();

    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $pdo = getDBConnection();

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
         WHERE u.user_id = ?
         LIMIT 1'
    );

    $stmt->execute([
        (int) $_SESSION['user_id']
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Reject deleted, inactive, or invalid accounts.
    if (
        !$user ||
        $user['account_status'] !== 'active' ||
        $user['role_name'] === 'Visitor' ||
        empty($user['password_hash'])
    ) {

        invalidate_authenticated_session();

        return null;
    }

    // Reject sessions created before the latest password change.
    $storedFingerprint =
        $_SESSION['password_fingerprint'] ?? '';

    $currentFingerprint = password_fingerprint(
        $user['password_hash']
    );

    if (
        !is_string($storedFingerprint) ||
        !hash_equals(
            $currentFingerprint,
            $storedFingerprint
        )
    ) {

        invalidate_authenticated_session();

        return null;
    }

    // Synchronize session information with the database.
    $_SESSION['full_name'] = $user['full_name'];

    $_SESSION['email'] = $user['email'];

    $_SESSION['role'] = $user['role_name'];

    $_SESSION['must_change_password'] =
        (bool) $user['must_change_password'];

    // Do not expose password hashes to other application pages.
    unset($user['password_hash']);

    return $user;
}

// Return the authenticated user's actual ID for audit logging.
function current_user_id(): ?int
{
    $user = current_user();

    return $user
        ? (int) $user['user_id']
        : null;
}

// Return the user's current role or Visitor.
function current_user_role(): string
{
    $user = current_user();

    return $user['role_name'] ?? 'Visitor';
}

// Determine whether a valid authenticated account exists.
function is_logged_in(): bool
{
    return current_user_id() !== null;
}

// Prevent users with temporary passwords from accessing
// protected pages until they replace their password.
function enforce_password_change(array $user): void
{
    if (!$user['must_change_password']) {
        return;
    }

    // Allow password changes and logout to avoid redirect loops.
    $currentPath = $_SERVER['SCRIPT_NAME'] ?? '';

    $allowedPaths = [
        '/pages/change_password.php',
        '/pages/logout.php'
    ];

    if (!in_array($currentPath, $allowedPaths, true)) {

        header('Location: /pages/change_password.php');

        exit;
    }
}

// Redirect unauthenticated visitors to the login page.
function require_login(): void
{
    $user = current_user();

    if (!$user) {

        header('Location: /pages/login.php');

        exit;
    }

    enforce_password_change($user);
}

// Enforce role permissions before displaying protected pages
// or executing protected database actions.
function require_role(string $requiredRole): void
{
    $user = current_user();

    if (!$user) {

        header('Location: /pages/login.php');

        exit;
    }

    enforce_password_change($user);

    $role = $user['role_name'];

    // Super-admins inherit Administrator permissions.
    $allowed =
        $role === $requiredRole ||
        (
            $role === 'Super-admin' &&
            $requiredRole === 'Administrator'
        );

    if (!$allowed) {

        http_response_code(403);

        exit(
            '403 Forbidden: You do not have permission ' .
            'to access this page.'
        );
    }
}
