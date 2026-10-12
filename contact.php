

<?php
// Load security and database functions
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/db.php';

// Set the page title
$pageTitle = "Contact - Pathways to Scholarships";

// Store form values and messages
$name = '';
$email = '';
$subject = '';
$message = '';
$errors = [];
$success = false;

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Get submitted values
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    // Check CSRF token
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $errors[] = "Invalid form submission. Please try again.";
    }

    // Validate name
    if ($name === '' || mb_strlen($name) > 150) {
        $errors[] = "Enter a name of 150 characters or fewer.";
    }

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)
        || strlen($email) > 255) {
        $errors[] = "Enter a valid email address.";
    }

    // Validate subject
    if ($subject === '' || mb_strlen($subject) > 255) {
        $errors[] = "Enter a subject of 255 characters or fewer.";
    }

    // Validate message
    if ($message === '' || mb_strlen($message) > 5000) {
        $errors[] = "Enter a message of 5000 characters or fewer.";
    }

    // Save message if there are no errors
    if (empty($errors)) {
        try {
            // Connect to MySQL
            $pdo = getDBConnection();

            // Insert message using a prepared statement
            $sql = "INSERT INTO contact_messages
                    (user_id, name, email, subject, message)
                    VALUES (NULL, :name, :email, :subject, :message)";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':subject' => $subject,
                ':message' => $message
            ]);

            $success = true;

            // Clear form after successful submission
            $name = '';
            $email = '';
            $subject = '';
            $message = '';

            // Generate a fresh token
            unset($_SESSION['csrf_token']);

        } catch (Exception $e) {
            error_log($e->getMessage());
            $errors[] = "Unable to send your message right now.";
        }
    }
}

// Load shared layout
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/dev-banner.php';
require_once __DIR__ . '/includes/nav.php';
?>

<main class="container mt-5">
    <h1>Contact Us</h1>

    <p class="lead">
        Have a question or feedback about Pathways to Scholarships?
        Send us a message using the form below.
    </p>

    <?php if ($success): ?>
        <div class="alert alert-success" role="alert">
            Your message was submitted successfully!
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger" role="alert">
            <?php foreach ($errors as $error): ?>
                <p class="mb-1"><?= escape($error) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-8">

            <form method="POST" action="contact.php">

                <!-- CSRF protection -->
                <input type="hidden" name="csrf_token"
                       value="<?= escape(csrf_token()) ?>">

                <!-- Name -->
                <div class="mb-3">
                    <label for="name" class="form-label">Name</label>
                    <input type="text" class="form-control"
                           id="name" name="name"
                           maxlength="150" required
                           value="<?= escape($name) ?>">
                </div>

                <!-- Email -->
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control"
                           id="email" name="email"
                           maxlength="255" required
                           value="<?= escape($email) ?>">
                </div>

                <!-- Subject -->
                <div class="mb-3">
                    <label for="subject" class="form-label">Subject</label>
                    <input type="text" class="form-control"
                           id="subject" name="subject"
                           maxlength="255" required
                           value="<?= escape($subject) ?>">
                </div>

                <!-- Message -->
                <div class="mb-3">
                    <label for="message" class="form-label">Message</label>
                    <textarea class="form-control"
                              id="message" name="message"
                              rows="5" maxlength="5000"
                              required><?= escape($message) ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary">
                    Send Message
                </button>

            </form>

        </div>
    </div>
</main>

<?php
// Load shared footer
require_once __DIR__ . '/includes/footer.php';
?>
