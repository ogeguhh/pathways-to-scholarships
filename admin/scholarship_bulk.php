<?php
declare(strict_types=1);

/*
 * Pathways to Scholarships
 * Iteration 4 — Bulk Scholarship Actions
 *
 * Allows administrators to archive or verify multiple
 * active scholarships in one database transaction.
 */

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/includes/db.php';
require_once $projectRoot . '/includes/security.php';
require_once $projectRoot . '/includes/config.php';

// Authentication is implemented in Iteration 7.
// Until then, only allow local development access.
require_role('Administrator');

// Database-changing operations must use POST and CSRF protection.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Invalid CSRF token.');
}

$action = $_POST['bulk_action'] ?? '';
$submittedIds = $_POST['scholarship_ids'] ?? [];

if (!is_string($action) ||
    !in_array($action, ['archive', 'verify'], true)) {
    http_response_code(400);
    exit('Invalid bulk action.');
}

if (!is_array($submittedIds) || $submittedIds === []) {
    http_response_code(400);
    exit('Select at least one scholarship.');
}

// Validate every ID before accessing the database.
$ids = [];

foreach ($submittedIds as $value) {
    if (!is_string($value) ||
        !ctype_digit($value) ||
        (int) $value < 1) {
        http_response_code(400);
        exit('Invalid scholarship selection.');
    }

    $ids[] = (int) $value;
}

// Remove duplicate IDs so each scholarship is processed once.
$ids = array_values(array_unique($ids));

$pdo = getDBConnection();

try {
    $pdo->beginTransaction();

    // Lock selected scholarships in a consistent order.
    sort($ids, SORT_NUMERIC);

    $find = $pdo->prepare(
        'SELECT scholarship_id, scholarship_name,
                status, verification_date
         FROM scholarships
         WHERE scholarship_id = ?
         FOR UPDATE'
    );

    $archive = $pdo->prepare(
        'UPDATE scholarships
         SET status = ?
         WHERE scholarship_id = ?'
    );

    $verify = $pdo->prepare(
        'UPDATE scholarships
         SET verification_date = CURRENT_DATE()
         WHERE scholarship_id = ?'
    );

    $audit = $pdo->prepare(
        'INSERT INTO audit_log
            (user_id, action, entity_type, entity_id, details)
         VALUES (?, ?, ?, ?, ?)'
    );

    // Validate all selected records before changing any of them.
    $scholarships = [];

    foreach ($ids as $id) {
        $find->execute([$id]);
        $scholarship = $find->fetch();

        if (!$scholarship) {
            throw new RuntimeException(
                'One of the selected scholarships does not exist.'
            );
        }

        if ($scholarship['status'] !== 'active') {
            throw new RuntimeException(
                'Bulk actions can only be applied to active scholarships.'
            );
        }

        $scholarships[] = $scholarship;
    }

    // Apply the selected action to every validated scholarship.
    foreach ($scholarships as $scholarship) {
        $id = (int) $scholarship['scholarship_id'];

        if ($action === 'archive') {
            $archive->execute(['archived', $id]);
        } else {
            $verify->execute([$id]);
        }

        $details = [
            'scholarship_name' => $scholarship['scholarship_name'],
            'bulk_action' => true,
            'development_mode' => true
        ];

        if ($action === 'archive') {
            $details['previous_status'] = 'active';
            $details['new_status'] = 'archived';
        } else {
            $details['previous_verification_date'] =
                $scholarship['verification_date'];
            $details['new_verification_date'] = date('Y-m-d');
        }

        $audit->execute([
            current_user_id(),
            $action,
            'scholarship',
            $id,
            json_encode($details, JSON_THROW_ON_ERROR)
        ]);
    }

    $pdo->commit();

    header('Location: /admin/scholarships.php');
    exit;

} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Bulk scholarship action failed: ' . $error->getMessage());

    http_response_code(400);

    if ($error instanceof RuntimeException) {
        exit(e($error->getMessage()));
    }

    exit('Bulk action failed. No scholarships were changed.');
}
