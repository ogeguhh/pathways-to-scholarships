<?php
declare(strict_types=1);

require_once __DIR__ . '/security.php';

function audit_actor_id(PDO $pdo): ?int
{
    $userId = current_user_id();
    if ($userId !== null) {
        return $userId;
    }

    $stmt = $pdo->prepare(
        "SELECT u.user_id
         FROM users AS u
         JOIN roles AS r ON r.role_id = u.role_id
         WHERE u.email = :email
           AND u.account_status = 'active'
           AND r.role_name = 'Administrator'
         LIMIT 1"
    );
    $stmt->execute([':email' => 'admin@example.test']);
    $placeholderId = $stmt->fetchColumn();

    return $placeholderId === false ? null : (int) $placeholderId;
}

function audit_safe_details(array $details): array
{
    $safe = [];
    foreach ($details as $key => $value) {
        $key = (string) $key;
        if (preg_match('/password|token|secret|credential|authorization|cookie|session/i', $key)) {
            $safe[$key] = '[redacted]';
        } elseif (is_array($value)) {
            $safe[$key] = audit_safe_details($value);
        } elseif (is_scalar($value) || $value === null) {
            $safe[$key] = $value;
        } else {
            $safe[$key] = '[unsupported value]';
        }
    }

    return $safe;
}

function log_audit(
    PDO $pdo,
    string $actionType,
    string $entityType,
    ?int $entityId,
    array $details = []
): void {
    if ($actionType === '' || strlen($actionType) > 100 || strlen($entityType) > 100) {
        throw new InvalidArgumentException('Audit action and entity types must fit the schema.');
    }

    $json = json_encode(
        audit_safe_details($details),
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );

    $stmt = $pdo->prepare(
        'INSERT INTO audit_log
            (user_id, action_type, entity_type, entity_id, action_details)
         VALUES
            (:user_id, :action_type, :entity_type, :entity_id, :action_details)'
    );
    $stmt->execute([
        ':user_id' => audit_actor_id($pdo),
        ':action_type' => $actionType,
        ':entity_type' => $entityType,
        ':entity_id' => $entityId,
        ':action_details' => $json,
    ]);
}
