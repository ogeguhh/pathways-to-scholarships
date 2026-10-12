<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/security.php';
$scholarships = [];
$error = false;
try {
    require_once __DIR__ . '/includes/db.php'; // Team's PDO file must provide $pdo.
    if (!isset($pdo) || !($pdo instanceof PDO)) throw new RuntimeException('PDO connection unavailable');
    // Confirm table/column names against the team's actual schema before use.
    $stmt = $pdo->query('SELECT title, amount FROM scholarships ORDER BY title LIMIT 100');
    $scholarships = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $ex) {
    error_log('Scholarship list error: ' . $ex->getMessage());
    http_response_code(500);
    $error = true;
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Scholarships | Pathways to Scholarships</title></head><body>
<nav><a href="index.php">Home</a> | <a href="about.php">About</a> | <a href="faq.php">FAQ</a> | <a href="contact.php">Contact</a> | <a href="scholarships.php">Scholarships</a></nav>
<main><h1>Available Scholarships</h1>
<?php if ($error): ?><p>Scholarships cannot be loaded right now.</p>
<?php elseif (!$scholarships): ?><p>No scholarships have been added yet.</p>
<?php else: ?><ul><?php foreach ($scholarships as $item): ?><li><?= e($item['title']) ?> — $<?= e(number_format((float) $item['amount'], 2)) ?></li><?php endforeach; ?></ul><?php endif; ?>
</main></body></html>
