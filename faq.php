
<?php
// Set the page title
$pageTitle = "FAQ - Pathways to Scholarships";

// Load shared layout
include 'includes/header.php';
include 'includes/dev-banner.php';
include 'includes/nav.php';
?>

<main class="container mt-5">
    <h1>Frequently Asked Questions</h1>

    <p class="lead">
        Here are some common questions about Pathways to Scholarships.
    </p>

    <div class="accordion mt-4" id="faqAccordion">

        <!-- Question 1 -->
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button" type="button"
                        data-bs-toggle="collapse" data-bs-target="#answer1"
                        aria-expanded="true" aria-controls="answer1">
                    What is Pathways to Scholarships?
                </button>
            </h2>
            <div id="answer1" class="accordion-collapse collapse show"
                 data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                    Pathways to Scholarships is a website that helps
                    middle and high school students find scholarship
                    opportunities based on their interests and education.
                </div>
            </div>
        </div>

        <!-- Question 2 -->
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button"
                        data-bs-toggle="collapse" data-bs-target="#answer2"
                        aria-expanded="false" aria-controls="answer2">
                    Do I need an account to search for scholarships?
                </button>
            </h2>
            <div id="answer2" class="accordion-collapse collapse"
                 data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                    No. Visitors can search and view scholarships without
                    creating an account. Accounts will provide additional
                    features such as personalized matches.
                </div>
            </div>
        </div>

        <!-- Question 3 -->
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button"
                        data-bs-toggle="collapse" data-bs-target="#answer3"
                        aria-expanded="false" aria-controls="answer3">
                    How can I find scholarships that match me?
                </button>
            </h2>
            <div id="answer3" class="accordion-collapse collapse"
                 data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                    You will be able to filter scholarships by state,
                    grade level, and field of study to find opportunities
                    that match your interests.
                </div>
            </div>
        </div>

        <!-- Question 4 -->
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button"
                        data-bs-toggle="collapse" data-bs-target="#answer4"
                        aria-expanded="false" aria-controls="answer4">
                    How do I apply for a scholarship?
                </button>
            </h2>
            <div id="answer4" class="accordion-collapse collapse"
                 data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                    Each scholarship will include a link to the official
                    provider's website, where you can review the
                    application instructions and requirements.
                </div>
            </div>
        </div>

        <!-- Question 5 -->
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button"
                        data-bs-toggle="collapse" data-bs-target="#answer5"
                        aria-expanded="false" aria-controls="answer5">
                    Is the website free to use?
                </button>
            </h2>
            <div id="answer5" class="accordion-collapse collapse"
                 data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                    Yes. Pathways to Scholarships is designed to help
                    students and parents explore scholarship information
                    without paying to use the website.
                </div>
            </div>
        </div>

    </div>
</main>

<?php
// Load shared footer
include 'includes/footer.php';
?>
