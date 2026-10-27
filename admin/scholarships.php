<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';

/*
 * Iteration 4 - Admin Scholarship Management
 *
 * Bullet 1: Admin list, filtering, sorting, and bulk actions.
 * Bullet 2: Links to Create, View, Edit, and Archive pages.
 * Bullet 5: Scholarship verification.
 *
 * Development only until authentication and RBAC are implemented.
 */

// Restrict this temporary admin page to local development.
$host = strtolower($_SERVER['HTTP_HOST'] ?? '');
$remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';

$allowedHosts = [
    'localhost',
    'localhost:80',
    'localhost:8001',
    '127.0.0.1',
    '127.0.0.1:80',
    '127.0.0.1:8001'
];

$allowedAddresses = ['127.0.0.1', '::1'];

if (
    PHP_SAPI !== 'cli-server' ||
    !in_array($host, $allowedHosts, true) ||
    !in_array($remoteAddress, $allowedAddresses, true)
) {
    http_response_code(403);
    exit('Development-only admin page.');
}

// Generate a CSRF token for bulk actions.
$csrfToken = csrf_token();

// Retrieve scholarship records and related information.
$sql = "
    SELECT
        sc.scholarship_id,
        sc.scholarship_name,
        p.provider_name,
        sc.national_or_state_local,
        st.state_name,
        nt.theme_name,
        sc.status,
        sc.contributing_student,
        sc.import_batch_id,
        sc.verification_date
    FROM scholarships AS sc
    LEFT JOIN providers AS p
        ON sc.provider_id = p.provider_id
    LEFT JOIN states AS st
        ON sc.state_id = st.state_id
    LEFT JOIN national_themes AS nt
        ON sc.theme_id = nt.theme_id
    ORDER BY sc.scholarship_id DESC
";

$stmt = $pdo->query($sql);
$scholarships = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Escape database values before displaying them in HTML.
function displayValue(mixed $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

// Retrieve success messages from bulk operations.
$verificationMessage = $_SESSION['verification_message'] ?? null;
$archiveMessage = $_SESSION['archive_message'] ?? null;

// Remove messages so they appear only once.
unset(
    $_SESSION['verification_message'],
    $_SESSION['archive_message']
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>Admin Scholarship Management</title>

    <!-- Bootstrap provides the page layout and styling. -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- DataTables provides searching, sorting, and pagination. -->
    <link
        href="https://cdn.datatables.net/2.3.4/css/dataTables.bootstrap5.min.css"
        rel="stylesheet"
    >
</head>

<body class="bg-light">

<!-- ADMIN NAVIGATION -->
<nav class="navbar navbar-dark bg-primary">
    <div class="container-fluid">
        <span class="navbar-brand mb-0 h1">
            Pathways to Scholarships — Admin
        </span>
    </div>
</nav>

<main class="container-fluid px-4 py-4">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
        <div>
            <h1 class="h2 mb-2">
                Admin Scholarship Management
            </h1>

            <p class="text-muted mb-0">
                View, create, edit, search, filter, verify,
                and archive scholarship records.
            </p>
        </div>

        <!-- Connect the admin list to the Create Scholarship page. -->
        <a
            href="scholarship_create.php"
            class="btn btn-primary"
        >
            + Add Scholarship
        </a>
    </div>

    <div class="alert alert-info">
        Development mode — no administrator login required.
        This page is restricted to local testing.
    </div>

    <!-- Display verification success messages. -->
    <?php if ($verificationMessage !== null): ?>
        <div class="alert alert-success" role="status">
            <?= displayValue($verificationMessage) ?>
        </div>
    <?php endif; ?>

    <!-- Display archive success messages. -->
    <?php if ($archiveMessage !== null): ?>
        <div class="alert alert-success" role="status">
            <?= displayValue($archiveMessage) ?>
        </div>
    <?php endif; ?>

    <!-- SCHOLARSHIP FILTERS -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">

            <h2 class="h5 mb-3">Scholarship Filters</h2>

            <div class="row g-3">

                <div class="col-md-3">
                    <label for="stateFilter" class="form-label">
                        State
                    </label>

                    <select id="stateFilter" class="form-select">
                        <option value="">All States</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="studentFilter" class="form-label">
                        Contributing Student
                    </label>

                    <select id="studentFilter" class="form-select">
                        <option value="">All Students</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="batchFilter" class="form-label">
                        Import Batch
                    </label>

                    <select id="batchFilter" class="form-select">
                        <option value="">All Batches</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="themeFilter" class="form-label">
                        National Theme
                    </label>

                    <select id="themeFilter" class="form-select">
                        <option value="">All Themes</option>
                    </select>
                </div>

            </div>

            <button
                type="button"
                id="clearFilters"
                class="btn btn-outline-secondary mt-3"
            >
                Clear Filters
            </button>

        </div>
    </div>

    <!-- SCHOLARSHIP RECORDS -->
    <div class="card shadow-sm">
        <div class="card-body">

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">

                <div>
                    <h2 class="h5 mb-1">
                        Scholarship Records
                    </h2>

                    <span id="selectedCount" class="text-muted">
                        0 scholarships selected
                    </span>
                </div>

                <div class="d-flex flex-wrap gap-2">

                    <!-- Bulk verification from Mohamed's implementation. -->
                    <button
                        type="button"
                        id="markVerifiedBtn"
                        class="btn btn-success"
                        disabled
                    >
                        Mark Verified
                    </button>

                    <!-- Bulk archive from Mohamed's implementation. -->
                    <button
                        type="button"
                        id="archiveBtn"
                        class="btn btn-danger"
                        disabled
                    >
                        Bulk Archive
                    </button>

                </div>

            </div>

            <div class="table-responsive">

                <table
                    id="scholarshipsTable"
                    class="table table-striped table-bordered align-middle w-100"
                >
                    <thead>
                        <tr>
                            <th>
                                <input
                                    type="checkbox"
                                    id="selectAll"
                                    class="form-check-input"
                                    aria-label="Select all filtered scholarships"
                                >
                            </th>

                            <th>ID</th>
                            <th>Scholarship</th>
                            <th>Provider</th>
                            <th>Type</th>
                            <th>State</th>
                            <th>Student</th>
                            <th>Batch</th>
                            <th>Theme</th>
                            <th>Status</th>
                            <th>Verification</th>

                            <!-- New column for individual CRUD actions. -->
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php foreach ($scholarships as $scholarship): ?>
                        <?php
                        // Use the database ID for this row's action links.
                        $scholarshipId = (int)$scholarship['scholarship_id'];
                        ?>

                        <tr>

                            <!-- Checkbox for bulk actions. -->
                            <td>
                                <input
                                    type="checkbox"
                                    class="form-check-input scholarship-checkbox"
                                    value="<?= $scholarshipId ?>"
                                    aria-label="Select scholarship <?= $scholarshipId ?>"
                                >
                            </td>

                            <td>
                                <?= $scholarshipId ?>
                            </td>

                            <td>
                                <?= displayValue($scholarship['scholarship_name']) ?>
                            </td>

                            <td>
                                <?= displayValue($scholarship['provider_name']) ?>
                            </td>

                            <td>
                                <?= displayValue($scholarship['national_or_state_local']) ?>
                            </td>

                            <td>
                                <?= displayValue($scholarship['state_name']) ?>
                            </td>

                            <td>
                                <?= displayValue($scholarship['contributing_student']) ?>
                            </td>

                            <td>
                                <?= displayValue($scholarship['import_batch_id']) ?>
                            </td>

                            <td>
                                <?= displayValue($scholarship['theme_name']) ?>
                            </td>

                            <!-- Show active or archived status. -->
                            <td>
                                <?php if ($scholarship['status'] === 'active'): ?>
                                    <span class="badge bg-success">
                                        Active
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">
                                        Archived
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- Show verification information. -->
                            <td>
                                <?php if (!empty($scholarship['verification_date'])): ?>

                                    <span class="badge bg-success">
                                        Verified
                                    </span>

                                    <div class="small text-muted">
                                        <?= displayValue($scholarship['verification_date']) ?>
                                    </div>

                                <?php else: ?>

                                    <span class="badge bg-warning text-dark">
                                        Not Verified
                                    </span>

                                <?php endif; ?>
                            </td>

                            <!-- Individual scholarship actions. -->
                            <td>
                                <div class="d-flex flex-wrap gap-1">

                                    <!-- View the scholarship details. -->
                                    <a
                                        href="scholarship_view.php?id=<?= $scholarshipId ?>"
                                        class="btn btn-sm btn-outline-primary"
                                    >
                                        View
                                    </a>

                                    <!-- Edit the selected scholarship. -->
                                    <a
                                        href="scholarship_edit.php?id=<?= $scholarshipId ?>"
                                        class="btn btn-sm btn-outline-secondary"
                                    >
                                        Edit
                                    </a>

                                    <!-- Only active scholarships can be archived. -->
                                    <?php if ($scholarship['status'] === 'active'): ?>
                                        <a
                                            href="scholarship_archive.php?id=<?= $scholarshipId ?>"
                                            class="btn btn-sm btn-outline-danger"
                                        >
                                            Archive
                                        </a>
                                    <?php endif; ?>

                                </div>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>
                </table>

            </div>

        </div>
    </div>

    <!-- Hidden form for bulk verification. -->
    <form
        id="verificationForm"
        action="verify_scholarships.php"
        method="POST"
        class="d-none"
    >
        <input
            type="hidden"
            name="csrf_token"
            value="<?= displayValue($csrfToken) ?>"
        >

        <div id="verificationSelectedIds"></div>
    </form>

    <!-- Hidden form for bulk archiving. -->
    <form
        id="archiveForm"
        action="archive_scholarships.php"
        method="POST"
        class="d-none"
    >
        <input
            type="hidden"
            name="csrf_token"
            value="<?= displayValue($csrfToken) ?>"
        >

        <div id="archiveSelectedIds"></div>
    </form>

</main>

<!-- JavaScript libraries -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/2.3.4/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.3.4/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function () {

    /*
     * Keep selected scholarship IDs in a Set.
     *
     * A Set stores unique values and prevents the same
     * scholarship from being selected more than once.
     */
    const selectedIds = new Set();

    // Initialize the scholarship table.
    const table = $('#scholarshipsTable').DataTable({
        pageLength: 10,
        order: [[1, 'desc']],

        columnDefs: [
            {
                // Checkbox column cannot be sorted or searched.
                targets: 0,
                orderable: false,
                searchable: false
            },
            {
                // Action buttons should not affect search or sorting.
                targets: -1,
                orderable: false,
                searchable: false
            }
        ]
    });

    /*
     * Populate a filter dropdown using the values
     * found in a particular DataTables column.
     */
    function populateFilter(selectId, columnIndex) {

        const select = $(selectId);
        const values = new Set();

        table.column(columnIndex).data().each(function (value) {

            const cleanValue = $('<div>')
                .html(value)
                .text()
                .trim();

            if (cleanValue !== '' && cleanValue !== '—') {
                values.add(cleanValue);
            }
        });

        Array.from(values).sort().forEach(function (value) {
            select.append(
                $('<option>', {
                    value: value,
                    text: value
                })
            );
        });
    }

    // Populate filters using their existing column positions.
    populateFilter('#stateFilter', 5);
    populateFilter('#studentFilter', 6);
    populateFilter('#batchFilter', 7);
    populateFilter('#themeFilter', 8);

    /*
     * Apply an exact-match filter to a table column.
     *
     * escapeRegex prevents special characters in filter
     * values from being interpreted as regular expressions.
     */
    function applyFilter(selectId, columnIndex) {

        $(selectId).on('change', function () {

            const selected = $(this).val();

            if (selected === '') {
                table.column(columnIndex).search('').draw();

            } else {
                const escaped =
                    $.fn.dataTable.util.escapeRegex(selected);

                table.column(columnIndex)
                    .search('^' + escaped + '$', true, false)
                    .draw();
            }
        });
    }

    // Connect each dropdown to its table column.
    applyFilter('#stateFilter', 5);
    applyFilter('#studentFilter', 6);
    applyFilter('#batchFilter', 7);
    applyFilter('#themeFilter', 8);

    // Clear all filters and restore the full table.
    $('#clearFilters').on('click', function () {

        $('#stateFilter').val('');
        $('#studentFilter').val('');
        $('#batchFilter').val('');
        $('#themeFilter').val('');

        table.search('');

        [5, 6, 7, 8].forEach(function (columnIndex) {
            table.column(columnIndex).search('');
        });

        table.draw();
    });

    /*
     * Update the selected count and bulk action buttons.
     *
     * Selected IDs are preserved when the user searches,
     * filters, sorts, or changes table pages.
     */
    function updateSelection() {

        const count = selectedIds.size;

        $('#selectedCount').text(
            count + (
                count === 1
                    ? ' scholarship selected'
                    : ' scholarships selected'
            )
        );

        $('#markVerifiedBtn').prop('disabled', count === 0);
        $('#archiveBtn').prop('disabled', count === 0);

        const filteredNodes = table.rows({
            search: 'applied'
        }).nodes();

        const filteredIds = [];

        $(filteredNodes)
            .find('.scholarship-checkbox')
            .each(function () {
                filteredIds.push(String(this.value));
            });

        const allSelected =
            filteredIds.length > 0 &&
            filteredIds.every(function (id) {
                return selectedIds.has(id);
            });

        const someSelected = filteredIds.some(function (id) {
            return selectedIds.has(id);
        });

        $('#selectAll')
            .prop('checked', allSelected)
            .prop(
                'indeterminate',
                someSelected && !allSelected
            );
    }

    // Track individual checkbox changes.
    $('#scholarshipsTable').on(
        'change',
        '.scholarship-checkbox',
        function () {

            const id = String(this.value);

            if (this.checked) {
                selectedIds.add(id);
            } else {
                selectedIds.delete(id);
            }

            updateSelection();
        }
    );

    // Select or deselect all filtered scholarships.
    $('#selectAll').on('change', function () {

        const shouldSelect = this.checked;

        table.rows({
            search: 'applied'
        }).nodes().to$()
            .find('.scholarship-checkbox')
            .each(function () {

                const id = String(this.value);

                if (shouldSelect) {
                    selectedIds.add(id);
                } else {
                    selectedIds.delete(id);
                }
            });

        table.draw(false);
    });

    // Restore checkbox states when DataTables redraws.
    table.on('draw', function () {

        $('#scholarshipsTable .scholarship-checkbox')
            .each(function () {
                this.checked = selectedIds.has(
                    String(this.value)
                );
            });

        updateSelection();
    });


    /*
     * Submit selected scholarship IDs to a hidden form.
     *
     * This allows the administrator to verify or archive
     * multiple scholarships with one action.
     */
    function submitSelected(formId, containerId) {

        const container = $(containerId);

        // Remove previously submitted IDs.
        container.empty();

        // Add each selected scholarship ID as a hidden input.
        selectedIds.forEach(function (id) {
            $('<input>', {
                type: 'hidden',
                name: 'scholarship_ids[]',
                value: id
            }).appendTo(container);
        });

        // Submit the form to the PHP processing page.
        $(formId)[0].submit();
    }

    /*
     * Bulk verification button.
     *
     * Ask for confirmation before updating records.
     */
    $('#markVerifiedBtn').on('click', function () {

        if (selectedIds.size === 0) {
            return;
        }

        const confirmed = confirm(
            'Mark ' + selectedIds.size +
            ' scholarship(s) as verified?'
        );

        if (!confirmed) {
            return;
        }

        submitSelected(
            '#verificationForm',
            '#verificationSelectedIds'
        );
    });

    /*
     * Bulk archive button.
     *
     * Ask for confirmation before archiving records.
     */
    $('#archiveBtn').on('click', function () {

        if (selectedIds.size === 0) {
            return;
        }

        const confirmed = confirm(
            'Archive ' + selectedIds.size +
            ' scholarship(s)? This will change their status to Archived.'
        );

        if (!confirmed) {
            return;
        }

        submitSelected(
            '#archiveForm',
            '#archiveSelectedIds'
        );
    });

    // Initialize the selection counter and button states.
    updateSelection();

});
</script>

</body>
</html>
