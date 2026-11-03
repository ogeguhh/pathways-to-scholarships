<?php
declare(strict_types=1);

/*
 * ICS 325 - Pathways to Scholarships
 * Iteration 5 - Bullets 1 and 2
 *
 * Bullet 1: Scholarship search filters
 * Bullet 2: Keyword search, sorting, pagination
 *
 * Bullet 3: AJAX filtering will be added next.
 */

require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/scholarship_search.php';

// Initialize values used by the search page.
$options = [];
$scholarships = [];
$filters = [];
$errors = [];
$databaseError = false;

$total = 0;
$page = 1;
$totalPages = 1;
$perPage = 10;

try {
    // Use the team's existing database connection.
    require_once __DIR__ . '/includes/db.php';

    // Load options for the dropdown filters.
    $options = scholarship_search_options($pdo);

    // Validate submitted search filters and controls.
    $validation = scholarship_search_filters($_GET, $options);

    $filters = $validation['filters'];
    $errors = $validation['errors'];

    if ($errors === []) {

        // Search helper returns both records and pagination details.
        $result = scholarship_search_results($pdo, $filters);

        $scholarships = $result['scholarships'];
        $total = $result['total'];
        $page = $result['page'];
        $totalPages = $result['total_pages'];
        $perPage = $result['per_page'];

    } else {
        http_response_code(400);
    }

} catch (Throwable $exception) {
    error_log('Scholarship search error: ' . $exception->getMessage());
    http_response_code(500);
    $databaseError = true;
}

/**
 * Keep the selected dropdown value after submitting.
 */
function selected_filter(
    array $filters,
    string $name,
    string|int $value
): string {
    return ($filters[$name] ?? '') === (string)$value
        ? 'selected'
        : '';
}

/**
 * Format the scholarship award range.
 */
function display_award(array $scholarship): string
{
    $minimum = $scholarship['award_amount_min'];
    $maximum = $scholarship['award_amount_max'];

    if ($minimum !== null && $maximum !== null) {
        if ((float)$minimum === (float)$maximum) {
            return '$' . number_format((float)$minimum, 2);
        }

        return '$' . number_format((float)$minimum, 2)
            . ' - $' . number_format((float)$maximum, 2);
    }

    if ($minimum !== null) {
        return 'From $' . number_format((float)$minimum, 2);
    }

    if ($maximum !== null) {
        return 'Up to $' . number_format((float)$maximum, 2);
    }

    return 'Not specified';
}

/**
 * Build pagination URLs while keeping the current filters.
 */
function scholarship_page_url(
    array $filters,
    int $page
): string {
    $filters['page'] = (string)$page;

    return 'scholarships.php?' . http_build_query($filters);
}
?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>Search Scholarships | Pathways to Scholarships</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link href="assets/css/site.css" rel="stylesheet">
</head>

<body class="bg-light">

<!-- PUBLIC NAVIGATION -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">

        <a class="navbar-brand" href="index.php">
            Pathways to Scholarships
        </a>

        <div class="navbar-nav">
            <a class="nav-link" href="index.php">Home</a>

            <a
                class="nav-link active"
                href="scholarships.php"
                aria-current="page"
            >
                Search Scholarships
            </a>

            <a class="nav-link" href="about.php">About</a>
            <a class="nav-link" href="faq.php">FAQ</a>
            <a class="nav-link" href="contact.php">Contact</a>
        </div>
    </div>
</nav>

<main class="container py-4">

    <h1>Search Scholarships</h1>

    <p class="text-muted">
        Find scholarships based on state, grade level,
        field of study, and eligibility requirements.
    </p>

    <!-- SCHOLARSHIP SEARCH FORM -->
    <section class="card shadow-sm mb-4">
        <div class="card-body">

            <h2 class="h5 mb-3">Scholarship Filters</h2>

            <form
                id="scholarshipSearchForm"
                action="scholarships.php"
                method="get"
            >

                <!-- KEYWORD SEARCH -->
                <div class="row g-3 mb-3">
                    <div class="col-md-8">

                        <label for="keyword" class="form-label">
                            Keyword Search
                        </label>

                        <input
                            type="search"
                            id="keyword"
                            name="keyword"
                            class="form-control"
                            maxlength="150"
                            placeholder="Search scholarships, providers, or notes"
                            value="<?= e($filters['keyword'] ?? '') ?>"
                        >
                    </div>

                    <!-- SORTING -->
                    <div class="col-md-4">

                        <label for="sort" class="form-label">
                            Sort By
                        </label>

                        <select
                            id="sort"
                            name="sort"
                            class="form-select"
                        >
                            <option
                                value="deadline"
                                <?= selected_filter($filters, 'sort', 'deadline') ?>
                            >
                                Deadline (Soonest First)
                            </option>

                            <option
                                value="award"
                                <?= selected_filter($filters, 'sort', 'award') ?>
                            >
                                Award Amount (Highest First)
                            </option>

                            <option
                                value="name"
                                <?= selected_filter($filters, 'sort', 'name') ?>
                            >
                                Scholarship Name (A-Z)
                            </option>
                        </select>
                    </div>
                </div>

                <!-- THREE MANDATORY FILTER TYPES -->
                <div class="row g-3 mb-3">

                    <!-- STATE -->
                    <div class="col-md-4">
                        <label for="state" class="form-label">
                            State
                        </label>

                        <select
                            id="state"
                            name="state"
                            class="form-select"
                        >
                            <option value="">All States</option>

                            <option
                                value="National"
                                <?= selected_filter($filters, 'state', 'National') ?>
                            >
                                National
                            </option>

                            <?php foreach ($options['states'] ?? [] as $state): ?>
                                <option
                                    value="<?= e($state['state_code']) ?>"
                                    <?= selected_filter(
                                        $filters,
                                        'state',
                                        $state['state_code']
                                    ) ?>
                                >
                                    <?= e($state['state_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- GRADE -->
                    <div class="col-md-4">
                        <label for="grade" class="form-label">
                            Grade Level
                        </label>

                        <select
                            id="grade"
                            name="grade"
                            class="form-select"
                        >
                            <option value="">All Grades</option>

                            <?php foreach ($options['grades'] ?? [] as $grade): ?>
                                <option
                                    value="<?= e($grade['grade_level']) ?>"
                                    <?= selected_filter(
                                        $filters,
                                        'grade',
                                        $grade['grade_level']
                                    ) ?>
                                >
                                    Grade <?= e($grade['grade_level']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- FIELD -->
                    <div class="col-md-4">
                        <label for="field" class="form-label">
                            Field of Study
                        </label>

                        <select
                            id="field"
                            name="field"
                            class="form-select"
                        >
                            <option value="">All Fields</option>

                            <?php foreach ($options['fields'] ?? [] as $field): ?>
                                <option
                                    value="<?= e($field['field_id']) ?>"
                                    <?= selected_filter(
                                        $filters,
                                        'field',
                                        $field['field_id']
                                    ) ?>
                                >
                                    <?= e($field['field_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- CATEGORY AND AWARD FILTERS -->
                <div class="row g-3 mb-3">

                    <div class="col-md-4">
                        <label for="category" class="form-label">
                            Scholarship Category
                        </label>

                        <select
                            id="category"
                            name="category"
                            class="form-select"
                        >
                            <option value="">All Categories</option>

                            <?php foreach ($options['categories'] ?? [] as $category): ?>
                                <option
                                    value="<?= e($category['category_id']) ?>"
                                    <?= selected_filter(
                                        $filters,
                                        'category',
                                        $category['category_id']
                                    ) ?>
                                >
                                    <?= e($category['category_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label for="award_min" class="form-label">
                            Minimum Award ($)
                        </label>

                        <input
                            type="number"
                            id="award_min"
                            name="award_min"
                            class="form-control"
                            min="0"
                            step="0.01"
                            value="<?= e($filters['award_min'] ?? '') ?>"
                        >
                    </div>

                    <div class="col-md-4">
                        <label for="award_max" class="form-label">
                            Maximum Award ($)
                        </label>

                        <input
                            type="number"
                            id="award_max"
                            name="award_max"
                            class="form-control"
                            min="0"
                            step="0.01"
                            value="<?= e($filters['award_max'] ?? '') ?>"
                        >
                    </div>
                </div>

                <!-- DEADLINE FILTERS -->
                <div class="row g-3 mb-3">

                    <div class="col-md-6">
                        <label for="deadline_from" class="form-label">
                            Deadline From
                        </label>

                        <input
                            type="date"
                            id="deadline_from"
                            name="deadline_from"
                            class="form-control"
                            value="<?= e($filters['deadline_from'] ?? '') ?>"
                        >
                    </div>

                    <div class="col-md-6">
                        <label for="deadline_to" class="form-label">
                            Deadline To
                        </label>

                        <input
                            type="date"
                            id="deadline_to"
                            name="deadline_to"
                            class="form-control"
                            value="<?= e($filters['deadline_to'] ?? '') ?>"
                        >
                    </div>
                </div>

                <!-- YES / NO / UNKNOWN FILTERS -->
                <?php
                $yesNoFilters = [
                    'renewable' => 'Renewable',
                    'essay_required' => 'Essay Required',
                    'financial_need_required' => 'Financial Need Required'
                ];
                ?>

                <div class="row g-3 mb-4">

                    <?php foreach ($yesNoFilters as $field => $label): ?>
                        <div class="col-md-4">

                            <label
                                for="<?= e($field) ?>"
                                class="form-label"
                            >
                                <?= e($label) ?>
                            </label>

                            <select
                                id="<?= e($field) ?>"
                                name="<?= e($field) ?>"
                                class="form-select"
                            >
                                <option value="">Any</option>

                                <?php foreach (['Yes', 'No', 'Unknown'] as $choice): ?>
                                    <option
                                        value="<?= e($choice) ?>"
                                        <?= selected_filter(
                                            $filters,
                                            $field,
                                            $choice
                                        ) ?>
                                    >
                                        <?= e($choice) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- SEARCH ACTIONS -->
                <div class="d-flex flex-wrap gap-2">

                    <button type="submit" class="btn btn-primary">
                        Search Scholarships
                    </button>

                    <a
                        href="scholarships.php"
                        class="btn btn-outline-secondary"
                    >
                        Clear Filters
                    </a>
                </div>

            </form>
        </div>
    </section>

    <!-- ERROR MESSAGES -->
    <?php if ($databaseError): ?>

        <div class="alert alert-danger" role="alert">
            Scholarships cannot be loaded right now.
        </div>

    <?php elseif ($errors !== []): ?>

        <div class="alert alert-danger" role="alert">
            <strong>Please correct these filters:</strong>

            <ul class="mb-0 mt-2">
                <?php foreach ($errors as $message): ?>
                    <li><?= e($message) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>

    <?php else: ?>

        <!-- SEARCH RESULTS -->
        <section
            id="scholarshipResults"
            class="card shadow-sm"
            aria-live="polite"
        >
            <div class="card-body">

                <h2 class="h5">Scholarship Results</h2>

                <p class="text-muted">
                    <?= $total ?> scholarship(s) found.
                    Page <?= $page ?> of <?= $totalPages ?>.
                </p>

                <?php if ($scholarships === []): ?>

                    <p>No scholarships match your search.</p>

                <?php else: ?>

                    <div class="table-responsive">
                        <table class="table table-striped align-middle">

                            <thead>
                                <tr>
                                    <th scope="col">Scholarship</th>
                                    <th scope="col">Provider</th>
                                    <th scope="col">Location</th>
                                    <th scope="col">Award</th>
                                    <th scope="col">Deadline</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($scholarships as $item): ?>
                                    <tr>
                                        <td>
                                            <?= e($item['scholarship_name']) ?>
                                        </td>

                                        <td>
                                            <?= e($item['provider_name']) ?>
                                        </td>

                                        <td>
                                            <?= e(
                                                $item['national_or_state_local'] === 'National'
                                                    ? 'National'
                                                    : ($item['state_name'] ?? 'Not specified')
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= e(display_award($item)) ?>
                                        </td>

                                        <td>
                                            <?= e(
                                                $item['application_deadline']
                                                    ?? 'Not specified'
                                            ) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>

                        </table>
                    </div>

                <?php endif; ?>

                <!-- PAGINATION CONTROLS -->
                <?php if ($totalPages > 1): ?>

                    <nav aria-label="Scholarship results pages">
                        <ul class="pagination justify-content-center mt-4 mb-0">

                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a
                                    class="page-link"
                                    href="<?= e(scholarship_page_url(
                                        $filters,
                                        max(1, $page - 1)
                                    )) ?>"
                                    <?= $page <= 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>
                                >
                                    Previous
                                </a>
                            </li>

                            <?php for ($number = 1; $number <= $totalPages; $number++): ?>
                                <li class="page-item <?= $number === $page ? 'active' : '' ?>">
                                    <a
                                        class="page-link"
                                        href="<?= e(scholarship_page_url(
                                            $filters,
                                            $number
                                        )) ?>"
                                        <?= $number === $page ? 'aria-current="page"' : '' ?>
                                    >
                                        <?= $number ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                <a
                                    class="page-link"
                                    href="<?= e(scholarship_page_url(
                                        $filters,
                                        min($totalPages, $page + 1)
                                    )) ?>"
                                    <?= $page >= $totalPages ? 'aria-disabled="true" tabindex="-1"' : '' ?>
                                >
                                    Next
                                </a>
                            </li>

                        </ul>
                    </nav>

                <?php endif; ?>

            </div>
        </section>

    <?php endif; ?>

</main>

<footer class="text-center text-muted py-4">
    Pathways to Scholarships — ICS 325 Final Project
</footer>

<!-- jQuery library required for AJAX filtering. -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- Iteration 5 scholarship AJAX search. -->
<script src="assets/js/scholarship_search.js"></script>
</body>
</html>
