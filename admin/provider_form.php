<?php
declare(strict_types=1);

/*
 * Pathways to Scholarships
 * Iteration 4 — Edit Provider
 *
 * Loads an existing provider and its type for editing.
 * The save handler will be created separately.
 */

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/includes/db.php';
require_once $projectRoot . '/includes/security.php';
require_once $projectRoot . '/includes/config.php';

// Keep administration restricted to local development
// until authentication is implemented in Iteration 7.
require_role('Administrator');

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    http_response_code(400);
    exit('Invalid provider ID.');
}

// Start the session before HTML output for CSRF protection.
ensure_session();

$pdo = getDBConnection();

// Load the provider being edited.
$stmt = $pdo->prepare(
    'SELECT provider_id, provider_name, provider_type_id
     FROM providers
     WHERE provider_id = ?'
);

$stmt->execute([$id]);
$provider = $stmt->fetch();

if (!$provider) {
    http_response_code(404);
    exit('Provider not found.');
}

// Load the existing provider types for the dropdown.
$types = $pdo->query(
    'SELECT provider_type_id, type_name
     FROM provider_types
     ORDER BY type_name'
)->fetchAll();

$pageTitle = 'Edit Provider | Pathways to Scholarships';

require_once $projectRoot . '/includes/header.php';
require_once $projectRoot . '/includes/nav.php';
?>

<main class="container py-5">

    <h1 class="mb-3">Edit Provider</h1>

    <p class="text-muted">
        Correct the provider's name or organization type.
    </p>

    <form action="/admin/provider_save.php" method="post">

        <input type="hidden"
               name="csrf_token"
               value="<?= e(csrf_token()) ?>">

        <input type="hidden"
               name="provider_id"
               value="<?= (int) $provider['provider_id'] ?>">

        <div class="mb-3">
            <label for="provider_name" class="form-label">
                Provider Name
            </label>

            <input type="text"
                   class="form-control"
                   id="provider_name"
                   name="provider_name"
                   maxlength="255"
                   value="<?= e($provider['provider_name']) ?>"
                   required>
        </div>

        <div class="mb-3">
            <label for="provider_type_id" class="form-label">
                Provider Type
            </label>

            <select class="form-select"
                    id="provider_type_id"
                    name="provider_type_id"
                    required>

                <?php foreach ($types as $type): ?>
                    <option
                        value="<?= (int) $type['provider_type_id'] ?>"
                        <?= (int) $type['provider_type_id'] ===
                            (int) $provider['provider_type_id']
                            ? 'selected' : '' ?>>

                        <?= e($type['type_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">
                Save Provider
            </button>

            <a href="/admin/providers.php"
               class="btn btn-outline-secondary">
                Cancel
            </a>
        </div>

    </form>

</main>

<?php require_once $projectRoot . '/includes/footer.php'; ?>
