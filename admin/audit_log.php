<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';
require_admin_access();
require_once __DIR__ . '/../includes/db.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    throw new RuntimeException('Database connection unavailable.');
}

$errors = [];
$queryValue = $_GET['q'] ?? '';
$query = is_string($queryValue) ? trim($queryValue) : '';
$query = mb_substr($query, 0, 100);
$actionValue = $_GET['action'] ?? '';
$action = is_string($actionValue) ? trim($actionValue) : '';
$administrator = filter_input(
    INPUT_GET,
    'administrator',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);
$fromValue = $_GET['from'] ?? '';
$from = is_string($fromValue) ? trim($fromValue) : '';
$toValue = $_GET['to'] ?? '';
$to = is_string($toValue) ? trim($toValue) : '';
$sortValue = $_GET['sort'] ?? 'newest';
$sort = is_string($sortValue) ? $sortValue : 'newest';
$sort = in_array($sort, ['newest', 'oldest'], true) ? $sort : 'newest';
$page = filter_input(
    INPUT_GET,
    'page',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);
$page = $page === false || $page === null ? 1 : $page;
$pageSize = 25;

$validDate = static function (string $value): bool {
    if ($value === '') {
        return true;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
};

if (!$validDate($from) || !$validDate($to) || ($from !== '' && $to !== '' && $from > $to)) {
    http_response_code(400);
    $errors[] = 'Enter valid dates, and make sure the start date is not after the end date.';
}
if ($administrator === false) {
    http_response_code(400);
    $errors[] = 'Choose a valid administrator.';
}

$actions = [];
$administrators = [];
$rows = [];
$totalPages = 1;
$totalRows = 0;

try {
    $actions = $pdo->query(
        'SELECT DISTINCT action_type FROM audit_log ORDER BY action_type'
    )->fetchAll(PDO::FETCH_COLUMN);
    $administrators = $pdo->query(
        "SELECT DISTINCT u.user_id,
                COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))), ''), u.email) AS display_name
         FROM audit_log AS a
         JOIN users AS u ON u.user_id = a.user_id
         JOIN roles AS r ON r.role_id = u.role_id
         WHERE r.role_name IN ('Administrator', 'Super-admin')
         ORDER BY display_name"
    )->fetchAll(PDO::FETCH_ASSOC);

    $where = [];
    $parameters = [];
    if ($query !== '') {
        $where[] = '(a.action_type LIKE :search_action
                     OR a.entity_type LIKE :search_entity
                     OR a.action_details LIKE :search_details
                     OR CAST(a.entity_id AS CHAR) = :search_entity_id
                     OR u.email LIKE :search_email
                     OR CONCAT(COALESCE(u.first_name, \'\'), \' \', COALESCE(u.last_name, \'\')) LIKE :search_name)';
        $like = '%' . $query . '%';
        $parameters += [
            ':search_action' => $like,
            ':search_entity' => $like,
            ':search_details' => $like,
            ':search_entity_id' => $query,
            ':search_email' => $like,
            ':search_name' => $like,
        ];
    }
    if ($action !== '') {
        if (!in_array($action, $actions, true)) {
            http_response_code(400);
            $errors[] = 'Choose a valid action type.';
        } else {
            $where[] = 'a.action_type = :action_type';
            $parameters[':action_type'] = $action;
        }
    }
    if (is_int($administrator) && $administrator > 0) {
        $where[] = 'a.user_id = :administrator_id';
        $parameters[':administrator_id'] = $administrator;
    }
    if ($from !== '') {
        $where[] = 'a.created_at >= :from_date';
        $parameters[':from_date'] = $from . ' 00:00:00';
    }
    if ($to !== '') {
        $where[] = 'a.created_at < :to_date';
        $parameters[':to_date'] = (new DateTimeImmutable($to))->modify('+1 day')->format('Y-m-d 00:00:00');
    }

    if (!$errors) {
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $count = $pdo->prepare(
            'SELECT COUNT(*) FROM audit_log AS a
             LEFT JOIN users AS u ON u.user_id = a.user_id' . $whereSql
        );
        $count->execute($parameters);
        $totalRows = (int) $count->fetchColumn();
        $totalPages = max(1, (int) ceil($totalRows / $pageSize));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $pageSize;
        $direction = $sort === 'oldest' ? 'ASC' : 'DESC';
        $list = $pdo->prepare(
            "SELECT a.audit_id, a.user_id, a.action_type, a.entity_type, a.entity_id,
                    a.action_details, a.created_at, u.email,
                    COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))), ''), u.email) AS administrator_name
             FROM audit_log AS a
             LEFT JOIN users AS u ON u.user_id = a.user_id" .
                $whereSql .
                " ORDER BY a.created_at $direction, a.audit_id $direction
                  LIMIT :limit OFFSET :offset"
        );
        foreach ($parameters as $key => $value) {
            $list->bindValue($key, $value);
        }
        $list->bindValue(':limit', $pageSize, PDO::PARAM_INT);
        $list->bindValue(':offset', $offset, PDO::PARAM_INT);
        $list->execute();
        $rows = $list->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $exception) {
    error_log('Audit log page failed: ' . $exception->getMessage());
    http_response_code(500);
    $errors[] = 'Unable to load audit records right now.';
}

$pageQuery = $_GET;
unset($pageQuery['page']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Audit Log | Pathways to Scholarships</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<main class="container py-5">
    <nav class="mb-4" aria-label="Administrator navigation">
        <a href="../index.php">Home</a> |
        <a href="providers.php">Manage Providers</a> |
        <a href="audit_log.php" aria-current="page">Audit Log</a>
    </nav>
    <h1>Audit log</h1>
    <p class="text-muted">Read-only record of administrative actions. Newest actions appear first.</p>

    <?php foreach ($errors as $error): ?>
        <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
    <?php endforeach; ?>

    <form method="get" action="audit_log.php" class="card card-body mb-4">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="audit-search">Search</label>
                <input class="form-control" id="audit-search" name="q" maxlength="100"
                       value="<?= e($query) ?>" placeholder="Action, entity, ID, administrator, details">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="action-filter">Action</label>
                <select class="form-select" id="action-filter" name="action">
                    <option value="">All actions</option>
                    <?php foreach ($actions as $availableAction): ?>
                        <option value="<?= e($availableAction) ?>" <?= $action === $availableAction ? 'selected' : '' ?>>
                            <?= e($availableAction) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="administrator-filter">Administrator</label>
                <select class="form-select" id="administrator-filter" name="administrator">
                    <option value="">All administrators</option>
                    <?php foreach ($administrators as $user): ?>
                        <option value="<?= (int) $user['user_id'] ?>"
                            <?= $administrator === (int) $user['user_id'] ? 'selected' : '' ?>>
                            <?= e($user['display_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-4 col-md-3">
                <label class="form-label" for="date-from">From</label>
                <input class="form-control" id="date-from" type="date" name="from" value="<?= e($from) ?>">
            </div>
            <div class="col-sm-4 col-md-3">
                <label class="form-label" for="date-to">To</label>
                <input class="form-control" id="date-to" type="date" name="to" value="<?= e($to) ?>">
            </div>
            <div class="col-sm-4 col-md-3">
                <label class="form-label" for="audit-sort">Timestamp order</label>
                <select class="form-select" id="audit-sort" name="sort">
                    <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest first</option>
                    <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest first</option>
                </select>
            </div>
            <div class="col-auto align-self-end">
                <button class="btn btn-primary" type="submit">Apply filters</button>
                <a class="btn btn-outline-secondary" href="audit_log.php">Clear</a>
            </div>
        </div>
    </form>

    <p><?= number_format($totalRows) ?> record(s); page <?= (int) $page ?> of <?= (int) $totalPages ?>.</p>
    <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle bg-white">
            <thead>
            <tr>
                <th scope="col">Audit ID</th>
                <th scope="col">Administrator</th>
                <th scope="col">Action</th>
                <th scope="col">Entity type</th>
                <th scope="col">Entity ID</th>
                <th scope="col">Action details</th>
                <th scope="col">Timestamp</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <?php
                $details = json_decode((string) $row['action_details'], true);
                $detailsText = is_array($details)
                    ? json_encode($details, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                    : (string) $row['action_details'];
                ?>
                <tr>
                    <td><?= (int) $row['audit_id'] ?></td>
                    <td>
                        <?= e($row['administrator_name'] ?? 'Unknown administrator') ?>
                        <?php if (!empty($row['email'])): ?><small class="d-block text-muted"><?= e($row['email']) ?></small><?php endif; ?>
                    </td>
                    <td><?= e($row['action_type']) ?></td>
                    <td><?= e($row['entity_type'] ?? '') ?></td>
                    <td><?= $row['entity_id'] === null ? '—' : (int) $row['entity_id'] ?></td>
                    <td>
                        <details>
                            <summary>View details</summary>
                            <pre class="mt-2 mb-0 text-wrap"><?= e($detailsText) ?></pre>
                        </details>
                    </td>
                    <td><?= e($row['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr><td colspan="7" class="text-center">No audit records match these filters.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <nav aria-label="Audit log pages">
            <ul class="pagination">
                <?php if ($page > 1): ?>
                    <li class="page-item"><a class="page-link" href="?<?= e(http_build_query($pageQuery + ['page' => $page - 1])) ?>">Previous</a></li>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                    <li class="page-item"><a class="page-link" href="?<?= e(http_build_query($pageQuery + ['page' => $page + 1])) ?>">Next</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>
</main>
</body>
</html>
