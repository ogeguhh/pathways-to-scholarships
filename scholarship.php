<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/db.php';

$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

$scholarship = null;
$loadError = false;

// Retrieve the selected scholarship
if ($id !== false && $id !== null) {
    try {
        if (!isset($pdo) || !($pdo instanceof PDO)) {
            throw new RuntimeException('PDO connection unavailable');
        }

        $sql = "
            SELECT
                sc.*,
                p.provider_name,
                st.state_name
            FROM scholarships AS sc
            JOIN providers AS p
                ON p.provider_id = sc.provider_id
            LEFT JOIN states AS st
                ON st.state_id = sc.state_id
            WHERE sc.scholarship_id = :id
              AND sc.status = 'active'
            LIMIT 1
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);

        $scholarship = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    } catch (Throwable $ex) {
        error_log('Scholarship detail error: ' . $ex->getMessage());
        $loadError = true;
        http_response_code(500);
    }
}

if (!$scholarship && !$loadError) {
    http_response_code(404);
}

// Safely display optional database values
function displayValue($value): string
{
    if ($value === null || $value === '') {
        return 'Not specified';
    }

    return e((string) $value);
}

// Format scholarship award amounts
function displayAmount($value): string
{
    if ($value === null) {
        return 'Not specified';
    }

    return '$' . number_format((float) $value, 2);
}

// Validate official URL
$officialUrl = $scholarship['official_url'] ?? '';

$safeUrl = filter_var($officialUrl, FILTER_VALIDATE_URL)
    && in_array(
        strtolower((string) parse_url($officialUrl, PHP_URL_SCHEME)),
        ['http', 'https'],
        true
    );
?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>
        <?= $scholarship
            ? e($scholarship['scholarship_name'])
            : 'Scholarship not found' ?>
        | Pathways to Scholarships
    </title>

    <link rel="stylesheet" href="assets/css/site.css">
    <link rel="stylesheet" href="assets/css/responsive-search.css">
</head>

<body>

<nav class="site-nav">
    <a href="index.php">Pathways to Scholarships</a> |
    <a href="index.php">Home</a> |
    <a href="about.php">About</a> |
    <a href="faq.php">FAQ</a> |
    <a href="contact.php">Contact</a> |
    <a href="scholarships.php">Scholarships</a>
</nav>

<main class="site-main">

    <p>
        <a href="scholarships.php">
            ← Back to scholarship search
        </a>
    </p>

    <?php if ($loadError): ?>

        <h1>Unable to load scholarship</h1>
        <p>Please try again later.</p>

    <?php elseif (!$scholarship): ?>

        <h1>Scholarship not found</h1>
        <p>This scholarship is unavailable or the link is invalid.</p>

    <?php else: ?>

        <h1>
            <?= e($scholarship['scholarship_name']) ?>
        </h1>

        <section class="detail-card">

            <h2>Scholarship Information</h2>

            <dl class="detail-list">

                <dt>Provider</dt>
                <dd>
                    <?= displayValue($scholarship['provider_name']) ?>
                </dd>

                <dt>Location</dt>
                <dd>
                    <?= displayValue(
                        $scholarship['state_name']
                        ?? $scholarship['national_or_state_local']
                    ) ?>
                </dd>

                <dt>School Level</dt>
                <dd>
                    <?= displayValue($scholarship['school_level']) ?>
                </dd>

                <dt>Minimum Award</dt>
                <dd>
                    <?= displayAmount($scholarship['award_amount_min']) ?>
                </dd>

                <dt>Maximum Award</dt>
                <dd>
                    <?= displayAmount($scholarship['award_amount_max']) ?>
                </dd>

                <dt>Application Opens</dt>
                <dd>
                    <?= displayValue($scholarship['application_open_date']) ?>
                </dd>

                <dt>Application Deadline</dt>
                <dd>
                    <?php if (!empty($scholarship['rolling_deadline'])): ?>
                        Rolling deadline
                    <?php else: ?>
                        <?= displayValue(
                            $scholarship['application_deadline']
                        ) ?>
                    <?php endif; ?>
                </dd>

                <dt>Minimum GPA</dt>
                <dd>
                    <?= displayValue($scholarship['minimum_gpa']) ?>
                </dd>

                <dt>Financial Need Required</dt>
                <dd>
                    <?= displayValue(
                        $scholarship['financial_need_required']
                    ) ?>
                </dd>

                <dt>Essay Required</dt>
                <dd>
                    <?= displayValue($scholarship['essay_required']) ?>
                </dd>

                <dt>Recommendation Required</dt>
                <dd>
                    <?= displayValue(
                        $scholarship['recommendation_required']
                    ) ?>
                </dd>

                <dt>Residency Requirement</dt>
                <dd>
                    <?= displayValue(
                        $scholarship['residency_requirement']
                    ) ?>
                </dd>

                <dt>Other Eligibility</dt>
                <dd>
                    <?= displayValue($scholarship['other_eligibility']) ?>
                </dd>

                <dt>Verification Date</dt>
                <dd>
                    <?= displayValue($scholarship['verification_date']) ?>
                </dd>

                <dt>Official Scholarship URL</dt>
                <dd>
                    <?php if ($safeUrl): ?>

                        <a
                            href="<?= e($officialUrl) ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <?= e($officialUrl) ?>
                        </a>

                    <?php else: ?>

                        Not available

                    <?php endif; ?>
                </dd>

            </dl>

            <?php if ($safeUrl): ?>

                <p>
                    <a
                        class="official-link"
                        href="<?= e($officialUrl) ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        Visit Official Scholarship Website
                    </a>
                </p>

            <?php endif; ?>

        </section>

    <?php endif; ?>

</main>

<footer class="site-footer">
    <p>
        Pathways to Scholarships - ICS 325 Final Project
    </p>
</footer>

</body>
</html>
