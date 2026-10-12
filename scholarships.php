
<?php
// Load security and database functions
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/db.php';

// Set the page title
$pageTitle = "Scholarships - Pathways to Scholarships";

// Store scholarship results
$scholarships = [];
$error = '';

// Get scholarships from MySQL
try {
    $pdo = getDBConnection();

    $sql = "SELECT scholarship_id,
                   scholarship_name,
                   award_amount_min,
                   award_amount_max,
                   application_deadline,
                   school_level,
                   official_url
            FROM scholarships
            ORDER BY scholarship_name ASC
            LIMIT 50";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    $scholarships = $stmt->fetchAll();

} catch (Exception $e) {
    error_log($e->getMessage());
    $error = "Unable to load scholarships right now.";
}

// Load shared layout
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/dev-banner.php';
require_once __DIR__ . '/includes/nav.php';
?>

<main class="container mt-5">

    <h1>Available Scholarships</h1>

    <p class="lead">
        Browse scholarship opportunities for middle and high school students.
    </p>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <?= escape($error) ?>
        </div>

    <?php elseif (empty($scholarships)): ?>
        <div class="alert alert-info">
            No scholarships are available yet.
        </div>

    <?php else: ?>

        <div class="row">

            <?php foreach ($scholarships as $scholarship): ?>

                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-body">

                            <h2 class="h5 card-title">
                                <?= escape($scholarship['scholarship_name']) ?>
                            </h2>

                            <p>
                                <strong>School Level:</strong>
                                <?= escape($scholarship['school_level'] ?? 'Not specified') ?>
                            </p>

                            <p>
                                <strong>Award Amount:</strong>
                                <?php
                                $min = $scholarship['award_amount_min'];
                                $max = $scholarship['award_amount_max'];

                                if ($min !== null && $max !== null) {
                                    echo '$' . number_format((float)$min, 2);
                                    echo ' - $' . number_format((float)$max, 2);
                                } elseif ($min !== null) {
                                    echo '$' . number_format((float)$min, 2);
                                } elseif ($max !== null) {
                                    echo 'Up to $' . number_format((float)$max, 2);
                                } else {
                                    echo 'Not specified';
                                }
                                ?>
                            </p>

                            <p>
                                <strong>Deadline:</strong>
                                <?= escape($scholarship['application_deadline'] ?? 'Not specified') ?>
                            </p>

                            <?php
                            $url = $scholarship['official_url'];
                            $scheme = parse_url($url, PHP_URL_SCHEME);
                            ?>

                            <?php if (in_array(strtolower((string)$scheme), ['http', 'https'], true)): ?>
                                <a href="<?= escape($url) ?>"
                                   class="btn btn-primary"
                                   target="_blank"
                                   rel="noopener noreferrer">
                                    View Scholarship
                                </a>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</main>

<?php
// Load shared footer
require_once __DIR__ . '/includes/footer.php';
?>
