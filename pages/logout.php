<?php
declare(strict_types=1);

// Pathways to Scholarships
// Iteration 7: Secure Logout

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/includes/security.php';

ensure_session();

// Only POST requests can perform logout.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        exit('Invalid security token.');
    }

    logout_user();

    header('Location: /pages/login.php?logged_out=1');
    exit;
}

require_login();

$pageTitle = 'Logout | Pathways to Scholarships';

require_once $projectRoot . '/includes/header.php';
require_once $projectRoot . '/includes/nav.php';
?>

<main class="container py-5">

    <div class="row justify-content-center">

        <div class="col-md-6">

            <div class="card shadow-sm">

                <div class="card-body p-4">

                    <h1 class="h3">Logout</h1>

                    <p>Are you sure you want to log out?</p>

                    <form method="POST">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= e(csrf_token()) ?>"
                        >

                        <button type="submit" class="btn btn-danger">
                            Logout
                        </button>

                        <a href="/" class="btn btn-secondary">
                            Cancel
                        </a>

                    </form>

                </div>

            </div>

        </div>

    </div>

</main>

<?php
require_once $projectRoot . '/includes/footer.php';
?>
