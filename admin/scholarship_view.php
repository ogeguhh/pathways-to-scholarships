<?php
declare(strict_types=1);

/*
 * Pathways to Scholarships
 * Iteration 4 — View Scholarship
 *
 * Displays a scholarship and its related eligibility
 * information without allowing database changes.
 */

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/includes/db.php';
require_once $projectRoot . '/includes/security.php';
require_once $projectRoot . '/includes/config.php';

require_role(['Administrator', 'Super-admin']);

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    http_response_code(400);
    exit('Invalid scholarship ID.');
}

$pdo = getDBConnection();

// Retrieve the scholarship and its reference information.
$stmt = $pdo->prepare(
    'SELECT
        s.*,
        p.provider_name,
        pt.type_name AS provider_type,
        st.state_name,
        nt.theme_name
     FROM scholarships s
     JOIN providers p
       ON p.provider_id = s.provider_id
     JOIN provider_types pt
       ON pt.provider_type_id = p.provider_type_id
     LEFT JOIN states st
       ON st.state_id = s.state_id
     LEFT JOIN national_themes nt
       ON nt.theme_id = s.theme_id
     WHERE s.scholarship_id = ?'
);

$stmt->execute([$id]);
$scholarship = $stmt->fetch();

if (!$scholarship) {
    http_response_code(404);
    exit('Scholarship not found.');
}

// Retrieve many-to-many values using their relationship tables.
function scholarshipRelatedValues(
    PDO $pdo,
    int $id,
    string $sql
): array {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);

    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

$grades = scholarshipRelatedValues(
    $pdo,
    $id,
    'SELECT g.grade_number
     FROM scholarship_grades sg
     JOIN grade_levels g ON g.grade_id = sg.grade_id
     WHERE sg.scholarship_id = ?
     ORDER BY g.grade_number'
);

$categories = scholarshipRelatedValues(
    $pdo,
    $id,
    'SELECT c.category_name
     FROM scholarship_categories sc
     JOIN categories c ON c.category_id = sc.category_id
     WHERE sc.scholarship_id = ?
     ORDER BY c.category_name'
);

$fields = scholarshipRelatedValues(
    $pdo,
    $id,
    'SELECT f.field_name
     FROM scholarship_fields sf
     JOIN fields_of_study f ON f.field_id = sf.field_id
     WHERE sf.scholarship_id = ?
     ORDER BY f.field_name'
);

// Format empty values without inventing missing information.
function displayScholarshipValue(mixed $value): string
{
    if ($value === null || $value === '') {
        return 'Not provided';
    }

    return e((string) $value);
}

$pageTitle = 'View Scholarship | Pathways to Scholarships';

require_once $projectRoot . '/includes/header.php';
require_once $projectRoot . '/includes/nav.php';
?>

<main class="container py-5">

    <h1 class="mb-3">
        <?= e($scholarship['scholarship_name']) ?>
    </h1>

    <p class="text-muted">
        Scholarship #<?= (int) $scholarship['scholarship_id'] ?>
        — <?= e($scholarship['status']) ?>
    </p>

    <div class="d-flex gap-2 mb-4">
        <a href="scholarship_form.php?id=<?= $id ?>"
           class="btn btn-primary">
            Edit Scholarship
        </a>

        <a href="scholarships.php"
           class="btn btn-outline-secondary">
            Back to Scholarships
        </a>
    </div>

    <?php
    // Display all saved scholarship fields in organized sections.
    $sections = [
        'Basic Information' => [
            'Provider' => $scholarship['provider_name'],
            'Provider Type' => $scholarship['provider_type'],
            'Official URL' => $scholarship['official_url'],
            'Discovery Source' => $scholarship['discovery_source'],
            'National or State/Local' =>
                $scholarship['national_or_state_local'],
            'State' => $scholarship['state_name'],
            'National Theme' => $scholarship['theme_name'],
            'School Level' => $scholarship['school_level'],
            'Contributing Student' =>
                $scholarship['contributing_student'],
            'Import Batch' => $scholarship['import_batch_id']
        ],

        'Award Information' => [
            'Minimum Award' => $scholarship['award_amount_min'],
            'Maximum Award' => $scholarship['award_amount_max'],
            'Number of Awards' => $scholarship['number_of_awards'],
            'Renewable' => $scholarship['renewable'],
            'Maximum Potential Value' =>
                $scholarship['maximum_potential_value']
        ],

        'Application Information' => [
            'Application Open Date' =>
                $scholarship['application_open_date'],
            'Application Deadline' =>
                $scholarship['application_deadline'],
            'Rolling Deadline' =>
                (int) $scholarship['rolling_deadline'] === 1
                    ? 'Yes' : 'No',
            'Application Method' =>
                $scholarship['application_method'],
            'Verification Date' =>
                $scholarship['verification_date']
        ],

        'Eligibility Requirements' => [
            'Residency Requirement' =>
                $scholarship['residency_requirement'],
            'Minimum GPA' => $scholarship['minimum_gpa'],
            'Financial Need Required' =>
                $scholarship['financial_need_required'],
            'Essay Required' => $scholarship['essay_required'],
            'Recommendation Required' =>
                $scholarship['recommendation_required'],
            'Transcript/Test Required' =>
                $scholarship['transcript_test_required'],
            'Community Service Required' =>
                $scholarship['community_service_required'],
            'Leadership Required' =>
                $scholarship['leadership_required'],
            'Other Eligibility' =>
                $scholarship['other_eligibility']
        ],

        'Additional Information' => [
            'Notes' => $scholarship['notes'],
            'Created At' => $scholarship['created_at'],
            'Updated At' => $scholarship['updated_at']
        ]
    ];
    ?>

    <?php foreach ($sections as $heading => $items): ?>

        <h2 class="h4 mt-4"><?= e($heading) ?></h2>

        <div class="table-responsive">
            <table class="table table-bordered">
                <tbody>
                    <?php foreach ($items as $label => $value): ?>
                        <tr>
                            <th style="width: 35%">
                                <?= e($label) ?>
                            </th>
                            <td>
                                <?php if ($label === 'Official URL'
                                    && !empty($value)): ?>
                                    <a href="<?= e($value) ?>"
                                       target="_blank"
                                       rel="noopener noreferrer">
                                        <?= e($value) ?>
                                    </a>
                                <?php else: ?>
                                    <?= displayScholarshipValue($value) ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php endforeach; ?>

    <h2 class="h4 mt-4">Related Eligibility Information</h2>

    <table class="table table-bordered">
        <tbody>
            <tr>
                <th style="width: 35%">Eligible Grades</th>
                <td>
                    <?= $grades
                        ? e(implode(', ', $grades))
                        : 'Not provided' ?>
                </td>
            </tr>

            <tr>
                <th>Scholarship Categories</th>
                <td>
                    <?= $categories
                        ? e(implode(', ', $categories))
                        : 'Not provided' ?>
                </td>
            </tr>

            <tr>
                <th>Fields of Study</th>
                <td>
                    <?= $fields
                        ? e(implode(', ', $fields))
                        : 'Not provided' ?>
                </td>
            </tr>
        </tbody>
    </table>

</main>

<?php require_once $projectRoot . '/includes/footer.php'; ?>
