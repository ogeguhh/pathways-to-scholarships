<?php
declare(strict_types=1);

/*
 * Pathways to Scholarships
 * Iteration 2 — Contact Page
 *
 * Validates contact messages and saves them to MySQL.
 */

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/includes/security.php';
require_once $projectRoot . '/includes/db.php';

$pageTitle = 'Contact | Pathways to Scholarships';

$name = '';
$email = '';
$subject = '';
$message = '';
$errors = [];
$success = false;

// Handle contact form submissions.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Trim removes unnecessary spaces from user input.
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $subject = trim((string) ($_POST['subject'] ?? ''));
    $message = trim((string) ($_POST['message'] ?? ''));

    // Verify the CSRF token to protect against forged requests.
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid form submission. Please try again.';
    }

    // Server-side validation ensures submitted values are acceptable.
    if ($name === '' || mb_strlen($name) > 150) {
        $errors[] = 'Name is required and must be 150 characters or fewer.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)
        || strlen($email) > 255) {
        $errors[] = 'Enter a valid email address.';
    }

    if ($subject === '' || mb_strlen($subject) > 255) {
        $errors[] = 'Subject is required and must be 255 characters or fewer.';
    }

    if ($message === '' || mb_strlen($message) > 5000) {
        $errors[] = 'Message is required and must be 5000 characters or fewer.';
    }

    // Save the message only if validation passes.
    if (empty($errors)) {
        try {
            $pdo = getDBConnection();

            // Prepared statements prevent user input from becoming SQL code.
            $stmt = $pdo->prepare(
                'INSERT INTO contact_messages
                 (user_id, sender_name, sender_email, subject, message)
                 VALUES (NULL, :name, :email, :subject, :message)'
            );

            $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':subject' => $subject,
                ':message' => $message,
            ]);

            $success = true;

            // Clear the form after saving the message.
            $name = '';
            $email = '';
            $subject = '';
            $message = '';

        } catch (Throwable $e) {
            error_log('Contact form error: ' . $e->getMessage());
            $errors[] = 'Your message could not be saved. Please try again.';
        }
    }
}

require_once $projectRoot . '/includes/header.php';
require_once $projectRoot . '/includes/nav.php';
require_once $projectRoot . '/includes/dev_banner.php';
?>

<main class="container py-5">
    <h1 class="mb-3">Contact Us</h1>

    <p class="lead">
        Have a question about Pathways to Scholarships?
        Send us a message using the form below.
    </p>

    <?php if ($success): ?>
        <div class="alert alert-success" role="status">
            Your message has been submitted successfully.
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" action="/pages/contact.php" class="mt-4">

        <!-- CSRF token is stored in the user's PHP session. -->
        <input
            type="hidden"
            name="csrf_token"
            value="<?= e(csrf_token()) ?>"
        >

        <div class="mb-3">
            <label for="name" class="form-label">Your Name</label>
            <input
                type="text"
                class="form-control"
                id="name"
                name="name"
                maxlength="150"
                value="<?= e($name) ?>"
                required
            >
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">Email Address</label>
            <input
                type="email"
                class="form-control"
                id="email"
                name="email"
                maxlength="255"
                value="<?= e($email) ?>"
                required
            >
        </div>

        <div class="mb-3">
            <label for="subject" class="form-label">Subject</label>
            <input
                type="text"
                class="form-control"
                id="subject"
                name="subject"
                maxlength="255"
                value="<?= e($subject) ?>"
                required
            >
        </div>

        <div class="mb-3">
            <label for="message" class="form-label">Message</label>
            <textarea
                class="form-control"
                id="message"
                name="message"
                rows="5"
                maxlength="5000"
                required
            ><?= e($message) ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary">
            Send Message
        </button>
    </form>
</main>

<?php
require_once $projectRoot . '/includes/footer.php';
?>
