<?php
declare(strict_types=1);

/*
 * Iteration 1 — Static Home Page
 *
 * This page introduces Pathways to Scholarships.
 * Search functionality will be added in Iteration 5.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Pathways to Scholarships</title>

    <!-- Bootstrap provides responsive layout and styling. -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
</head>
<body class="d-flex flex-column min-vh-100">

    <!-- Main navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                Pathways to Scholarships
            </a>

            <button
                class="navbar-toggler"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#mainNavigation"
                aria-controls="mainNavigation"
                aria-expanded="false"
                aria-label="Toggle navigation"
            >
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="mainNavigation">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#search">Search Scholarships</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#about">About</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

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

    <main class="flex-grow-1">
        <!-- Static search form for Iteration 1 -->
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
                    <button type="button" class="btn btn-primary btn-lg" disabled>
                        Search Scholarships
                    </button>
                    <p class="text-muted small mt-2">
                        Search functionality is coming in a later iteration.
                    </p>
                </div>
            </form>
        </section>

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

    <!-- Page footer -->
    <footer class="bg-dark text-white text-center py-3">
        <div class="container">
            <p class="mb-0">
                &copy; <?= date('Y') ?> Pathways to Scholarships
            </p>
        </div>
    </footer>

    <!-- Bootstrap JavaScript enables the mobile navigation menu. -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
