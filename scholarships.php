<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/security.php';

$scholarships = [];
$error = false;

// Search filters
$state = trim((string) ($_GET['state'] ?? ''));
$grade = trim((string) ($_GET['grade'] ?? ''));
$field = trim((string) ($_GET['field'] ?? ''));

// FR-SRCH-06: Expired scholarships
$includeExpired = ($_GET['include_expired'] ?? '') === '1';

// FR-SRCH-04: Sorting
$sort = (string) ($_GET['sort'] ?? 'deadline_asc');

$sortOptions = [
    'deadline_asc'  => 'sc.application_deadline ASC',
    'deadline_desc' => 'sc.application_deadline DESC',
    'award_desc'    => 'COALESCE(sc.award_amount_max, sc.award_amount_min) DESC',
    'award_asc'     => 'COALESCE(sc.award_amount_min, sc.award_amount_max) ASC',
    'name_asc'      => 'sc.scholarship_name ASC',
    'name_desc'     => 'sc.scholarship_name DESC'
];

if (!array_key_exists($sort, $sortOptions)) {
    $sort = 'deadline_asc';
}

// Validate existing filters
$validStates = ['MN', 'WI', 'IA'];
$validGrades = ['9', '10', '11', '12'];

$invalidFilters =
    ($state !== '' && !in_array($state, $validStates, true))
    || ($grade !== '' && !in_array($grade, $validGrades, true))
    || mb_strlen($field) > 150;

// Server-side fallback when JavaScript is unavailable
if ($invalidFilters) {

    http_response_code(400);

} else {

    try {

        require_once __DIR__ . '/includes/db.php';

        if (!isset($pdo) || !($pdo instanceof PDO)) {
            throw new RuntimeException('PDO connection unavailable');
        }

        $conditions = ["sc.status = 'active'"];
        $parameters = [];

        if (!$includeExpired) {
            $conditions[] = "(
                sc.application_deadline >= CURDATE()
                OR sc.application_deadline IS NULL
                OR sc.rolling_deadline = 1
            )";
        }

        if ($state !== '') {
            $conditions[] = "(
                st.state_code = :state
                OR sc.national_or_state_local = 'National'
            )";
            $parameters[':state'] = $state;
        }

        if ($grade !== '') {
            $conditions[] = "EXISTS (
                SELECT 1
                FROM scholarship_grades AS sg
                JOIN grade_levels AS gl
                    ON gl.grade_id = sg.grade_id
                WHERE sg.scholarship_id = sc.scholarship_id
                  AND gl.grade_level = :grade
            )";
            $parameters[':grade'] = (int) $grade;
        }

        if ($field !== '') {
            $conditions[] = "EXISTS (
                SELECT 1
                FROM scholarship_fields AS sf
                JOIN fields_of_study AS fs
                    ON fs.field_id = sf.field_id
                WHERE sf.scholarship_id = sc.scholarship_id
                  AND fs.field_name LIKE :field
            )";
            $parameters[':field'] = '%' . $field . '%';
        }

        $orderBy = $sortOptions[$sort];

        $nullOrdering = in_array(
            $sort,
            ['deadline_asc', 'deadline_desc'],
            true
        )
            ? 'sc.application_deadline IS NULL ASC,'
            : '';

        $sql = "SELECT
                    sc.scholarship_id,
                    sc.scholarship_name,
                    p.provider_name,
                    st.state_name,
                    sc.national_or_state_local,
                    sc.award_amount_min,
                    sc.award_amount_max,
                    sc.application_deadline
                FROM scholarships AS sc
                JOIN providers AS p
                    ON p.provider_id = sc.provider_id
                LEFT JOIN states AS st
                    ON st.state_id = sc.state_id
                WHERE " . implode(' AND ', $conditions) . "
                ORDER BY
                    $nullOrdering
                    $orderBy,
                    sc.scholarship_id ASC
                LIMIT 10";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($parameters);

        $scholarships = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Throwable $ex) {

        error_log('Scholarship search error: ' . $ex->getMessage());

        http_response_code(500);
        $error = true;
    }
}

function formatScholarshipAward(array $item): string
{
    $min = $item['award_amount_min'];
    $max = $item['award_amount_max'];

    if ($min !== null && $max !== null) {
        if ((float) $min === (float) $max) {
            return '$' . number_format((float) $min, 2);
        }

        return '$' . number_format((float) $min, 2)
            . '–$' . number_format((float) $max, 2);
    }

    if ($min !== null) {
        return 'From $' . number_format((float) $min, 2);
    }

    if ($max !== null) {
        return 'Up to $' . number_format((float) $max, 2);
    }

    return 'Not specified';
}

?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Scholarships | Pathways to Scholarships</title>

    <link href="assets/css/site.css" rel="stylesheet">

    <style>
        .search-filters {
            display: flex;
            flex-wrap: wrap;
            align-items: end;
            gap: 15px;
            margin-bottom: 25px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .filter-group select,
        .filter-group input {
            padding: 8px;
            max-width: 250px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .checkbox-group label {
            white-space: nowrap;
        }

        .search-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .search-actions button,
        #scholarship-pagination button {
            padding: 9px 18px;
            cursor: pointer;
        }

        .results-table {
            width: 100%;
            border-collapse: collapse;
        }

        .results-table th,
        .results-table td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
        }

        .results-table th {
            background-color: #f3f4f6;
        }

        #search-status {
            margin: 15px 0;
        }

        #scholarship-pagination {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin: 25px 0;
        }

        #scholarship-pagination button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        @media (max-width: 700px) {
            .search-filters {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-group select,
            .filter-group input {
                max-width: 100%;
            }

            .results-container {
                overflow-x: auto;
            }
        }
    </style>

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

    <h1>Available Scholarships</h1>

    <form
        action="scholarships.php"
        method="get"
        class="search-filters"
    >

        <div class="filter-group">
            <label for="state">State</label>

            <select id="state" name="state">
                <option value="">Any state</option>

                <option value="MN" <?= $state === 'MN' ? 'selected' : '' ?>>
                    Minnesota
                </option>

                <option value="WI" <?= $state === 'WI' ? 'selected' : '' ?>>
                    Wisconsin
                </option>

                <option value="IA" <?= $state === 'IA' ? 'selected' : '' ?>>
                    Iowa
                </option>
            </select>
        </div>

        <div class="filter-group">
            <label for="grade">Grade Level</label>

            <select id="grade" name="grade">
                <option value="">Any grade</option>

                <option value="9" <?= $grade === '9' ? 'selected' : '' ?>>
                    9th Grade
                </option>

                <option value="10" <?= $grade === '10' ? 'selected' : '' ?>>
                    10th Grade
                </option>

                <option value="11" <?= $grade === '11' ? 'selected' : '' ?>>
                    11th Grade
                </option>

                <option value="12" <?= $grade === '12' ? 'selected' : '' ?>>
                    12th Grade
                </option>
            </select>
        </div>

        <div class="filter-group">
            <label for="field">Field of Study</label>

            <input
                id="field"
                name="field"
                maxlength="150"
                value="<?= e($field) ?>"
                placeholder="Example: STEM"
            >
        </div>

        <!-- FR-SRCH-04 -->
        <div class="filter-group">
            <label for="sort">Sort By</label>

            <select id="sort" name="sort">

                <option value="deadline_asc"
                    <?= $sort === 'deadline_asc' ? 'selected' : '' ?>>
                    Deadline: Soonest First
                </option>

                <option value="deadline_desc"
                    <?= $sort === 'deadline_desc' ? 'selected' : '' ?>>
                    Deadline: Latest First
                </option>

                <option value="award_desc"
                    <?= $sort === 'award_desc' ? 'selected' : '' ?>>
                    Award: Highest First
                </option>

                <option value="award_asc"
                    <?= $sort === 'award_asc' ? 'selected' : '' ?>>
                    Award: Lowest First
                </option>

                <option value="name_asc"
                    <?= $sort === 'name_asc' ? 'selected' : '' ?>>
                    Scholarship Name: A–Z
                </option>

                <option value="name_desc"
                    <?= $sort === 'name_desc' ? 'selected' : '' ?>>
                    Scholarship Name: Z–A
                </option>

            </select>
        </div>

        <!-- FR-SRCH-06 -->
        <div class="checkbox-group">

            <input
                type="checkbox"
                id="include_expired"
                name="include_expired"
                value="1"
                <?= $includeExpired ? 'checked' : '' ?>
            >

            <label for="include_expired">
                Include expired scholarships
            </label>
        </div>

        <div class="search-actions">
            <button type="submit">Search</button>
            <a href="scholarships.php">Clear filters</a>
        </div>

    </form>

    <!-- FR-SRCH-05: AJAX status -->
    <p id="search-status" role="status" aria-live="polite"></p>

    <!-- AJAX replaces the contents of this container -->
    <div id="scholarship-results">

        <?php if ($invalidFilters): ?>

            <p role="alert">Invalid search filters.</p>

        <?php elseif ($error): ?>

            <p role="alert">
                Scholarships cannot be loaded right now.
            </p>

        <?php elseif (!$scholarships): ?>

            <p>No scholarships match your search.</p>

        <?php else: ?>

            <div class="results-container">

                <table class="results-table">
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

                        <?php
                        $location = $item['state_name']
                            ?? $item['national_or_state_local'];
                        ?>

                        <tr>
                            <td><?= e($item['scholarship_name']) ?></td>
                            <td><?= e($item['provider_name']) ?></td>
                            <td><?= e($location) ?></td>
                            <td><?= e(formatScholarshipAward($item)) ?></td>
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

    </div>

    <!-- FR-SRCH-05: Pagination buttons appear here -->
    <div id="scholarship-pagination"></div>

</main>

<footer class="site-footer">
    <p>Pathways to Scholarships - ICS 325 Final Project</p>
</footer>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- FR-SRCH-05: AJAX scholarship search -->
<script src="assets/js/scholarship-search.js"></script>

</body>
</html>
