<?php
declare(strict_types=1);

// Start a session so PHP can store the CSRF token.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/**
 * Escape text before displaying it in HTML.
 * Helps prevent cross-site scripting (XSS).
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
 * Generate or reuse a security token for forms.
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
 * Verify the submitted security token.
 */
function verify_csrf(mixed $submitted): bool
{
    return is_string($submitted)
        && hash_equals(csrf_token(), $submitted);
}

/**
 * Return the development user's ID.
 *
 * Authentication will be implemented in Iteration 7.
 * For now, null is used until the seeded development
 * user's actual ID has been confirmed.
 */
/**
 * Return the placeholder development user's ID.
 *
 * Iteration 4 uses user ID 1 for local testing.
 * Iteration 7 will replace this with the logged-in user's ID.
 */
function current_user_id(): ?int
{
    return 1;
}
/**
 * Development-only role check.
 *
 * Iteration 4: No login required.
 * Iteration 7: Replace with actual role authorization.
 *
 * IMPORTANT: Only use this on localhost or behind
 * HTTP Basic Authentication during development.
 */
function require_role(string $role): void
{
    // Intentionally empty during local development.
}
