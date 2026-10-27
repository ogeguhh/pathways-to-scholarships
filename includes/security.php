<?php
declare(strict_types=1);

/*
 * Shared Security Helpers
 *
 * During Iteration 4, administrator features can be
 * tested locally without the completed login system.
 *
 * Production access remains blocked until real
 * authentication and authorization are implemented.
 */

// Start a session for CSRF protection.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/**
 * Escape text before displaying it in HTML.
 */
function e(mixed $value): string
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

/**
 * Create or reuse a CSRF token.
 */
function csrf_token(): string
{
    if (
        !isset($_SESSION['csrf_token']) ||
        !is_string($_SESSION['csrf_token'])
    ) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Verify the submitted CSRF token.
 */
function verify_csrf(mixed $submitted): bool
{
    return is_string($submitted)
        && hash_equals(csrf_token(), $submitted);
}

/**
 * Allow development mode only for local requests
 * handled by PHP's built-in development server.
 *
 * This is not a replacement for real authentication.
 */
function development_mode(): bool
{
    if (PHP_SAPI !== 'cli-server') {
        return false;
    }

    $address = $_SERVER['REMOTE_ADDR'] ?? '';

    return in_array(
        $address,
        ['127.0.0.1', '::1'],
        true
    );
}

/**
 * Return the placeholder administrator ID locally.
 *
 * The seeded development user has ID 1.
 * Real authentication will replace this later.
 */
function current_user_id(): ?int
{
    return development_mode() ? 1 : null;
}

/**
 * Check whether the user has a particular role.
 *
 * During local development, allow administrator
 * testing. Other environments remain blocked.
 */
function has_role(string $role): bool
{
    if (development_mode()) {
        return in_array(
            $role,
            ['Administrator', 'Super-admin'],
            true
        );
    }

    return false;
}

/**
 * Check administrator access.
 */
function is_admin(): bool
{
    return has_role('Administrator')
        || has_role('Super-admin');
}

/**
 * Require administrator access.
 */
function require_admin_access(): void
{
    if (is_admin()) {
        return;
    }

    http_response_code(403);

    exit(
        'Administrator access is unavailable until ' .
        'application authorization is implemented.'
    );
}

/**
 * Require a particular role.
 */
function require_role(string $role): void
{
    if (has_role($role)) {
        return;
    }

    http_response_code(403);

    exit(
        'Access denied. The required role is unavailable.'
    );
}
