<?php
declare(strict_types=1);

/*
 * Pathways to Scholarships
 * Iteration 4 — Mark Scholarship as Verified
 *
 * Updates the verification date of an active scholarship
 * and records the action in the audit log.
 */

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/includes/db.php';
require_once $projectRoot . '/includes/security.php';
require_once $projectRoot . '/includes/config.php';

// Until authentication is implemented in Iteration 7,
// restrict administration to local development.
$remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';

if (!APP_DEBUG ||
    !in_array($remoteAddress, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Access denied.');
}

// Verification changes database information, so POST is required.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

// Reject requests without the correct session CSRF token.
if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Invalid CSRF token.');
}

// A scholarship ID must be a positive integer.
$id = filter_input(INPUT_POST, 'scholarship_id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    http_response_code(400);
    exit('Invalid scholarship ID.');
}

$pdo = getDBConnection();

try {
    $pdo->beginTransaction();

    // Lock the scholarship while its verification date is updated.
    $stmt = $pdo->prepare(
        'SELECT scholarship_name, status, verification_date
         FROM scholarships
         WHERE scholarship_id = ?
         FOR UPDATE'
    );

    $stmt->execute([$id]);
    $scholarship = $stmt->fetch();

    if (!$scholarship) {
        throw new RuntimeException('Scholarship not found.');
    }

    if ($scholarship['status'] !== 'active') {
        throw new RuntimeException(
            'Only active scholarships can be verified.'
        );
    }

    // Use the database's current date for consistency.
    $update = $pdo->prepare(
        'UPDATE scholarships
         SET verification_date = CURRENT_DATE()
         WHERE scholarship_id = ?'
    );

    $update->execute([$id]);

    // Store the old and new dates in the audit record.
    $audit = $pdo->prepare(
        'INSERT INTO audit_log
            (user_id, action, entity_type, entity_id, details)
         VALUES (
            ?,
            ?,
            ?,
            ?,
            JSON_OBJECT(
                "scholarship_name", ?,
                "previous_verification_date", ?,
                "new_verification_date", CURRENT_DATE(),
                "development_mode", true
            )
         )'
    );

    $audit->execute([
        current_user_id(),
        'verify',
        'scholarship',
        $id,
        $scholarship['scholarship_name'],
        $scholarship['verification_date']
    ]);

    $pdo->commit();

    // Return to the scholarship management page.
    header('Location: /admin/scholarships.php');
    exit;

} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Scholarship verification failed: ' . $error->getMessage());

    http_response_code(400);

    if ($error instanceof RuntimeException) {
        exit(e($error->getMessage()));
    }

    exit('Scholarship could not be verified.');
}
