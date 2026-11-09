
<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';
require_admin_access();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/import_reader.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit('Database connection unavailable.');
}

function exp_e($value): string {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

// Prevent spreadsheet formula injection.
function exp_safe($value): string {
    $value = (string)($value ?? '');

    return preg_match('/^\s*[=+@\-]/u', $value)
        ? "'" . $value
        : $value;
}

// Combine many-to-many values into one CSV field.
function exp_join(PDO $pdo, string $sql): array {
    $rows = [];

    foreach ($pdo->query($sql) as $row) {
        $id = (int)$row['scholarship_id'];
        $rows[$id][] = $row['label'];
    }

    return array_map(
        fn($values) => implode(', ', array_unique($values)),
        $rows
    );
}

$state = filter_input(
    INPUT_GET,
    'state',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

$theme = filter_input(
    INPUT_GET,
    'theme',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

$error = null;
$states = [];
$themes = [];

try {
    if ($state === false || $theme === false) {
        throw new InvalidArgumentException(
            'Invalid state or theme filter.'
        );
    }

    // Load available states.
    $states = $pdo->query(
        "SELECT state_id, state_name, state_code
         FROM states
         WHERE state_code <> 'NA'
         ORDER BY state_name"
    )->fetchAll(PDO::FETCH_ASSOC);

    // Load national themes.
    $themes = $pdo->query(
        "SELECT theme_id, theme_name
         FROM national_themes
         ORDER BY theme_name"
    )->fetchAll(PDO::FETCH_ASSOC);

    if ($state !== null && !in_array(
        $state,
        array_map('intval', array_column($states, 'state_id')),
        true
    )) {
        throw new InvalidArgumentException('Unknown state.');
    }

    if ($theme !== null && !in_array(
        $theme,
        array_map('intval', array_column($themes, 'theme_id')),
        true
    )) {
        throw new InvalidArgumentException('Unknown national theme.');
    }

    if (($_GET['download'] ?? '') === '1') {

        // Read exact column names from the original template.
        $headers = import_expected_headers();

        // Template columns mapped to schema_v1.sql fields.
        $mapping = [
            'scholarship name' => 'scholarship_name',
            'scholarship provider/organization' => 'provider_name',
            'provider type' => 'provider_type_name',
            'official scholarship url' => 'official_url',
            'discovery source' => 'discovery_source',
            'state/region' => 'state_code',
            'national vs. state/local' => 'national_or_state_local',
            'eligible grade levels' => 'grades',
            'middle school / high school / both' => 'school_level',
            'scholarship category' => 'categories',
            'field/area of interest' => 'fields',
            'award amount' => 'award_amount',
            'number of awards' => 'number_of_awards',
            'renewable' => 'renewable',
            'maximum potential value' => 'maximum_potential_value',
            'application open date' => 'application_open_date',
            'application deadline' => 'application_deadline',
            'residency requirement' => 'residency_requirement',
            'minimum gpa' => 'minimum_gpa',
            'financial need requirement' => 'financial_need_required',
            'financial need required' => 'financial_need_required',
            'essay requirement' => 'essay_required',
            'essay required' => 'essay_required',
            'recommendation letter requirement' => 'recommendation_required',
            'recommendation required' => 'recommendation_required',
            'transcript/test score requirement' => 'transcript_test_required',
            'transcript/test scores requirement' => 'transcript_test_required',
            'community service requirement' => 'community_service_required',
            'leadership requirement' => 'leadership_required',
            'other major eligibility requirements' => 'other_eligibility',
            'application method' => 'application_method',
            'verification date' => 'verification_date',
            'notes' => 'notes',
        ];

        $normalized = [];
        $unknown = [];

        foreach ($headers as $header) {
            $key = strtolower(trim(
                preg_replace('/\s+/u', ' ', (string)$header)
            ));

            if (!isset($mapping[$key])) {
                $unknown[] = $header;
            } else {
                $normalized[] = $mapping[$key];
            }
        }

        if ($unknown) {
            throw new RuntimeException(
                'Template columns need mapping: ' .
                implode(', ', $unknown)
            );
        }

        // Only export active scholarships.
        $where = ["s.status = 'active'"];
        $params = [];

        if ($state !== null) {
            $where[] = 's.state_id = :state';
            $params[':state'] = $state;
        }

        if ($theme !== null) {
            $where[] = 's.theme_id = :theme';
            $params[':theme'] = $theme;
        }

        $sql = "
            SELECT
                s.*,
                p.provider_name,
                pt.provider_type_name,
                st.state_code
            FROM scholarships s
            JOIN providers p
                ON p.provider_id = s.provider_id
            LEFT JOIN provider_types pt
                ON pt.provider_type_id = p.provider_type_id
            LEFT JOIN states st
                ON st.state_id = s.state_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY s.scholarship_id
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        // Related grade levels.
        $grades = exp_join(
            $pdo,
            "SELECT
                sg.scholarship_id,
                CAST(g.grade_level AS CHAR) AS label
             FROM scholarship_grades sg
             JOIN grade_levels g ON g.grade_id = sg.grade_id
             ORDER BY g.grade_level"
        );

        // Related scholarship categories.
        $categories = exp_join(
            $pdo,
            "SELECT
                sc.scholarship_id,
                c.category_name AS label
             FROM scholarship_categories sc
             JOIN categories c ON c.category_id = sc.category_id
             ORDER BY c.category_name"
        );

        // Related fields of study.
        $fields = exp_join(
            $pdo,
            "SELECT
                sf.scholarship_id,
                f.field_name AS label
             FROM scholarship_fields sf
             JOIN fields_of_study f ON f.field_id = sf.field_id
             ORDER BY f.field_name"
        );

        // Download CSV.
        header('Content-Type: text/csv; charset=UTF-8');

        header(
            'Content-Disposition: attachment; filename="scholarships_' .
            date('Ymd_His') . '.csv"'
        );

        header('Cache-Control: no-store, private');
        header('X-Content-Type-Options: nosniff');

        $out = fopen('php://output', 'wb');

        // Write original template headers.
        fputcsv($out, $headers, ',', '"', '');

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $id = (int)$row['scholarship_id'];

            $row['grades'] = $grades[$id] ?? '';
            $row['categories'] = $categories[$id] ?? '';
            $row['fields'] = $fields[$id] ?? '';

            // Format award amount.
            $min = $row['award_amount_min'];
            $max = $row['award_amount_max'];

            $row['award_amount'] =
                $min !== null && $max !== null && $min != $max
                    ? $min . ' - ' . $max
                    : ($min ?? $max ?? '');

            // National scholarships use National as their region.
            $row['state_code'] =
                $row['national_or_state_local'] === 'National'
                    ? 'National'
                    : ($row['state_code'] ?? '');

            // Preserve rolling deadlines.
            $row['application_deadline'] =
                !empty($row['rolling_deadline'])
                    ? 'Rolling'
                    : ($row['application_deadline'] ?? '');

            $csvRow = array_map(
                fn($key) => exp_safe($row[$key] ?? ''),
                $normalized
            );

            fputcsv($out, $csvRow, ',', '"', '');
        }

        fclose($out);
        exit;
    }

} catch (Throwable $ex) {
    error_log('CSV export: ' . $ex->getMessage());

    $error = $ex instanceof InvalidArgumentException
        ? $ex->getMessage()
        : 'CSV export could not be completed: ' . $ex->getMessage();

    http_response_code(400);
}
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <title>CSV Export | Pathways to Scholarships</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
        rel="stylesheet">
</head>

<body class="bg-light">
<main class="container py-5" style="max-width:850px">

    <nav class="mb-4">
        <a href="../index.php">Home</a> |
        <a href="coverage_report.php">Coverage Report</a> |
        <a href="import.php">Import</a>
    </nav>

    <h1>Export Scholarships</h1>

    <p class="text-muted">
        Download all active scholarships or filter the results
        by state and national theme.
    </p>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <?= exp_e($error) ?>
        </div>
    <?php endif; ?>

    <form method="get" class="card card-body">

        <input type="hidden" name="download" value="1">

        <div class="mb-3">
            <label for="state" class="form-label">State</label>

            <select id="state" name="state" class="form-select">
                <option value="">All States</option>

                <?php foreach ($states as $s): ?>
                    <option
                        value="<?= (int)$s['state_id'] ?>"
                        <?= $state === (int)$s['state_id']
                            ? 'selected' : '' ?>>
                        <?= exp_e($s['state_name']) ?>
                        (<?= exp_e($s['state_code']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="theme" class="form-label">
                National Theme
            </label>

            <select id="theme" name="theme" class="form-select">
                <option value="">All Themes</option>

                <?php foreach ($themes as $t): ?>
                    <option
                        value="<?= (int)$t['theme_id'] ?>"
                        <?= $theme === (int)$t['theme_id']
                            ? 'selected' : '' ?>>
                        <?= exp_e($t['theme_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <button class="btn btn-primary" type="submit">
            Download CSV
        </button>

    </form>

    <p class="small text-muted mt-3">
        Only active scholarships are exported.
        Staged and archived records are excluded.
        The original 29-column CSV template must exist
        in the templates folder.
    </p>

</main>
</body>
</html>
