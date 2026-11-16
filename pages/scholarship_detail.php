<?php
declare(strict_types=1);

/*
 * Pathways to Scholarships
 * Iteration 5 — Public Scholarship Details
 *
 * Displays an active scholarship and its eligibility
 * information for students and parents.
 */

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/includes/db.php';
require_once $projectRoot . '/includes/security.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    http_response_code(400);
    exit('Invalid scholarship ID.');
}

$pdo = getDBConnection();

// Only active scholarships are visible to public visitors.
$stmt = $pdo->prepare(
    'SELECT
        s.*,
        p.provider_name,
        st.state_name,
        nt.theme_name
     FROM scholarships s
     JOIN providers p ON p.provider_id = s.provider_id
     LEFT JOIN states st ON st.state_id = s.state_id
     LEFT JOIN national_themes nt ON nt.theme_id = s.theme_id
     WHERE s.scholarship_id = ?
       AND s.status = ?'
);

$stmt->execute([$id, 'active']);
$scholarship = $stmt->fetch();

if (!$scholarship) {
    http_response_code(404);
    exit('Scholarship not found.');
}

// Load related grade levels, categories, and fields of study.
$relationships = [
    'grades' => '
        SELECT g.grade_number
        FROM scholarship_grades sg
        JOIN grade_levels g ON g.grade_id = sg.grade_id
        WHERE sg.scholarship_id = ?
        ORDER BY g.grade_number',
    'categories' => '
        SELECT c.category_name
        FROM scholarship_categories sc
        JOIN categories c ON c.category_id = sc.category_id
        WHERE sc.scholarship_id = ?
        ORDER BY c.category_name',
    'fields' => '
        SELECT f.field_name
        FROM scholarship_fields sf
        JOIN fields_of_study f ON f.field_id = sf.field_id
        WHERE sf.scholarship_id = ?
        ORDER BY f.field_name'
];

$related = [];

foreach ($relationships as $key => $sql) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $related[$key] = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Display missing information without inventing values.
function detailValue(mixed $value): string
{
    return $value === null || $value === ''
        ? 'Not specified'
        : e((string) $value);
}

$pageTitle = $scholarship['scholarship_name']
    . ' | Pathways to Scholarships';

require_once $projectRoot . '/includes/header.php';
require_once $projectRoot . '/includes/nav.php';
require_once $projectRoot . '/includes/dev_banner.php';
?>

<main class="container py-5">

    <h1 class="mb-3">
        <?= e($scholarship['scholarship_name']) ?>
    </h1>

    <p class="lead">
        <?= e($scholarship['provider_name']) ?>
    </p>

    <div class="mb-4">
        <a href="/pages/scholarships.php"
           class="btn btn-outline-secondary">
            Back to Search
        </a>

        <?php
        $url = $scholarship['official_url'];
        $scheme = strtolower(
            (string) parse_url($url, PHP_URL_SCHEME)
        );
        ?>

        <?php if (in_array($scheme, ['http', 'https'], true)): ?>
            <a href="<?= e($url) ?>"
               target="_blank"
               rel="noopener noreferrer"
               class="btn btn-primary">
                Official Scholarship Website
            </a>
        <?php endif; ?>
    </div>

    <h2 class="h4">Scholarship Information</h2>

    <?php
    $details = [
        'Type' => $scholarship['national_or_state_local'],
        'State' => $scholarship['state_name'],
        'National Theme' => $scholarship['theme_name'],
        'School Level' => $scholarship['school_level'],
        'Minimum Award' => $scholarship['award_amount_min'],
        'Maximum Award' => $scholarship['award_amount_max'],
        'Renewable' => $scholarship['renewable'],
        'Application Deadline' =>
            $scholarship['application_deadline'],
        'Rolling Deadline' =>
            (int) $scholarship['rolling_deadline'] === 1
                ? 'Yes' : 'No',
        'Verification Date' => $scholarship['verification_date'],
        'Minimum GPA' => $scholarship['minimum_gpa'],
        'Residency Requirement' =>
            $scholarship['residency_requirement'],
        'Application Method' =>
            $scholarship['application_method']
    ];
    ?>

    <div class="table-responsive">
        <table class="table table-bordered">
            <tbody>
                <?php foreach ($details as $label => $value): ?>
                    <tr>
                        <th style="width: 35%">
                            <?= e($label) ?>
                        </th>
                        <td><?= detailValue($value) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h2 class="h4 mt-4">Eligibility</h2>

    <p>
        <strong>Eligible Grades:</strong>
        <?= $related['grades']
            ? e(implode(', ', $related['grades']))
            : 'Not specified' ?>
    </p>

    <p>
        <strong>Categories:</strong>
        <?= $related['categories']
            ? e(implode(', ', $related['categories']))
            : 'Not specified' ?>
    </p>

    <p>
        <strong>Fields of Study:</strong>
        <?= $related['fields']
            ? e(implode(', ', $related['fields']))
            : 'Not specified' ?>
    </p>

    <?php if (!empty($scholarship['other_eligibility'])): ?>
        <h2 class="h4 mt-4">Other Eligibility Requirements</h2>
        <p><?= e($scholarship['other_eligibility']) ?></p>
    <?php endif; ?>

    <?php if (!empty($scholarship['notes'])): ?>
        <h2 class="h4 mt-4">Additional Notes</h2>
        <p><?= e($scholarship['notes']) ?></p>
    <?php endif; ?>

</main>

<?php require_once $projectRoot . '/includes/footer.php'; ?>
