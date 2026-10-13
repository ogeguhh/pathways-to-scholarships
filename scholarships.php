<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/security.php';
$scholarships = [];
$error = false;
$state = trim((string) ($_GET['state'] ?? ''));
$grade = trim((string) ($_GET['grade'] ?? ''));
$field = trim((string) ($_GET['field'] ?? ''));
$validStates = ['MN', 'WI', 'IA'];
$validGrades = ['9', '10', '11', '12'];
$invalidFilters = ($state !== '' && !in_array($state, $validStates, true))
    || ($grade !== '' && !in_array($grade, $validGrades, true))
    || mb_strlen($field) > 150;

if ($invalidFilters) {
    http_response_code(400);
} else {
    try {
        require_once __DIR__ . '/includes/db.php'; // Team's PDO file must provide $pdo.
        if (!isset($pdo) || !($pdo instanceof PDO)) throw new RuntimeException('PDO connection unavailable');
        $conditions = ["sc.status = 'active'"];
        $parameters = [];
        if ($state !== '') {
            $conditions[] = "(st.state_code = :state OR sc.national_or_state_local = 'National')";
            $parameters[':state'] = $state;
        }
        if ($grade !== '') {
            $conditions[] = 'EXISTS (
                SELECT 1
                FROM scholarship_grades AS sg
                JOIN grade_levels AS gl ON gl.grade_id = sg.grade_id
                WHERE sg.scholarship_id = sc.scholarship_id
                  AND gl.grade_level = :grade
            )';
            $parameters[':grade'] = (int) $grade;
        }
        if ($field !== '') {
            $conditions[] = 'EXISTS (
                SELECT 1
                FROM scholarship_fields AS sf
                JOIN fields_of_study AS fs ON fs.field_id = sf.field_id
                WHERE sf.scholarship_id = sc.scholarship_id
                  AND fs.field_name LIKE :field
            )';
            $parameters[':field'] = '%' . $field . '%';
        }

        $sql = "SELECT
                sc.scholarship_name,
                p.provider_name,
                st.state_name,
                sc.national_or_state_local,
                sc.award_amount_min,
                sc.award_amount_max,
                sc.application_deadline
            FROM scholarships AS sc
            JOIN providers AS p ON p.provider_id = sc.provider_id
            LEFT JOIN states AS st ON st.state_id = sc.state_id
            WHERE " . implode(' AND ', $conditions) . "
            ORDER BY sc.scholarship_name
            LIMIT 100";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($parameters);
        $scholarships = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $ex) {
        error_log('Scholarship list error: ' . $ex->getMessage());
        http_response_code(500);
        $error = true;
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Scholarships | Pathways to Scholarships</title><link href="assets/css/site.css" rel="stylesheet"></head><body>
<nav class="site-nav"><a href="index.php">Pathways to Scholarships</a> | <a href="index.php">Home</a> | <a href="about.php">About</a> | <a href="faq.php">FAQ</a> | <a href="contact.php">Contact</a> | <a href="scholarships.php">Scholarships</a></nav>
<main class="site-main"><h1>Available Scholarships</h1>
<form action="scholarships.php" method="get">
    <label for="state">State</label>
    <select id="state" name="state">
        <option value="">Any state</option>
        <option value="MN"<?= $state === 'MN' ? ' selected' : '' ?>>Minnesota</option>
        <option value="WI"<?= $state === 'WI' ? ' selected' : '' ?>>Wisconsin</option>
        <option value="IA"<?= $state === 'IA' ? ' selected' : '' ?>>Iowa</option>
    </select>
    <label for="grade">Grade Level</label>
    <select id="grade" name="grade">
        <option value="">Any grade</option>
        <option value="9"<?= $grade === '9' ? ' selected' : '' ?>>9th Grade</option>
        <option value="10"<?= $grade === '10' ? ' selected' : '' ?>>10th Grade</option>
        <option value="11"<?= $grade === '11' ? ' selected' : '' ?>>11th Grade</option>
        <option value="12"<?= $grade === '12' ? ' selected' : '' ?>>12th Grade</option>
    </select>
    <label for="field">Field of Study</label>
    <input id="field" name="field" maxlength="150" value="<?= e($field) ?>" placeholder="Example: STEM">
    <button type="submit">Search</button>
    <a href="scholarships.php">Clear filters</a>
</form>
<?php if ($invalidFilters): ?><p role="alert">Choose a valid state and grade, and keep the field of study to 150 characters or fewer.</p>
<?php elseif ($error): ?><p>Scholarships cannot be loaded right now.</p>
<?php elseif (!$scholarships): ?><p>No scholarships match your search.</p>
<?php else: ?>
<table>
<thead><tr><th scope="col">Scholarship</th><th scope="col">Provider</th><th scope="col">Location</th><th scope="col">Award</th><th scope="col">Deadline</th></tr></thead>
<tbody>
<?php foreach ($scholarships as $item): ?>
<?php
$minimum = $item['award_amount_min'];
$maximum = $item['award_amount_max'];
if ($minimum !== null && $maximum !== null) {
    $award = (float) $minimum === (float) $maximum
        ? '$' . number_format((float) $minimum, 2)
        : '$' . number_format((float) $minimum, 2) . '–$' . number_format((float) $maximum, 2);
} elseif ($minimum !== null) {
    $award = 'From $' . number_format((float) $minimum, 2);
} elseif ($maximum !== null) {
    $award = 'Up to $' . number_format((float) $maximum, 2);
} else {
    $award = 'Not specified';
}
$location = $item['state_name'] ?? $item['national_or_state_local'];
?>
<tr>
    <td><?= e($item['scholarship_name']) ?></td>
    <td><?= e($item['provider_name']) ?></td>
    <td><?= e($location) ?></td>
    <td><?= e($award) ?></td>
    <td><?= e($item['application_deadline'] ?? 'Not specified') ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
</main><footer class="site-footer"><p>Pathways to Scholarships - ICS 325 Final Project</p></footer></body></html>
