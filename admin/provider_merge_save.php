<?php
declare(strict_types=1);

/*
 * Pathways to Scholarships
 * Iteration 4 — Save Provider Merge
 *
 * Moves scholarships from a duplicate provider to
 * the retained provider, then deletes the duplicate.
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

// Merging changes database records, so require POST and CSRF protection.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Invalid CSRF token.');
}

$sourceId = filter_input(INPUT_POST, 'source_id', FILTER_VALIDATE_INT);
$targetId = filter_input(INPUT_POST, 'target_id', FILTER_VALIDATE_INT);

if (!$sourceId || !$targetId || $sourceId < 1 || $targetId < 1) {
    http_response_code(400);
    exit('Select valid providers.');
}

if ($sourceId === $targetId) {
    http_response_code(400);
    exit('A provider cannot be merged into itself.');
}

$pdo = getDBConnection();

try {
    // All merge operations must succeed together.
    $pdo->beginTransaction();

    // Lock both providers in ID order to reduce concurrency conflicts.
    $firstId = min($sourceId, $targetId);
    $secondId = max($sourceId, $targetId);

    $find = $pdo->prepare(
        'SELECT provider_id, provider_name, provider_type_id
         FROM providers
         WHERE provider_id = ?
         FOR UPDATE'
    );

    $find->execute([$firstId]);
    $first = $find->fetch();

    $find->execute([$secondId]);
    $second = $find->fetch();

    if (!$first || !$second) {
        throw new RuntimeException('One or both providers no longer exist.');
    }

    $source = (int) $first['provider_id'] === $sourceId
        ? $first : $second;

    $target = (int) $first['provider_id'] === $targetId
        ? $first : $second;

    // Count the scholarships that will be reassigned.
    $count = $pdo->prepare(
        'SELECT COUNT(*)
         FROM scholarships
         WHERE provider_id = ?'
    );

    $count->execute([$sourceId]);
    $scholarshipCount = (int) $count->fetchColumn();

    // Move all scholarship references to the retained provider.
    $update = $pdo->prepare(
        'UPDATE scholarships
         SET provider_id = ?
         WHERE provider_id = ?'
    );

    $update->execute([$targetId, $sourceId]);

    // Record the merge before deleting the source provider.
    $details = [
        'source_provider_id' => $sourceId,
        'source_provider_name' => $source['provider_name'],
        'target_provider_id' => $targetId,
        'target_provider_name' => $target['provider_name'],
        'scholarships_reassigned' => $scholarshipCount,
        'development_mode' => true
    ];

    $audit = $pdo->prepare(
        'INSERT INTO audit_log
            (user_id, action, entity_type, entity_id, details)
         VALUES (?, ?, ?, ?, ?)'
    );

    $audit->execute([
        current_user_id(),
        'merge',
        'provider',
        $targetId,
        json_encode($details, JSON_THROW_ON_ERROR)
    ]);

    // Delete the duplicate only after its scholarships are reassigned.
    $delete = $pdo->prepare(
        'DELETE FROM providers
         WHERE provider_id = ?'
    );

    $delete->execute([$sourceId]);

    $pdo->commit();

    header('Location: /admin/providers.php');
    exit;

} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Provider merge failed: ' . $error->getMessage());

    http_response_code(400);

    if ($error instanceof RuntimeException) {
        exit(e($error->getMessage()));
    }

    exit('Provider merge failed. No changes were saved.');
}
