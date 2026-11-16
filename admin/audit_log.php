<?php
declare(strict_types=1);

/*
 * Pathways to Scholarships
 * Iteration 4 — Audit Log Viewer
 *
 * Displays administrative actions recorded in audit_log.
 * This page does not modify database records.
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

$pdo = getDBConnection();

// Retrieve the most recent audit records first.
$stmt = $pdo->query(
    'SELECT
        audit_id,
        user_id,
        action,
        entity_type,
        entity_id,
        details,
        created_at
     FROM audit_log
     ORDER BY created_at DESC, audit_id DESC
     LIMIT 500'
);

$records = $stmt->fetchAll();

$pageTitle = 'Audit Log | Pathways to Scholarships';

require_once $projectRoot . '/includes/header.php';
require_once $projectRoot . '/includes/nav.php';
require_once $projectRoot . '/includes/dev_banner.php';
?>

<main class="container py-5">

    <h1 class="mb-3">Administrative Audit Log</h1>

    <p class="text-muted">
        Review administrative changes to scholarships,
        providers, and import batches.
    </p>

    <div class="table-responsive">

        <table class="table table-striped table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <th>Audit ID</th>
                    <th>Date</th>
                    <th>Action</th>
                    <th>Entity Type</th>
                    <th>Entity ID</th>
                    <th>User</th>
                    <th>Details</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($records as $record): ?>
                    <tr>
                        <td><?= (int) $record['audit_id'] ?></td>

                        <td><?= e($record['created_at']) ?></td>

                        <td><?= e($record['action']) ?></td>

                        <td><?= e($record['entity_type']) ?></td>

                        <td>
                            <?= $record['entity_id'] !== null
                                ? (int) $record['entity_id']
                                : '—' ?>
                        </td>

                        <td>
                            <?= $record['user_id'] !== null
                                ? (int) $record['user_id']
                                : 'Development' ?>
                        </td>

                        <td>
                            <pre class="mb-0 small"
                                 style="white-space: pre-wrap; min-width: 220px;"><?= e($record['details']) ?></pre>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if ($records === []): ?>
                    <tr>
                        <td colspan="7" class="text-center">
                            No audit records found.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

    </div>

</main>

<?php require_once $projectRoot . '/includes/footer.php'; ?>
