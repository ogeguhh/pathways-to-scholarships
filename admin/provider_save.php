<?php
declare(strict_types=1);

/*
 * Pathways to Scholarships
 * Iteration 7 — Save Provider Changes
 *
 * Updates an existing provider's name and type.
 * Records changes in the audit log.
 */

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/includes/db.php';
require_once $projectRoot . '/includes/security.php';
require_once $projectRoot . '/includes/config.php';

// Administrator and Super-admin only.
require_role(['Administrator', 'Super-admin']);

// Only accept POST requests.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

// Validate CSRF token.
if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Invalid CSRF token.');
}

// Read submitted values.
$id = filter_input(
    INPUT_POST,
    'provider_id',
    FILTER_VALIDATE_INT
);

$typeId = filter_input(
    INPUT_POST,
    'provider_type_id',
    FILTER_VALIDATE_INT
);

$name = $_POST['provider_name'] ?? '';

if (!is_string($name)) {
    http_response_code(400);
    exit('Invalid provider name.');
}

$name = trim($name);

// Validate provider ID.
if (!$id || $id < 1) {
    http_response_code(400);
    exit('Invalid provider ID.');
}

// Validate provider name.
if ($name === '' || mb_strlen($name) > 255) {
    http_response_code(422);
    exit('Provider name must contain 1 to 255 characters.');
}

// Validate provider type.
if (!$typeId || $typeId < 1) {
    http_response_code(422);
    exit('Select a valid provider type.');
}

$pdo = getDBConnection();

try {

    // Begin transaction.
    $pdo->beginTransaction();

    // Lock the existing provider.
    $stmt = $pdo->prepare(
        'SELECT provider_name, provider_type_id
         FROM providers
         WHERE provider_id = ?
         FOR UPDATE'
    );

    $stmt->execute([$id]);

    $provider = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$provider) {
        throw new RuntimeException('Provider not found.');
    }

    // Confirm provider type exists.
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM provider_types
         WHERE provider_type_id = ?'
    );

    $stmt->execute([$typeId]);

    if ((int) $stmt->fetchColumn() !== 1) {
        throw new RuntimeException('Invalid provider type.');
    }

    // Update provider.
    $update = $pdo->prepare(
        'UPDATE providers
         SET provider_name = ?,
             provider_type_id = ?
         WHERE provider_id = ?'
    );

    $update->execute([
        $name,
        $typeId,
        $id
    ]);

    // Record changes in audit log.
    $details = [
        'old_name' => $provider['provider_name'],
        'new_name' => $name,
        'old_type_id' => (int) $provider['provider_type_id'],
        'new_type_id' => $typeId
    ];

    $audit = $pdo->prepare(
        'INSERT INTO audit_log
            (
                user_id,
                action_type,
                entity_type,
                entity_id,
                action_details
            )
         VALUES (?, ?, ?, ?, ?)'
    );

    $audit->execute([
        current_user_id(),
        'edit',
        'provider',
        $id,
        json_encode($details, JSON_THROW_ON_ERROR)
    ]);

    // Save all changes.
    $pdo->commit();

    // Return to Provider Management.
    header(
        'Location: ' . app_url('admin/providers.php'),
        true,
        303
    );
    exit;

} catch (Throwable $error) {

    // Undo changes if anything fails.
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Provider update failed: ' . $error->getMessage()
    );

    if ($error instanceof RuntimeException) {
        http_response_code(400);
        exit(e($error->getMessage()));
    }

    http_response_code(500);
    exit('Provider could not be updated. No changes were saved.');
}
