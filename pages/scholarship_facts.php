<?php
declare(strict_types=1);

/*
 * Pathways to Scholarships
 * Iteration 6 — Public Scholarship Facts
 *
 * Displays scholarship statistics using Chart.js.
 * All database queries are read-only.
 */

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/includes/db.php';
require_once $projectRoot . '/includes/security.php';

$pdo = getDBConnection();

// Count active scholarships by national/state-local classification.
$summaryStmt = $pdo->query(
    "SELECT
        COUNT(*) AS total_active,
        SUM(CASE WHEN national_or_state_local = 'National'
                 THEN 1 ELSE 0 END) AS national_count,
        SUM(CASE WHEN national_or_state_local = 'State/Local'
                 THEN 1 ELSE 0 END) AS state_count
     FROM scholarships
     WHERE status = 'active'"
);

$summary = $summaryStmt->fetch();

$totalActive = (int) $summary['total_active'];
$nationalCount = (int) $summary['national_count'];
$stateCount = (int) $summary['state_count'];

// Retrieve the ten states with the most active state/local scholarships.
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

$stateCoverage = $stateStmt->fetchAll();

// Retrieve national scholarship counts by theme.
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

$themeCoverage = $themeStmt->fetchAll();

// Encode database results for safe use in JavaScript charts.
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

$pageTitle = 'Scholarship Facts | Pathways to Scholarships';

require_once $projectRoot . '/includes/header.php';
require_once $projectRoot . '/includes/nav.php';
?>

<main class="container py-5">
    <h1 class="mb-3">Scholarship Facts</h1>

    <p class="lead">
        Explore scholarship statistics from our current dataset.
        These figures update as new scholarship records are added.
    </p>

    <div class="row g-3 mb-5">
        <?php
        $summaryCards = [
            'Active Scholarships' => $totalActive,
            'National Scholarships' => $nationalCount,
            'State/Local Scholarships' => $stateCount
        ];
        ?>

        <?php foreach ($summaryCards as $label => $value): ?>
            <div class="col-md-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body text-center">
                        <h2 class="h6"><?= e($label) ?></h2>
                        <p class="display-5 mb-0">
                            <?= (int) $value ?>
                        </p>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <section class="mb-5">
        <h2 class="h4">Top States by Scholarship Coverage</h2>
        <p class="text-muted">
            Active state/local scholarships by state.
        </p>

        <div style="height: 350px;">
            <canvas id="stateChart"></canvas>
        </div>
    </section>

    <section class="mb-5">
        <h2 class="h4">National Scholarships by Theme</h2>
        <p class="text-muted">
            Active national scholarships grouped by theme.
        </p>

        <div style="height: 350px;">
            <canvas id="themeChart"></canvas>
        </div>
    </section>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// PHP converts the database arrays into JSON for Chart.js.
const stateLabels = <?= json_encode($stateLabels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const stateCounts = <?= json_encode($stateCounts) ?>;

const themeLabels = <?= json_encode($themeLabels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const themeCounts = <?= json_encode($themeCounts) ?>;

// Chart.js draws responsive bar charts inside canvas elements.
new Chart(document.getElementById('stateChart'), {
    type: 'bar',
    data: {
        labels: stateLabels,
        datasets: [{
            label: 'Scholarships',
            data: stateCounts,
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

new Chart(document.getElementById('themeChart'), {
    type: 'bar',
    data: {
        labels: themeLabels,
        datasets: [{
            label: 'Scholarships',
            data: themeCounts,
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
