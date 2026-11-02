<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new RuntimeException('Database unavailable');
    }

    $state = trim((string) ($_GET['state'] ?? ''));
    $grade = trim((string) ($_GET['grade'] ?? ''));
    $field = trim((string) ($_GET['field'] ?? ''));
    $sort = (string) ($_GET['sort'] ?? 'deadline_asc');
    $includeExpired = ($_GET['include_expired'] ?? '') === '1';

    $page = filter_var(
        $_GET['page'] ?? 1,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($page === false) {
        throw new InvalidArgumentException('Invalid page number');
    }

    $validStates = ['MN', 'WI', 'IA'];
    $validGrades = ['9', '10', '11', '12'];

    if (
        ($state !== '' && !in_array($state, $validStates, true))
        || ($grade !== '' && !in_array($grade, $validGrades, true))
        || mb_strlen($field) > 150
    ) {
        throw new InvalidArgumentException('Invalid search filters');
    }

    $sortOptions = [
        'deadline_asc' => 'sc.application_deadline ASC',
        'deadline_desc' => 'sc.application_deadline DESC',
        'award_desc' => 'COALESCE(sc.award_amount_max, sc.award_amount_min) DESC',
        'award_asc' => 'COALESCE(sc.award_amount_min, sc.award_amount_max) ASC',
        'name_asc' => 'sc.scholarship_name ASC',
        'name_desc' => 'sc.scholarship_name DESC'
    ];

    if (!array_key_exists($sort, $sortOptions)) {
        $sort = 'deadline_asc';
    }

    $conditions = ["sc.status = 'active'"];
    $parameters = [];

    // FR-SRCH-06: Exclude expired scholarships
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

    $fromWhere = "
        FROM scholarships AS sc
        JOIN providers AS p
            ON p.provider_id = sc.provider_id
        LEFT JOIN states AS st
            ON st.state_id = sc.state_id
        WHERE " . implode(' AND ', $conditions);

    // Count results before applying pagination
    $countStmt = $pdo->prepare(
        "SELECT COUNT(*) " . $fromWhere
    );

    $countStmt->execute($parameters);

    $total = (int) $countStmt->fetchColumn();

    // FR-SRCH-05: Pagination
    $perPage = 10;
    $totalPages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $orderBy = $sortOptions[$sort];

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
        " . $fromWhere . "
        ORDER BY
            " . ($sort === 'deadline_asc' || $sort === 'deadline_desc'
                ? 'sc.application_deadline IS NULL ASC,'
                : '') . "
            $orderBy,
            sc.scholarship_id ASC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $pdo->prepare($sql);

    foreach ($parameters as $key => $value) {
        $stmt->bindValue(
            $key,
            $value,
            is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR
        );
    }

    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmt->execute();

    echo json_encode([
        'success' => true,
        'scholarships' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'page' => $page,
        'perPage' => $perPage,
        'total' => $total,
        'totalPages' => $totalPages
    ], JSON_THROW_ON_ERROR);

} catch (InvalidArgumentException $ex) {

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => $ex->getMessage()
    ]);

} catch (Throwable $ex) {

    error_log('Scholarship AJAX error: ' . $ex->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Unable to load scholarships.'
    ]);
}
