
<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';
require_admin_access();

require_once __DIR__ . '/../includes/db.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit('Database unavailable.');
}

function dash_e($value): string {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function dash_json($value): string {
    return json_encode(
        $value,
        JSON_HEX_TAG | JSON_HEX_AMP |
        JSON_HEX_APOS | JSON_HEX_QUOT |
        JSON_THROW_ON_ERROR
    );
}

$error = null;
$cards = [];
$stateRows = [];
$themeRows = [];
$recent = [];
$statusRows = [];

try {
    $cards = $pdo->query(
        "SELECT
            COUNT(*) AS all_scholarships,
            COALESCE(SUM(status = 'active'), 0) AS active,
            COALESCE(SUM(status = 'archived'), 0) AS archived,
            COALESCE(SUM(
                status = 'active'
                AND national_or_state_local = 'National'
            ), 0) AS national,
            COALESCE(SUM(
                status = 'active'
                AND national_or_state_local = 'State/Local'
            ), 0) AS local
         FROM scholarships"
    )->fetch(PDO::FETCH_ASSOC);

    $cards['providers'] = (int)$pdo->query(
        "SELECT COUNT(*) FROM providers"
    )->fetchColumn();

    $cards['batches'] = (int)$pdo->query(
        "SELECT COUNT(*) FROM import_batches"
    )->fetchColumn();

    // Include states with zero scholarships.
    $stateRows = $pdo->query(
        "SELECT
            st.state_name AS label,
            COUNT(s.scholarship_id) AS total
         FROM states st
         LEFT JOIN scholarships s
           ON s.state_id = st.state_id
          AND s.status = 'active'
          AND s.national_or_state_local = 'State/Local'
         WHERE st.state_code <> 'NA'
         GROUP BY st.state_id, st.state_name
         ORDER BY total ASC, st.state_name"
    )->fetchAll(PDO::FETCH_ASSOC);

    // Include national themes with zero scholarships.
    $themeRows = $pdo->query(
        "SELECT
            nt.theme_name AS label,
            COUNT(s.scholarship_id) AS total
         FROM national_themes nt
         LEFT JOIN scholarships s
           ON s.theme_id = nt.theme_id
          AND s.status = 'active'
          AND s.national_or_state_local = 'National'
         GROUP BY nt.theme_id, nt.theme_name
         ORDER BY total ASC, nt.theme_name"
    )->fetchAll(PDO::FETCH_ASSOC);

    // Recent uploads.
    $recent = $pdo->query(
        "SELECT
            import_batch_id,
            file_name,
            batch_status,
            total_rows,
            ready_rows,
            warning_rows,
            duplicate_rows,
            error_rows,
            imported_at
         FROM import_batches
         ORDER BY imported_at DESC, import_batch_id DESC
         LIMIT 8"
    )->fetchAll(PDO::FETCH_ASSOC);

    // Import statuses for Chart.js.
    $statusRows = $pdo->query(
        "SELECT
            COALESCE(
                NULLIF(batch_status, ''),
                'Unspecified'
            ) AS label,
            COUNT(*) AS total
         FROM import_batches
         GROUP BY label
         ORDER BY total DESC"
    )->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $ex) {
    error_log('Dashboard: ' . $ex->getMessage());
    $error = 'Unable to load dashboard statistics.';
    http_response_code(500);
}

$threshold = 5;

$lowStates = count(array_filter(
    $stateRows,
    fn($row) => (int)$row['total'] < $threshold
));

$lowThemes = count(array_filter(
    $themeRows,
    fn($row) => (int)$row['total'] < $threshold
));

$lowestStates = array_slice($stateRows, 0, 10);
$lowestThemes = array_slice($themeRows, 0, 10);
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <title>Admin Dashboard | Pathways to Scholarships</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <script
        src="https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.min.js"
        defer></script>
</head>

<body class="bg-light">
<main class="container py-5">

    <nav class="mb-4">
        <a href="../index.php">Home</a> |
        <a href="../scholarship_facts.php">Public Facts</a> |
        <a href="coverage_report.php">Coverage Report</a> |
        <a href="export.php">CSV Export</a> |
        <a href="import.php">Import</a> |
        <a href="providers.php">Providers</a>
    </nav>

    <h1>Admin Dashboard</h1>

    <p class="text-muted">
        Scholarship statistics, coverage gaps,
        and recent import activity.
    </p>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <?= dash_e($error) ?>
        </div>
    <?php else: ?>

    <!-- Summary cards -->
    <?php
    $summaryCards = [
        'active' => 'Active Scholarships',
        'archived' => 'Archived Scholarships',
        'national' => 'National',
        'local' => 'State/Local',
        'providers' => 'Providers',
        'batches' => 'Import Batches'
    ];
    ?>

    <div class="row g-3 mb-4">

        <?php foreach ($summaryCards as $key => $label): ?>
            <div class="col-6 col-lg-4 col-xl-2">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted small">
                            <?= dash_e($label) ?>
                        </div>

                        <div class="fs-2 fw-bold">
                            <?= number_format((int)$cards[$key]) ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

    </div>

    <!-- Coverage gap summary -->
    <div class="row g-3 mb-4">

        <div class="col-md-6">
            <div class="card card-body">
                <span class="text-muted">
                    States with fewer than 5 scholarships
                </span>

                <strong class="fs-3">
                    <?= $lowStates ?>
                </strong>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card card-body">
                <span class="text-muted">
                    Themes with fewer than 5 scholarships
                </span>

                <strong class="fs-3">
                    <?= $lowThemes ?>
                </strong>
            </div>
        </div>

    </div>

    <!-- Charts -->
    <div class="row g-4 mb-4">

        <section class="col-lg-6">
            <div class="card">
                <div class="card-body">
                    <h2 class="h5">Active vs. Archived</h2>
                    <div style="height:280px">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>
        </section>

        <section class="col-lg-6">
            <div class="card">
                <div class="card-body">
                    <h2 class="h5">Import Batch Statuses</h2>
                    <div style="height:280px">
                        <canvas id="batchChart"></canvas>
                    </div>
                </div>
            </div>
        </section>

        <section class="col-lg-6">
            <div class="card">
                <div class="card-body">
                    <h2 class="h5">Lowest State Coverage</h2>
                    <div style="height:340px">
                        <canvas id="stateChart"></canvas>
                    </div>
                </div>
            </div>
        </section>

        <section class="col-lg-6">
            <div class="card">
                <div class="card-body">
                    <h2 class="h5">Lowest Theme Coverage</h2>
                    <div style="height:340px">
                        <canvas id="themeChart"></canvas>
                    </div>
                </div>
            </div>
        </section>

    </div>

    <!-- Recent import table -->
    <section class="card">
        <div class="card-body">

            <h2 class="h5">Recent Import Batches</h2>

            <div class="table-responsive">
                <table class="table table-striped">

                    <thead>
                        <tr>
                            <th>Batch</th>
                            <th>File</th>
                            <th>Status</th>
                            <th>Rows</th>
                            <th>Ready</th>
                            <th>Warning</th>
                            <th>Duplicate</th>
                            <th>Error</th>
                            <th>Imported</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($recent as $row): ?>
                        <tr>
                            <td><?= (int)$row['import_batch_id'] ?></td>
                            <td><?= dash_e($row['file_name']) ?></td>
                            <td><?= dash_e($row['batch_status'] ?? '') ?></td>
                            <td><?= (int)$row['total_rows'] ?></td>
                            <td><?= (int)$row['ready_rows'] ?></td>
                            <td><?= (int)$row['warning_rows'] ?></td>
                            <td><?= (int)$row['duplicate_rows'] ?></td>
                            <td><?= (int)$row['error_rows'] ?></td>
                            <td><?= dash_e($row['imported_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$recent): ?>
                        <tr>
                            <td colspan="9" class="text-center">
                                No import batches yet.
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>

                </table>
            </div>

        </div>
    </section>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        if (typeof Chart === 'undefined') return;

        const states = <?= dash_json($lowestStates) ?>;
        const themes = <?= dash_json($lowestThemes) ?>;
        const batches = <?= dash_json($statusRows) ?>;

        const colors = [
            '#2563eb', '#16a34a', '#f59e0b',
            '#7c3aed', '#0891b2', '#dc2626',
            '#4f46e5', '#65a30d', '#ea580c', '#0d9488'
        ];

        function draw(id, type, labels, values, horizontal = false) {
            new Chart(document.getElementById(id), {
                type: type,
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Count',
                        data: values,
                        backgroundColor: colors
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: horizontal ? 'y' : 'x',
                    plugins: {
                        legend: {
                            display: type === 'doughnut'
                        }
                    },
                    scales: type === 'doughnut' ? {} : {
                        x: {
                            beginAtZero: true,
                            ticks: { precision: 0 }
                        },
                        y: {
                            ticks: horizontal ? {} : { precision: 0 }
                        }
                    }
                }
            });
        }

        draw(
            'statusChart',
            'doughnut',
            ['Active', 'Archived'],
            [
                <?= (int)$cards['active'] ?>,
                <?= (int)$cards['archived'] ?>
            ]
        );

        draw(
            'batchChart',
            'bar',
            batches.map(row => row.label),
            batches.map(row => Number(row.total))
        );

        draw(
            'stateChart',
            'bar',
            states.map(row => row.label),
            states.map(row => Number(row.total)),
            true
        );

        draw(
            'themeChart',
            'bar',
            themes.map(row => row.label),
            themes.map(row => Number(row.total)),
            true
        );
    });
    </script>

    <?php endif; ?>

</main>
</body>
</html>
