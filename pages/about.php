<?php
declare(strict_types=1);

/*
 * Pathways to Scholarships
 * Iteration 2 — About Page
 *
 * Explains the purpose of the scholarship website.
 */

// __DIR__ identifies this file's folder.
// dirname(__DIR__) moves up to the project root.
$projectRoot = dirname(__DIR__);

$pageTitle = 'About | Pathways to Scholarships';

require_once $projectRoot . '/includes/header.php';
require_once $projectRoot . '/includes/nav.php';
require_once $projectRoot . '/includes/dev_banner.php';
?>

<main class="container py-5">
    <h1 class="mb-4">About Pathways to Scholarships</h1>

    <p class="lead">
        Pathways to Scholarships helps middle school and high school
        students discover scholarship opportunities.
    </p>

    <h2 class="h4 mt-4">Our Purpose</h2>
    <p>
        Finding scholarships can be difficult when information is
        spread across different websites. Our goal is to make it
        easier for students and families to explore opportunities
        in one place.
    </p>

    <h2 class="h4 mt-4">How It Works</h2>
    <p>
        Students will be able to search for scholarships using
        information such as their state, grade level, and
        field of study.
    </p>

    <h2 class="h4 mt-4">Who Can Use This Website?</h2>
    <p>
        The website is designed for middle school students,
        high school students, parents, and guardians.
    </p>

    <p class="text-muted mt-4">
        This website is currently under development as a
        student software project.
    </p>
</main>

<?php
require_once $projectRoot . '/includes/footer.php';
?>
