<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Pathways to Scholarships</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/site.css" rel="stylesheet">
</head>

<body>

    <!-- Navigation bar -->
    <nav class="navbar navbar-expand-lg bg-dark navbar-dark site-nav">
        <div class="container">
            <a class="navbar-brand" href="index.php">Pathways to Scholarships</a>

            <div class="navbar-nav ms-auto">
                <a class="nav-link active" href="index.php">Home</a>
                <a class="nav-link" href="scholarships.php">Search Scholarships</a>
                <a class="nav-link" href="about.php">About</a>
                <a class="nav-link" href="faq.php">FAQ</a>
                <a class="nav-link" href="contact.php">Contact</a>
                <a class="nav-link" href="#">Login</a>
            </div>
        </div>
    </nav>

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

                    <form action="scholarships.php" method="get">
                        <div class="mb-3">
                            <label class="form-label" for="state">State</label>
                            <select class="form-select" id="state" name="state">
                                <option value="">Any state</option>
                                <option value="MN">Minnesota</option>
                                <option value="WI">Wisconsin</option>
                                <option value="IA">Iowa</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="grade">Grade Level</label>
                            <select class="form-select" id="grade" name="grade">
                                <option value="">Any grade</option>
                                <option value="9">9th Grade</option>
                                <option value="10">10th Grade</option>
                                <option value="11">11th Grade</option>
                                <option value="12">12th Grade</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="field">Field of Study</label>
                            <input type="text" class="form-control" id="field" name="field"
                                   maxlength="150" placeholder="Example: STEM">
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            Search Scholarships
                        </button>
                    </form>

                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-dark text-white text-center p-3 mt-5 site-footer">
        <p class="mb-0">Pathways to Scholarships - ICS 325 Final Project</p>
    </footer>

</body>

</html>
