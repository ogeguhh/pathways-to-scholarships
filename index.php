
<?php
// Set the page title
$pageTitle = "Pathways to Scholarships";

// Load the shared website layout
include 'includes/header.php';
include 'includes/dev-banner.php';
include 'includes/nav.php';
?>

<!-- Main header -->
<header class="container text-center mt-5">
    <h1>Find Scholarships For You</h1>

    <p class="lead">
        Search for scholarship opportunities that match your education goals.
    </p>
</header>

<!-- Scholarship search area -->
<main class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">

            <div class="card p-4">

                <h2 class="text-center mb-4">Search Scholarships</h2>

                <div class="mb-3">
                    <label for="state" class="form-label">State</label>
                    <select id="state" class="form-select">
                        <option value="">Select State</option>
                        <option value="MN">Minnesota</option>
                        <option value="WI">Wisconsin</option>
                        <option value="IA">Iowa</option>
                        <option value="National">National</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="grade" class="form-label">Grade Level</label>
                    <select id="grade" class="form-select">
                        <option value="">Select Grade</option>
                        <option value="6">6th Grade</option>
                        <option value="7">7th Grade</option>
                        <option value="8">8th Grade</option>
                        <option value="9">9th Grade</option>
                        <option value="10">10th Grade</option>
                        <option value="11">11th Grade</option>
                        <option value="12">12th Grade</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="field" class="form-label">Field of Study</label>
                    <input type="text" id="field" class="form-control"
                           placeholder="Example: Computer Science">
                </div>

                <button type="button" class="btn btn-primary w-100">
                    Search Scholarships
                </button>

            </div>
        </div>
    </div>
</main>

<?php
// Load the shared footer
include 'includes/footer.php';
?>
