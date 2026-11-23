<?php
declare(strict_types=1);

// Pathways to Scholarships
// Iteration 7: Administrative Audit Log Viewer

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/includes/db.php';
require_once $projectRoot . '/includes/security.php';

// Only Super-admins can view administrative audit records.
require_role('Super-admin');

$pdo = getDBConnection();

// Retrieve the selected filters from the URL.
$userId = $_GET['user_id'] ?? '';
$action = $_GET['action'] ?? '';
$entityType = $_GET['entity_type'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// Reject malformed filter parameters.
foreach (
    [$userId, $action, $entityType, $dateFrom, $dateTo]
    as $filter
) {
    if (!is_string($filter)) {
        http_response_code(400);
        exit('Invalid filter.');
    }
}

// Retrieve available users for the filter dropdown.
$users = $pdo->query(
    'SELECT user_id, full_name, email
     FROM users
     ORDER BY full_name'
)->fetchAll(PDO::FETCH_ASSOC);

// Retrieve existing action types from the audit log.
$actions = $pdo->query(
    'SELECT DISTINCT action
     FROM audit_log
     ORDER BY action'
)->fetchAll(PDO::FETCH_COLUMN);

// Retrieve existing entity types.
$entityTypes = $pdo->query(
    'SELECT DISTINCT entity_type
     FROM audit_log
     ORDER BY entity_type'
)->fetchAll(PDO::FETCH_COLUMN);

// Build the query dynamically based on selected filters.
$conditions = [];
$parameters = [];
$error = '';

function validAuditDate(string $value): bool
{
    $date = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $value
    );

    return $date !== false
        && $date->format('Y-m-d') === $value;
}

// Filter by the user who performed the action.
if ($userId === 'unknown') {

    $conditions[] = 'a.user_id IS NULL';

} elseif ($userId !== '') {

    $validatedId = filter_var(
        $userId,
        FILTER_VALIDATE_INT
    );

    if (!$validatedId || $validatedId < 1) {
        $error = 'Invalid user filter.';
    } else {
        $conditions[] = 'a.user_id = ?';
        $parameters[] = $validatedId;
    }
}

// Filter by action type.
if ($action !== '') {

    $conditions[] = 'a.action = ?';
    $parameters[] = $action;
}

// Filter by the affected entity.
if ($entityType !== '') {

    $conditions[] = 'a.entity_type = ?';
    $parameters[] = $entityType;
}

// Filter by the beginning of the selected date range.
if ($dateFrom !== '') {

    if (!validAuditDate($dateFrom)) {
        $error = 'Invalid starting date.';
    } else {
        $conditions[] = 'a.created_at >= ?';
        $parameters[] = $dateFrom . ' 00:00:00';
    }
}

// Include the entire ending date.
if ($dateTo !== '') {

    if (!validAuditDate($dateTo)) {
        $error = 'Invalid ending date.';
    } else {

        $nextDay = new DateTimeImmutable($dateTo);

        $nextDay = $nextDay->modify('+1 day');

        $conditions[] = 'a.created_at < ?';

        $parameters[] = $nextDay->format(
            'Y-m-d 00:00:00'
        );
    }
}

if (
    $dateFrom !== '' &&
    $dateTo !== '' &&
    $dateFrom > $dateTo
) {
    $error = 'The starting date cannot be after the ending date.';
}

// LEFT JOIN keeps historical records even when no user is assigned.
$sql = 'SELECT
            a.audit_id,
            a.user_id,
            a.action,
            a.entity_type,
            a.entity_id,
            a.details,
            a.created_at,
            u.full_name,
            u.email
        FROM audit_log a
        LEFT JOIN users u ON a.user_id = u.user_id';

if ($conditions !== []) {

    $sql .= ' WHERE ' . implode(
        ' AND ',
        $conditions
    );
}

$sql .= ' ORDER BY a.created_at DESC, a.audit_id DESC
          LIMIT 500';

$records = [];

if ($error === '') {

    $stmt = $pdo->prepare($sql);

    $stmt->execute($parameters);

    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Convert stored JSON into readable, formatted text.
function formatAuditDetails(?string $details): string
{
    if ($details === null || $details === '') {
        return 'No additional details.';
    }

    $decoded = json_decode($details, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return $details;
    }

    $formatted = json_encode(
        $decoded,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
    );

    return $formatted !== false
        ? $formatted
        : $details;
}

$pageTitle = 'Audit Log | Pathways to Scholarships';

require_once $projectRoot . '/includes/header.php';
require_once $projectRoot . '/includes/nav.php';
?>

<main class="container py-4">

    <div class="mb-4">

        <h1 class="h2">Administrative Audit Log</h1>

        <p class="text-muted">

            Review administrative changes to user accounts,
            reference data, scholarships, providers,
            and import batches.

        </p>

    </div>

    <?php if ($error !== ''): ?>

        <div class="alert alert-danger">
            <?= e($error) ?>
        </div>

    <?php endif; ?>

    <div class="card shadow-sm mb-4">

        <div class="card-body">

            <form method="GET"
                  action="/admin/audit_log.php">

                <div class="row g-3">

                    <div class="col-md-4">

                        <label for="user_id"
                               class="form-label">
                            User
                        </label>

                        <select
                            id="user_id"
                            name="user_id"
                            class="form-select"
                        >

                            <option value="">All Users</option>

                            <option
                                value="unknown"
                                <?= $userId === 'unknown'
                                    ? 'selected' : '' ?>
                            >
                                Unassigned / Historical
                            </option>

                            <?php foreach ($users as $user): ?>

                                <option
                                    value="<?= (int) $user['user_id'] ?>"
                                    <?= $userId === (string) $user['user_id']
                                        ? 'selected' : '' ?>
                                >

                                    <?= e($user['full_name']) ?>
                                    (<?= e($user['email']) ?>)

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="col-md-4">

                        <label for="action"
                               class="form-label">
                            Action
                        </label>

                        <select
                            id="action"
                            name="action"
                            class="form-select"
                        >

                            <option value="">All Actions</option>

                            <?php foreach ($actions as $item): ?>

                                <option
                                    value="<?= e($item) ?>"
                                    <?= $action === $item
                                        ? 'selected' : '' ?>
                                >
                                    <?= e(ucfirst($item)) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="col-md-4">

                        <label for="entity_type"
                               class="form-label">
                            Entity Type
                        </label>

                        <select
                            id="entity_type"
                            name="entity_type"
                            class="form-select"
                        >

                            <option value="">All Entity Types</option>

                            <?php foreach ($entityTypes as $item): ?>

                                <option
                                    value="<?= e($item) ?>"
                                    <?= $entityType === $item
                                        ? 'selected' : '' ?>
                                >

                                    <?= e(ucwords(
                                        str_replace('_', ' ', $item)
                                    )) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="col-md-4">

                        <label for="date_from"
                               class="form-label">
                            Date From
                        </label>

                        <input
                            type="date"
                            id="date_from"
                            name="date_from"
                            class="form-control"
                            value="<?= e($dateFrom) ?>"
                        >

                    </div>

                    <div class="col-md-4">

                        <label for="date_to"
                               class="form-label">
                            Date To
                        </label>

                        <input
                            type="date"
                            id="date_to"
                            name="date_to"
                            class="form-control"
                            value="<?= e($dateTo) ?>"
                        >

                    </div>

                    <div class="col-md-4 d-flex
                                align-items-end gap-2">

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            Apply Filters
                        </button>

                        <a
                            href="/admin/audit_log.php"
                            class="btn btn-outline-secondary"
                        >
                            Clear
                        </a>

                    </div>

                </div>

            </form>

        </div>

    </div>

    <div class="card shadow-sm">

        <div class="card-header bg-white">

            <strong>Audit Records</strong>

            <span class="text-muted">
                (<?= count($records) ?> displayed)
            </span>

        </div>

        <div class="table-responsive">

            <table class="table table-striped
                          align-middle mb-0">

                <thead class="table-light">

                    <tr>
                        <th>ID</th>
                        <th>Date</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Entity Type</th>
                        <th>Entity ID</th>
                        <th>Details</th>
                    </tr>

                </thead>

                <tbody>

                    <?php foreach ($records as $record): ?>

                        <?php
                        // Assign a color based on the action type.
                        $badgeClass = match ($record['action']) {
                            'create' => 'bg-success',
                            'update' => 'bg-primary',
                            'delete' => 'bg-danger',
                            'rollback' => 'bg-warning text-dark',
                            default => 'bg-secondary'
                        };
                        ?>

                        <tr>

                            <td>
                                <?= (int) $record['audit_id'] ?>
                            </td>

                            <td style="min-width: 150px;">
                                <?= e($record['created_at']) ?>
                            </td>

                            <td style="min-width: 180px;">

                                <?php if ($record['full_name'] !== null): ?>

                                    <strong>
                                        <?= e($record['full_name']) ?>
                                    </strong>

                                    <div class="small text-muted">
                                        <?= e($record['email']) ?>
                                    </div>

                                <?php else: ?>

                                    <span class="text-muted">
                                        Unassigned / Historical
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>

                                <span class="badge <?= e($badgeClass) ?>">

                                    <?= e(ucfirst($record['action'])) ?>

                                </span>

                            </td>

                            <td>

                                <?= e(ucwords(
                                    str_replace(
                                        '_',
                                        ' ',
                                        $record['entity_type']
                                    )
                                )) ?>

                            </td>

                            <td>

                                <?= $record['entity_id'] !== null
                                    ? (int) $record['entity_id']
                                    : '—' ?>

                            </td>

                            <td>

                                <!-- Expand details without leaving the page. -->
                                <details>

                                    <summary class="text-primary"
                                             style="cursor: pointer;">
                                        View Details
                                    </summary>

                                    <pre class="small bg-light border rounded p-2 mt-2 mb-0"
                                         style="white-space: pre-wrap; overflow-wrap: anywhere; min-width: 220px; max-width: 450px;"><?= e(formatAuditDetails($record['details'])) ?></pre>

                                </details>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    <?php if ($records === []): ?>

                        <tr>

                            <td colspan="7"
                                class="text-center text-muted py-4">

                                No matching audit records found.

                            </td>

                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

        <div class="card-footer bg-white small text-muted">

            Showing up to 500 matching audit records,
            with the newest records first.

            Audit records are read-only.

        </div>

    </div>

</main>

<?php
require_once $projectRoot . '/includes/footer.php';
?>
