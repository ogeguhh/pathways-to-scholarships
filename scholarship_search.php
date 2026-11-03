<?php
declare(strict_types=1);

/*
 * ICS 325 - Pathways to Scholarships
 * Iteration 5 - Integrated Search Logic
 *
 * Bullets 1-3:
 * - Scholarship filters
 * - Keyword search, sorting, and pagination
 * - Shared search logic for AJAX
 *
 * Bullet 4:
 * - Hide expired scholarships by default
 * - Allow users to include expired scholarships
 */

/**
 * Load dropdown options from the database.
 */
function scholarship_search_options(PDO $pdo): array
{
    return [
        'states' => $pdo->query("
            SELECT state_code, state_name
            FROM states
            ORDER BY state_name
        ")->fetchAll(PDO::FETCH_ASSOC),

        'grades' => $pdo->query("
            SELECT grade_level
            FROM grade_levels
            WHERE grade_level BETWEEN 6 AND 12
            ORDER BY grade_level
        ")->fetchAll(PDO::FETCH_ASSOC),

        'fields' => $pdo->query("
            SELECT field_id, field_name
            FROM fields_of_study
            ORDER BY field_name
        ")->fetchAll(PDO::FETCH_ASSOC),

        'categories' => $pdo->query("
            SELECT category_id, category_name
            FROM categories
            ORDER BY category_name
        ")->fetchAll(PDO::FETCH_ASSOC)
    ];
}

/**
 * Validate and clean all search inputs.
 *
 * Returns filters and validation errors separately.
 */
function scholarship_search_filters(
    array $input,
    array $options
): array {
    $errors = [];

    $names = [
        'state',
        'grade',
        'field',
        'category',
        'award_min',
        'award_max',
        'deadline_from',
        'deadline_to',
        'renewable',
        'essay_required',
        'financial_need_required',
        'keyword',
        'sort',
        'page',
        'include_expired'
    ];

    $filters = [];

    foreach ($names as $name) {
        $value = $input[$name] ?? '';

        // Reject arrays where a single value is expected.
        if (!is_string($value) && !is_int($value)) {
            $errors[$name] = 'Invalid filter value.';
            $filters[$name] = '';
            continue;
        }

        $filters[$name] = trim((string)$value);
    }

    // Default sorting and page number.
    if ($filters['sort'] === '') {
        $filters['sort'] = 'deadline';
    }

    if ($filters['page'] === '') {
        $filters['page'] = '1';
    }

    // Validate state codes, including National.
    $validStates = array_column(
        $options['states'],
        'state_code'
    );

    $validStates[] = 'National';

    if (
        $filters['state'] !== '' &&
        !in_array($filters['state'], $validStates, true)
    ) {
        $errors['state'] = 'Select a valid state.';
    }

    // Validate grade levels.
    $validGrades = array_map(
        'strval',
        array_column($options['grades'], 'grade_level')
    );

    if (
        $filters['grade'] !== '' &&
        !in_array($filters['grade'], $validGrades, true)
    ) {
        $errors['grade'] = 'Select a valid grade.';
    }

    // Validate field and category IDs.
    foreach (
        ['field' => 'fields', 'category' => 'categories']
        as $name => $list
    ) {
        $idColumn = $name === 'field'
            ? 'field_id'
            : 'category_id';

        $validIds = array_map(
            'strval',
            array_column($options[$list], $idColumn)
        );

        if (
            $filters[$name] !== '' &&
            !in_array($filters[$name], $validIds, true)
        ) {
            $errors[$name] = 'Select a valid option.';
        }
    }

    // Validate nonnegative award amounts.
    foreach (['award_min', 'award_max'] as $name) {
        $value = $filters[$name];

        if (
            $value !== '' &&
            (
                !is_numeric($value) ||
                !is_finite((float)$value) ||
                (float)$value < 0 ||
                (float)$value > 9999999999.99
            )
        ) {
            $errors[$name] = 'Enter a valid award amount.';
        }
    }

    if (
        !isset($errors['award_min']) &&
        !isset($errors['award_max']) &&
        $filters['award_min'] !== '' &&
        $filters['award_max'] !== '' &&
        (float)$filters['award_max'] < (float)$filters['award_min']
    ) {
        $errors['award_max'] =
            'Maximum award cannot be less than minimum award.';
    }

    // Validate deadline dates.
    foreach (['deadline_from', 'deadline_to'] as $name) {
        $value = $filters[$name];

        if ($value === '') {
            continue;
        }

        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $value
        );

        if (!$date || $date->format('Y-m-d') !== $value) {
            $errors[$name] = 'Enter a valid date.';
        }
    }

    if (
        !isset($errors['deadline_from']) &&
        !isset($errors['deadline_to']) &&
        $filters['deadline_from'] !== '' &&
        $filters['deadline_to'] !== '' &&
        $filters['deadline_to'] < $filters['deadline_from']
    ) {
        $errors['deadline_to'] =
            'End date cannot be before start date.';
    }

    // Validate Yes, No, and Unknown selections.
    foreach (
        ['renewable', 'essay_required', 'financial_need_required']
        as $name
    ) {
        if (
            $filters[$name] !== '' &&
            !in_array(
                $filters[$name],
                ['Yes', 'No', 'Unknown'],
                true
            )
        ) {
            $errors[$name] = 'Select a valid option.';
        }
    }

    // Limit keyword length.
    if (mb_strlen($filters['keyword']) > 150) {
        $errors['keyword'] =
            'Keyword must be 150 characters or fewer.';
    }

    // Only allow known sorting choices.
    if (!in_array(
        $filters['sort'],
        ['deadline', 'award', 'name'],
        true
    )) {
        $errors['sort'] = 'Select a valid sorting option.';
    }

    // Pagination requires a positive integer.
    if (
        !ctype_digit($filters['page']) ||
        (int)$filters['page'] < 1 ||
        (int)$filters['page'] > 100000
    ) {
        $errors['page'] = 'Invalid page number.';
    }

    // Validate expired scholarship checkbox.
    // An unchecked checkbox sends no value.
    if (
        $filters['include_expired'] !== '' &&
        !in_array(
            $filters['include_expired'],
            ['0', '1'],
            true
        )
    ) {
        $errors['include_expired'] =
            'Invalid expired scholarship option.';
    }

    return [
        'filters' => $filters,
        'errors' => $errors
    ];
}

/**
 * Build the WHERE conditions and bound parameters.
 *
 * EXISTS is used for relationship tables to avoid
 * duplicate scholarship rows.
 */
function scholarship_search_conditions(array $filters): array
{
    $conditions = ["sc.status = 'active'"];
    $params = [];

    /*
     * Bullet 4: Hide expired scholarships by default.
     *
     * Include scholarships when:
     * - Their deadline is today or in the future
     * - Their deadline is not specified
     * - They have a rolling deadline
     *
     * Checking "Include expired" removes this restriction.
     */
    if (($filters['include_expired'] ?? '') !== '1') {
        $conditions[] = "(
            sc.application_deadline >= CURDATE()
            OR sc.application_deadline IS NULL
            OR sc.rolling_deadline = 1
        )";
    }

    // State filtering, including national scholarships.
    if ($filters['state'] === 'National') {
        $conditions[] =
            "sc.national_or_state_local = 'National'";
    } elseif ($filters['state'] !== '') {
        $conditions[] = "(
            st.state_code = :state
            OR sc.national_or_state_local = 'National'
        )";

        $params[':state'] = $filters['state'];
    }

    // Grade level.
    if ($filters['grade'] !== '') {
        $conditions[] = "EXISTS (
            SELECT 1
            FROM scholarship_grades sg
            JOIN grade_levels gl
                ON gl.grade_id = sg.grade_id
            WHERE sg.scholarship_id = sc.scholarship_id
              AND gl.grade_level = :grade
        )";

        $params[':grade'] = (int)$filters['grade'];
    }

    // Field of study.
    if ($filters['field'] !== '') {
        $conditions[] = "EXISTS (
            SELECT 1
            FROM scholarship_fields sf
            WHERE sf.scholarship_id = sc.scholarship_id
              AND sf.field_id = :field
        )";

        $params[':field'] = (int)$filters['field'];
    }

    // Scholarship category.
    if ($filters['category'] !== '') {
        $conditions[] = "EXISTS (
            SELECT 1
            FROM scholarship_categories sca
            WHERE sca.scholarship_id = sc.scholarship_id
              AND sca.category_id = :category
        )";

        $params[':category'] = (int)$filters['category'];
    }

    // Match award ranges that overlap the requested range.
    if ($filters['award_min'] !== '') {
        $conditions[] = "
            COALESCE(sc.award_amount_max, sc.award_amount_min)
            >= :award_min
        ";

        $params[':award_min'] = $filters['award_min'];
    }

    if ($filters['award_max'] !== '') {
        $conditions[] = "
            COALESCE(sc.award_amount_min, sc.award_amount_max)
            <= :award_max
        ";

        $params[':award_max'] = $filters['award_max'];
    }

    // Deadline date range.
    if ($filters['deadline_from'] !== '') {
        $conditions[] =
            "sc.application_deadline >= :deadline_from";

        $params[':deadline_from'] = $filters['deadline_from'];
    }

    if ($filters['deadline_to'] !== '') {
        $conditions[] =
            "sc.application_deadline <= :deadline_to";

        $params[':deadline_to'] = $filters['deadline_to'];
    }

    // Eligibility filters.
    foreach (
        ['renewable', 'essay_required', 'financial_need_required']
        as $name
    ) {
        if ($filters[$name] !== '') {
            // Column names come only from our fixed list.
            $conditions[] = "sc.$name = :$name";
            $params[":$name"] = $filters[$name];
        }
    }

    // Search scholarship name, provider, and notes.
    if ($filters['keyword'] !== '') {
        $conditions[] = "(
            sc.scholarship_name LIKE :keyword_name
            OR p.provider_name LIKE :keyword_provider
            OR sc.notes LIKE :keyword_notes
        )";

        $pattern = '%' . $filters['keyword'] . '%';

        $params[':keyword_name'] = $pattern;
        $params[':keyword_provider'] = $pattern;
        $params[':keyword_notes'] = $pattern;
    }

    return [
        'where' => implode(' AND ', $conditions),
        'params' => $params
    ];
}

/**
 * Return paginated search results and the total count.
 *
 * COUNT(*) determines the total number of matching
 * scholarships before pagination is applied.
 */
function scholarship_search_results(
    PDO $pdo,
    array $filters
): array {
    $search = scholarship_search_conditions($filters);

    $where = $search['where'];
    $params = $search['params'];

    $from = "
        FROM scholarships sc
        JOIN providers p
            ON p.provider_id = sc.provider_id
        LEFT JOIN states st
            ON st.state_id = sc.state_id
    ";

    // Count all matching scholarships.
    $countStmt = $pdo->prepare(
        "SELECT COUNT(*) " . $from . " WHERE " . $where
    );

    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    // Show ten scholarships per page.
    $perPage = 10;
    $totalPages = max(1, (int)ceil($total / $perPage));

    $page = min((int)$filters['page'], $totalPages);
    $offset = ($page - 1) * $perPage;

    // Sorting uses fixed SQL, never raw user input.
    $sortOptions = [
        'deadline' => "
            sc.application_deadline IS NULL ASC,
            sc.application_deadline ASC,
            sc.scholarship_id ASC
        ",

        'award' => "
            sc.award_amount_max IS NULL ASC,
            sc.award_amount_max DESC,
            sc.scholarship_id ASC
        ",

        'name' => "
            sc.scholarship_name ASC,
            sc.scholarship_id ASC
        "
    ];

    $orderBy = $sortOptions[$filters['sort']];

    $sql = "
        SELECT
            sc.scholarship_id,
            sc.scholarship_name,
            p.provider_name,
            st.state_name,
            sc.national_or_state_local,
            sc.award_amount_min,
            sc.award_amount_max,
            sc.application_deadline
        " . $from . "
        WHERE " . $where . "
        ORDER BY " . $orderBy . "
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $pdo->prepare($sql);

    // Bind filters to the prepared statement.
    foreach ($params as $key => $value) {
        $stmt->bindValue(
            $key,
            $value,
            is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR
        );
    }

    // Pagination values must be bound as integers.
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmt->execute();

    return [
        'scholarships' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => $totalPages
    ];
}
