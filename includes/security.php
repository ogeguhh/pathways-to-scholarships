
<?php
// Start a session if one is not already running
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Escape text before displaying it on a webpage
function escape($text) {
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}

// Create a CSRF token for forms
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

// Check that a submitted CSRF token is valid
function verify_csrf($token) {
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}
