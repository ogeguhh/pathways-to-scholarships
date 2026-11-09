
<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';
require_admin_access();

require_once __DIR__ . '/../includes/db.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit('Database connection unavailable.');
}

function cov_e($value): string {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

$threshold = filter_input(
    INPUT_GET,
    'threshold',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1, 'max_range' => 100]]
);

$threshold = is_int($threshold) ? $threshold : 5;

$errors = [];
$stateRows = [];
$themeRows = [];
$total = 0;

try {
    // Total active scholarships
    $total = (int)$pdo->query(
        "SELECT COUNT(*)
         FROM scholarships
         WHERE status = 'active'"
    )->fetchColumn();

    // Count active state/local scholarships by state.
    // LEFT JOIN includes states with zero scholarships.
    $stateRows = $pdo->query(
        "SELECT
            st.state_code,
            st.state_name,
            COUNT(s.scholarship_id) AS total
         FROM states st
         LEFT JOIN scholarships s
            ON s.state_id = st.state_id
            AND s.status = 'active'
            AND s.national_or_state_local = 'State/Local'
         WHERE st.state_code <> 'NA'
         GROUP BY
            st.state_id,
            st.state_code,
            st.state_name
         ORDER BY total ASC, st.state_name ASC"
    )->fetchAll(PDO::FETCH_ASSOC);

    // Count active national scholarships by theme.
    // LEFT JOIN includes themes with zero scholarships.
    $themeRows = $pdo->query(
        "SELECT
            nt.theme_name,
            COUNT(s.scholarship_id) AS total
         FROM national_themes nt
         LEFT JOIN scholarships s
            ON s.theme_id = nt.theme_id
            AND s.status = 'active'
            AND s.national_or_state_local = 'National'
         GROUP BY nt.theme_id, nt.theme_name
         ORDER BY total ASC, nt.theme_name ASC"
    )->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $ex) {
    error_log('Coverage report: ' . $ex->getMessage());
    http_response_code(500);
    $errors[] = 'Unable to load coverage data.';
}

$stateGaps = count(array_filter(
    $stateRows,
    fn($row) => (int)$row['total'] < $threshold
));

$themeGaps = count(array_filter(
    $themeRows,
    fn($row) => (int)$row['total'] < $threshold
));
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <title>Coverage Report | Pathways to Scholarships</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
        rel="stylesheet">
</head>

<body class="bg-light">
<main class="container py-5">

    <nav class="mb-4">
        <a href="../index.php">Home</a> |
        <a href="import.php">Import</a> |
        <a href="export.php">CSV Export</a>
    </nav>

    <h1>Scholarship Coverage Report</h1>

    <p class="text-muted">
        View scholarship coverage by state and national theme.
        Identify states and themes with few or no scholarships.
    </p>

    <?php foreach ($errors as $error): ?>
        <div class="alert alert-danger">
            <?= cov_e($error) ?>
        </div>
    <?php endforeach; ?>

    <?php if (!$errors): ?>

    <form method="get" class="row g-2 mb-4">
        <div class="col-auto">
            <label for="threshold" class="form-label">
                Highlight counts below
            </label>

            <input
                type="number"
                id="threshold"
                name="threshold"
                class="form-control"
                min="1"
                max="100"
                value="<?= $threshold ?>">
        </div>

        <div class="col-auto align-self-end">
            <button class="btn btn-primary">
                Update Report
            </button>
        </div>
    </form>

    <!-- Summary cards -->
    <div class="row g-3 mb-4">

        <div class="col-md-4">
            <div class="card card-body">
                <div class="text-muted">Active Scholarships</div>
                <strong class="fs-3"><?= $total ?></strong>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card card-body">
                <div class="text-muted">States Below Threshold</div>
                <strong class="fs-3"><?= $stateGaps ?></strong>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card card-body">
                <div class="text-muted">Themes Below Threshold</div>
                <strong class="fs-3"><?= $themeGaps ?></strong>
            </div>
        </div>

    </div>

    <div class="row g-4">

        <!-- State coverage -->
        <section class="col-lg-6">

            <h2 class="h4">State Coverage</h2>

            <div class="table-responsive">
                <table class="table table-striped table-bordered bg-white">

                    <thead>
                        <tr>
                            <th>State</th>
                            <th>Scholarships</th>
                            <th>Coverage</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($stateRows as $row): ?>
                        <?php $count = (int)$row['total']; ?>

                        <tr>
                            <td>
                                <?= cov_e($row['state_name']) ?>
                                (<?= cov_e($row['state_code']) ?>)
                            </td>

                            <td><?= $count ?></td>

                            <td>
                                <?php if ($count === 0): ?>
                                    <span class="badge bg-danger">
                                        Missing
                                    </span>

                                <?php elseif ($count < $threshold): ?>
                                    <span class="badge bg-warning text-dark">
                                        Low
                                    </span>

                                <?php else: ?>
                                    <span class="badge bg-success">
                                        OK
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>

                    <?php endforeach; ?>
                    </tbody>

                </table>
            </div>

        </section>

        <!-- National theme coverage -->
        <section class="col-lg-6">

            <h2 class="h4">National Theme Coverage</h2>

            <div class="table-responsive">
                <table class="table table-striped table-bordered bg-white">

                    <thead>
                        <tr>
                            <th>Theme</th>
                            <th>Scholarships</th>
                            <th>Coverage</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($themeRows as $row): ?>
                        <?php $count = (int)$row['total']; ?>

                        <tr>
                            <td><?= cov_e($row['theme_name']) ?></td>

                            <td><?= $count ?></td>

                            <td>
                                <?php if ($count === 0): ?>
                                    <span class="badge bg-danger">
                                        Missing
                                    </span>

                                <?php elseif ($count < $threshold): ?>
                                    <span class="badge bg-warning text-dark">
                                        Low
                                    </span>

                                <?php else: ?>
                                    <span class="badge bg-success">
                                        OK
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>

                    <?php endforeach; ?>
                    </tbody>

                </table>
            </div>

        </section>

    </div>

    <p class="small text-muted mt-4">
        Only active scholarships are counted. State coverage
        counts state/local scholarships; theme coverage counts
        national scholarships assigned to a theme.
    </p>

    <?php endif; ?>

</main>
</body>
</html>
