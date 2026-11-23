<?php
declare(strict_types=1);

// Pathways to Scholarships
// Iteration 7: Add and Edit Reference Values

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/includes/db.php';
require_once $projectRoot . '/includes/security.php';
require_once $projectRoot . '/includes/reference_service.php';

// Restrict reference management to Super-admins.
require_role('Super-admin');

$pdo = getDBConnection();

$table = $_GET['table'] ?? 'states';

$definitions = referenceDefinitions();

// Only approved reference tables can be selected.
if (!is_string($table) || !isset($definitions[$table])) {
    http_response_code(400);
    exit('Invalid reference data table.');
}

$definition = referenceDefinition($table);

// Determine whether we are adding or editing a record.
$isEdit = isset($_GET['id']);

$id = filter_var(
    $_GET['id'] ?? null,
    FILTER_VALIDATE_INT
);

$record = [];
$usageCount = 0;

if ($isEdit) {

    if (!$id || $id < 1) {
        http_response_code(400);
        exit('Invalid reference ID.');
    }

    $record = referenceFind($pdo, $table, $id);

    if (!$record) {
        http_response_code(404);
        exit('Reference value not found.');
    }

    $usageCount = referenceUsageCount(
        $pdo,
        $table,
        $id
    );

} else {

    // Initialize empty fields when creating a new record.
    foreach ($definition['fields'] as $column => $label) {
        $record[$column] = '';
    }
}

$heading = $isEdit
    ? 'Edit Reference Value'
    : 'Add Reference Value';

$pageTitle = $heading . ' | Pathways to Scholarships';

require_once $projectRoot . '/includes/header.php';
require_once $projectRoot . '/includes/nav.php';
?>

<main class="container py-4">

    <div class="row justify-content-center">

        <div class="col-lg-7">

            <div class="d-flex flex-wrap
                        justify-content-between
                        align-items-center gap-3 mb-4">

                <h1 class="h2 mb-0">
                    <?= e($heading) ?>
                </h1>

                <a
                    href="/admin/reference_data.php?table=<?= e($table) ?>"
                    class="btn btn-outline-secondary"
                >
                    Back to Reference Data
                </a>

            </div>

            <div class="card shadow-sm">

                <div class="card-body p-4">

                    <h2 class="h5">
                        <?= e($definition['label']) ?>
                    </h2>

                    <p class="text-muted">

                        <?= $isEdit
                            ? 'Update the selected reference value.'
                            : 'Create a new reference value.' ?>

                    </p>

                    <?php if ($isEdit && $usageCount > 0): ?>

                        <div class="alert alert-warning">

                            This value is currently used by
                            <?= $usageCount ?> related record(s).

                            Changes will affect how this value
                            appears throughout the website.

                        </div>

                    <?php endif; ?>

                    <form
                        method="POST"
                        action="/admin/reference_save.php"
                    >

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= e(csrf_token()) ?>"
                        >

                        <input
                            type="hidden"
                            name="table"
                            value="<?= e($table) ?>"
                        >

                        <input
                            type="hidden"
                            name="id"
                            value="<?= $isEdit ? (int) $id : '' ?>"
                        >

                        <!-- Generate fields using the selected
                             reference table definition. -->

                        <?php foreach ($definition['fields']
                            as $column => $label): ?>

                            <div class="mb-3">

                                <label
                                    for="<?= e($column) ?>"
                                    class="form-label"
                                >
                                    <?= e($label) ?>
                                </label>

                                <?php if ($column === 'grade_number'): ?>

                                    <input
                                        type="number"
                                        class="form-control"
                                        id="<?= e($column) ?>"
                                        name="<?= e($column) ?>"
                                        value="<?= e((string) $record[$column]) ?>"
                                        min="6"
                                        max="12"
                                        step="1"
                                        required
                                    >

                                    <div class="form-text">
                                        Enter a grade between 6 and 12.
                                    </div>

                                <?php elseif ($column === 'state_code'): ?>

                                    <input
                                        type="text"
                                        class="form-control"
                                        id="<?= e($column) ?>"
                                        name="<?= e($column) ?>"
                                        value="<?= e((string) $record[$column]) ?>"
                                        minlength="2"
                                        maxlength="2"
                                        pattern="[A-Za-z]{2}"
                                        style="text-transform: uppercase;"
                                        required
                                    >

                                    <div class="form-text">
                                        Enter a two-letter state code.
                                    </div>

                                <?php else: ?>

                                    <input
                                        type="text"
                                        class="form-control"
                                        id="<?= e($column) ?>"
                                        name="<?= e($column) ?>"
                                        value="<?= e((string) $record[$column]) ?>"
                                        maxlength="<?= $column === 'state_name'
                                            ? 100
                                            : 150 ?>"
                                        required
                                    >

                                <?php endif; ?>

                            </div>

                        <?php endforeach; ?>

                        <div class="d-flex gap-2 mt-4">

                            <button
                                type="submit"
                                class="btn btn-primary"
                            >

                                <?= $isEdit
                                    ? 'Save Changes'
                                    : 'Add Reference Value' ?>

                            </button>

                            <a
                                href="/admin/reference_data.php?table=<?= e($table) ?>"
                                class="btn btn-outline-secondary"
                            >
                                Cancel
                            </a>

                        </div>

                    </form>

                </div>

            </div>

        </div>

    </div>

</main>

<?php
require_once $projectRoot . '/includes/footer.php';
?>
