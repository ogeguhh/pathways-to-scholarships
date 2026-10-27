
<?php
/*
 * Iteration 4 - Shared Scholarship Form
 *
 * Used by the Create and Edit scholarship pages.
 *
 * The parent page supplies:
 * $options - dropdown and checkbox data from MySQL
 * $values  - existing or submitted scholarship values
 * $errors  - server-side validation errors
 * $action  - form submission URL
 *
 * The parent page must load security.php first.
 */

$options = $options ?? [];
$values = $values ?? [];
$errors = $errors ?? [];
$action = $action ?? '';

// Read a form value and preserve it after validation errors.
$formValue = static function (string $field) use ($values): string {
    $value = $values[$field] ?? '';
    return is_scalar($value) ? (string)$value : '';
};

// Display an error message for a field.
$formError = static function (string $field) use ($errors): void {
    if (isset($errors[$field])) {
        echo '<div class="invalid-feedback d-block">'
            . e($errors[$field])
            . '</div>';
    }
};

// Determine whether a dropdown option is selected.
$isSelected = static function (
    string $field,
    string|int $value
) use ($formValue): string {
    return $formValue($field) === (string)$value
        ? 'selected'
        : '';
};

// Determine whether a checkbox option is selected.
$isChecked = static function (
    string $field,
    string|int $value
) use ($values): string {
    $selected = $values[$field] ?? [];

    return is_array($selected) &&
        in_array((string)$value, array_map('strval', $selected), true)
        ? 'checked'
        : '';
};
?>

<form
    id="scholarshipForm"
    method="post"
    action="<?= e($action) ?>"
    novalidate
>
    <!-- CSRF protects database-changing form submissions. -->
    <input
        type="hidden"
        name="csrf_token"
        value="<?= e(csrf_token()) ?>"
    >

    <!-- SECTION 1: BASIC INFORMATION -->
    <section class="card mb-4">
        <div class="card-body">
            <h2 class="h5">1. Basic Information</h2>

            <div class="mb-3">
                <label class="form-label" for="scholarship_name">
                    Scholarship Name *
                </label>

                <input
                    type="text"
                    class="form-control"
                    id="scholarship_name"
                    name="scholarship_name"
                    maxlength="255"
                    value="<?= e($formValue('scholarship_name')) ?>"
                    required
                >

                <?php $formError('scholarship_name'); ?>
            </div>

            <div class="mb-3">
                <label class="form-label" for="provider_id">
                    Scholarship Provider *
                </label>

                <select
                    class="form-select"
                    id="provider_id"
                    name="provider_id"
                    required
                >
                    <option value="">Select provider</option>

                    <?php foreach ($options['providers'] ?? [] as $provider): ?>
                        <option
                            value="<?= e($provider['provider_id']) ?>"
                            <?= $isSelected('provider_id', $provider['provider_id']) ?>
                        >
                            <?= e($provider['provider_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <?php $formError('provider_id'); ?>
            </div>

            <div class="mb-3">
                <label class="form-label" for="official_url">
                    Official Scholarship URL *
                </label>

                <input
                    type="url"
                    class="form-control"
                    id="official_url"
                    name="official_url"
                    maxlength="1000"
                    value="<?= e($formValue('official_url')) ?>"
                    required
                >

                <?php $formError('official_url'); ?>
            </div>

            <div class="mb-3">
                <label class="form-label" for="discovery_source">
                    Discovery Source
                </label>

                <input
                    type="text"
                    class="form-control"
                    id="discovery_source"
                    name="discovery_source"
                    maxlength="1000"
                    value="<?= e($formValue('discovery_source')) ?>"
                >

                <?php $formError('discovery_source'); ?>
            </div>
        </div>
    </section>

    <!-- SECTION 2: CLASSIFICATION -->
    <section class="card mb-4">
        <div class="card-body">
            <h2 class="h5">2. Scholarship Classification</h2>

            <div class="mb-3">
                <label class="form-label" for="national_or_state_local">
                    National or State/Local *
                </label>

                <select
                    class="form-select"
                    id="national_or_state_local"
                    name="national_or_state_local"
                    required
                >
                    <option value="">Select classification</option>

                    <?php foreach (['National', 'State/Local'] as $scope): ?>
                        <option
                            value="<?= e($scope) ?>"
                            <?= $isSelected('national_or_state_local', $scope) ?>
                        >
                            <?= e($scope) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <?php $formError('national_or_state_local'); ?>
            </div>

            <div class="mb-3">
                <label class="form-label" for="state_id">
                    State
                </label>

                <select
                    class="form-select"
                    id="state_id"
                    name="state_id"
                >
                    <option value="">Select state</option>

                    <?php foreach ($options['states'] ?? [] as $state): ?>
                        <option
                            value="<?= e($state['state_id']) ?>"
                            <?= $isSelected('state_id', $state['state_id']) ?>
                        >
                            <?= e($state['state_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <?php $formError('state_id'); ?>
            </div>

            <div class="mb-3">
                <label class="form-label" for="theme_id">
                    National Theme
                </label>

                <select
                    class="form-select"
                    id="theme_id"
                    name="theme_id"
                >
                    <option value="">Select national theme</option>

                    <?php foreach ($options['themes'] ?? [] as $theme): ?>
                        <option
                            value="<?= e($theme['theme_id']) ?>"
                            <?= $isSelected('theme_id', $theme['theme_id']) ?>
                        >
                            <?= e($theme['theme_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <?php $formError('theme_id'); ?>
            </div>

            <div class="mb-3">
                <label class="form-label" for="school_level">
                    School Level
                </label>

                <select
                    class="form-select"
                    id="school_level"
                    name="school_level"
                >
                    <option value="">Select school level</option>

                    <?php foreach (['Middle School', 'High School', 'Both'] as $level): ?>
                        <option
                            value="<?= e($level) ?>"
                            <?= $isSelected('school_level', $level) ?>
                        >
                            <?= e($level) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <?php $formError('school_level'); ?>
            </div>
        </div>
    </section>

    <!-- SECTION 3: MANY-TO-MANY SELECTIONS -->
    <section class="card mb-4">
        <div class="card-body">
            <h2 class="h5">3. Grades, Categories, and Fields</h2>

            <?php
            $groups = [
                'grade_ids' => [
                    'title' => 'Eligible Grades *',
                    'items' => $options['grades'] ?? [],
                    'id' => 'grade_id',
                    'label' => 'grade_level'
                ],
                'category_ids' => [
                    'title' => 'Scholarship Categories',
                    'items' => $options['categories'] ?? [],
                    'id' => 'category_id',
                    'label' => 'category_name'
                ],
                'field_ids' => [
                    'title' => 'Fields of Study',
                    'items' => $options['fields'] ?? [],
                    'id' => 'field_id',
                    'label' => 'field_name'
                ]
            ];
            ?>

            <?php foreach ($groups as $field => $group): ?>
                <fieldset class="mb-4">
                    <legend class="fs-6 fw-semibold">
                        <?= e($group['title']) ?>
                    </legend>

                    <?php foreach ($group['items'] as $item): ?>
                        <div class="form-check">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                name="<?= e($field) ?>[]"
                                value="<?= e($item[$group['id']]) ?>"
                                id="<?= e($field . '_' . $item[$group['id']]) ?>"
                                <?= $isChecked($field, $item[$group['id']]) ?>
                            >

                            <label
                                class="form-check-label"
                                for="<?= e($field . '_' . $item[$group['id']]) ?>"
                            >
                                <?= e($item[$group['label']]) ?>
                            </label>
                        </div>
                    <?php endforeach; ?>

                    <?php $formError($field); ?>
                </fieldset>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- SECTION 4: AWARD INFORMATION -->
    <section class="card mb-4">
        <div class="card-body">
            <h2 class="h5">4. Award Information</h2>

            <?php
            $numberFields = [
                'award_amount_min' => 'Minimum Award ($)',
                'award_amount_max' => 'Maximum Award ($)',
                'maximum_potential_value' => 'Maximum Potential Value ($)',
                'number_of_awards' => 'Number of Awards',
                'minimum_gpa' => 'Minimum GPA'
            ];
            ?>

            <div class="row">
                <?php foreach ($numberFields as $field => $label): ?>
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="<?= e($field) ?>">
                            <?= e($label) ?>
                        </label>

                        <input
                            type="number"
                            class="form-control"
                            id="<?= e($field) ?>"
                            name="<?= e($field) ?>"
                            min="0"
                            <?= $field === 'number_of_awards'
                                ? 'step="1"'
                                : 'step="0.01"' ?>
                            <?= $field === 'minimum_gpa'
                                ? 'max="4"'
                                : '' ?>
                            value="<?= e($formValue($field)) ?>"
                        >

                        <?php $formError($field); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- SECTION 5: APPLICATION DETAILS -->
    <section class="card mb-4">
        <div class="card-body">
            <h2 class="h5">5. Application Details</h2>

            <?php
            $dateFields = [
                'application_open_date' => 'Application Open Date',
                'application_deadline' => 'Application Deadline',
                'verification_date' => 'Verification Date'
            ];
            ?>

            <div class="row">
                <?php foreach ($dateFields as $field => $label): ?>
                    <div class="col-md-4 mb-3">
                        <label class="form-label" for="<?= e($field) ?>">
                            <?= e($label) ?>
                        </label>

                        <input
                            type="date"
                            class="form-control"
                            id="<?= e($field) ?>"
                            name="<?= e($field) ?>"
                            value="<?= e($formValue($field)) ?>"
                        >

                        <?php $formError($field); ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="form-check mb-3">
                <input
                    class="form-check-input"
                    type="checkbox"
                    id="rolling_deadline"
                    name="rolling_deadline"
                    value="1"
                    <?= $formValue('rolling_deadline') === '1'
                        ? 'checked'
                        : '' ?>
                >

                <label class="form-check-label" for="rolling_deadline">
                    Rolling Deadline
                </label>
            </div>

            <?php $formError('rolling_deadline'); ?>

            <div class="mb-3">
                <label class="form-label" for="application_method">
                    Application Method
                </label>

                <select
                    class="form-select"
                    id="application_method"
                    name="application_method"
                >
                    <option value="">Select method</option>

                    <?php foreach (['Online Form', 'Email', 'Mail', 'Other'] as $method): ?>
                        <option
                            value="<?= e($method) ?>"
                            <?= $isSelected('application_method', $method) ?>
                        >
                            <?= e($method) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <?php $formError('application_method'); ?>
            </div>
        </div>
    </section>

    <!-- SECTION 6: ELIGIBILITY REQUIREMENTS -->
    <section class="card mb-4">
        <div class="card-body">
            <h2 class="h5">6. Scholarship Requirements</h2>

            <?php
            $yesNoFields = [
                'renewable' => 'Renewable',
                'financial_need_required' => 'Financial Need Required',
                'essay_required' => 'Essay Required',
                'recommendation_required' => 'Recommendation Required',
                'transcript_test_required' => 'Transcript/Test Required',
                'community_service_required' => 'Community Service Required',
                'leadership_required' => 'Leadership Required'
            ];
            ?>

            <div class="row">
                <?php foreach ($yesNoFields as $field => $label): ?>
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="<?= e($field) ?>">
                            <?= e($label) ?>
                        </label>

                        <select
                            class="form-select"
                            id="<?= e($field) ?>"
                            name="<?= e($field) ?>"
                        >
                            <?php foreach (['Unknown', 'Yes', 'No'] as $choice): ?>
                                <option
                                    value="<?= e($choice) ?>"
                                    <?= ($formValue($field) ?: 'Unknown') === $choice
                                        ? 'selected'
                                        : '' ?>
                                >
                                    <?= e($choice) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <?php $formError($field); ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php
            $textAreas = [
                'residency_requirement' => 'Residency Requirements',
                'other_eligibility' => 'Other Eligibility Requirements',
                'notes' => 'Notes'
            ];
            ?>

            <?php foreach ($textAreas as $field => $label): ?>
                <div class="mb-3">
                    <label class="form-label" for="<?= e($field) ?>">
                        <?= e($label) ?>
                    </label>

                    <textarea
                        class="form-control"
                        id="<?= e($field) ?>"
                        name="<?= e($field) ?>"
                        rows="3"
                    ><?= e($formValue($field)) ?></textarea>

                    <?php $formError($field); ?>
                </div>
            <?php endforeach; ?>

            <div class="mb-3">
                <label class="form-label" for="contributing_student">
                    Contributing Student
                </label>

                <input
                    type="text"
                    class="form-control"
                    id="contributing_student"
                    name="contributing_student"
                    maxlength="150"
                    value="<?= e($formValue('contributing_student')) ?>"
                >

                <?php $formError('contributing_student'); ?>
            </div>
        </div>
    </section>

    <!-- FORM ACTIONS -->
    <div class="d-flex gap-2 mb-5">
        <button class="btn btn-primary" type="submit">
            Save Scholarship
        </button>

        <a
            href="../scholarships.php"
            class="btn btn-outline-secondary"
        >
            Cancel
        </a>
    </div>
</form>


<!-- jQuery provides immediate client-side validation. -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script>
$(function () {

    // Require a state or theme depending on the classification.
    function updateClassification() {
        const scope = $('#national_or_state_local').val();

        $('#state_id').prop('required', scope === 'State/Local');
        $('#theme_id').prop('required', scope === 'National');
    }

    $('#national_or_state_local').on('change', updateClassification);
    updateClassification();

    // Validate the scholarship form before submitting it.
    $('#scholarshipForm').on('submit', function (event) {

        let valid = true;

        // Clear previous client-side validation errors.
        $('.client-error').remove();
        $('.is-invalid').removeClass('is-invalid');

        // Display an error message beside an invalid field.
        function showError(selector, message) {
            const field = $(selector);

            field.addClass('is-invalid');

            field.after(
                $('<div>')
                    .addClass('invalid-feedback d-block client-error')
                    .text(message)
            );

            valid = false;
        }

        // Scholarship name is required.
        if (!$('#scholarship_name').val().trim()) {
            showError('#scholarship_name', 'Scholarship name is required.');
        }

        // Validate the official scholarship URL.
        const url = $('#official_url').val().trim();

        try {
            const parsedUrl = new URL(url);

            if (!['http:', 'https:'].includes(parsedUrl.protocol)) {
                throw new Error('Invalid protocol');
            }
        } catch (error) {
            showError('#official_url', 'Enter a valid HTTP or HTTPS URL.');
        }

        // Provider must be selected.
        if (!$('#provider_id').val()) {
            showError('#provider_id', 'Select a scholarship provider.');
        }

        // Check national/state classification.
        const scope = $('#national_or_state_local').val();

        if (!scope) {
            showError('#national_or_state_local', 'Select a classification.');
        }

        if (scope === 'State/Local' && !$('#state_id').val()) {
            showError('#state_id', 'Select a state.');
        }

        if (scope === 'National' && !$('#theme_id').val()) {
            showError('#theme_id', 'Select a national theme.');
        }

        // At least one eligible grade must be checked.
        if ($('input[name="grade_ids[]"]:checked').length === 0) {
            $('input[name="grade_ids[]"]').first().closest('fieldset').append(
                $('<div>')
                    .addClass('text-danger client-error mt-2')
                    .text('Select at least one eligible grade.')
            );

            valid = false;
        }

        // Check that maximum award is not below minimum award.
        const minAmount = $('#award_amount_min').val();
        const maxAmount = $('#award_amount_max').val();

        if (
            minAmount !== '' &&
            maxAmount !== '' &&
            Number(maxAmount) < Number(minAmount)
        ) {
            showError(
                '#award_amount_max',
                'Maximum award cannot be less than minimum award.'
            );
        }

        // GPA must be between 0.00 and 4.00.
        const gpa = $('#minimum_gpa').val();

        if (
            gpa !== '' &&
            (Number(gpa) < 0 || Number(gpa) > 4)
        ) {
            showError('#minimum_gpa', 'GPA must be between 0.00 and 4.00.');
        }

        // Application deadline cannot precede the opening date.
        const openDate = $('#application_open_date').val();
        const deadline = $('#application_deadline').val();

        if (openDate && deadline && deadline < openDate) {
            showError(
                '#application_deadline',
                'Deadline cannot be before the opening date.'
            );
        }

        // Check browser-level constraints such as numeric step and length.
        if (!this.checkValidity()) {
            valid = false;
            this.reportValidity();
        }

        // Stop the form from submitting if validation fails.
        if (!valid) {
            event.preventDefault();
        }
    });
});
</script>
