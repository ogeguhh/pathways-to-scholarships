<?php
declare(strict_types=1);

/*
 * Pathways to Scholarships
 * Iteration 6 — Scholarship Coverage Report
 *
 * Reports active scholarship coverage by state and national theme.
 * All queries are read-only.
 */

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/includes/db.php';
require_once $projectRoot . '/includes/security.php';
require_once $projectRoot . '/includes/config.php';

// Apply the same development-only restriction used by
// the other admin pages until role-based access is added.
require_role('Administrator');

ensure_session();

$pdo = getDBConnection();

// Count active state/local scholarships for every state.
// LEFT JOIN includes states that currently have no scholarships.
$stateStmt = $pdo->query(
    "SELECT
        st.state_id,
        st.state_code,
        st.state_name,
        COUNT(s.scholarship_id) AS scholarship_count
     FROM states st
     LEFT JOIN scholarships s
       ON s.state_id = st.state_id
      AND s.status = 'active'
      AND s.national_or_state_local = 'State/Local'
     GROUP BY st.state_id, st.state_code, st.state_name
     ORDER BY st.state_name"
);

$stateCoverage = $stateStmt->fetchAll();

// Count active national scholarships for every theme.
// LEFT JOIN includes themes without scholarship records.
$themeStmt = $pdo->query(
    "SELECT
        nt.theme_id,
        nt.theme_name,
        COUNT(s.scholarship_id) AS scholarship_count
     FROM national_themes nt
     LEFT JOIN scholarships s
       ON s.theme_id = nt.theme_id
      AND s.status = 'active'
      AND s.national_or_state_local = 'National'
     GROUP BY nt.theme_id, nt.theme_name
     ORDER BY nt.theme_name"
);

$themeCoverage = $themeStmt->fetchAll();

// These totals provide an overview of the current dataset.
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

// Count coverage gaps from the reference tables.
$statesWithoutCoverage = 0;

foreach ($stateCoverage as $state) {
    if ((int) $state['scholarship_count'] === 0) {
        $statesWithoutCoverage++;
    }
}

$themesWithoutCoverage = 0;

foreach ($themeCoverage as $theme) {
    if ((int) $theme['scholarship_count'] === 0) {
        $themesWithoutCoverage++;
    }
}

$pageTitle = 'Scholarship Coverage Report | Pathways to Scholarships';

require_once $projectRoot . '/includes/header.php';
require_once $projectRoot . '/includes/nav.php';
?>

<main class="container py-5">
    <h1 class="mb-3">Scholarship Coverage Report</h1>

    <p class="lead">
        Review scholarship coverage by state and national theme
        and identify gaps in the current dataset.
    </p>

    <div class="row g-3 mb-5">
        <?php
        $summaryCards = [
            'Active Scholarships' => $totalActive,
            'State/Local Scholarships' => $stateCount,
            'National Scholarships' => $nationalCount,
            'States With No Coverage' => $statesWithoutCoverage,
            'Themes With No Coverage' => $themesWithoutCoverage
        ];
        ?>

        <?php foreach ($summaryCards as $label => $value): ?>
            <div class="col-md-4 col-lg">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <h2 class="h6"><?= e($label) ?></h2>
                        <p class="display-6 mb-0">
                            <?= (int) $value ?>
                        </p>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <section class="mb-5">
        <h2 class="h4">Coverage by State</h2>

        <p class="text-muted">
            Counts include active state/local scholarships only.
            National scholarships are reported separately.
        </p>

        <div class="table-responsive">
            <table class="table table-striped table-bordered align-middle">
                <thead>
                    <tr>
                        <th>State</th>
                        <th>Code</th>
                        <th>Scholarships</th>
                        <th>Coverage</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($stateCoverage as $state): ?>
                        <?php $count = (int) $state['scholarship_count']; ?>
                        <tr>
                            <td><?= e($state['state_name']) ?></td>
                            <td><?= e($state['state_code']) ?></td>
                            <td><?= $count ?></td>
                            <td>
                                <?php if ($count === 0): ?>
                                    <span class="badge bg-warning text-dark">
                                        Coverage Gap
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-success">
                                        Covered
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="mb-5">
        <h2 class="h4">Coverage by National Theme</h2>

        <p class="text-muted">
            Counts include active national scholarships only.
        </p>

        <div class="table-responsive">
            <table class="table table-striped table-bordered align-middle">
                <thead>
                    <tr>
                        <th>National Theme</th>
                        <th>Scholarships</th>
                        <th>Coverage</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($themeCoverage as $theme): ?>
                        <?php $count = (int) $theme['scholarship_count']; ?>
                        <tr>
                            <td><?= e($theme['theme_name']) ?></td>
                            <td><?= $count ?></td>
                            <td>
                                <?php if ($count === 0): ?>
                                    <span class="badge bg-warning text-dark">
                                        Coverage Gap
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-success">
                                        Covered
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<?php
require_once $projectRoot . '/includes/footer.php';
?>
