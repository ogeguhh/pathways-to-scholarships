<?php
declare(strict_types=1);

/*
 * Pathways to Scholarships
 * Iteration 5 — Scholarship Search
 *
 * Search active scholarships using state, grade level,
 * and field of study. AJAX will be added later.
 */

// Sessions let us recognize a previously logged search.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/includes/db.php';
require_once $projectRoot . '/includes/security.php';

$pageTitle = 'Scholarship Search | Pathways to Scholarships';

$scholarships = [];
$states = [];
$grades = [];
$fields = [];
$categories = [];
$errorMessage = '';

// Read the selected search filters from the URL.
// Empty values mean the visitor has not restricted that filter.
$stateId = filter_input(INPUT_GET, 'state_id', FILTER_VALIDATE_INT);
$gradeId = filter_input(INPUT_GET, 'grade_id', FILTER_VALIDATE_INT);
$fieldId = filter_input(INPUT_GET, 'field_id', FILTER_VALIDATE_INT);

$stateId = $stateId ?: null;
$gradeId = $gradeId ?: null;
$fieldId = $fieldId ?: null;

// Read an optional keyword from the search form.
$keyword = trim((string) ($_GET['keyword'] ?? ''));
$keyword = mb_substr($keyword, 0, 150);

// Read the additional filters and validate their values.
$categoryId = filter_input(INPUT_GET, 'category_id', FILTER_VALIDATE_INT);
$categoryId = $categoryId ?: null;

$awardMin = filter_input(INPUT_GET, 'award_min', FILTER_VALIDATE_FLOAT);
$awardMax = filter_input(INPUT_GET, 'award_max', FILTER_VALIDATE_FLOAT);

$awardMin = $awardMin !== false && $awardMin !== null && $awardMin >= 0
    ? $awardMin : null;
$awardMax = $awardMax !== false && $awardMax !== null && $awardMax >= 0
    ? $awardMax : null;

// Only accept dates in YYYY-MM-DD format that represent real dates.
function validSearchDate(mixed $value): ?string
{
    if (!is_string($value)) {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

    return $date && $date->format('Y-m-d') === $value
        ? $value : null;
}

$deadlineFrom = validSearchDate($_GET['deadline_from'] ?? null);
$deadlineTo = validSearchDate($_GET['deadline_to'] ?? null);

$yesNoFilters = [];

foreach (['renewable', 'essay_required', 'financial_need_required'] as $name) {
    $value = $_GET[$name] ?? '';

    $yesNoFilters[$name] = is_string($value)
        && in_array($value, ['Yes', 'No', 'Unknown'], true)
        ? $value : null;
}

$showExpired = ($_GET['show_expired'] ?? '') === '1';

try {
    $pdo = getDBConnection();

    // Log a submitted search once per unique filter combination
    // during this visitor's session.
    if (($_GET['search'] ?? '') === '1') {
        $searchKey = hash('sha256', json_encode([
            $stateId,
            $gradeId,
            $fieldId,
            $keyword
        ]));

        if (($_SESSION['last_logged_search'] ?? '') !== $searchKey) {
            $logStmt = $pdo->prepare(
                'INSERT INTO search_log
                    (state_filter, grade_filter, field_filter)
                 VALUES (?, ?, ?)'
            );

            $logStmt->execute([
                $stateId !== null ? (string) $stateId : null,
                $gradeId !== null ? (string) $gradeId : null,
                $fieldId !== null ? (string) $fieldId : null
            ]);

            $_SESSION['last_logged_search'] = $searchKey;
        }
    }

    // Load the options for the three required search filters.
    $states = $pdo->query(
        'SELECT state_id, state_name
         FROM states
         ORDER BY state_name'
    )->fetchAll();

    $grades = $pdo->query(
        'SELECT grade_id, grade_number
         FROM grade_levels
         ORDER BY grade_number'
    )->fetchAll();

    $fields = $pdo->query(
        'SELECT field_id, field_name
         FROM fields_of_study
         ORDER BY field_name'
    )->fetchAll();

    // Load category options for the additional filter dropdown.
    $categories = $pdo->query(
        'SELECT category_id, category_name
         FROM categories
         ORDER BY category_name'
    )->fetchAll();

    // Build the search using fixed SQL conditions and bound values.
    $sql = '
        SELECT
            s.scholarship_id,
            s.scholarship_name,
            s.official_url,
            s.national_or_state_local,
            s.award_amount_max,
            s.application_deadline,
            s.notes,
            p.provider_name,
            st.state_name
        FROM scholarships s
        JOIN providers p
          ON p.provider_id = s.provider_id
        LEFT JOIN states st
          ON st.state_id = s.state_id
        WHERE s.status = :status
    ';

    // Expired scholarships remain hidden unless explicitly requested.
    if (!$showExpired) {
        $sql .= '
            AND (
                s.application_deadline IS NULL
                OR s.application_deadline >= CURRENT_DATE()
            )
        ';
    }

    $parameters = [
        ':status' => 'active'
    ];

    // National scholarships are eligible for the selected state
    // alongside scholarships associated with that state.
    if ($stateId !== null) {
        $sql .= '
            AND (
                s.state_id = :state_id
                OR s.national_or_state_local = "National"
            )
        ';

        $parameters[':state_id'] = $stateId;
    }

    // EXISTS avoids duplicate scholarship rows when joining
    // many-to-many grade and field relationships.
    if ($gradeId !== null) {
        $sql .= '
            AND EXISTS (
                SELECT 1
                FROM scholarship_grades sg
                WHERE sg.scholarship_id = s.scholarship_id
                  AND sg.grade_id = :grade_id
            )
        ';

        $parameters[':grade_id'] = $gradeId;
    }

    if ($fieldId !== null) {
        $sql .= '
            AND EXISTS (
                SELECT 1
                FROM scholarship_fields sf
                WHERE sf.scholarship_id = s.scholarship_id
                  AND sf.field_id = :field_id
            )
        ';

        $parameters[':field_id'] = $fieldId;
    }

    // Match award ranges against the scholarship's reported award.
    if ($awardMin !== null) {
        $sql .= ' AND s.award_amount_max >= :award_min';
        $parameters[':award_min'] = $awardMin;
    }

    if ($awardMax !== null) {
        $sql .= ' AND s.award_amount_min <= :award_max';
        $parameters[':award_max'] = $awardMax;
    }

    // Restrict results to the selected application deadline range.
    if ($deadlineFrom !== null) {
        $sql .= ' AND s.application_deadline >= :deadline_from';
        $parameters[':deadline_from'] = $deadlineFrom;
    }

    if ($deadlineTo !== null) {
        $sql .= ' AND s.application_deadline <= :deadline_to';
        $parameters[':deadline_to'] = $deadlineTo;
    }

    // EXISTS avoids duplicate results for scholarships with many categories.
    if ($categoryId !== null) {
        $sql .= '
            AND EXISTS (
                SELECT 1
                FROM scholarship_categories sc
                WHERE sc.scholarship_id = s.scholarship_id
                  AND sc.category_id = :category_id
            )
        ';
        $parameters[':category_id'] = $categoryId;
    }

    // Column names come only from this fixed list, never from user input.
    foreach ($yesNoFilters as $column => $value) {
        if ($value !== null) {
            $parameter = ':' . $column;
            $sql .= " AND s.$column = $parameter";
            $parameters[$parameter] = $value;
        }
    }

    // Search scholarship names, providers, and descriptions.
    if ($keyword !== '') {
        $sql .= '
            AND (
                s.scholarship_name LIKE :keyword_name
                OR p.provider_name LIKE :keyword_provider
                OR s.notes LIKE :keyword_notes
            )
        ';

        $searchTerm = '%' . $keyword . '%';

        $parameters[':keyword_name'] = $searchTerm;
        $parameters[':keyword_provider'] = $searchTerm;
        $parameters[':keyword_notes'] = $searchTerm;
    }

    // Only permit predefined sorting choices in SQL.
    $sortOptions = [
        'deadline_asc' =>
            's.application_deadline IS NULL,
             s.application_deadline ASC, s.scholarship_id ASC',
        'deadline_desc' =>
            's.application_deadline IS NULL,
             s.application_deadline DESC, s.scholarship_id ASC',
        'name_asc' =>
            's.scholarship_name ASC, s.scholarship_id ASC',
        'name_desc' =>
            's.scholarship_name DESC, s.scholarship_id ASC',
        'award_desc' =>
            's.award_amount_max IS NULL,
             s.award_amount_max DESC, s.scholarship_id ASC',
        'award_asc' =>
            's.award_amount_max IS NULL,
             s.award_amount_max ASC, s.scholarship_id ASC'
    ];

    $sort = $_GET['sort'] ?? 'deadline_asc';

    if (!is_string($sort) || !isset($sortOptions[$sort])) {
        $sort = 'deadline_asc';
    }

    // Count all matching scholarships before applying pagination.
    $countSql = 'SELECT COUNT(*) FROM (' . $sql . ') AS matches';
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($parameters);

    $totalResults = (int) $countStmt->fetchColumn();

    $perPage = 10;
    $totalPages = max(1, (int) ceil($totalResults / $perPage));

    $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
    $page = $page && $page > 0 ? min($page, $totalPages) : 1;

    $offset = ($page - 1) * $perPage;

    $sql .= ' ORDER BY ' . $sortOptions[$sort] . ' LIMIT :limit OFFSET :offset';

    $stmt = $pdo->prepare($sql);

    foreach ($parameters as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    // LIMIT and OFFSET must be bound as integers.
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmt->execute();
    $scholarships = $stmt->fetchAll();

} catch (Throwable $error) {
    error_log('Scholarship search failed: ' . $error->getMessage());
    $errorMessage = 'Scholarships cannot be loaded right now.';
}

require_once $projectRoot . '/includes/header.php';
require_once $projectRoot . '/includes/nav.php';
?>

<main class="container py-5">

    <h1 class="mb-3">Find Scholarships</h1>

    <p class="lead">
        Search scholarships by state, grade level, and field of study.
    </p>

    <form action="/pages/scholarships.php"
          method="get"
          class="row g-3 mb-4">

        <input type="hidden" name="search" value="1">

        <div class="col-md-4">
            <label for="state_id" class="form-label">State</label>

            <select name="state_id"
                    id="state_id"
                    class="form-select">

                <option value="">All States</option>

                <?php foreach ($states as $state): ?>
                    <option value="<?= (int) $state['state_id'] ?>"
                        <?= $stateId === (int) $state['state_id']
                            ? 'selected' : '' ?>>
                        <?= e($state['state_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-4">
            <label for="grade_id" class="form-label">Grade Level</label>

            <select name="grade_id"
                    id="grade_id"
                    class="form-select">

                <option value="">All Grades</option>

                <?php foreach ($grades as $grade): ?>
                    <option value="<?= (int) $grade['grade_id'] ?>"
                        <?= $gradeId === (int) $grade['grade_id']
                            ? 'selected' : '' ?>>
                        Grade <?= (int) $grade['grade_number'] ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-4">
            <label for="field_id" class="form-label">Field of Study</label>

            <select name="field_id"
                    id="field_id"
                    class="form-select">

                <option value="">All Fields</option>

                <?php foreach ($fields as $field): ?>
                    <option value="<?= (int) $field['field_id'] ?>"
                        <?= $fieldId === (int) $field['field_id']
                            ? 'selected' : '' ?>>
                        <?= e($field['field_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Additional scholarship filters required by FR-SRCH-02. -->
        <div class="col-md-6">
            <label for="award_min" class="form-label">
                Minimum Award ($)
            </label>
            <input type="number"
                   name="award_min"
                   id="award_min"
                   class="form-control"
                   min="0"
                   step="0.01"
                   value="<?= e((string) ($_GET['award_min'] ?? '')) ?>">
        </div>

        <div class="col-md-6">
            <label for="award_max" class="form-label">
                Maximum Award ($)
            </label>
            <input type="number"
                   name="award_max"
                   id="award_max"
                   class="form-control"
                   min="0"
                   step="0.01"
                   value="<?= e((string) ($_GET['award_max'] ?? '')) ?>">
        </div>

        <div class="col-md-6">
            <label for="deadline_from" class="form-label">
                Deadline From
            </label>
            <input type="date"
                   name="deadline_from"
                   id="deadline_from"
                   class="form-control"
                   value="<?= e((string) ($_GET['deadline_from'] ?? '')) ?>">
        </div>

        <div class="col-md-6">
            <label for="deadline_to" class="form-label">
                Deadline To
            </label>
            <input type="date"
                   name="deadline_to"
                   id="deadline_to"
                   class="form-control"
                   value="<?= e((string) ($_GET['deadline_to'] ?? '')) ?>">
        </div>

        <div class="col-md-4">
            <label for="category_id" class="form-label">
                Scholarship Category
            </label>
            <select name="category_id"
                    id="category_id"
                    class="form-select">
                <option value="">All Categories</option>

                <?php foreach ($categories as $category): ?>
                    <option value="<?= (int) $category['category_id'] ?>"
                        <?= $categoryId === (int) $category['category_id']
                            ? 'selected' : '' ?>>
                        <?= e($category['category_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php
        // Each Yes/No filter uses the same three choices.
        $yesNoFilters = [
            'renewable' => 'Renewable',
            'essay_required' => 'Essay Required',
            'financial_need_required' => 'Financial Need Required'
        ];
        ?>

        <?php foreach ($yesNoFilters as $filterName => $filterLabel): ?>
            <div class="col-md-4">
                <label for="<?= e($filterName) ?>" class="form-label">
                    <?= e($filterLabel) ?>
                </label>

                <select name="<?= e($filterName) ?>"
                        id="<?= e($filterName) ?>"
                        class="form-select">
                    <option value="">Any</option>
                    <option value="Yes"
                        <?= ($_GET[$filterName] ?? '') === 'Yes'
                            ? 'selected' : '' ?>>Yes</option>
                    <option value="No"
                        <?= ($_GET[$filterName] ?? '') === 'No'
                            ? 'selected' : '' ?>>No</option>
                    <option value="Unknown"
                        <?= ($_GET[$filterName] ?? '') === 'Unknown'
                            ? 'selected' : '' ?>>Unknown</option>
                </select>
            </div>
        <?php endforeach; ?>

        <div class="col-12">
            <div class="form-check">
                <input type="checkbox"
                       name="show_expired"
                       id="show_expired"
                       value="1"
                       class="form-check-input"
                       <?= ($_GET['show_expired'] ?? '') === '1'
                           ? 'checked' : '' ?>>
                <label for="show_expired" class="form-check-label">
                    Include Expired Scholarships
                </label>
            </div>
        </div>

        <div class="col-12">
            <label for="keyword" class="form-label">
                Keyword Search
            </label>

            <input type="search"
                   id="keyword"
                   name="keyword"
                   class="form-control"
                   maxlength="150"
                   placeholder="Search scholarships or providers"
                   value="<?= e($keyword) ?>">
        </div>

        <div class="col-md-6">
            <label for="sort" class="form-label">Sort By</label>
            <select name="sort" id="sort" class="form-select">
                <?php
                $sortLabels = [
                    'deadline_asc' => 'Deadline: Soonest First',
                    'deadline_desc' => 'Deadline: Latest First',
                    'name_asc' => 'Scholarship Name: A-Z',
                    'name_desc' => 'Scholarship Name: Z-A',
                    'award_desc' => 'Award: Highest First',
                    'award_asc' => 'Award: Lowest First'
                ];

                foreach ($sortLabels as $value => $label):
                ?>
                    <option value="<?= e($value) ?>"
                        <?= ($sort ?? 'deadline_asc') === $value
                            ? 'selected' : '' ?>>
                        <?= e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-12 d-flex gap-2">
            <button type="submit" class="btn btn-primary">
                Search Scholarships
            </button>

            <a href="/pages/scholarships.php"
               class="btn btn-outline-secondary">
                Clear Filters
            </a>
        </div>
    </form>

    <div id="scholarshipResults"
         aria-live="polite">

    <?php if ($errorMessage !== ''): ?>

        <div class="alert alert-danger">
            <?= e($errorMessage) ?>
        </div>

    <?php else: ?>

        <p class="text-muted">
            <?= $totalResults ?> scholarship(s) found.
            Page <?= $page ?> of <?= $totalPages ?>.
        </p>

        <?php if ($scholarships === []): ?>

            <div class="alert alert-info">
                No scholarships match the selected filters.
            </div>

        <?php else: ?>

            <!-- Mobile layout: scholarship cards -->
            <div class="row g-4 d-lg-none">

                <?php foreach ($scholarships as $scholarship): ?>

                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 shadow-sm">

                            <div class="card-body">

                                <h2 class="h5 card-title">
                                    <?= e($scholarship['scholarship_name']) ?>
                                </h2>

                                <p>
                                    <strong>Provider:</strong>
                                    <?= e($scholarship['provider_name']) ?>
                                </p>

                                <p>
                                    <strong>State:</strong>
                                    <?= e($scholarship['state_name'] ?? 'Not specified') ?>
                                </p>

                                <p>
                                    <strong>Type:</strong>
                                    <?= e($scholarship['national_or_state_local']) ?>
                                </p>

                                <p>
                                    <strong>Award:</strong>

                                    <?php if ($scholarship['award_amount_max'] !== null): ?>
                                        $<?= number_format(
                                            (float) $scholarship['award_amount_max'],
                                            2
                                        ) ?>
                                    <?php else: ?>
                                        Not specified
                                    <?php endif; ?>
                                </p>

                                <p>
                                    <strong>Deadline:</strong>
                                    <?= e(
                                        $scholarship['application_deadline']
                                        ?? 'Not specified'
                                    ) ?>
                                </p>

                                <?php if (!empty($scholarship['notes'])): ?>
                                    <p class="text-muted small">
                                        <?= e($scholarship['notes']) ?>
                                    </p>
                                <?php endif; ?>

                            </div>

                            <div class="card-footer bg-white d-flex flex-wrap gap-2">
                                <a href="/pages/scholarship_detail.php?id=<?= (int) $scholarship['scholarship_id'] ?>"
                                   class="btn btn-primary">
                                    View Details
                                </a>

                                <a href="<?= e($scholarship['official_url']) ?>"
                                   class="btn btn-outline-primary"
                                   target="_blank"
                                   rel="noopener noreferrer">
                                    Official Website
                                </a>
                            </div>

                        </div>
                    </div>

                <?php endforeach; ?>

            </div>

            <!-- Desktop layout: scholarship results table -->
            <div class="table-responsive d-none d-lg-block">
                <table class="table table-striped table-bordered align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Scholarship</th>
                            <th>Provider</th>
                            <th>State</th>
                            <th>Type</th>
                            <th>Award</th>
                            <th>Deadline</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($scholarships as $scholarship): ?>
                            <tr>
                                <td>
                                    <?= e($scholarship['scholarship_name']) ?>
                                </td>

                                <td>
                                    <?= e($scholarship['provider_name']) ?>
                                </td>

                                <td>
                                    <?= e(
                                        $scholarship['state_name']
                                        ?? 'Not specified'
                                    ) ?>
                                </td>

                                <td>
                                    <?= e(
                                        $scholarship['national_or_state_local']
                                    ) ?>
                                </td>

                                <td>
                                    <?php if ($scholarship['award_amount_max'] !== null): ?>
                                        $<?= number_format(
                                            (float) $scholarship['award_amount_max'],
                                            2
                                        ) ?>
                                    <?php else: ?>
                                        Not specified
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?= e(
                                        $scholarship['application_deadline']
                                        ?? 'Not specified'
                                    ) ?>
                                </td>

                                <td>
                                    <div class="d-flex flex-column gap-2">
                                        <a href="/pages/scholarship_detail.php?id=<?= (int) $scholarship['scholarship_id'] ?>"
                                           class="btn btn-sm btn-primary">
                                            View Details
                                        </a>

                                        <a href="<?= e($scholarship['official_url']) ?>"
                                           class="btn btn-sm btn-outline-primary"
                                           target="_blank"
                                           rel="noopener noreferrer">
                                            Official Website
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>

    <?php endif; ?>

    <?php if ($errorMessage === '' && $totalPages > 1): ?>
        <nav aria-label="Scholarship result pages" class="mt-4">
            <ul class="pagination flex-wrap">
                <?php for ($number = 1; $number <= $totalPages; $number++): ?>
                    <?php
                    $query = $_GET;
                    unset($query['search']);
                    $query['page'] = $number;
                    $url = '/pages/scholarships.php?' .
                        http_build_query($query);
                    ?>
                    <li class="page-item <?= $number === $page ? 'active' : '' ?>">
                        <a class="page-link"
                           href="<?= e($url) ?>">
                            <?= $number ?>
                        </a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>

    </div>

</main>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="/assets/js/scholarship_search.js"></script>

<?php require_once $projectRoot . '/includes/footer.php'; ?>
