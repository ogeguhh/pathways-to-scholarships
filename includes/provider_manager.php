<?php
declare(strict_types=1);

require_once __DIR__ . '/audit_logger.php';

function provider_list(PDO $pdo, string $search = ''): array
{
    $sql = "SELECT p.provider_id, p.provider_name, p.provider_type_id,
                   pt.provider_type_name, COUNT(sc.scholarship_id) AS scholarship_count
            FROM providers AS p
            LEFT JOIN provider_types AS pt
                ON pt.provider_type_id = p.provider_type_id
            LEFT JOIN scholarships AS sc
                ON sc.provider_id = p.provider_id";
    $parameters = [];
    if ($search !== '') {
        $sql .= ' WHERE p.provider_name LIKE :name_search
                  OR pt.provider_type_name LIKE :type_search
                  OR CAST(p.provider_id AS CHAR) = :id_search';
        $parameters = [
            ':name_search' => '%' . $search . '%',
            ':type_search' => '%' . $search . '%',
            ':id_search' => $search,
        ];
    }
    $sql .= ' GROUP BY p.provider_id, p.provider_name, p.provider_type_id, pt.provider_type_name
              ORDER BY p.provider_name, p.provider_id';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($parameters);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function provider_types(PDO $pdo): array
{
    return $pdo->query(
        'SELECT provider_type_id, provider_type_name
         FROM provider_types
         ORDER BY provider_type_name'
    )->fetchAll(PDO::FETCH_ASSOC);
}

function provider_merge_preview(PDO $pdo, int $keepId, int $duplicateId): array
{
    if ($keepId < 1 || $duplicateId < 1 || $keepId === $duplicateId) {
        throw new InvalidArgumentException('Choose two different providers.');
    }

    $stmt = $pdo->prepare(
        "SELECT p.provider_id, p.provider_name, pt.provider_type_name,
                COUNT(sc.scholarship_id) AS scholarship_count
         FROM providers AS p
         LEFT JOIN provider_types AS pt
            ON pt.provider_type_id = p.provider_type_id
         LEFT JOIN scholarships AS sc
            ON sc.provider_id = p.provider_id
         WHERE p.provider_id IN (:keep_id, :duplicate_id)
         GROUP BY p.provider_id, p.provider_name, pt.provider_type_name
         ORDER BY p.provider_id"
    );
    $stmt->execute([
        ':keep_id' => $keepId,
        ':duplicate_id' => $duplicateId,
    ]);
    $providers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($providers) !== 2) {
        throw new InvalidArgumentException('One or both selected providers no longer exist.');
    }

    $byId = [];
    foreach ($providers as $provider) {
        $byId[(int) $provider['provider_id']] = $provider;
    }

    return [
        'keep' => $byId[$keepId],
        'duplicate' => $byId[$duplicateId],
    ];
}

function edit_provider(PDO $pdo, int $providerId, string $name, ?int $typeId): array
{
    $name = trim($name);
    if ($providerId < 1 || $name === '' || mb_strlen($name) > 200) {
        throw new InvalidArgumentException('Provider name must be between 1 and 200 characters.');
    }

    if ($typeId !== null) {
        $typeStmt = $pdo->prepare('SELECT 1 FROM provider_types WHERE provider_type_id = ?');
        $typeStmt->execute([$typeId]);
        if (!$typeStmt->fetchColumn()) {
            throw new InvalidArgumentException('Choose a valid provider type.');
        }
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'SELECT provider_id, provider_name, provider_type_id
             FROM providers WHERE provider_id = ? FOR UPDATE'
        );
        $stmt->execute([$providerId]);
        $before = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$before) {
            throw new InvalidArgumentException('Provider not found.');
        }

        $duplicateStmt = $pdo->prepare(
            'SELECT provider_id
             FROM providers
             WHERE provider_id <> :provider_id
               AND LOWER(TRIM(provider_name)) = LOWER(TRIM(:provider_name))
             LIMIT 1'
        );
        $duplicateStmt->execute([
            ':provider_id' => $providerId,
            ':provider_name' => $name,
        ]);
        if ($duplicateStmt->fetchColumn() !== false) {
            throw new InvalidArgumentException(
                'A provider with that name already exists. Use the merge tool to combine duplicates.'
            );
        }

        $changes = [];
        if ((string) $before['provider_name'] !== $name) {
            $changes['provider_name'] = [
                'before' => (string) $before['provider_name'],
                'after' => $name,
            ];
        }
        $oldTypeId = $before['provider_type_id'] === null
            ? null
            : (int) $before['provider_type_id'];
        if ($oldTypeId !== $typeId) {
            $changes['provider_type_id'] = [
                'before' => $oldTypeId,
                'after' => $typeId,
            ];
        }

        if ($changes) {
            $update = $pdo->prepare(
                'UPDATE providers
                 SET provider_name = :provider_name,
                     provider_type_id = :provider_type_id
                 WHERE provider_id = :provider_id'
            );
            $update->execute([
                ':provider_name' => $name,
                ':provider_type_id' => $typeId,
                ':provider_id' => $providerId,
            ]);
            log_audit($pdo, 'provider.edit', 'provider', $providerId, [
                'changed_fields' => $changes,
            ]);
        }

        $pdo->commit();
        return $changes;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function merge_providers(PDO $pdo, int $keepId, int $duplicateId): array
{
    if ($keepId < 1 || $duplicateId < 1 || $keepId === $duplicateId) {
        throw new InvalidArgumentException('Choose two different providers.');
    }

    $pdo->beginTransaction();
    try {
        $lock = $pdo->prepare(
            'SELECT provider_id FROM providers
             WHERE provider_id IN (?, ?)
             ORDER BY provider_id
             FOR UPDATE'
        );
        $lock->execute([$keepId, $duplicateId]);
        if (count($lock->fetchAll(PDO::FETCH_COLUMN)) !== 2) {
            throw new InvalidArgumentException('One or both selected providers no longer exist.');
        }

        $references = $pdo->query(
            "SELECT TABLE_NAME, COLUMN_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE REFERENCED_TABLE_SCHEMA = DATABASE()
               AND REFERENCED_TABLE_NAME = 'providers'
               AND REFERENCED_COLUMN_NAME = 'provider_id'
               AND NOT (TABLE_NAME = 'scholarships' AND COLUMN_NAME = 'provider_id')"
        )->fetchAll(PDO::FETCH_ASSOC);
        if ($references) {
            throw new RuntimeException(
                'The provider has additional database references and cannot be merged safely.'
            );
        }

        $preview = provider_merge_preview($pdo, $keepId, $duplicateId);
        $update = $pdo->prepare(
            'UPDATE scholarships SET provider_id = :keep_id WHERE provider_id = :duplicate_id'
        );
        $update->execute([
            ':keep_id' => $keepId,
            ':duplicate_id' => $duplicateId,
        ]);
        $reassigned = $update->rowCount();

        $delete = $pdo->prepare('DELETE FROM providers WHERE provider_id = ?');
        $delete->execute([$duplicateId]);
        if ($delete->rowCount() !== 1) {
            throw new RuntimeException('The duplicate provider could not be removed.');
        }

        log_audit($pdo, 'provider.merge', 'provider', $keepId, [
            'original_provider_id' => $duplicateId,
            'original_provider_name' => (string) $preview['duplicate']['provider_name'],
            'retained_provider_id' => $keepId,
            'retained_provider_name' => (string) $preview['keep']['provider_name'],
            'reassigned_scholarships' => $reassigned,
        ]);

        $pdo->commit();
        return [
            'keep_name' => (string) $preview['keep']['provider_name'],
            'duplicate_name' => (string) $preview['duplicate']['provider_name'],
            'reassigned' => $reassigned,
        ];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}
