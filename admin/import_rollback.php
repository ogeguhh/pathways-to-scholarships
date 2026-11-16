<?php
declare(strict_types=1);

/*
 * Iteration 3 — Roll back a previously committed import batch.
 * POST-only action with CSRF and local-development restrictions.
 */

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/includes/db.php';
require_once $projectRoot . '/includes/security.php';
require_once $projectRoot . '/includes/config.php';
require_once $projectRoot . '/includes/import_service.php';

// Temporary development-only access control.
// Replace with administrator authentication in Iteration 7.
$remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';

if (!APP_DEBUG ||
    !in_array($remoteAddress, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Access denied.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('POST required.');
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Invalid CSRF token.');
}

$batchId = filter_input(INPUT_POST, 'batch_id', FILTER_VALIDATE_INT);

if ($batchId === false || $batchId === null || $batchId < 1) {
    http_response_code(400);
    exit('Invalid batch ID.');
}

try {
    // The service removes the batch's scholarships and preserves history.
    rollbackScholarshipBatch($batchId);

    header(
        'Location: import_validation.php?batch_id=' . $batchId,
        true,
        303
    );
    exit;
} catch (Throwable $error) {
    http_response_code(500);
    exit('Rollback failed: ' . e($error->getMessage()));
}
