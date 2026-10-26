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
$preview = null;
$keepId = 0;
$duplicateId = 0;
$providers = [];

try {
    $providers = provider_list($pdo);
} catch (Throwable $exception) {
    error_log('Provider merge list failed: ' . $exception->getMessage());
    http_response_code(500);
    $errors[] = 'Unable to load providers right now.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        $errors[] = 'Your security token is invalid. Reload the page and try again.';
    } else {
        $keepId = filter_var(
            $_POST['keep_provider_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        ) ?: 0;
        $duplicateId = filter_var(
            $_POST['duplicate_provider_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        ) ?: 0;

        if ($keepId < 1 || $duplicateId < 1 || $keepId === $duplicateId) {
            http_response_code(400);
            $errors[] = 'Choose two different providers.';
        } elseif (($_POST['action'] ?? '') === 'preview') {
            try {
                $preview = provider_merge_preview($pdo, $keepId, $duplicateId);
            } catch (InvalidArgumentException $exception) {
                $errors[] = $exception->getMessage();
            } catch (Throwable $exception) {
                error_log('Provider merge preview failed: ' . $exception->getMessage());
                http_response_code(500);
                $errors[] = 'Unable to preview this provider merge right now.';
            }
        } elseif (($_POST['action'] ?? '') === 'merge') {
            if (($_POST['confirm_merge'] ?? '') !== 'yes') {
                $errors[] = 'Confirm the merge before continuing.';
            } else {
                try {
                    $merged = merge_providers($pdo, $keepId, $duplicateId);
                    $_SESSION['provider_merge_success'] = sprintf(
                        '%s was merged into %s. %d scholarship(s) reassigned.',
                        $merged['duplicate_name'],
                        $merged['keep_name'],
                        $merged['reassigned']
                    );
                    header('Location: provider_merge.php?merged=1', true, 303);
                    exit;
                } catch (InvalidArgumentException $exception) {
                    $errors[] = $exception->getMessage();
                } catch (Throwable $exception) {
                    error_log('Provider merge failed: ' . $exception->getMessage());
                    http_response_code(500);
                    $errors[] = 'Unable to merge these providers. No changes were saved.';
                }
            }
        } else {
            http_response_code(400);
            $errors[] = 'Invalid provider action.';
        }
    }
} else {
    $duplicateId = filter_input(
        INPUT_GET,
        'duplicate_id',
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    ) ?: 0;
}

$success = '';
if (($_GET['merged'] ?? '') === '1' && isset($_SESSION['provider_merge_success'])) {
    $success = (string) $_SESSION['provider_merge_success'];
    unset($_SESSION['provider_merge_success']);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Merge Providers | Pathways to Scholarships</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<main class="container py-5">
    <nav class="mb-4" aria-label="Administrator navigation">
        <a href="providers.php">Manage Providers</a> |
        <a href="audit_log.php">Audit Log</a>
    </nav>
    <h1>Merge duplicate providers</h1>
    <p class="text-muted">Select the provider record to keep and the duplicate record to merge into it.</p>

    <?php if ($success !== ''): ?>
        <div class="alert alert-success" role="status"><?= e($success) ?></div>
    <?php endif; ?>
    <?php foreach ($errors as $error): ?>
        <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
    <?php endforeach; ?>

    <form method="post" action="provider_merge.php" class="card card-body mb-4">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="preview">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="keep-provider">Provider to keep</label>
                <select class="form-select" id="keep-provider" name="keep_provider_id" required>
                    <option value="">Select provider</option>
                    <?php foreach ($providers as $provider): ?>
                        <option value="<?= (int) $provider['provider_id'] ?>"
                            <?= $keepId === (int) $provider['provider_id'] ? 'selected' : '' ?>>
                            #<?= (int) $provider['provider_id'] ?> — <?= e($provider['provider_name']) ?>
                            (<?= (int) $provider['scholarship_count'] ?> scholarships)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="duplicate-provider">Duplicate provider to merge</label>
                <select class="form-select" id="duplicate-provider" name="duplicate_provider_id" required>
                    <option value="">Select duplicate</option>
                    <?php foreach ($providers as $provider): ?>
                        <option value="<?= (int) $provider['provider_id'] ?>"
                            <?= $duplicateId === (int) $provider['provider_id'] ? 'selected' : '' ?>>
                            #<?= (int) $provider['provider_id'] ?> — <?= e($provider['provider_name']) ?>
                            (<?= (int) $provider['scholarship_count'] ?> scholarships)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <button class="btn btn-primary" type="submit">Preview merge</button>
            </div>
        </div>
    </form>

    <?php if ($preview !== null): ?>
        <section class="card card-body border-warning">
            <h2 class="h4">Confirm provider merge</h2>
            <p>The duplicate provider will be removed after its scholarships are reassigned. This cannot be undone.</p>
            <div class="table-responsive">
                <table class="table table-bordered bg-white">
                    <thead><tr><th scope="col">Role</th><th scope="col">Provider</th><th scope="col">Type</th><th scope="col">Scholarships</th></tr></thead>
                    <tbody>
                    <tr>
                        <th scope="row">Keep</th>
                        <td><?= e($preview['keep']['provider_name']) ?></td>
                        <td><?= e($preview['keep']['provider_type_name'] ?? 'Unspecified') ?></td>
                        <td><?= (int) $preview['keep']['scholarship_count'] ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Merge</th>
                        <td><?= e($preview['duplicate']['provider_name']) ?></td>
                        <td><?= e($preview['duplicate']['provider_type_name'] ?? 'Unspecified') ?></td>
                        <td><?= (int) $preview['duplicate']['scholarship_count'] ?></td>
                    </tr>
                    </tbody>
                </table>
            </div>
            <form method="post" action="provider_merge.php">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="merge">
                <input type="hidden" name="keep_provider_id" value="<?= (int) $keepId ?>">
                <input type="hidden" name="duplicate_provider_id" value="<?= (int) $duplicateId ?>">
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="confirm_merge" value="yes" id="confirm-merge" required>
                    <label class="form-check-label" for="confirm-merge">
                        I confirm that the duplicate provider should be merged into the provider being kept.
                    </label>
                </div>
                <button class="btn btn-danger" type="submit">Confirm merge</button>
            </form>
        </section>
    <?php endif; ?>
</main>
</body>
</html>
