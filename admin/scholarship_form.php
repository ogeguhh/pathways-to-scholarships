<?php
declare(strict_types=1);

/*
 * Pathways to Scholarships
 * Iteration 4 — Create/Edit Scholarship Form
 *
 * One form handles both creating and editing scholarships.
 * The save handler will be implemented separately.
 */

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/includes/db.php';
require_once $projectRoot . '/includes/security.php';
require_once $projectRoot . '/includes/config.php';

// allow admin pages only in local development.
require_role(['Administrator', 'Super-admin']);

$pdo = getDBConnection();

// A positive ID means we are editing an existing scholarship.
// No ID means we are creating a new scholarship.
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (isset($_GET['id']) && (!$id || $id < 1)) {
    http_response_code(400);
    exit('Invalid scholarship ID.');
}

$isEditing = $id !== null && $id !== false;

// Load dropdown options from existing reference tables.
$providers = $pdo->query(
    'SELECT provider_id, provider_name
     FROM providers ORDER BY provider_name'
)->fetchAll();

$states = $pdo->query(
    'SELECT state_id, state_name
     FROM states ORDER BY state_name'
)->fetchAll();

$themes = $pdo->query(
    'SELECT theme_id, theme_name
     FROM national_themes ORDER BY theme_name'
)->fetchAll();

$grades = $pdo->query(
    'SELECT grade_id, grade_number
     FROM grade_levels ORDER BY grade_number'
)->fetchAll();

$categories = $pdo->query(
    'SELECT category_id, category_name
     FROM categories ORDER BY category_name'
)->fetchAll();

$fields = $pdo->query(
    'SELECT field_id, field_name
     FROM fields_of_study ORDER BY field_name'
)->fetchAll();

// Empty defaults allow the same form to work for new records.
$scholarship = [
    'scholarship_name' => '',
    'provider_id' => '',
    'official_url' => '',
    'discovery_source' => '',
    'national_or_state_local' => 'State/Local',
    'state_id' => '',
    'theme_id' => '',
    'school_level' => '',
    'award_amount_min' => '',
    'award_amount_max' => '',
    'number_of_awards' => '',
    'renewable' => 'Unknown',
    'maximum_potential_value' => '',
    'application_open_date' => '',
    'application_deadline' => '',
    'rolling_deadline' => 0,
    'residency_requirement' => '',
    'minimum_gpa' => '',
    'financial_need_required' => 'Unknown',
    'essay_required' => 'Unknown',
    'recommendation_required' => 'Unknown',
    'transcript_test_required' => 'Unknown',
    'community_service_required' => 'Unknown',
    'leadership_required' => 'Unknown',
    'other_eligibility' => '',
    'application_method' => '',
    'verification_date' => '',
    'notes' => '',
    'contributing_student' => '',
];

$selectedGrades = [];
$selectedCategories = [];
$selectedFields = [];

// Editing requires loading the existing scholarship and its relationships.
if ($isEditing) {
    $stmt = $pdo->prepare(
        'SELECT * FROM scholarships WHERE scholarship_id = ?'
    );
    $stmt->execute([$id]);
    $existing = $stmt->fetch();

    if (!$existing) {
        http_response_code(404);
        exit('Scholarship not found.');
    }

    $scholarship = array_merge($scholarship, $existing);

    $relations = [
        'scholarship_grades' => ['grade_id', 'grades'],
        'scholarship_categories' => ['category_id', 'categories'],
        'scholarship_fields' => ['field_id', 'fields'],
    ];

    foreach ($relations as $table => [$column, $type]) {
        $stmt = $pdo->prepare(
            "SELECT `$column` FROM `$table` WHERE scholarship_id = ?"
        );
        $stmt->execute([$id]);

        $values = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        if ($type === 'grades') {
            $selectedGrades = $values;
        } elseif ($type === 'categories') {
            $selectedCategories = $values;
        } else {
            $selectedFields = $values;
        }
    }
}

// Convert database NULL values into form-friendly strings.
function formValue(array $data, string $field): string
{
    return e((string) ($data[$field] ?? ''));
}

// Render a reusable Bootstrap dropdown.
function formSelect(
    string $name,
    string $label,
    array $options,
    mixed $selected,
    bool $required = false
): void {
    echo '<div class="col-md-6">';
    echo '<label class="form-label" for="' . e($name) . '">'
        . e($label) . '</label>';

    echo '<select class="form-select" id="' . e($name)
        . '" name="' . e($name) . '"'
        . ($required ? ' required' : '') . '>';

    echo '<option value="">Select an option</option>';

    foreach ($options as $value => $text) {
        $isSelected = (string) $selected === (string) $value;

        echo '<option value="' . e((string) $value) . '"'
            . ($isSelected ? ' selected' : '') . '>'
            . e((string) $text) . '</option>';
    }

    echo '</select></div>';
}

function formInput(
    string $name,
    string $label,
    array $data,
    string $type = 'text',
    bool $required = false
): void {
    echo '<div class="col-md-6">';
    echo '<label class="form-label" for="' . e($name) . '">'
        . e($label) . '</label>';

    echo '<input class="form-control" type="' . e($type)
        . '" id="' . e($name)
        . '" name="' . e($name)
        . '" value="' . formValue($data, $name) . '"'
        . ($type === 'number' ? ' step="0.01" min="0"' : '')
        . ($required ? ' required' : '') . '>';

    echo '</div>';
}

function formTextarea(
    string $name,
    string $label,
    array $data
): void {
    echo '<div class="col-12">';
    echo '<label class="form-label" for="' . e($name) . '">'
        . e($label) . '</label>';

    echo '<textarea class="form-control" rows="3" id="'
        . e($name) . '" name="' . e($name) . '">'
        . formValue($data, $name) . '</textarea>';

    echo '</div>';
}

function yesNoOptions(): array
{
    return [
        'Yes' => 'Yes',
        'No' => 'No',
        'Unknown' => 'Unknown',
    ];
}

$pageTitle = ($isEditing ? 'Edit' : 'Create')
    . ' Scholarship | Pathways to Scholarships';

require_once $projectRoot . '/includes/header.php';
require_once $projectRoot . '/includes/nav.php';
?>

<main class="container py-5">

    <h1 class="mb-3">
        <?= $isEditing ? 'Edit Scholarship' : 'Create Scholarship' ?>
    </h1>

    <p class="text-muted">
        Manage scholarship details and eligibility information.
    </p>

    <form id="scholarshipForm"
          action="scholarship_save.php"
          method="post"
          novalidate>

        <input type="hidden"
               name="csrf_token"
               value="<?= e(csrf_token()) ?>">

        <input type="hidden"
               name="scholarship_id"
               value="<?= $isEditing ? (int) $id : '' ?>">

        <h2 class="h4 mt-4">Basic Information</h2>

        <div class="row g-3">

            <?php
            formInput(
                'scholarship_name',
                'Scholarship Name',
                $scholarship,
                'text',
                true
            );

            formSelect(
                'provider_id',
                'Scholarship Provider',
                array_column($providers, 'provider_name', 'provider_id'),
                $scholarship['provider_id'],
                true
            );

            formInput(
                'official_url',
                'Official Scholarship URL',
                $scholarship,
                'url',
                true
            );

            formSelect(
                'national_or_state_local',
                'National or State/Local',
                ['National' => 'National', 'State/Local' => 'State/Local'],
                $scholarship['national_or_state_local'],
                true
            );

            formSelect(
                'state_id',
                'State',
                array_column($states, 'state_name', 'state_id'),
                $scholarship['state_id']
            );

            formSelect(
                'theme_id',
                'National Theme',
                array_column($themes, 'theme_name', 'theme_id'),
                $scholarship['theme_id']
            );

            formSelect(
                'school_level',
                'School Level',
                [
                    'Middle School' => 'Middle School',
                    'High School' => 'High School',
                    'Both' => 'Both',
                ],
                $scholarship['school_level']
            );

            formInput(
                'contributing_student',
                'Contributing Student',
                $scholarship
            );

            formTextarea(
                'discovery_source',
                'Discovery Source',
                $scholarship
            );
            ?>

        </div>

        <h2 class="h4 mt-5">Award Information</h2>

        <div class="row g-3">

            <?php
            formInput('award_amount_min', 'Minimum Award Amount', $scholarship, 'number');
            formInput('award_amount_max', 'Maximum Award Amount', $scholarship, 'number');
            formInput('number_of_awards', 'Number of Awards', $scholarship, 'number');
            formInput('maximum_potential_value', 'Maximum Potential Value', $scholarship, 'number');

            formSelect(
                'renewable',
                'Renewable',
                yesNoOptions(),
                $scholarship['renewable']
            );
            ?>

        </div>

        <h2 class="h4 mt-5">Application Information</h2>

        <div class="row g-3">

            <?php
            formInput('application_open_date', 'Application Open Date', $scholarship, 'date');
            formInput('application_deadline', 'Application Deadline', $scholarship, 'date');

            formSelect(
                'application_method',
                'Application Method',
                [
                    'Online Form' => 'Online Form',
                    'Email' => 'Email',
                    'Mail' => 'Mail',
                    'Other' => 'Other',
                ],
                $scholarship['application_method']
            );
            ?>

            <div class="col-md-6 d-flex align-items-end">
                <div class="form-check mb-2">
                    <input class="form-check-input"
                           type="checkbox"
                           id="rolling_deadline"
                           name="rolling_deadline"
                           value="1"
                           <?= (int) $scholarship['rolling_deadline'] === 1
                               ? 'checked' : '' ?>>

                    <label class="form-check-label" for="rolling_deadline">
                        Rolling Deadline
                    </label>
                </div>
            </div>

            <?php
            formInput('verification_date', 'Verification Date', $scholarship, 'date');
            ?>

        </div>

        <h2 class="h4 mt-5">Eligibility Requirements</h2>

        <div class="row g-3">

            <?php
            formInput('minimum_gpa', 'Minimum GPA', $scholarship, 'number');

            foreach ([
                'financial_need_required' => 'Financial Need Required',
                'essay_required' => 'Essay Required',
                'recommendation_required' => 'Recommendation Required',
                'transcript_test_required' => 'Transcript/Test Required',
                'community_service_required' => 'Community Service Required',
                'leadership_required' => 'Leadership Required',
            ] as $name => $label) {
                formSelect(
                    $name,
                    $label,
                    yesNoOptions(),
                    $scholarship[$name]
                );
            }

            formTextarea(
                'residency_requirement',
                'Residency Requirement',
                $scholarship
            );

            formTextarea(
                'other_eligibility',
                'Other Eligibility Requirements',
                $scholarship
            );
            ?>

        </div>

        <h2 class="h4 mt-5">Grades, Categories, and Fields</h2>

        <div class="row g-4">

            <div class="col-md-4">
                <h3 class="h6">Eligible Grades</h3>

                <?php foreach ($grades as $grade): ?>
                    <div class="form-check">
                        <input class="form-check-input"
                               type="checkbox"
                               name="grades[]"
                               value="<?= (int) $grade['grade_id'] ?>"
                               id="grade<?= (int) $grade['grade_id'] ?>"
                               <?= in_array(
                                   (int) $grade['grade_id'],
                                   $selectedGrades,
                                   true
                               ) ? 'checked' : '' ?>>

                        <label class="form-check-label"
                               for="grade<?= (int) $grade['grade_id'] ?>">
                            Grade <?= (int) $grade['grade_number'] ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="col-md-4">
                <h3 class="h6">Scholarship Categories</h3>

                <?php foreach ($categories as $category): ?>
                    <div class="form-check">
                        <input class="form-check-input"
                               type="checkbox"
                               name="categories[]"
                               value="<?= (int) $category['category_id'] ?>"
                               id="category<?= (int) $category['category_id'] ?>"
                               <?= in_array(
                                   (int) $category['category_id'],
                                   $selectedCategories,
                                   true
                               ) ? 'checked' : '' ?>>

                        <label class="form-check-label"
                               for="category<?= (int) $category['category_id'] ?>">
                            <?= e($category['category_name']) ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="col-md-4">
                <h3 class="h6">Fields of Study</h3>

                <?php foreach ($fields as $field): ?>
                    <div class="form-check">
                        <input class="form-check-input"
                               type="checkbox"
                               name="fields[]"
                               value="<?= (int) $field['field_id'] ?>"
                               id="field<?= (int) $field['field_id'] ?>"
                               <?= in_array(
                                   (int) $field['field_id'],
                                   $selectedFields,
                                   true
                               ) ? 'checked' : '' ?>>

                        <label class="form-check-label"
                               for="field<?= (int) $field['field_id'] ?>">
                            <?= e($field['field_name']) ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>

        </div>

        <h2 class="h4 mt-5">Additional Notes</h2>

        <div class="row g-3">
            <?php formTextarea('notes', 'Notes', $scholarship); ?>
        </div>

        <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary">
                <?= $isEditing ? 'Save Changes' : 'Create Scholarship' ?>
            </button>

            <a href="scholarships.php"
               class="btn btn-outline-secondary">
                Cancel
            </a>
        </div>

    </form>

</main>


<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.21.0/jquery.validate.min.js"></script>

<script>
$(function () {
    // Match the optional decimal limits enforced by scholarship_save.php.
    $.validator.addMethod('scholarshipDecimal', function (value, element, max) {
        if (this.optional(element)) {
            return true;
        }

        return /^\d+(?:\.\d{1,2})?$/.test(value)
            && Number(value) <= max;
    }, 'Enter a valid nonnegative number with at most two decimal places.');

    // PHP accepts only whole numbers from 0 through 2147483647.
    $.validator.addMethod('wholeNumber', function (value, element) {
        if (this.optional(element)) {
            return true;
        }

        return /^\d+$/.test(value) && Number(value) <= 2147483647;
    }, 'Enter a nonnegative whole number.');

    // Require an HTTP or HTTPS URL, as the server does.
    $.validator.addMethod('httpUrl', function (value, element) {
        if (this.optional(element)) {
            return true;
        }

        try {
            const url = new URL(value);
            return ['http:', 'https:'].includes(url.protocol)
                && url.hostname !== '';
        } catch (error) {
            return false;
        }
    }, 'Enter a valid HTTP or HTTPS URL.');

    // Ensure the maximum award is not smaller than the minimum.
    $.validator.addMethod('awardRange', function (value, element) {
        if (this.optional(element)) {
            return true;
        }

        const minimum = $('#award_amount_min').val();

        return minimum === '' || Number(value) >= Number(minimum);
    }, 'Maximum award cannot be less than minimum award.');

    $('#number_of_awards').attr('step', '1');

    $('#scholarshipForm').validate({
        rules: {
            scholarship_name: {
                required: true,
                maxlength: 255
            },
            provider_id: {
                required: true
            },
            official_url: {
                required: true,
                maxlength: 1000,
                httpUrl: true
            },
            national_or_state_local: {
                required: true
            },
            renewable: {
                required: true
            },
            financial_need_required: {
                required: true
            },
            essay_required: {
                required: true
            },
            recommendation_required: {
                required: true
            },
            transcript_test_required: {
                required: true
            },
            community_service_required: {
                required: true
            },
            leadership_required: {
                required: true
            },
            award_amount_min: {
                scholarshipDecimal: 9999999999.99
            },
            award_amount_max: {
                scholarshipDecimal: 9999999999.99,
                awardRange: true
            },
            maximum_potential_value: {
                scholarshipDecimal: 9999999999.99
            },
            minimum_gpa: {
                scholarshipDecimal: 4
            },
            number_of_awards: {
                wholeNumber: true
            },
            contributing_student: {
                maxlength: 150
            }
        },

        // Display errors beside the relevant input.
        errorElement: 'div',
        errorClass: 'text-danger small mt-1',

        highlight: function (element) {
            $(element).addClass('is-invalid');
        },

        unhighlight: function (element) {
            $(element).removeClass('is-invalid');
        },

        errorPlacement: function (error, element) {
            error.insertAfter(element);
        }
    });
});
</script>

<?php require_once $projectRoot . '/includes/footer.php'; ?>
