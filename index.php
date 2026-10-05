<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Pathways to Scholarships</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>

    <!-- Navigation bar -->
    <nav class="navbar navbar-expand-lg bg-dark navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#">Pathways to Scholarships</a>

            <div class="navbar-nav ms-auto">
                <a class="nav-link active" href="#">Home</a>
                <a class="nav-link" href="#">Search Scholarships</a>
                <a class="nav-link" href="#">About</a>
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

                    <div class="mb-3">
                        <label class="form-label">State</label>
                        <select class="form-select">
                            <option>Select State</option>
                            <option>Minnesota</option>
                            <option>Wisconsin</option>
                            <option>Iowa</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Grade Level</label>
                        <select class="form-select">
                            <option>Select Grade</option>
                            <option>9th Grade</option>
                            <option>10th Grade</option>
                            <option>11th Grade</option>
                            <option>12th Grade</option>
                            <option>College</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Field of Study</label>
                        <input type="text" class="form-control"
                               placeholder="Example: Computer Science">
                    </div>

                    <button class="btn btn-primary w-100">
                        Search Scholarships
                    </button>

                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-dark text-white text-center p-3 mt-5">
        <p class="mb-0">Pathways to Scholarships - ICS 325 Final Project</p>
    </footer>

</body>

</html>
