
<?php
// Set the page title
$pageTitle = "Contact - Pathways to Scholarships";

// Load shared layout
require_once 'includes/header.php';
require_once 'includes/dev-banner.php';
require_once 'includes/nav.php';
?>

<main class="container mt-5">
    <h1>Contact Us</h1>

    <p class="lead">
        Have a question or feedback about Pathways to Scholarships?
        Send us a message using the form below.
    </p>

    <div class="row">
        <div class="col-md-8">
            <form method="POST" action="contact.php">

                <div class="mb-3">
                    <label for="name" class="form-label">Name</label>
                    <input type="text" class="form-control"
                           id="name" name="name"
                           maxlength="100" required>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control"
                           id="email" name="email"
                           maxlength="255" required>
                </div>

                <div class="mb-3">
                    <label for="message" class="form-label">Message</label>
                    <textarea class="form-control"
                              id="message" name="message"
                              rows="5" maxlength="5000"
                              required></textarea>
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
require_once 'includes/footer.php';
?>
