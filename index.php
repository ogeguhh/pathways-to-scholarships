<?php
declare(strict_types=1);

/*
 * Pathways to Scholarships
 * Iteration 7 — Homepage
 *
 * Includes:
 * - Scholarship quick search
 * - Session-aware account controls
 * - Secure logout form
 * - Shared header, navigation, and footer
 */

// Load database and security helpers.
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';

// Start or resume the session.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Enforce the session timeout if the security helper provides it.
if (function_exists('check_session_timeout')) {
    check_session_timeout();
}

// Determine the current authenticated user.
$loggedIn = !empty($_SESSION['user_id']);

$userName = '';

if ($loggedIn) {
    $userName = trim(
        (string) ($_SESSION['full_name'] ?? '')
    );

    if ($userName === '') {
        $userName = (string) ($_SESSION['email'] ?? 'Account');
    }

    // Generate a CSRF token for secure logout.
    if (empty($_SESSION['logout_csrf'])) {
        $_SESSION['logout_csrf'] = bin2hex(random_bytes(32));
    }
}

// Load scholarship search filters.
$pdo = getDBConnection();

$states = $pdo->query(
    'SELECT state_id, state_name
     FROM states
     ORDER BY state_name'
)->fetchAll();

$grades = $pdo->query(
    'SELECT grade_id, grade_number
     FROM grade_levels
     ORDER BY grade_number'
)->fetchAll();

$fields = $pdo->query(
    'SELECT field_id, field_name
     FROM fields_of_study
     ORDER BY field_name'
)->fetchAll();

// Browser title.
$pageTitle = 'Home | Pathways to Scholarships';

// Load shared page components.
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';
?>

<!-- Homepage introduction -->
<header class="bg-light py-5">
    <div class="container text-center">

        <h1 class="display-5 fw-bold">
            Find Scholarships For You
        </h1>

        <p class="lead">
            Explore scholarship opportunities based on your state,
            grade level, and field of study.
        </p>

        <?php if ($loggedIn): ?>
            <p class="text-primary fw-semibold mb-0">
                Welcome, <?= e($userName) ?>!
            </p>
        <?php endif; ?>

    </div>
</header>

<main>


    <!-- Scholarship quick search -->
    <section id="search" class="container py-5">

        <h2 class="h3 text-center mb-4">
            Search Scholarships
        </h2>

        <form action="pages/scholarships.php" method="get">

            <input type="hidden" name="search" value="1">

            <div class="row g-3">

                <!-- State -->
                <div class="col-md-4">

                    <label for="state_id" class="form-label">
                        State
                    </label>

                    <select
                        id="state_id"
                        name="state_id"
                        class="form-select"
                    >

                        <option value="">
                            All States
                        </option>

                        <?php foreach ($states as $state): ?>
                            <option value="<?= (int) $state['state_id'] ?>">
                                <?= e($state['state_name']) ?>
                            </option>
                        <?php endforeach; ?>

                    </select>

                </div>

                <!-- Grade -->
                <div class="col-md-4">

                    <label for="grade_id" class="form-label">
                        Grade Level
                    </label>

                    <select
                        id="grade_id"
                        name="grade_id"
                        class="form-select"
                    >

                        <option value="">
                            All Grades
                        </option>

                        <?php foreach ($grades as $grade): ?>
                            <option value="<?= (int) $grade['grade_id'] ?>">
                                Grade <?= (int) $grade['grade_number'] ?>
                            </option>
                        <?php endforeach; ?>

                    </select>

                </div>

                <!-- Field of Study -->
                <div class="col-md-4">

                    <label for="field_id" class="form-label">
                        Field of Study
                    </label>

                    <select
                        id="field_id"
                        name="field_id"
                        class="form-select"
                    >

                        <option value="">
                            All Fields
                        </option>

                        <?php foreach ($fields as $field): ?>
                            <option value="<?= (int) $field['field_id'] ?>">
                                <?= e($field['field_name']) ?>
                            </option>
                        <?php endforeach; ?>

                    </select>

                </div>

            </div>

            <div class="text-center mt-4">

                <button
                    type="submit"
                    class="btn btn-primary btn-lg"
                >
                    Search Scholarships
                </button>

            </div>

        </form>

    </section>

    <!-- About -->
    <section id="about" class="bg-light py-5">

        <div class="container text-center">

            <h2 class="h3">
                About Pathways to Scholarships
            </h2>

            <p class="mb-0">
                Our goal is to help middle and high school students
                and their families discover scholarship opportunities.
            </p>

        </div>

    </section>

</main>

<?php
// Shared footer.
require_once __DIR__ . '/includes/footer.php';
?>
