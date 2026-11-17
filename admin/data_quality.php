<?php
declare(strict_types=1);

/*
 * Pathways to Scholarships
 * Iteration 7 — Data Quality Dashboard
 *
 * Access:
 * - Administrator
 * - Super-admin
 *
 * Reports data quality issues without changing
 * scholarship records.
 */

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/includes/db.php';
require_once $projectRoot . '/includes/security.php';

// Only authenticated administrators may access this page.
// Replaces the previous APP_DEBUG / localhost restriction.
require_role(['Administrator', 'Super-admin']);

$pdo = getDBConnection();

// Determine the project base URL.
$baseUrl = rtrim(
    str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))),
    '/'
);

if ($baseUrl === '/') {
    $baseUrl = '';
}

// Use a fixed 90-day threshold for stale verification.
$staleDays = 90;

// Read-only scholarship data quality checks.
$qualityChecks = [

    'Expired Deadlines' => [
        'description' =>
            'Scholarships with application deadlines in the past.',

        'sql' => "
            SELECT
                scholarship_id,
                scholarship_name,
                application_deadline AS issue_value

            FROM scholarships

            WHERE status = 'active'
              AND application_deadline < CURRENT_DATE()

            ORDER BY application_deadline ASC
        "
    ],

    'Stale Verification Dates' => [
        'description' =>
            'Scholarships not verified within the last '
            . $staleDays . ' days.',

        'sql' => "
            SELECT
                scholarship_id,
                scholarship_name,
                verification_date AS issue_value

            FROM scholarships

            WHERE status = 'active'
              AND (
                  verification_date IS NULL
                  OR verification_date < DATE_SUB(
                      CURRENT_DATE(), INTERVAL 90 DAY
                  )
              )

            ORDER BY verification_date ASC
        "
    ],

    'Missing Key Fields' => [
        'description' =>
            'Scholarships with missing important information.',

        'sql' => "
            SELECT
                scholarship_id,
                scholarship_name,

                CONCAT_WS(', ',
                    IF(
                        scholarship_name IS NULL
                        OR TRIM(scholarship_name) = '',
                        'Name',
                        NULL
                    ),

                    IF(
                        official_url IS NULL
                        OR TRIM(official_url) = '',
                        'Official URL',
                        NULL
                    ),

                    IF(
                        application_deadline IS NULL
                        AND rolling_deadline = 0,
                        'Deadline',
                        NULL
                    ),

                    IF(
                        verification_date IS NULL,
                        'Verification Date',
                        NULL
                    )
                ) AS issue_value

            FROM scholarships

            WHERE status = 'active'
              AND (
                  scholarship_name IS NULL
                  OR TRIM(scholarship_name) = ''
                  OR official_url IS NULL
                  OR TRIM(official_url) = ''
                  OR (
                      application_deadline IS NULL
                      AND rolling_deadline = 0
                  )
                  OR verification_date IS NULL
              )

            ORDER BY scholarship_name
        "
    ],

    'Possible Duplicates' => [
        'description' =>
            'Scholarships sharing the same normalized official URL.',

        'sql' => "
            SELECT
                s.scholarship_id,
                s.scholarship_name,
                s.official_url AS issue_value

            FROM scholarships s

            WHERE s.status = 'active'
              AND s.normalized_url IS NOT NULL
              AND TRIM(s.normalized_url) <> ''

              AND EXISTS (
                  SELECT 1

                  FROM scholarships other

                  WHERE other.status = 'active'
                    AND other.normalized_url = s.normalized_url
                    AND other.scholarship_id <> s.scholarship_id
              )

            ORDER BY
                s.normalized_url,
                s.scholarship_name
        "
    ]
];

// Execute each read-only query.
$results = [];

foreach ($qualityChecks as $name => $check) {

    $stmt = $pdo->query($check['sql']);

    $results[$name] = [
        'description' => $check['description'],
        'records' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ];
}

// Page title.
$pageTitle = 'Data Quality Dashboard | Pathways to Scholarships';

// Shared layout.
require_once $projectRoot . '/includes/header.php';
require_once $projectRoot . '/includes/nav.php';
?>

<main class="container py-5">

    <!-- Page heading -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">

        <div>

            <h1 class="mb-3">
                Data Quality Dashboard
            </h1>

            <p class="lead mb-0">
                Review scholarship data quality issues before
                exporting or sharing the dataset.
            </p>

        </div>

        <a
            href="<?= e($baseUrl . '/admin/dashboard.php') ?>"
            class="btn btn-outline-secondary"
        >
            Back to Admin Dashboard
        </a>

    </div>

    <!-- Data quality summary cards -->
    <div class="row g-3 mb-5">

        <?php foreach ($results as $name => $result): ?>

            <div class="col-md-6 col-lg-3">

                <div class="card h-100 shadow-sm">

                    <div class="card-body">

                        <h2 class="h6">
                            <?= e($name) ?>
                        </h2>

                        <p class="display-6 mb-0">
                            <?= count($result['records']) ?>
                        </p>

                    </div>

                </div>

            </div>

        <?php endforeach; ?>

    </div>

    <!-- Detailed issue tables -->
    <?php foreach ($results as $name => $result): ?>

        <section class="mb-5">

            <h2 class="h4">
                <?= e($name) ?>
            </h2>

            <p class="text-muted">
                <?= e($result['description']) ?>
            </p>

            <?php if ($result['records'] === []): ?>

                <div class="alert alert-success">
                    No matching issues found.
                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table class="table table-striped table-bordered align-middle">

                        <thead>

                            <tr>
                                <th>Scholarship ID</th>
                                <th>Scholarship Name</th>
                                <th>Issue Details</th>
                                <th>Action</th>
                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($result['records'] as $record): ?>

                                <tr>

                                    <td>
                                        <?= (int) $record['scholarship_id'] ?>
                                    </td>

                                    <td>
                                        <?= e((string) $record['scholarship_name']) ?>
                                    </td>

                                    <td>
                                        <?= e(
                                            (string) (
                                                $record['issue_value']
                                                ?? 'Not specified'
                                            )
                                        ) ?>
                                    </td>

                                    <td>

                                        <a
                                            href="<?= e(
                                                $baseUrl . '/admin/scholarship_view.php'
                                            ) ?>?id=<?= (int) $record['scholarship_id'] ?>"
                                            class="btn btn-sm btn-outline-primary"
                                        >
                                            Review
                                        </a>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </section>

    <?php endforeach; ?>

</main>

<?php
require_once $projectRoot . '/includes/footer.php';
?>
