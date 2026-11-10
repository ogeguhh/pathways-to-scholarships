
<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit('Database unavailable.');
}

function facts_e($value): string {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function facts_json($value): string {
    return json_encode(
        $value,
        JSON_HEX_TAG | JSON_HEX_AMP |
        JSON_HEX_APOS | JSON_HEX_QUOT |
        JSON_THROW_ON_ERROR
    );
}

$error = null;
$summary = [
    'total' => 0,
    'national' => 0,
    'local' => 0,
    'providers' => 0
];
$states = [];
$themes = [];
$grades = [];

try {
    $summary = $pdo->query(
        "SELECT
            COUNT(*) AS total,
            COALESCE(SUM(national_or_state_local = 'National'), 0)
                AS national,
            COALESCE(SUM(national_or_state_local = 'State/Local'), 0)
                AS local,
            COUNT(DISTINCT provider_id) AS providers
         FROM scholarships
         WHERE status = 'active'"
    )->fetch(PDO::FETCH_ASSOC);

    $states = $pdo->query(
        "SELECT st.state_name AS label,
                COUNT(s.scholarship_id) AS total
         FROM states st
         JOIN scholarships s
           ON s.state_id = st.state_id
          AND s.status = 'active'
          AND s.national_or_state_local = 'State/Local'
         GROUP BY st.state_id, st.state_name
         ORDER BY total DESC, st.state_name
         LIMIT 10"
    )->fetchAll(PDO::FETCH_ASSOC);

    $themes = $pdo->query(
        "SELECT nt.theme_name AS label,
                COUNT(s.scholarship_id) AS total
         FROM national_themes nt
         JOIN scholarships s
           ON s.theme_id = nt.theme_id
          AND s.status = 'active'
          AND s.national_or_state_local = 'National'
         GROUP BY nt.theme_id, nt.theme_name
         ORDER BY total DESC, nt.theme_name
         LIMIT 10"
    )->fetchAll(PDO::FETCH_ASSOC);

    $grades = $pdo->query(
        "SELECT g.grade_level AS label,
                COUNT(DISTINCT s.scholarship_id) AS total
         FROM grade_levels g
         LEFT JOIN scholarship_grades sg
           ON sg.grade_id = g.grade_id
         LEFT JOIN scholarships s
           ON s.scholarship_id = sg.scholarship_id
          AND s.status = 'active'
         GROUP BY g.grade_id, g.grade_level
         ORDER BY g.grade_level"
    )->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $ex) {
    error_log('Scholarship facts: ' . $ex->getMessage());
    $error = 'Scholarship statistics are temporarily unavailable.';
    http_response_code(500);
}
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <title>Scholarship Facts | Pathways to Scholarships</title>

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
        <a href="index.php">Home</a> |
        <strong>Scholarship Facts</strong>
    </nav>

    <h1>Scholarship Facts</h1>

    <p class="text-muted">
        Statistics about active scholarships in our database.
        Archived and staged submissions are excluded.
    </p>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <?= facts_e($error) ?>
        </div>
    <?php else: ?>

    <!-- Summary cards -->
    <div class="row g-3 mb-4">

        <?php
        $cards = [
            'total' => 'Active Scholarships',
            'national' => 'National Scholarships',
            'local' => 'State/Local Scholarships',
            'providers' => 'Scholarship Providers'
        ];
        ?>

        <?php foreach ($cards as $key => $label): ?>
            <div class="col-6 col-lg-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted small">
                            <?= facts_e($label) ?>
                        </div>
                        <div class="fs-2 fw-bold">
                            <?= number_format((int)$summary[$key]) ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

    </div>

    <!-- Charts -->
    <div class="row g-4">

        <section class="col-lg-6">
            <div class="card h-100">
                <div class="card-body">
                    <h2 class="h5">National vs. State/Local</h2>
                    <div style="height:290px">
                        <canvas id="scopeChart"></canvas>
                    </div>
                </div>
            </div>
        </section>

        <section class="col-lg-6">
            <div class="card h-100">
                <div class="card-body">
                    <h2 class="h5">Grade Eligibility</h2>
                    <div style="height:290px">
                        <canvas id="gradeChart"></canvas>
                    </div>
                    <p class="small text-muted">
                        One scholarship may support multiple grades.
                    </p>
                </div>
            </div>
        </section>

        <section class="col-lg-6">
            <div class="card h-100">
                <div class="card-body">
                    <h2 class="h5">Top 10 States</h2>
                    <div style="height:360px">
                        <canvas id="stateChart"></canvas>
                    </div>
                </div>
            </div>
        </section>

        <section class="col-lg-6">
            <div class="card h-100">
                <div class="card-body">
                    <h2 class="h5">Top National Themes</h2>
                    <div style="height:360px">
                        <canvas id="themeChart"></canvas>
                    </div>
                </div>
            </div>
        </section>

    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        if (typeof Chart === 'undefined') return;

        const states = <?= facts_json($states) ?>;
        const themes = <?= facts_json($themes) ?>;
        const grades = <?= facts_json($grades) ?>;

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
                        label: 'Scholarships',
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
            'scopeChart',
            'doughnut',
            ['National', 'State/Local'],
            [
                <?= (int)$summary['national'] ?>,
                <?= (int)$summary['local'] ?>
            ]
        );

        draw(
            'gradeChart',
            'bar',
            grades.map(row => 'Grade ' + row.label),
            grades.map(row => Number(row.total))
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
