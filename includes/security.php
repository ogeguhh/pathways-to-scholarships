<?php
// Pathways to Scholarships
// Iteration 2: Shared Security Helpers

// Escape text before displaying it in HTML.
// This helps prevent cross-site scripting (XSS).
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Start a PHP session when needed.
// Sessions allow us to store a CSRF token between requests.
function ensure_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

// Create or retrieve the current session's CSRF token.
// Forms include this token to help prevent forged submissions.
function csrf_token(): string
{
    ensure_session();

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

// Verify that a submitted token matches the session token.
function verify_csrf_token(?string $submittedToken): bool
{
    ensure_session();

    return is_string($submittedToken)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $submittedToken);
}

// Return the signed-in user's ID.
// Authentication is not implemented yet, so no user is signed in.
function current_user_id(): ?int
{
    return null;
}

// Require a particular role before allowing access.
// Until authentication is implemented, access is always denied.
function require_role(string $requiredRole): void
{
    http_response_code(403);
    exit('Access denied. Authentication is not available yet.');
}
