<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';

/*
 * Iteration 4 - Requirements 1 and 5
 * Development-only scholarship management.
 * Authentication and RBAC arrive in Iteration 7.
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$host = strtolower($_SERVER['HTTP_HOST'] ?? '');
$remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';

$allowedHosts = [
    'localhost',
    'localhost:80',
    '127.0.0.1',
    '127.0.0.1:80'
];

$allowedAddresses = ['127.0.0.1', '::1'];

if (
    !in_array($host, $allowedHosts, true) ||
    !in_array($remoteAddress, $allowedAddresses, true)
) {
    http_response_code(403);
    exit('Development-only admin page.');
}

$csrfToken = csrf_token();

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

function displayValue($value): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$verificationMessage = $_SESSION['verification_message'] ?? null;
$archiveMessage = $_SESSION['archive_message'] ?? null;

unset($_SESSION['verification_message'], $_SESSION['archive_message']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Admin Scholarship Management</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        href="https://cdn.datatables.net/2.3.4/css/dataTables.bootstrap5.min.css"
        rel="stylesheet"
    >
</head>

<body class="bg-light">

<nav class="navbar navbar-dark bg-primary">
    <div class="container-fluid">
        <span class="navbar-brand mb-0 h1">
            Pathways to Scholarships — Admin
        </span>
    </div>
</nav>

<main class="container-fluid px-4 py-4">

    <h2>Admin Scholarship Management</h2>

    <p class="text-muted">
        View, search, filter, verify, and archive scholarship records.
    </p>

    <div class="alert alert-info">
        Development mode — no administrator login required.
        This page is restricted to local testing.
    </div>

    <?php if ($verificationMessage !== null): ?>
        <div class="alert alert-success" role="status">
            <?= displayValue($verificationMessage) ?>
        </div>
    <?php endif; ?>

    <?php if ($archiveMessage !== null): ?>
        <div class="alert alert-success" role="status">
            <?= displayValue($archiveMessage) ?>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm mb-4">
        <div class="card-body">

            <h5 class="mb-3">Scholarship Filters</h5>

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

    <div class="card shadow-sm">
        <div class="card-body">

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">

                <div>
                    <h5 class="mb-1">Scholarship Records</h5>
                    <span id="selectedCount" class="text-muted">
                        0 scholarships selected
                    </span>
                </div>

                <div class="d-flex flex-wrap gap-2">

                    <button
                        type="button"
                        id="markVerifiedBtn"
                        class="btn btn-success"
                        disabled
                    >
                        Mark Verified
                    </button>

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
                        </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($scholarships as $scholarship): ?>
                        <tr>

                            <td>
                                <input
                                    type="checkbox"
                                    class="form-check-input scholarship-checkbox"
                                    value="<?= (int)$scholarship['scholarship_id'] ?>"
                                    aria-label="Select scholarship <?= (int)$scholarship['scholarship_id'] ?>"
                                >
                            </td>

                            <td>
                                <?= (int)$scholarship['scholarship_id'] ?>
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

                        </tr>
                    <?php endforeach; ?>
                    </tbody>

                </table>

            </div>

        </div>
    </div>

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

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/2.3.4/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.3.4/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function () {

    const selectedIds = new Set();

    const table = $('#scholarshipsTable').DataTable({
        pageLength: 10,
        order: [[1, 'desc']],
        columnDefs: [{
            targets: 0,
            orderable: false,
            searchable: false
        }]
    });

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

    populateFilter('#stateFilter', 5);
    populateFilter('#studentFilter', 6);
    populateFilter('#batchFilter', 7);
    populateFilter('#themeFilter', 8);

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

    applyFilter('#stateFilter', 5);
    applyFilter('#studentFilter', 6);
    applyFilter('#batchFilter', 7);
    applyFilter('#themeFilter', 8);

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

    function updateSelection() {

        const count = selectedIds.size;

        $('#selectedCount').text(
            count + (count === 1
                ? ' scholarship selected'
                : ' scholarships selected')
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

    table.on('draw', function () {

        $('#scholarshipsTable .scholarship-checkbox')
            .each(function () {
                this.checked = selectedIds.has(
                    String(this.value)
                );
            });

        updateSelection();
    });

    function submitSelected(formId, containerId) {

        const container = $(containerId);
        container.empty();

        selectedIds.forEach(function (id) {
            $('<input>', {
                type: 'hidden',
                name: 'scholarship_ids[]',
                value: id
            }).appendTo(container);
        });

        $(formId)[0].submit();
    }

    $('#markVerifiedBtn').on('click', function () {

        if (selectedIds.size === 0) {
            return;
        }

        if (!confirm(
            'Mark ' + selectedIds.size +
            ' scholarship(s) as verified?'
        )) {
            return;
        }

        submitSelected(
            '#verificationForm',
            '#verificationSelectedIds'
        );
    });

    $('#archiveBtn').on('click', function () {

        if (selectedIds.size === 0) {
            return;
        }

        if (!confirm(
            'Archive ' + selectedIds.size +
            ' scholarship(s)? This will change their status to Archived.'
        )) {
            return;
        }

        submitSelected(
            '#archiveForm',
            '#archiveSelectedIds'
        );
    });

    updateSelection();

});
</script>

</body>
</html>
