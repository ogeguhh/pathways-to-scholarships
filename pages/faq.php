<?php
declare(strict_types=1);

/*
 * Pathways to Scholarships
 * Iteration 2 — Frequently Asked Questions
 *
 * Answers common questions about the scholarship website.
 */

$projectRoot = dirname(__DIR__);
$pageTitle = 'FAQ | Pathways to Scholarships';

require_once $projectRoot . '/includes/header.php';
require_once $projectRoot . '/includes/nav.php';
require_once $projectRoot . '/includes/dev_banner.php';
?>

<main class="container py-5">
    <h1 class="mb-3">Frequently Asked Questions</h1>

    <p class="lead mb-4">
        Find answers to common questions about Pathways to Scholarships.
    </p>

    <!-- Bootstrap accordion allows visitors to expand each answer. -->
    <div class="accordion" id="faqAccordion">

        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button" type="button"
                        data-bs-toggle="collapse" data-bs-target="#faqOne"
                        aria-expanded="true" aria-controls="faqOne">
                    Who can use Pathways to Scholarships?
                </button>
            </h2>
            <div id="faqOne" class="accordion-collapse collapse show"
                 data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                    The website is designed for middle school and high
                    school students, as well as parents and guardians.
                </div>
            </div>
        </div>

        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button"
                        data-bs-toggle="collapse" data-bs-target="#faqTwo"
                        aria-expanded="false" aria-controls="faqTwo">
                    How do I find scholarships?
                </button>
            </h2>
            <div id="faqTwo" class="accordion-collapse collapse"
                 data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                    Once scholarship searching is available, you will
                    be able to filter opportunities by state, grade
                    level, and field of study.
                </div>
            </div>
        </div>

        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button"
                        data-bs-toggle="collapse" data-bs-target="#faqThree"
                        aria-expanded="false" aria-controls="faqThree">
                    Can I apply for scholarships directly on this website?
                </button>
            </h2>
            <div id="faqThree" class="accordion-collapse collapse"
                 data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                    No. The website will help you discover scholarships
                    and visit the official scholarship provider's
                    website for application instructions.
                </div>
            </div>
        </div>

        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button"
                        data-bs-toggle="collapse" data-bs-target="#faqFour"
                        aria-expanded="false" aria-controls="faqFour">
                    Are scholarships available nationwide?
                </button>
            </h2>
            <div id="faqFour" class="accordion-collapse collapse"
                 data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                    The project includes national scholarships as well
                    as scholarships limited to particular states or
                    local communities. Eligibility varies by scholarship.
                </div>
            </div>
        </div>

        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button"
                        data-bs-toggle="collapse" data-bs-target="#faqFive"
                        aria-expanded="false" aria-controls="faqFive">
                    Is the website finished?
                </button>
            </h2>
            <div id="faqFive" class="accordion-collapse collapse"
                 data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                    Not yet. Pathways to Scholarships is currently
                    being developed as a student software project.
                    Additional features will be introduced in
                    later iterations.
                </div>
            </div>
        </div>

    </div>
</main>

<?php
require_once $projectRoot . '/includes/footer.php';
?>
