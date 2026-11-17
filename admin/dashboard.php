<?php
declare(strict_types=1);

/*
 * Pathways to Scholarships
 * Iteration 7 — Admin Dashboard
 *
 * Access:
 * - Administrator
 * - Super-admin
 *
 * Features:
 * - Scholarship statistics
 * - Data quality indicators
 * - State and national theme coverage
 * - Filtered CSV export
 */

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/includes/db.php';
require_once $projectRoot . '/includes/security.php';

// Require a real authenticated administrator.
// This replaces the old localhost/APP_DEBUG restriction.
require_role(['Administrator', 'Super-admin']);

// Database connection.
$pdo = getDBConnection();

// Project-relative URLs.
$baseUrl = rtrim(
    str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))),
    '/'
);

if ($baseUrl === '/') {
    $baseUrl = '';
}

// Scholarship export filter options.
$exportStates = $pdo->query(
    'SELECT state_id, state_name
     FROM states
     ORDER BY state_name'
)->fetchAll();

$exportGrades = $pdo->query(
    'SELECT grade_id, grade_number
     FROM grade_levels
     ORDER BY grade_number'
)->fetchAll();

$exportFields = $pdo->query(
    'SELECT field_id, field_name
     FROM fields_of_study
     ORDER BY field_name'
)->fetchAll();

// Scholarship totals and data quality indicators.
$summaryStmt = $pdo->query(
    "SELECT
        COUNT(*) AS total_records,

        SUM(
            CASE WHEN status = 'active'
            THEN 1 ELSE 0 END
        ) AS active_count,

        SUM(
            CASE WHEN status = 'archived'
            THEN 1 ELSE 0 END
        ) AS archived_count,

        SUM(
            CASE WHEN status = 'active'
                AND national_or_state_local = 'National'
            THEN 1 ELSE 0 END
        ) AS national_count,

        SUM(
            CASE WHEN status = 'active'
                AND national_or_state_local = 'State/Local'
            THEN 1 ELSE 0 END
        ) AS state_count,

        SUM(
            CASE WHEN status = 'active'
                AND application_deadline < CURRENT_DATE()
            THEN 1 ELSE 0 END
        ) AS expired_count,

        SUM(
            CASE WHEN status = 'active'
                AND verification_date IS NULL
            THEN 1 ELSE 0 END
        ) AS unverified_count,

        SUM(
            CASE WHEN status = 'active'
                AND (
                    official_url IS NULL
                    OR TRIM(official_url) = ''
                )
            THEN 1 ELSE 0 END
        ) AS missing_url_count

     FROM scholarships"
);

$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

// Active state/local scholarships by state.
$stateStmt = $pdo->query(
    "SELECT
        st.state_name,
        COUNT(s.scholarship_id) AS scholarship_count
     FROM states st
     JOIN scholarships s
       ON s.state_id = st.state_id
     WHERE s.status = 'active'
       AND s.national_or_state_local = 'State/Local'
     GROUP BY st.state_id, st.state_name
     ORDER BY scholarship_count DESC, st.state_name
     LIMIT 10"
);

$stateCoverage = $stateStmt->fetchAll(PDO::FETCH_ASSOC);

// Active national scholarships by theme.
$themeStmt = $pdo->query(
    "SELECT
        nt.theme_name,
        COUNT(s.scholarship_id) AS scholarship_count
     FROM national_themes nt
     JOIN scholarships s
       ON s.theme_id = nt.theme_id
     WHERE s.status = 'active'
       AND s.national_or_state_local = 'National'
     GROUP BY nt.theme_id, nt.theme_name
     ORDER BY scholarship_count DESC, nt.theme_name"
);

$themeCoverage = $themeStmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare chart data.
$stateLabels = [];
$stateCounts = [];

foreach ($stateCoverage as $state) {
    $stateLabels[] = $state['state_name'];
    $stateCounts[] = (int) $state['scholarship_count'];
}

$themeLabels = [];
$themeCounts = [];

foreach ($themeCoverage as $theme) {
    $themeLabels[] = $theme['theme_name'];
    $themeCounts[] = (int) $theme['scholarship_count'];
}

// Dashboard cards.
$summaryCards = [
    'Total Records' => $summary['total_records'] ?? 0,
    'Active Scholarships' => $summary['active_count'] ?? 0,
    'Archived Scholarships' => $summary['archived_count'] ?? 0,
    'National Scholarships' => $summary['national_count'] ?? 0,
    'State/Local Scholarships' => $summary['state_count'] ?? 0
];

$qualityCards = [
    'Expired Deadlines' => $summary['expired_count'] ?? 0,
    'Missing Verification Dates' => $summary['unverified_count'] ?? 0,
    'Missing Official URLs' => $summary['missing_url_count'] ?? 0
];

// Browser title.
$pageTitle = 'Admin Dashboard | Pathways to Scholarships';

// Shared layout.
require_once $projectRoot . '/includes/header.php';
require_once $projectRoot . '/includes/nav.php';
?>

<main class="container py-5">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">

        <div>
            <h1 class="mb-2">Admin Dashboard</h1>

            <p class="lead mb-0">
                Monitor scholarship records, data quality, and coverage.
            </p>
        </div>

        <a
            href="<?= e($baseUrl . '/index.php') ?>"
            class="btn btn-outline-secondary"
        >
            Back to Home
        </a>

    </div>

    <!-- Scholarship summary -->
    <section class="mb-5">

        <h2 class="h4 mb-3">
            Scholarship Overview
        </h2>

        <div class="row g-3">

            <?php foreach ($summaryCards as $label => $value): ?>

                <div class="col-md-4 col-lg">

                    <div class="card h-100 shadow-sm">

                        <div class="card-body">

                            <h3 class="h6">
                                <?= e($label) ?>
                            </h3>

                            <p class="display-6 mb-0">
                                <?= (int) $value ?>
                            </p>

                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    </section>

    <!-- Data quality summary -->
    <section class="mb-5">

        <h2 class="h4 mb-3">
            Data Quality Overview
        </h2>

        <div class="row g-3 mb-4">

            <?php foreach ($qualityCards as $label => $value): ?>

                <div class="col-md-4">

                    <div class="card h-100 shadow-sm">

                        <div class="card-body">

                            <h3 class="h6">
                                <?= e($label) ?>
                            </h3>

                            <p class="display-6 mb-0">
                                <?= (int) $value ?>
                            </p>

                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

        <div class="d-flex flex-wrap gap-2">

            <a
                href="<?= e($baseUrl . '/admin/data_quality.php') ?>"
                class="btn btn-outline-primary"
            >
                View Data Quality Dashboard
            </a>

            <a
                href="<?= e($baseUrl . '/admin/coverage_report.php') ?>"
                class="btn btn-outline-primary"
            >
                View Coverage Report
            </a>

            <a
                href="<?= e($baseUrl . '/admin/scholarships.php') ?>"
                class="btn btn-outline-primary"
            >
                Manage Scholarships
            </a>

            <a
                href="<?= e($baseUrl . '/admin/import.php') ?>"
                class="btn btn-outline-primary"
            >
                Import Scholarships
            </a>

        </div>

    </section>

    <!-- CSV export -->
    <section class="mb-5">

        <h2 class="h4 mb-3">
            Export Scholarship Data
        </h2>

        <p class="text-muted">
            Select filters or leave everything set to All
            to export the complete scholarship dataset.
        </p>

        <form
            action="<?= e($baseUrl . '/admin/scholarship_export.php') ?>"
            method="get"
            class="row g-3"
        >

            <div class="col-md-3">

                <label for="exportState" class="form-label">
                    State
                </label>

                <select
                    name="state_id"
                    id="exportState"
                    class="form-select"
                >

                    <option value="">All States</option>

                    <?php foreach ($exportStates as $state): ?>
                        <option value="<?= (int) $state['state_id'] ?>">
                            <?= e($state['state_name']) ?>
                        </option>
                    <?php endforeach; ?>

                </select>

            </div>

            <div class="col-md-3">

                <label for="exportGrade" class="form-label">
                    Grade Level
                </label>

                <select
                    name="grade_id"
                    id="exportGrade"
                    class="form-select"
                >

                    <option value="">All Grades</option>

                    <?php foreach ($exportGrades as $grade): ?>
                        <option value="<?= (int) $grade['grade_id'] ?>">
                            Grade <?= (int) $grade['grade_number'] ?>
                        </option>
                    <?php endforeach; ?>

                </select>

            </div>

            <div class="col-md-3">

                <label for="exportField" class="form-label">
                    Field of Study
                </label>

                <select
                    name="field_id"
                    id="exportField"
                    class="form-select"
                >

                    <option value="">All Fields</option>

                    <?php foreach ($exportFields as $field): ?>
                        <option value="<?= (int) $field['field_id'] ?>">
                            <?= e($field['field_name']) ?>
                        </option>
                    <?php endforeach; ?>

                </select>

            </div>

            <div class="col-md-3">

                <label for="exportType" class="form-label">
                    Scholarship Type
                </label>

                <select
                    name="type"
                    id="exportType"
                    class="form-select"
                >

                    <option value="">All Types</option>
                    <option value="National">National</option>
                    <option value="State/Local">State/Local</option>

                </select>

            </div>

            <div class="col-12">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Download CSV
                </button>

            </div>

        </form>

    </section>

    <!-- State coverage chart -->
    <section class="mb-5">

        <h2 class="h4 mb-3">
            Scholarships by State
        </h2>

        <div style="height: 350px;">
            <canvas id="adminStateChart"></canvas>
        </div>

    </section>

    <!-- National theme chart -->
    <section class="mb-5">

        <h2 class="h4 mb-3">
            National Scholarships by Theme
        </h2>

        <div style="height: 350px;">
            <canvas id="adminThemeChart"></canvas>
        </div>

    </section>

</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
const adminStateLabels = <?= json_encode(
    $stateLabels,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) ?>;

const adminStateCounts = <?= json_encode($stateCounts) ?>;

const adminThemeLabels = <?= json_encode(
    $themeLabels,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) ?>;

const adminThemeCounts = <?= json_encode($themeCounts) ?>;

// State chart.
new Chart(document.getElementById('adminStateChart'), {
    type: 'bar',

    data: {
        labels: adminStateLabels,

        datasets: [{
            label: 'Scholarships',
            data: adminStateCounts,
            backgroundColor: 'rgba(13, 110, 253, 0.7)'
        }]
    },

    options: {
        responsive: true,
        maintainAspectRatio: false,

        scales: {
            y: {
                beginAtZero: true,
                ticks: { precision: 0 }
            }
        }
    }
});

// National themes chart.
new Chart(document.getElementById('adminThemeChart'), {
    type: 'bar',

    data: {
        labels: adminThemeLabels,

        datasets: [{
            label: 'Scholarships',
            data: adminThemeCounts,
            backgroundColor: 'rgba(25, 135, 84, 0.7)'
        }]
    },

    options: {
        responsive: true,
        maintainAspectRatio: false,

        scales: {
            y: {
                beginAtZero: true,
                ticks: { precision: 0 }
            }
        }
    }
});
</script>

<?php
require_once $projectRoot . '/includes/footer.php';
?>
