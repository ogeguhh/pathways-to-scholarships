<?php
declare(strict_types=1);

// Pathways to Scholarships
// Iteration 7: Reference Data Management Dashboard

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/includes/db.php';
require_once $projectRoot . '/includes/security.php';
require_once $projectRoot . '/includes/reference_service.php';

// Only Super-admins can manage reference data.
require_role('Super-admin');

$pdo = getDBConnection();

$pageTitle = 'Reference Data | Pathways to Scholarships';

// Retrieve the approved reference table definitions.
$definitions = referenceDefinitions();

$table = (string) ($_GET['table'] ?? 'states');

// Reject unauthorized table names.
if (!isset($definitions[$table])) {
    http_response_code(400);
    exit('Invalid reference data table.');
}

$definition = referenceDefinition($table);

$records = referenceRows($pdo, $table);

// Retrieve feedback from previous save or delete operations.
$successMessage = $_SESSION['reference_success'] ?? '';
$errorMessage = $_SESSION['reference_error'] ?? '';

unset($_SESSION['reference_success']);
unset($_SESSION['reference_error']);

require_once $projectRoot . '/includes/header.php';
require_once $projectRoot . '/includes/nav.php';
?>

<main class="container py-4">

    <div class="d-flex flex-wrap justify-content-between
                align-items-center gap-3 mb-4">

        <div>

            <h1 class="h2">Reference Data Management</h1>

            <p class="text-muted mb-0">
                Manage the lookup values used throughout
                Pathways to Scholarships.
            </p>

        </div>

        <a href="/admin/reference_form.php?table=<?= e($table) ?>"
           class="btn btn-primary">
            Add Reference Value
        </a>

    </div>

    <?php if ($successMessage !== ''): ?>

        <div class="alert alert-success" role="status">
            <?= e($successMessage) ?>
        </div>

    <?php endif; ?>

    <?php if ($errorMessage !== ''): ?>

        <div class="alert alert-danger" role="alert">
            <?= e($errorMessage) ?>
        </div>

    <?php endif; ?>

    <!-- Display the six available reference data categories. -->
    <div class="row g-3 mb-4">

        <?php foreach ($definitions as $tableName => $item): ?>

            <?php
            $recordCount = (int) $pdo->query(
                "SELECT COUNT(*) FROM `$tableName`"
            )->fetchColumn();

            $isSelected = $table === $tableName;
            ?>

            <div class="col-6 col-md-4 col-xl-2">

                <a
                    href="/admin/reference_data.php?table=<?= e($tableName) ?>"
                    class="text-decoration-none"
                >

                    <div class="card shadow-sm h-100
                        <?= $isSelected ? 'border-primary' : '' ?>">

                        <div class="card-body">

                            <p class="small mb-2
                                <?= $isSelected
                                    ? 'text-primary fw-bold'
                                    : 'text-muted' ?>">

                                <?= e($item['label']) ?>

                            </p>

                            <h2 class="h3 text-dark mb-0">
                                <?= $recordCount ?>
                            </h2>

                        </div>

                    </div>

                </a>

            </div>

        <?php endforeach; ?>

    </div>

    <div class="alert alert-info">

        Reference values that are currently used by scholarships,
        providers, or student profiles cannot be deleted.

    </div>

    <div class="card shadow-sm">

        <div class="card-header bg-white">

            <strong><?= e($definition['label']) ?></strong>

            <span class="text-muted">
                (<?= count($records) ?> records)
            </span>

        </div>

        <div class="table-responsive">

            <table class="table table-striped table-hover
                          align-middle mb-0">

                <thead class="table-light">

                    <tr>

                        <th scope="col">ID</th>

                        <?php foreach ($definition['fields']
                            as $column => $label): ?>

                            <th scope="col">
                                <?= e($label) ?>
                            </th>

                        <?php endforeach; ?>

                        <th scope="col">Usage Count</th>
                        <th scope="col">Actions</th>

                    </tr>

                </thead>

                <tbody>

                    <?php if (empty($records)): ?>

                        <tr>

                            <td
                                colspan="<?= count($definition['fields']) + 3 ?>"
                                class="text-center text-muted py-4"
                            >
                                No reference values found.
                            </td>

                        </tr>

                    <?php endif; ?>

                    <?php foreach ($records as $record): ?>

                        <?php
                        $id = (int) $record[$definition['id']];

                        // Determine whether other records use this value.
                        $usageCount = referenceUsageCount(
                            $pdo,
                            $table,
                            $id
                        );
                        ?>

                        <tr>

                            <td><?= $id ?></td>

                            <?php foreach ($definition['fields']
                                as $column => $label): ?>

                                <td>
                                    <?= e((string) $record[$column]) ?>
                                </td>

                            <?php endforeach; ?>

                            <td>

                                <?php if ($usageCount > 0): ?>

                                    <span class="badge bg-primary">
                                        <?= $usageCount ?> in use
                                    </span>

                                <?php else: ?>

                                    <span class="badge bg-secondary">
                                        Unused
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>

                                <div class="d-flex flex-wrap gap-2">

                                    <a
                                        href="/admin/reference_form.php?table=<?= e($table) ?>&amp;id=<?= $id ?>"
                                        class="btn btn-sm btn-outline-primary"
                                    >
                                        Edit
                                    </a>

                                    <!-- Deletion requires POST and CSRF. -->
                                    <form
                                        method="POST"
                                        action="/admin/reference_delete.php"
                                        onsubmit="return confirm('Delete this reference value?');"
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
                                            value="<?= $id ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="btn btn-sm btn-outline-danger"
                                            <?= $usageCount > 0
                                                ? 'disabled'
                                                : '' ?>
                                        >
                                            Delete
                                        </button>

                                    </form>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    </div>

</main>

<?php
require_once $projectRoot . '/includes/footer.php';
?>
