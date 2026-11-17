<?php
declare(strict_types=1);

/*
 * Pathways to Scholarships
 * Iteration 7 — Rollback Scholarship Import Batch
 *
 * Authorized roles:
 * - Administrator
 * - Super-admin
 *
 * Security:
 * - Authenticated administrator required
 * - POST requests only
 * - CSRF verification
 * - Valid numeric batch ID
 * - Rollback delegated to existing import service
 */

$projectRoot = dirname(__DIR__);

// Load authentication and authorization helpers.
require_once $projectRoot . '/includes/security.php';

// Only administrators and super-admins may roll back batches.
// Replaces the old APP_DEBUG / localhost restriction.
require_role(['Administrator', 'Super-admin']);

// Only allow POST requests.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('POST required.');
}

// Verify the CSRF token before changing any records.
if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Invalid CSRF token.');
}

// Validate the import batch ID.
$batchId = filter_input(
    INPUT_POST,
    'batch_id',
    FILTER_VALIDATE_INT
);

if (
    $batchId === false ||
    $batchId === null ||
    $batchId < 1
) {
    http_response_code(400);
    exit('Invalid batch ID.');
}

// Load the existing database and import service.
require_once $projectRoot . '/includes/db.php';
require_once $projectRoot . '/includes/import_service.php';

try {

    /*
     * The existing import service handles:
     *
     * - Checking whether the batch can be rolled back
     * - Removing scholarships imported by that batch
     * - Preserving the batch's import history
     * - Updating the batch status
     * - Database transaction management
     *
     * Keep this logic in the import service.
     */

    rollbackScholarshipBatch($batchId);

    // Return to the validation report after rollback.
    header(
        'Location: import_validation.php?batch_id=' . $batchId,
        true,
        303
    );

    exit;

} catch (Throwable $error) {

    // Log technical details without displaying them publicly.
    error_log(
        'Scholarship import rollback failed. Batch ID: ' .
        $batchId . '. Error: ' . $error->getMessage()
    );

    http_response_code(500);

    exit(
        'Unable to roll back this scholarship batch. ' .
        'Please check the server logs or contact an administrator.'
    );
}
