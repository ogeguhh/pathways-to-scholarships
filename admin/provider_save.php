<?php
declare(strict_types=1);

/*
 * Pathways to Scholarships
 * Iteration 4 — Save Provider Changes
 *
 * Updates an existing provider's name and type.
 * Records the changes in the audit log.
 */

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/includes/db.php';
require_once $projectRoot . '/includes/security.php';
require_once $projectRoot . '/includes/config.php';

// Restrict access to local development until Iteration 7.
$remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';

if (!APP_DEBUG ||
    !in_array($remoteAddress, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Access denied.');
}

// Provider updates must use POST and a valid CSRF token.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Invalid CSRF token.');
}

$id = filter_input(INPUT_POST, 'provider_id', FILTER_VALIDATE_INT);
$typeId = filter_input(INPUT_POST, 'provider_type_id', FILTER_VALIDATE_INT);

$name = $_POST['provider_name'] ?? '';

if (!is_string($name)) {
    http_response_code(400);
    exit('Invalid provider name.');
}

$name = trim($name);

// Validate the required provider fields.
if (!$id || $id < 1) {
    http_response_code(400);
    exit('Invalid provider ID.');
}

if ($name === '' || mb_strlen($name) > 255) {
    http_response_code(422);
    exit('Provider name must contain 1 to 255 characters.');
}

if (!$typeId || $typeId < 1) {
    http_response_code(422);
    exit('Select a valid provider type.');
}

$pdo = getDBConnection();

try {
    $pdo->beginTransaction();

    // Lock the provider record while updating it.
    $stmt = $pdo->prepare(
        'SELECT provider_name, provider_type_id
         FROM providers
         WHERE provider_id = ?
         FOR UPDATE'
    );

    $stmt->execute([$id]);
    $provider = $stmt->fetch();

    if (!$provider) {
        throw new RuntimeException('Provider not found.');
    }

    // Ensure the selected provider type exists.
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM provider_types
         WHERE provider_type_id = ?'
    );

    $stmt->execute([$typeId]);

    if ((int) $stmt->fetchColumn() !== 1) {
        throw new RuntimeException('Invalid provider type.');
    }

    // Update only the provider's name and type.
    // Scholarships continue referencing the same provider ID.
    $update = $pdo->prepare(
        'UPDATE providers
         SET provider_name = ?,
             provider_type_id = ?
         WHERE provider_id = ?'
    );

    $update->execute([$name, $typeId, $id]);

    // Record the old and new values in the audit log.
    $audit = $pdo->prepare(
        'INSERT INTO audit_log
            (user_id, action, entity_type, entity_id, details)
         VALUES (?, ?, ?, ?, ?)'
    );

    $audit->execute([
        current_user_id(),
        'edit',
        'provider',
        $id,
        json_encode(
            [
                'old_name' => $provider['provider_name'],
                'new_name' => $name,
                'old_type_id' => (int) $provider['provider_type_id'],
                'new_type_id' => $typeId,
                'development_mode' => true
            ],
            JSON_THROW_ON_ERROR
        )
    ]);

    $pdo->commit();

    header('Location: /admin/providers.php');
    exit;

} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Provider update failed: ' . $error->getMessage());

    http_response_code(400);

    if ($error instanceof RuntimeException) {
        exit(e($error->getMessage()));
    }

    exit('Provider could not be updated.');
}
