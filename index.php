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

// Set the browser title before loading the shared header.
$pageTitle = 'Home | Pathways to Scholarships';

// Load reusable page components.
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';
require_once __DIR__ . '/includes/dev_banner.php';
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
    <!-- Search form remains inactive until Iteration 5. -->
    <section id="search" class="container py-5">
        <h2 class="h3 text-center mb-4">Search Scholarships</h2>

        <form>
            <div class="row g-3">

                <div class="col-md-4">
                    <label for="state" class="form-label">State</label>

                    <select id="state" class="form-select">
                        <option selected>Select State</option>
                        <option>National</option>
                        <option>Minnesota</option>
                        <option>Tennessee</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label for="grade" class="form-label">Grade Level</label>

                    <select id="grade" class="form-select">
                        <option selected>Select Grade</option>

                        <?php for ($grade = 6; $grade <= 12; $grade++): ?>
                            <option><?= $grade ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label for="field" class="form-label">Field of Study</label>

                    <select id="field" class="form-select">
                        <option selected>Select Field</option>
                        <option>STEM</option>
                        <option>Arts</option>
                        <option>General</option>
                    </select>
                </div>

            </div>

            <div class="text-center mt-4">
                <button
                    type="button"
                    class="btn btn-primary btn-lg"
                    disabled
                >
                    Search Scholarships
                </button>

                <p class="text-muted small mt-2">
                    Search functionality is coming in a later iteration.
                </p>
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
