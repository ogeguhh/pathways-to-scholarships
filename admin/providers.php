<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';
require_admin_access();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/provider_manager.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    throw new RuntimeException('Database connection unavailable.');
}

$errors = [];
$success = '';
$searchValue = $_GET['q'] ?? '';
$search = is_string($searchValue) ? trim($searchValue) : '';
$search = mb_substr($search, 0, 100);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        $errors[] = 'Your security token is invalid. Reload the page and try again.';
    } elseif (($_POST['action'] ?? '') !== 'edit') {
        http_response_code(400);
        $errors[] = 'Invalid provider action.';
    } else {
        $providerIdValue = $_POST['provider_id'] ?? null;
        $providerId = is_string($providerIdValue)
            ? filter_var($providerIdValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            : false;
        $typeValue = $_POST['provider_type_id'] ?? '';
        $typeId = $typeValue === ''
            ? null
            : (is_string($typeValue)
                ? filter_var($typeValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
                : false);

        if ($providerId === false || ($typeValue !== '' && $typeId === false)) {
            http_response_code(400);
            $errors[] = 'Choose a valid provider and provider type.';
        } elseif (!is_string($_POST['provider_name'] ?? null)) {
            http_response_code(400);
            $errors[] = 'Enter a valid provider name.';
        } else {
            try {
                $changes = edit_provider(
                    $pdo,
                    $providerId,
                    $_POST['provider_name'],
                    $typeId
                );
                $success = $changes
                    ? 'Provider details were saved.'
                    : 'No provider details changed.';
            } catch (InvalidArgumentException $exception) {
                $errors[] = $exception->getMessage();
            } catch (Throwable $exception) {
                error_log('Provider edit failed: ' . $exception->getMessage());
                http_response_code(500);
                $errors[] = 'Unable to save provider details right now.';
            }
        }
    }
}

try {
    $providers = provider_list($pdo, $search);
    $types = provider_types($pdo);
} catch (Throwable $exception) {
    error_log('Provider list failed: ' . $exception->getMessage());
    http_response_code(500);
    $providers = [];
    $types = [];
    $errors[] = 'Unable to load providers right now.';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Providers | Pathways to Scholarships</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<main class="container py-5">
    <nav class="mb-4" aria-label="Administrator navigation">
        <a href="../index.php">Home</a> |
        <a href="providers.php" aria-current="page">Manage Providers</a> |
        <a href="audit_log.php">Audit Log</a> |
        <a href="import.php">Import Scholarships</a>
    </nav>
    <h1>Manage Providers</h1>
    <p class="text-muted">Correct provider details or merge duplicate records while preserving their scholarships.</p>

    <?php if ($success !== ''): ?>
        <div class="alert alert-success" role="status"><?= e($success) ?></div>
    <?php endif; ?>
    <?php foreach ($errors as $error): ?>
        <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
    <?php endforeach; ?>

    <form class="row g-2 mb-4" method="get" action="providers.php">
        <div class="col-sm-8 col-md-6">
            <label class="form-label" for="provider-search">Search providers</label>
            <input class="form-control" id="provider-search" name="q" value="<?= e($search) ?>"
                   maxlength="100" placeholder="Provider name, type, or ID">
        </div>
        <div class="col-auto align-self-end">
            <button class="btn btn-primary" type="submit">Search</button>
            <a class="btn btn-outline-secondary" href="providers.php">Clear</a>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle bg-white">
            <thead>
            <tr>
                <th scope="col">Provider ID</th>
                <th scope="col">Provider name</th>
                <th scope="col">Provider type</th>
                <th scope="col">Scholarships</th>
                <th scope="col">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($providers as $provider): ?>
                <tr>
                    <td><?= (int) $provider['provider_id'] ?></td>
                    <td>
                        <form id="provider-<?= (int) $provider['provider_id'] ?>" method="post" action="providers.php<?= $search !== '' ? '?q=' . rawurlencode($search) : '' ?>">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="provider_id" value="<?= (int) $provider['provider_id'] ?>">
                            <label class="visually-hidden" for="provider-name-<?= (int) $provider['provider_id'] ?>">Provider name</label>
                            <input class="form-control" id="provider-name-<?= (int) $provider['provider_id'] ?>"
                                   name="provider_name" maxlength="200" required
                                   value="<?= e($provider['provider_name']) ?>">
                        </form>
                    </td>
                    <td>
                        <label class="visually-hidden" for="provider-type-<?= (int) $provider['provider_id'] ?>">Provider type</label>
                        <select class="form-select" id="provider-type-<?= (int) $provider['provider_id'] ?>"
                                name="provider_type_id" form="provider-<?= (int) $provider['provider_id'] ?>">
                            <option value="">Unspecified</option>
                            <?php foreach ($types as $type): ?>
                                <option value="<?= (int) $type['provider_type_id'] ?>"
                                    <?= (int) $provider['provider_type_id'] === (int) $type['provider_type_id'] ? 'selected' : '' ?>>
                                    <?= e($type['provider_type_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><?= (int) $provider['scholarship_count'] ?></td>
                    <td class="text-nowrap">
                        <button class="btn btn-sm btn-primary" type="submit" form="provider-<?= (int) $provider['provider_id'] ?>">Save</button>
                        <a class="btn btn-sm btn-outline-secondary"
                           href="provider_merge.php?duplicate_id=<?= (int) $provider['provider_id'] ?>">Merge</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$providers): ?>
                <tr><td colspan="5" class="text-center">No providers found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
</body>
</html>
