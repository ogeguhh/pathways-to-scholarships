<?php
declare(strict_types=1);

/*
 * ICS 325 - Pathways to Scholarships
 * Iteration 5 - Integrated Scholarship Search
 *
 * Items 1-3: Search filters, keyword search, sorting,
 * pagination, and AJAX integration.
 *
 * Items 4-6: Expired scholarship filtering,
 * scholarship detail links, and responsive results.
 */

require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/scholarship_search.php';

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
    require_once __DIR__ . '/includes/db.php';

    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new RuntimeException('Database connection unavailable.');
    }

    $options = scholarship_search_options($pdo);

    $validation = scholarship_search_filters($_GET, $options);

    $filters = $validation['filters'];
    $errors = $validation['errors'];

    if ($errors === []) {
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
 * Preserve selected dropdown values.
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
 * Format scholarship award amounts.
 */
function display_award(array $scholarship): string
{
    $minimum = $scholarship['award_amount_min'] ?? null;
    $maximum = $scholarship['award_amount_max'] ?? null;

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
 * Display scholarship location.
 */
function display_location(array $scholarship): string
{
    if (
        ($scholarship['national_or_state_local'] ?? '') === 'National'
    ) {
        return 'National';
    }

    return $scholarship['state_name'] ?? 'Not specified';
}

/**
 * Generate a safe scholarship detail URL.
 */
function scholarship_detail_url(array $scholarship): string
{
    return 'scholarship.php?id='
        . (int)$scholarship['scholarship_id'];
}

/**
 * Preserve search filters when changing pages.
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

    <style>
        /*
         * Item 6: Desktop table and mobile cards.
         */

        .scholarship-desktop-results {
            display: block;
        }

        .scholarship-mobile-results {
            display: none;
        }

        .scholarship-mobile-card {
            border: 1px solid #dee2e6;
            border-radius: 10px;
            background: #fff;
            padding: 18px;
            margin-bottom: 15px;
            overflow-wrap: anywhere;
        }

        .scholarship-mobile-card h3 {
            font-size: 1.1rem;
            margin-bottom: 12px;
        }

        .scholarship-mobile-card p {
            margin-bottom: 8px;
        }

        .scholarship-mobile-card .btn {
            margin-top: 10px;
        }

        .scholarship-link {
            font-weight: 600;
            text-decoration: none;
        }

        .scholarship-link:hover,
        .scholarship-link:focus {
            text-decoration: underline;
        }

        .expired-filter {
            padding: 12px 15px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
        }

        @media (max-width: 767px) {
            .scholarship-desktop-results {
                display: none;
            }

            .scholarship-mobile-results {
                display: block;
            }

            .pagination {
                flex-wrap: wrap;
                gap: 4px;
            }
        }
    </style>
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

    <!-- SEARCH FILTERS -->
    <section class="card shadow-sm mb-4">
        <div class="card-body">

            <h2 class="h5 mb-3">Scholarship Filters</h2>

            <form
                id="scholarshipSearchForm"
                action="scholarships.php"
                method="get"
            >

                <!-- KEYWORD AND SORTING -->
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
                                <?= selected_filter(
                                    $filters,
                                    'sort',
                                    'deadline'
                                ) ?>
                            >
                                Deadline (Soonest First)
                            </option>

                            <option
                                value="award"
                                <?= selected_filter(
                                    $filters,
                                    'sort',
                                    'award'
                                ) ?>
                            >
                                Award Amount (Highest First)
                            </option>

                            <option
                                value="name"
                                <?= selected_filter(
                                    $filters,
                                    'sort',
                                    'name'
                                ) ?>
                            >
                                Scholarship Name (A-Z)
                            </option>
                        </select>
                    </div>
                </div>

                <!-- STATE, GRADE, FIELD -->
                <div class="row g-3 mb-3">

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
                                <?= selected_filter(
                                    $filters,
                                    'state',
                                    'National'
                                ) ?>
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

                <!-- CATEGORY AND AWARD -->
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

                <!-- DEADLINE RANGE -->
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

                <!-- ELIGIBILITY -->
                <?php
                $yesNoFilters = [
                    'renewable' => 'Renewable',
                    'essay_required' => 'Essay Required',
                    'financial_need_required' => 'Financial Need Required'
                ];
                ?>

                <div class="row g-3 mb-3">

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

                                <?php foreach (
                                    ['Yes', 'No', 'Unknown'] as $choice
                                ): ?>
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

                <!-- ITEM 4: EXPIRED SCHOLARSHIPS -->
                <div class="expired-filter mb-4">

                    <div class="form-check">

                        <input
                            type="checkbox"
                            id="include_expired"
                            name="include_expired"
                            value="1"
                            class="form-check-input"
                            <?= ($filters['include_expired'] ?? '') === '1'
                                ? 'checked'
                                : '' ?>
                        >

                        <label
                            for="include_expired"
                            class="form-check-label"
                        >
                            Include expired scholarships
                        </label>
                    </div>

                    <small class="text-muted">
                        Scholarships with past deadlines are hidden
                        by default. Check this box to include them.
                    </small>
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

    <!-- SEARCH ERRORS -->
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

        <!-- RESULTS CONTAINER -->
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

                    <!-- ITEM 6: DESKTOP TABLE -->
                    <div class="scholarship-desktop-results">

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
                                                <a
                                                    class="scholarship-link"
                                                    href="<?= e(
                                                        scholarship_detail_url($item)
                                                    ) ?>"
                                                >
                                                    <?= e($item['scholarship_name']) ?>
                                                </a>
                                            </td>

                                            <td>
                                                <?= e($item['provider_name']) ?>
                                            </td>

                                            <td>
                                                <?= e(display_location($item)) ?>
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
                    </div>

                    <!-- ITEM 6: MOBILE CARDS -->
                    <div class="scholarship-mobile-results">

                        <?php foreach ($scholarships as $item): ?>

                            <article class="scholarship-mobile-card">

                                <h3>
                                    <a
                                        class="scholarship-link"
                                        href="<?= e(
                                            scholarship_detail_url($item)
                                        ) ?>"
                                    >
                                        <?= e($item['scholarship_name']) ?>
                                    </a>
                                </h3>

                                <p>
                                    <strong>Provider:</strong>
                                    <?= e($item['provider_name']) ?>
                                </p>

                                <p>
                                    <strong>Location:</strong>
                                    <?= e(display_location($item)) ?>
                                </p>

                                <p>
                                    <strong>Award:</strong>
                                    <?= e(display_award($item)) ?>
                                </p>

                                <p>
                                    <strong>Deadline:</strong>
                                    <?= e(
                                        $item['application_deadline']
                                            ?? 'Not specified'
                                    ) ?>
                                </p>

                                <a
                                    class="btn btn-outline-primary btn-sm"
                                    href="<?= e(
                                        scholarship_detail_url($item)
                                    ) ?>"
                                >
                                    View Scholarship Details
                                </a>

                            </article>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

                <!-- PAGINATION -->
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
                                    <?= $page <= 1
                                        ? 'aria-disabled="true" tabindex="-1"'
                                        : '' ?>
                                >
                                    Previous
                                </a>
                            </li>

                            <?php for (
                                $number = 1;
                                $number <= $totalPages;
                                $number++
                            ): ?>

                                <li class="page-item <?= $number === $page ? 'active' : '' ?>">

                                    <a
                                        class="page-link"
                                        href="<?= e(scholarship_page_url(
                                            $filters,
                                            $number
                                        )) ?>"
                                        <?= $number === $page
                                            ? 'aria-current="page"'
                                            : '' ?>
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
                                    <?= $page >= $totalPages
                                        ? 'aria-disabled="true" tabindex="-1"'
                                        : '' ?>
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

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- Teammate's AJAX search -->
<script src="assets/js/scholarship_search.js"></script>

</body>
</html>
