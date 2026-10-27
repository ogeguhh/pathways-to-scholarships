<?php
declare(strict_types=1);

/*
 * Iteration 4 - View Scholarship
 *
 * Displays scholarship information without editing it.
 * Loads the scholarship and its related database records.
 *
 * Development mode only until login is implemented.
 */

// Load the project's existing security and database helpers.
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';

// Use the development administrator role placeholder.
require_role('Administrator');

// Read and validate the scholarship ID from the URL.
$id = filter_var(
    $_GET['id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($id === false || $id === null) {
    http_response_code(400);
    exit('A valid scholarship ID is required.');
}

// Fetch the scholarship along with its provider, state, and theme.
$stmt = $pdo->prepare("
    SELECT
        s.*,
        p.provider_name,
        st.state_name,
        nt.theme_name
    FROM scholarships s
    JOIN providers p ON s.provider_id = p.provider_id
    LEFT JOIN states st ON s.state_id = st.state_id
    LEFT JOIN national_themes nt ON s.theme_id = nt.theme_id
    WHERE s.scholarship_id = ?
    LIMIT 1
");

$stmt->execute([$id]);
$scholarship = $stmt->fetch(PDO::FETCH_ASSOC);

// Return a 404 response when the scholarship does not exist.
if (!$scholarship) {
    http_response_code(404);
    exit('Scholarship not found.');
}

/**
 * Load the names of related grades, categories, or fields.
 *
 * The SQL queries below are fixed in our code.
 * Only the scholarship ID is supplied by the user.
 */
function scholarship_related_values(
    PDO $pdo,
    string $sql,
    int $scholarshipId
): array {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$scholarshipId]);

    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Get eligible grade levels.
$grades = scholarship_related_values(
    $pdo,
    "
    SELECT g.grade_level
    FROM scholarship_grades sg
    JOIN grade_levels g ON sg.grade_id = g.grade_id
    WHERE sg.scholarship_id = ?
    ORDER BY g.grade_level
    ",
    $id
);

// Get scholarship categories.
$categories = scholarship_related_values(
    $pdo,
    "
    SELECT c.category_name
    FROM scholarship_categories sc
    JOIN categories c ON sc.category_id = c.category_id
    WHERE sc.scholarship_id = ?
    ORDER BY c.category_name
    ",
    $id
);

// Get fields of study.
$fields = scholarship_related_values(
    $pdo,
    "
    SELECT f.field_name
    FROM scholarship_fields sf
    JOIN fields_of_study f ON sf.field_id = f.field_id
    WHERE sf.scholarship_id = ?
    ORDER BY f.field_name
    ",
    $id
);

/**
 * Format optional values for display.
 * Empty database values appear as "Not specified".
 */
function scholarship_display(mixed $value): string
{
    if ($value === null || $value === '') {
        return 'Not specified';
    }

    return (string)$value;
}

/**
 * Format a scholarship amount as US dollars.
 */
function scholarship_money(mixed $value): string
{
    if ($value === null || $value === '') {
        return 'Not specified';
    }

    return '$' . number_format((float)$value, 2);
}

/**
 * Display one label and its corresponding value.
 *
 * Escape both strings to prevent HTML injection.
 */
function scholarship_detail(
    string $label,
    mixed $value
): void {
    echo '<dt class="col-sm-4 text-muted">'
        . e($label)
        . '</dt>';

    echo '<dd class="col-sm-8">'
        . e(scholarship_display($value))
        . '</dd>';
}
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <title>
        View Scholarship | Pathways to Scholarships
    </title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
</head>

<body class="bg-light">

<main class="container py-5" style="max-width:950px">

    <a href="../index.php" class="text-decoration-none">
        &larr; Home
    </a>

    <div class="d-flex justify-content-between align-items-start mt-3 mb-3">
        <div>
            <h1>View Scholarship</h1>

            <p class="text-muted">
                Scholarship #<?= e($id) ?>
            </p>
        </div>

        <a
            href="scholarship_edit.php?id=<?= e($id) ?>"
            class="btn btn-primary"
        >
            Edit Scholarship
        </a>
    </div>

    <div class="alert alert-warning">
        <strong>Development mode — no login required.</strong>
        This administrator page is intended for local testing only.
    </div>

    <!-- BASIC INFORMATION -->
    <section class="card mb-4">
        <div class="card-body">
            <h2 class="h5 mb-3">1. Basic Information</h2>

            <dl class="row mb-0">
                <?php
                scholarship_detail(
                    'Scholarship Name',
                    $scholarship['scholarship_name']
                );

                scholarship_detail(
                    'Provider',
                    $scholarship['provider_name']
                );

                scholarship_detail(
                    'Status',
                    ucfirst($scholarship['status'])
                );

                scholarship_detail(
                    'Official URL',
                    $scholarship['official_url']
                );

                scholarship_detail(
                    'Discovery Source',
                    $scholarship['discovery_source']
                );

                scholarship_detail(
                    'Contributing Student',
                    $scholarship['contributing_student']
                );
                ?>
            </dl>
        </div>
    </section>

    <!-- CLASSIFICATION -->
    <section class="card mb-4">
        <div class="card-body">
            <h2 class="h5 mb-3">2. Classification</h2>

            <dl class="row mb-0">
                <?php
                scholarship_detail(
                    'National or State/Local',
                    $scholarship['national_or_state_local']
                );

                scholarship_detail(
                    'State',
                    $scholarship['state_name']
                );

                scholarship_detail(
                    'National Theme',
                    $scholarship['theme_name']
                );

                scholarship_detail(
                    'School Level',
                    $scholarship['school_level']
                );
                ?>
            </dl>
        </div>
    </section>

    <!-- GRADES, CATEGORIES, FIELDS -->
    <section class="card mb-4">
        <div class="card-body">
            <h2 class="h5 mb-3">3. Eligibility Selections</h2>

            <dl class="row mb-0">
                <?php
                scholarship_detail(
                    'Eligible Grades',
                    $grades !== [] ? implode(', ', $grades) : null
                );

                scholarship_detail(
                    'Categories',
                    $categories !== [] ? implode(', ', $categories) : null
                );

                scholarship_detail(
                    'Fields of Study',
                    $fields !== [] ? implode(', ', $fields) : null
                );
                ?>
            </dl>
        </div>
    </section>

    <!-- AWARD INFORMATION -->
    <section class="card mb-4">
        <div class="card-body">
            <h2 class="h5 mb-3">4. Award Information</h2>

            <dl class="row mb-0">
                <?php
                scholarship_detail(
                    'Minimum Award',
                    scholarship_money($scholarship['award_amount_min'])
                );

                scholarship_detail(
                    'Maximum Award',
                    scholarship_money($scholarship['award_amount_max'])
                );

                scholarship_detail(
                    'Maximum Potential Value',
                    scholarship_money($scholarship['maximum_potential_value'])
                );

                scholarship_detail(
                    'Number of Awards',
                    $scholarship['number_of_awards']
                );

                scholarship_detail(
                    'Minimum GPA',
                    $scholarship['minimum_gpa']
                );

                scholarship_detail(
                    'Renewable',
                    $scholarship['renewable']
                );
                ?>
            </dl>
        </div>
    </section>

    <!-- APPLICATION DETAILS -->
    <section class="card mb-4">
        <div class="card-body">
            <h2 class="h5 mb-3">5. Application Details</h2>

            <dl class="row mb-0">
                <?php
                scholarship_detail(
                    'Application Open Date',
                    $scholarship['application_open_date']
                );

                scholarship_detail(
                    'Application Deadline',
                    $scholarship['application_deadline']
                );

                scholarship_detail(
                    'Rolling Deadline',
                    $scholarship['rolling_deadline'] ? 'Yes' : 'No'
                );

                scholarship_detail(
                    'Application Method',
                    $scholarship['application_method']
                );

                scholarship_detail(
                    'Verification Date',
                    $scholarship['verification_date']
                );
                ?>
            </dl>
        </div>
    </section>

    <!-- ELIGIBILITY REQUIREMENTS -->
    <section class="card mb-4">
        <div class="card-body">
            <h2 class="h5 mb-3">6. Scholarship Requirements</h2>

            <dl class="row mb-0">
                <?php
                $requirements = [
                    'Financial Need Required' => 'financial_need_required',
                    'Essay Required' => 'essay_required',
                    'Recommendation Required' => 'recommendation_required',
                    'Transcript/Test Required' => 'transcript_test_required',
                    'Community Service Required' => 'community_service_required',
                    'Leadership Required' => 'leadership_required',
                    'Residency Requirements' => 'residency_requirement',
                    'Other Eligibility' => 'other_eligibility',
                    'Notes' => 'notes'
                ];

                foreach ($requirements as $label => $column) {
                    scholarship_detail(
                        $label,
                        $scholarship[$column]
                    );
                }
                ?>
            </dl>
        </div>
    </section>

    <!-- RECORD INFORMATION -->
    <section class="card mb-4">
        <div class="card-body">
            <h2 class="h5 mb-3">7. Record Information</h2>

            <dl class="row mb-0">
                <?php
                scholarship_detail(
                    'Created By User ID',
                    $scholarship['created_by']
                );

                scholarship_detail(
                    'Updated By User ID',
                    $scholarship['updated_by']
                );

                scholarship_detail(
                    'Created At',
                    $scholarship['created_at']
                );

                scholarship_detail(
                    'Last Updated',
                    $scholarship['updated_at']
                );
                ?>
            </dl>
        </div>
    </section>

    <div class="d-flex gap-2 mb-5">
        <a
            href="scholarship_edit.php?id=<?= e($id) ?>"
            class="btn btn-primary"
        >
            Edit Scholarship
        </a>

        <a
            href="../scholarships.php"
            class="btn btn-outline-secondary"
        >
            Back to Scholarships
        </a>
    </div>

</main>

</body>
</html>
