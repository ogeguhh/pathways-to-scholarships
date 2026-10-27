<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';

/*
 * Iteration 4 - Requirement 5
 * Development-only scholarship verification.
 *
 * Authentication is scheduled for Iteration 7.
 * This endpoint must not be exposed publicly.
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Temporary development administrator.
$developmentAdminId = 1;

// Restrict this temporary endpoint to local development.
$host = strtolower($_SERVER['HTTP_HOST'] ?? '');
$remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';

$allowedHosts = ['localhost', 'localhost:80', 'localhost:8001', '127.0.0.1', '127.0.0.1:80', '127.0.0.1:8001'];
$allowedAddresses = ['127.0.0.1', '::1'];

if (
    !in_array($host, $allowedHosts, true) ||
    !in_array($remoteAddress, $allowedAddresses, true)
) {
    http_response_code(403);
    exit('Development-only endpoint.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

// Validate the existing CSRF token.
if (
    !isset($_POST['csrf_token']) ||
    !is_string($_POST['csrf_token']) ||
    !verify_csrf($_POST['csrf_token'])
) {
    http_response_code(403);
    exit('Invalid CSRF token.');
}

$submittedIds = $_POST['scholarship_ids'] ?? [];

if (!is_array($submittedIds)) {
    http_response_code(400);
    exit('Invalid scholarship selection.');
}

$ids = [];

foreach ($submittedIds as $submittedId) {
    if (
        !is_string($submittedId) &&
        !is_int($submittedId)
    ) {
        http_response_code(400);
        exit('Invalid scholarship ID.');
    }

    $id = filter_var(
        $submittedId,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($id === false) {
        http_response_code(400);
        exit('Invalid scholarship ID.');
    }

    $ids[$id] = $id;
}

$ids = array_values($ids);

if (count($ids) === 0) {
    http_response_code(400);
    exit('No scholarships selected.');
}

if (count($ids) > 1000) {
    http_response_code(400);
    exit('Too many scholarships selected.');
}

try {
    $pdo->beginTransaction();

    $select = $pdo->prepare("
        SELECT scholarship_id
        FROM scholarships
        WHERE scholarship_id = :id
          AND status = 'active'
          AND verification_date IS NULL
        FOR UPDATE
    ");

    $update = $pdo->prepare("
        UPDATE scholarships
        SET verification_date = CURRENT_DATE(),
            updated_at = CURRENT_TIMESTAMP,
            updated_by = :admin_id
        WHERE scholarship_id = :id
          AND status = 'active'
          AND verification_date IS NULL
    ");

    $audit = $pdo->prepare("
        INSERT INTO audit_log (
            user_id,
            action_type,
            entity_type,
            entity_id,
            action_details,
            created_at
        )
        VALUES (
            :user_id,
            'MARK_VERIFIED',
            'scholarship',
            :entity_id,
            :details,
            CURRENT_TIMESTAMP
        )
    ");

    $verifiedCount = 0;

    foreach ($ids as $id) {
        $select->execute([':id' => $id]);

        if (!$select->fetch(PDO::FETCH_ASSOC)) {
            continue;
        }

        $update->execute([
            ':admin_id' => $developmentAdminId,
            ':id' => $id
        ]);

        if ($update->rowCount() !== 1) {
            throw new RuntimeException(
                'Unable to update scholarship ' . $id
            );
        }

        $audit->execute([
            ':user_id' => $developmentAdminId,
            ':entity_id' => $id,
            ':details' => json_encode([
                'action' => 'Scholarship marked verified',
                'verification_date' => $pdo->query('SELECT CURRENT_DATE()')->fetchColumn(),
                'development_mode' => true
            ], JSON_THROW_ON_ERROR)
        ]);

        $verifiedCount++;
    }

    $pdo->commit();

    $_SESSION['verification_message'] =
        $verifiedCount . ' scholarship(s) marked verified.';

    header('Location: scholarships.php', true, 303);
    exit;

} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($error->getMessage());

    http_response_code(500);
    exit('Verification failed. Check the PHP error log.');
}
