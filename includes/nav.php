<?php
// Pathways to Scholarships
// Shared navigation bar.

// Identify the current page so we can highlight its navigation link.
$currentPage = basename(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">

        <!-- Website name links back to the homepage. -->
        <a class="navbar-brand fw-bold" href="/">
            Pathways to Scholarships
        </a>

        <!-- This button displays the menu on smaller screens. -->
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
                    <a class="nav-link <?= $currentPage === 'index.php' || $currentPage === '' ? 'active' : '' ?>"
                       href="/">
                        Home
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'scholarships.php' ? 'active' : '' ?>"
                       href="/pages/scholarships.php">
                        Search Scholarships
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'about.php' ? 'active' : '' ?>"
                       href="/pages/about.php">
                        About
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'login.php' ? 'active' : '' ?>"
                       href="/pages/login.php">
                        Login
                    </a>
                </li>

            </ul>
        </div>
    </div>
</nav>
