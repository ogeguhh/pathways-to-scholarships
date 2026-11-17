<?php
declare(strict_types=1);

/*
 * Pathways to Scholarships
 * Iteration 7 — Commit Scholarship Import Batch
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
 * - Transaction handling delegated to import service
 */

$projectRoot = dirname(__DIR__);

// Load authentication and authorization helpers.
require_once $projectRoot . '/includes/security.php';

// Enforce real role-based access before any database changes.
// This replaces the previous APP_DEBUG / localhost restriction.
require_role(['Administrator', 'Super-admin']);

// Only allow POST requests.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('POST required.');
}

// Verify the CSRF token before processing the request.
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

// Load the existing import service.
require_once $projectRoot . '/includes/db.php';
require_once $projectRoot . '/includes/import_service.php';

try {

    /*
     * The existing import service is responsible for:
     *
     * - Confirming that the batch is validated
     * - Processing eligible scholarship records
     * - Handling database transactions
     * - Updating the batch status
     *
     * We preserve this functionality rather than
     * duplicating its database logic here.
     */

    commitScholarshipBatch($batchId);

    // Return to the validation report after committing.
    header(
        'Location: import_validation.php?batch_id=' . $batchId,
        true,
        303
    );

    exit;

} catch (Throwable $error) {

    // Record technical details in the server log.
    error_log(
        'Scholarship import commit failed. Batch ID: ' .
        $batchId . '. Error: ' . $error->getMessage()
    );

    // Avoid exposing database details to users.
    http_response_code(500);

    exit(
        'Unable to commit this scholarship batch. ' .
        'Please check the server logs or contact an administrator.'
    );
}
