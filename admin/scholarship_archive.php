<?php
declare(strict_types=1);

/*
 * Pathways to Scholarships
 * Iteration 4 — Archive Scholarship
 *
 * Archives a scholarship without deleting its record.
 * Records the action in the audit log.
 */

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/includes/db.php';
require_once $projectRoot . '/includes/security.php';
require_once $projectRoot . '/includes/config.php';

// Restrict administration to local development until Iteration 7.
$remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';

if (!APP_DEBUG ||
    !in_array($remoteAddress, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Access denied.');
}

// Archiving must use POST to prevent accidental changes from links.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Invalid CSRF token.');
}

$id = filter_input(INPUT_POST, 'scholarship_id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    http_response_code(400);
    exit('Invalid scholarship ID.');
}

$pdo = getDBConnection();

try {
    $pdo->beginTransaction();

    // Lock the scholarship row during the status change.
    $stmt = $pdo->prepare(
        'SELECT scholarship_name, status
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
        throw new RuntimeException('Scholarship is already archived.');
    }

    // Soft delete: retain the record and all its relationships.
    $update = $pdo->prepare(
        'UPDATE scholarships
         SET status = ?
         WHERE scholarship_id = ?'
    );

    $update->execute(['archived', $id]);

    // Record the administrative action in the existing audit table.
    $audit = $pdo->prepare(
        'INSERT INTO audit_log
            (user_id, action, entity_type, entity_id, details)
         VALUES (?, ?, ?, ?, ?)'
    );

    $audit->execute([
        current_user_id(),
        'archive',
        'scholarship',
        $id,
        json_encode(
            [
                'scholarship_name' => $scholarship['scholarship_name'],
                'previous_status' => 'active',
                'new_status' => 'archived',
                'development_mode' => true
            ],
            JSON_THROW_ON_ERROR
        )
    ]);

    $pdo->commit();

    header('Location: /admin/scholarships.php');
    exit;

} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Scholarship archive failed: ' . $error->getMessage());

    http_response_code(400);

    if ($error instanceof RuntimeException) {
        exit(e($error->getMessage()));
    }

    exit('Scholarship could not be archived.');
}
