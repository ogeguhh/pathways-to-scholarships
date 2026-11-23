<?php
declare(strict_types=1);

/*
 * Pathways to Scholarships
 * Iteration 2 — Homepage
 *
 * This page uses shared PHP components for the header,
 * navigation, development banner, and footer.
 *
 * Scholarship searching will be implemented in Iteration 5.
 */

// Load the database and escaping helper for the quick search.
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';

// Load the same filter options used by the scholarship search page.
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

// Set the browser title before loading the shared header.
$pageTitle = 'Home | Pathways to Scholarships';

// Load reusable page components.
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';
?>

<!-- Homepage introduction -->
<header class="bg-light py-5">
    <div class="container text-center">
        <h1 class="display-5 fw-bold">Find Scholarships For You</h1>

        <p class="lead">
            Explore scholarship opportunities based on your state,
            grade level, and field of study.
        </p>
    </div>
</header>

<main>
    <!-- Homepage quick search connected to Iteration 5. -->
    <section id="search" class="container py-5">
        <h2 class="h3 text-center mb-4">Search Scholarships</h2>

        <form action="/pages/scholarships.php" method="get">
            <input type="hidden" name="search" value="1">
            <div class="row g-3">

                <div class="col-md-4">
                    <label for="state_id" class="form-label">State</label>

                    <select id="state_id"
                            name="state_id"
                            class="form-select">

                        <option value="">All States</option>

                        <?php foreach ($states as $state): ?>
                            <option value="<?= (int) $state['state_id'] ?>">
                                <?= e($state['state_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label for="grade_id" class="form-label">
                        Grade Level
                    </label>

                    <select id="grade_id"
                            name="grade_id"
                            class="form-select">

                        <option value="">All Grades</option>

                        <?php foreach ($grades as $grade): ?>
                            <option value="<?= (int) $grade['grade_id'] ?>">
                                Grade <?= (int) $grade['grade_number'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label for="field_id" class="form-label">
                        Field of Study
                    </label>

                    <select id="field_id"
                            name="field_id"
                            class="form-select">

                        <option value="">All Fields</option>

                        <?php foreach ($fields as $field): ?>
                            <option value="<?= (int) $field['field_id'] ?>">
                                <?= e($field['field_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

            </div>

            <div class="text-center mt-4">
                <button type="submit"
                        class="btn btn-primary btn-lg">
                    Search Scholarships
                </button>
            </div>
        </form>
    </section>

    <!-- Brief introduction to the project -->
    <section id="about" class="bg-light py-5">
        <div class="container text-center">
            <h2 class="h3">About Pathways to Scholarships</h2>

            <p class="mb-0">
                Our goal is to help middle and high school students
                and their families discover scholarship opportunities.
            </p>
        </div>
    </section>
</main>

<?php
// Close the page using the shared footer.
require_once __DIR__ . '/includes/footer.php';
?>
