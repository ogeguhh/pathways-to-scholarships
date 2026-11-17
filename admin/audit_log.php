<?php
declare(strict_types=1);

/*
 * Pathways to Scholarships
 * Iteration 7 — Administrative Audit Log
 *
 * Authorized roles:
 * - Administrator
 * - Super-admin
 *
 * Displays the 500 most recent administrative
 * actions recorded in the audit_log table.
 *
 * This page is read-only.
 */

$projectRoot = dirname(__DIR__);

// Load security functions.
require_once $projectRoot . '/includes/security.php';

// Restrict access to administrators.
require_role(['Administrator', 'Super-admin']);

// Load database connection.
require_once $projectRoot . '/includes/db.php';

$pdo = getDBConnection();

// Retrieve the 500 most recent audit records.
// Column names match the existing database schema.
$stmt = $pdo->query(
    'SELECT
        audit_id,
        user_id,
        action_type,
        entity_type,
        entity_id,
        action_details,
        created_at
     FROM audit_log
     ORDER BY created_at DESC, audit_id DESC
     LIMIT 500'
);

$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Audit Log | Pathways to Scholarships';

// Shared page layout.
require_once $projectRoot . '/includes/header.php';
require_once $projectRoot . '/includes/nav.php';
?>

<main class="container py-5">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">

        <div>
            <h1 class="mb-3">
                Administrative Audit Log
            </h1>

            <p class="text-muted mb-0">
                Review administrative changes to scholarships,
                providers, and import batches.
            </p>
        </div>

        <a href="dashboard.php" class="btn btn-outline-secondary">
            Back to Admin Dashboard
        </a>

    </div>

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

                        <td>
                            <?= (int) $record['audit_id'] ?>
                        </td>

                        <td>
                            <?= e((string) ($record['created_at'] ?? '')) ?>
                        </td>

                        <td>
                            <?= e((string) ($record['action_type'] ?? '')) ?>
                        </td>

                        <td>
                            <?= e((string) ($record['entity_type'] ?? '')) ?>
                        </td>

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
                            <pre
                                class="mb-0 small"
                                style="white-space: pre-wrap; min-width: 220px;"
                            ><?= e((string) ($record['action_details'] ?? '')) ?></pre>
                        </td>

                    </tr>

                <?php endforeach; ?>

                <?php if ($records === []): ?>

                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            No audit records found.
                        </td>
                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</main>

<?php
require_once $projectRoot . '/includes/footer.php';
?>