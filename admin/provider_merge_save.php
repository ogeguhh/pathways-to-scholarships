<?php
declare(strict_types=1);

/*
 * Pathways to Scholarships
 * Iteration 7 — Save Provider Merge
 *
 * Securely merges a duplicate provider into another provider.
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

// Verify CSRF protection.
if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Invalid CSRF token.');
}

// Match the field names in provider_merge.php.
$sourceId = filter_input(
    INPUT_POST,
    'source_provider_id',
    FILTER_VALIDATE_INT
);

$targetId = filter_input(
    INPUT_POST,
    'target_provider_id',
    FILTER_VALIDATE_INT
);

// Validate provider IDs.
if (!$sourceId || !$targetId || $sourceId < 1 || $targetId < 1) {
    http_response_code(400);
    exit('Please select valid providers.');
}

if ($sourceId === $targetId) {
    http_response_code(400);
    exit('A provider cannot be merged into itself.');
}

$pdo = getDBConnection();

try {
    // Begin transaction.
    $pdo->beginTransaction();

    // Lock providers in a consistent order.
    $firstId = min($sourceId, $targetId);
    $secondId = max($sourceId, $targetId);

    $find = $pdo->prepare(
        'SELECT provider_id, provider_name, provider_type_id
         FROM providers
         WHERE provider_id = ?
         FOR UPDATE'
    );

    $find->execute([$firstId]);
    $first = $find->fetch(PDO::FETCH_ASSOC);

    $find->execute([$secondId]);
    $second = $find->fetch(PDO::FETCH_ASSOC);

    if (!$first || !$second) {
        throw new RuntimeException(
            'One or both providers no longer exist.'
        );
    }

    $source = (int) $first['provider_id'] === $sourceId
        ? $first
        : $second;

    $target = (int) $first['provider_id'] === $targetId
        ? $first
        : $second;

    // Count scholarships belonging to the source provider.
    $count = $pdo->prepare(
        'SELECT COUNT(*)
         FROM scholarships
         WHERE provider_id = ?'
    );

    $count->execute([$sourceId]);

    $scholarshipCount = (int) $count->fetchColumn();

    // Reassign scholarships to the destination provider.
    $update = $pdo->prepare(
        'UPDATE scholarships
         SET provider_id = ?
         WHERE provider_id = ?'
    );

    $update->execute([$targetId, $sourceId]);

    // Prepare audit log details.
    $details = [
        'source_provider_id' => $sourceId,
        'source_provider_name' => $source['provider_name'],
        'target_provider_id' => $targetId,
        'target_provider_name' => $target['provider_name'],
        'scholarships_reassigned' => $scholarshipCount
    ];

    // Record the merge using the correct audit_log columns.
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
        'merge',
        'provider',
        $targetId,
        json_encode($details, JSON_THROW_ON_ERROR)
    ]);

    // Delete the duplicate provider.
    $delete = $pdo->prepare(
        'DELETE FROM providers
         WHERE provider_id = ?'
    );

    $delete->execute([$sourceId]);

    // Commit all changes.
    $pdo->commit();

    // Redirect to Provider Management.
    $projectFolder = 'pathways-to-scholarships-project-rebuild-clean';

    header(
        'Location: /' . $projectFolder . '/admin/providers.php'
    );
    exit;

} catch (Throwable $error) {

    // Undo changes if anything fails.
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Provider merge failed: ' . $error->getMessage()
    );

    if ($error instanceof RuntimeException) {
        http_response_code(400);
        exit(e($error->getMessage()));
    }

    http_response_code(500);
    exit('Provider merge failed. No changes were saved.');
}
