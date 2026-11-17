<?php
declare(strict_types=1);

/*
 * Pathways to Scholarships
 * Iteration 4 — Admin Scholarship List
 *
 * Displays scholarship records and provides searching,
 * sorting, and filtering using DataTables.
 */

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/includes/db.php';
require_once $projectRoot . '/includes/security.php';
require_once $projectRoot . '/includes/config.php';

require_role(['Administrator', 'Super-admin']);

// Start the session before HTML output so the browser
// receives the session cookie used for CSRF protection.
ensure_session();

$pdo = getDBConnection();

// LEFT JOIN keeps scholarships visible even when optional
// state or national theme information is unavailable.
$stmt = $pdo->prepare(
    'SELECT
        s.scholarship_id,
        s.scholarship_name,
        s.official_url,
        s.national_or_state_local,
        s.contributing_student,
        s.import_batch_id,
        s.application_deadline,
        s.verification_date,
        s.status,
        p.provider_name,
        st.state_name,
        nt.theme_name
     FROM scholarships s
     JOIN providers p
       ON p.provider_id = s.provider_id
     LEFT JOIN states st
       ON st.state_id = s.state_id
     LEFT JOIN national_themes nt
       ON nt.theme_id = s.theme_id
     ORDER BY s.scholarship_id DESC'
);

$stmt->execute();
$scholarships = $stmt->fetchAll();

$pageTitle = 'Manage Scholarships | Pathways to Scholarships';

require_once $projectRoot . '/includes/header.php';
require_once $projectRoot . '/includes/nav.php';
?>

<main class="container py-5">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="mb-0">Manage Scholarships</h1>
        <a href="scholarship_form.php"
           class="btn btn-primary">
            Create Scholarship
        </a>
    </div>

    <p class="lead">
        Review scholarship records, search by name, and filter
        imported scholarships.
    </p>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <label for="stateFilter" class="form-label">State</label>
            <select id="stateFilter" class="form-select">
                <option value="">All states</option>
            </select>
        </div>

        <div class="col-md-3">
            <label for="studentFilter" class="form-label">Contributing Student</label>
            <select id="studentFilter" class="form-select">
                <option value="">All students</option>
            </select>
        </div>

        <div class="col-md-3">
            <label for="batchFilter" class="form-label">Import Batch</label>
            <select id="batchFilter" class="form-select">
                <option value="">All batches</option>
            </select>
        </div>

        <div class="col-md-3">
            <label for="themeFilter" class="form-label">National Theme</label>
            <select id="themeFilter" class="form-select">
                <option value="">All themes</option>
            </select>
        </div>
    </div>

    <form id="bulkActionForm"
          action="scholarship_bulk.php"
          method="post">

        <input type="hidden"
               name="csrf_token"
               value="<?= e(csrf_token()) ?>">

        <div class="d-flex gap-2 align-items-center mb-3">
            <label for="bulkAction" class="form-label mb-0">
                Bulk Action
            </label>

            <select id="bulkAction"
                    name="bulk_action"
                    class="form-select w-auto"
                    required>
                <option value="">Choose action</option>
                <option value="verify">Mark as Verified</option>
                <option value="archive">Archive</option>
            </select>

            <button type="submit" class="btn btn-primary">
                Apply
            </button>
        </div>

    </form>

    <div class="table-responsive">
        <table id="scholarshipsTable"
               class="table table-striped table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <th>
                        <input type="checkbox"
                               id="selectAll"
                               aria-label="Select all visible scholarships">
                    </th>
                    <th>ID</th>
                    <th>Scholarship</th>
                    <th>Provider</th>
                    <th>State</th>
                    <th>Type</th>
                    <th>Theme</th>
                    <th>Student</th>
                    <th>Batch</th>
                    <th>Deadline</th>
                    <th>Verified</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($scholarships as $scholarship): ?>
                    <tr>
                        <td>
                            <?php if ($scholarship['status'] === 'active'): ?>
                                <input type="checkbox"
                                       class="scholarship-select"
                                       value="<?= (int) $scholarship['scholarship_id'] ?>"
                                       aria-label="Select scholarship <?= (int) $scholarship['scholarship_id'] ?>">
                            <?php endif; ?>
                        </td>
                        <td><?= (int) $scholarship['scholarship_id'] ?></td>

                        <td>
                            <a href="<?= e($scholarship['official_url']) ?>"
                               target="_blank"
                               rel="noopener noreferrer">
                                <?= e($scholarship['scholarship_name']) ?>
                            </a>
                        </td>

                        <td><?= e($scholarship['provider_name']) ?></td>
                        <td><?= e($scholarship['state_name']) ?></td>
                        <td><?= e($scholarship['national_or_state_local']) ?></td>
                        <td><?= e($scholarship['theme_name']) ?></td>
                        <td><?= e($scholarship['contributing_student']) ?></td>

                        <td>
                            <?= $scholarship['import_batch_id'] !== null
                                ? (int) $scholarship['import_batch_id']
                                : '' ?>
                        </td>

                        <td><?= e($scholarship['application_deadline']) ?></td>
                        <td><?= e($scholarship['verification_date']) ?></td>

                        <td>
                            <?php if ($scholarship['status'] === 'active'): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Archived</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <a href="scholarship_view.php?id=<?= (int) $scholarship['scholarship_id'] ?>"
                               class="btn btn-sm btn-outline-info">
                                View
                            </a>

                            <a href="scholarship_form.php?id=<?= (int) $scholarship['scholarship_id'] ?>"
                               class="btn btn-sm btn-outline-primary">
                                Edit
                            </a>

                            <?php if ($scholarship['status'] === 'active'): ?>
                                <form action="scholarship_verify.php"
                                      method="post"
                                      class="d-inline"
                                      onsubmit="return confirm('Mark this scholarship as verified?');">

                                    <input type="hidden"
                                           name="csrf_token"
                                           value="<?= e(csrf_token()) ?>">

                                    <input type="hidden"
                                           name="scholarship_id"
                                           value="<?= (int) $scholarship['scholarship_id'] ?>">

                                    <button type="submit"
                                            class="btn btn-sm btn-outline-success">
                                        Verify
                                    </button>
                                </form>

                                <form action="scholarship_archive.php"
                                      method="post"
                                      class="d-inline"
                                      onsubmit="return confirm('Archive this scholarship?');">

                                    <input type="hidden"
                                           name="csrf_token"
                                           value="<?= e(csrf_token()) ?>">

                                    <input type="hidden"
                                           name="scholarship_id"
                                           value="<?= (int) $scholarship['scholarship_id'] ?>">

                                    <button type="submit"
                                            class="btn btn-sm btn-outline-danger">
                                        Archive
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</main>

<link rel="stylesheet"
      href="https://cdn.datatables.net/2.3.4/css/dataTables.bootstrap5.min.css">

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/2.3.4/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.3.4/js/dataTables.bootstrap5.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {

    // Initialize the sortable, searchable scholarship table.
    const table = new DataTable('#scholarshipsTable', {
        pageLength: 10,
        order: [[1, 'desc']]
    });

    // Populate a filter with unique values from a table column.
    function setupFilter(selectId, columnIndex) {
        const select = document.getElementById(selectId);
        const column = table.column(columnIndex);

        column.data().unique().sort().each(function (value) {
            const text = $('<div>').html(value).text().trim();

            if (text !== '') {
                const option = document.createElement('option');
                option.value = text;
                option.textContent = text;
                select.appendChild(option);
            }
        });

        select.addEventListener('change', function () {
            const selected = this.value;

            column.search(
                selected ? '^' + $.fn.dataTable.util.escapeRegex(selected) + '$' : '',
                true,
                false
            ).draw();
        });
    }

    setupFilter('stateFilter', 4);
    setupFilter('studentFilter', 7);
    setupFilter('batchFilter', 8);
    setupFilter('themeFilter', 6);

    // Select or deselect checkboxes on the current table page.
    document.getElementById('selectAll').addEventListener('change', function () {
        table.rows({ page: 'current', search: 'applied' })
            .nodes()
            .to$()
            .find('.scholarship-select')
            .prop('checked', this.checked);
    });

    // Collect selected IDs before submitting the bulk action.
    document.getElementById('bulkActionForm').addEventListener('submit', function (event) {
        const selected = table.$('.scholarship-select:checked');

        if (selected.length === 0) {
            event.preventDefault();
            alert('Select at least one scholarship.');
            return;
        }

        if (!confirm('Apply this action to ' + selected.length + ' scholarships?')) {
            event.preventDefault();
            return;
        }

        // Remove IDs from a previous submission attempt.
        this.querySelectorAll('input[name="scholarship_ids[]"]').forEach(
            input => input.remove()
        );

        const uniqueIds = new Set();

        selected.each((index, checkbox) => {
            uniqueIds.add(checkbox.value);
        });

        uniqueIds.forEach(id => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'scholarship_ids[]';
            input.value = id;
            this.appendChild(input);
        });
    });

});
</script>

<?php require_once $projectRoot . '/includes/footer.php'; ?>
