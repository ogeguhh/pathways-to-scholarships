<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/security.php';
$errors = [];
$success = false;
$name = $email = $message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        $errors[] = 'Your session token is invalid. Refresh and try again.';
    } else {
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $message = trim((string) ($_POST['message'] ?? ''));
        if ($name === '' || mb_strlen($name) > 100) $errors[] = 'Name must be 1–100 characters.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) $errors[] = 'Enter a valid email address.';
        if ($message === '' || mb_strlen($message) > 5000) $errors[] = 'Message must be 1–5000 characters.';
        if (!$errors) {
            try {
                require_once __DIR__ . '/includes/db.php'; // Team's PDO file must provide $pdo.
                if (!isset($pdo) || !($pdo instanceof PDO)) throw new RuntimeException('PDO connection unavailable');
                // Confirm table/column names against the team's actual schema before use.
                $stmt = $pdo->prepare('INSERT INTO contact_messages (name, email, message) VALUES (:name, :email, :message)');
                $stmt->execute([':name' => $name, ':email' => $email, ':message' => $message]);
                $success = true;
                $name = $email = $message = '';
            } catch (Throwable $ex) {
                error_log('Contact form error: ' . $ex->getMessage());
                http_response_code(500);
                $errors[] = 'Unable to save your message right now.';
            }
        }
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Contact | Pathways to Scholarships</title><link href="assets/css/site.css" rel="stylesheet"></head><body>
<nav class="site-nav"><a href="index.php">Pathways to Scholarships</a> | <a href="index.php">Home</a> | <a href="about.php">About</a> | <a href="faq.php">FAQ</a> | <a href="contact.php">Contact</a> | <a href="scholarships.php">Scholarships</a></nav>
<main class="site-main"><h1>Contact Us</h1>
<?php if ($success): ?><p role="status">Your message was saved successfully.</p><?php endif; ?>
<?php foreach ($errors as $error): ?><p role="alert"><?= e($error) ?></p><?php endforeach; ?>
<form method="post" action="contact.php">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
<p><label for="name">Name</label><br><input id="name" name="name" maxlength="100" required value="<?= e($name) ?>"></p>
<p><label for="email">Email</label><br><input id="email" name="email" type="email" maxlength="254" required value="<?= e($email) ?>"></p>
<p><label for="message">Message</label><br><textarea id="message" name="message" maxlength="5000" required><?= e($message) ?></textarea></p>
<button type="submit">Send message</button>
</form></main><footer class="site-footer"><p>Pathways to Scholarships - ICS 325 Final Project</p></footer></body></html>
