<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/** Escape untrusted content before inserting it into HTML. */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Create or reuse a session-bound CSRF token. */
function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Verify a token supplied by a submitted form. */
function verify_csrf(mixed $submitted): bool
{
    return is_string($submitted) && hash_equals(csrf_token(), $submitted);
}

/** Authentication placeholder. Replace when the team's login is implemented. */
function current_user_id(): ?int
{
    return null;
}

/** Authorization placeholder. Replace when the team's role checks are implemented. */
function has_role(string $role): bool
{
    return false;
}

function is_admin(): bool
{
    return has_role('Administrator') || has_role('Super-admin');
}

/** Require administrator access while keeping the unfinished auth system fail-closed. */
function require_admin_access(): void
{
    if (is_admin()) {
        return;
    }

    http_response_code(403);
    exit('Administrator access is unavailable until application authorization is implemented.');
}

/** Fail closed until the team's real role authorization is implemented. */
function require_role(string $role): void
{
    if (!has_role($role)) {
        throw new LogicException('Role authorization is not implemented yet.');
    }
}
