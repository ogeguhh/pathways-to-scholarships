<?php
declare(strict_types=1);

/*
 * Pathways to Scholarships
 * Iteration 6 — Data Quality Dashboard
 *
 * Reports data quality issues without changing scholarship records.
 */

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/includes/db.php';
require_once $projectRoot . '/includes/security.php';
require_once $projectRoot . '/includes/config.php';

// Keep the same development-only access restriction as
// the existing admin scholarship management page.
require_role('Administrator');

ensure_session();

$pdo = getDBConnection();

// Use a fixed 90-day threshold for stale verification records.
$staleDays = 90;

// Each query is read-only and returns records needing review.
$qualityChecks = [
    'Expired Deadlines' => [
        'description' => 'Scholarships with application deadlines in the past.',
        'sql' => "
            SELECT scholarship_id, scholarship_name,
                   application_deadline AS issue_value
            FROM scholarships
            WHERE status = 'active'
              AND application_deadline < CURRENT_DATE()
            ORDER BY application_deadline ASC
        "
    ],
    'Stale Verification Dates' => [
        'description' => 'Scholarships not verified within the last 90 days.',
        'sql' => "
            SELECT scholarship_id, scholarship_name,
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
        'description' => 'Scholarships with missing important information.',
        'sql' => "
            SELECT scholarship_id, scholarship_name,
                   CONCAT_WS(', ',
                       IF(TRIM(scholarship_name) = '',
                          'Name', NULL),
                       IF(official_url IS NULL
                          OR TRIM(official_url) = '',
                          'Official URL', NULL),
                       IF(application_deadline IS NULL
                          AND rolling_deadline = 0,
                          'Deadline', NULL),
                       IF(verification_date IS NULL,
                          'Verification Date', NULL)
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
        'description' => 'Scholarships sharing the same normalized official URL.',
        'sql' => "
            SELECT s.scholarship_id, s.scholarship_name,
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
            ORDER BY s.normalized_url, s.scholarship_name
        "
    ]
];

$results = [];

foreach ($qualityChecks as $name => $check) {
    $stmt = $pdo->query($check['sql']);

    $results[$name] = [
        'description' => $check['description'],
        'records' => $stmt->fetchAll()
    ];
}

$pageTitle = 'Data Quality Dashboard | Pathways to Scholarships';

require_once $projectRoot . '/includes/header.php';
require_once $projectRoot . '/includes/nav.php';
?>

<main class="container py-5">
    <h1 class="mb-3">Data Quality Dashboard</h1>

    <p class="lead">
        Review scholarship data quality issues before exporting
        or sharing the dataset.
    </p>

    <div class="row g-3 mb-5">
        <?php foreach ($results as $name => $result): ?>
            <div class="col-md-6 col-lg-3">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <h2 class="h6"><?= e($name) ?></h2>

                        <p class="display-6 mb-0">
                            <?= count($result['records']) ?>
                        </p>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php foreach ($results as $name => $result): ?>
        <section class="mb-5">
            <h2 class="h4"><?= e($name) ?></h2>

            <p class="text-muted">
                <?= e($result['description']) ?>
            </p>

            <?php if ($result['records'] === []): ?>
                <div class="alert alert-success">
                    No matching issues found.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered">
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
                                        <?= e($record['scholarship_name']) ?>
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
                                            href="/admin/scholarship_view.php?id=<?= (int) $record['scholarship_id'] ?>"
                                            class="btn btn-sm btn-outline-primary">
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
