<?php
declare(strict_types=1);

/*
 * Pathways to Scholarships
 * Iteration 2 — Read-Only Scholarship List
 *
 * Retrieves scholarship records from MySQL.
 * Search filters will be implemented in Iteration 5.
 */

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/includes/db.php';
require_once $projectRoot . '/includes/security.php';

$pageTitle = 'Scholarships | Pathways to Scholarships';
$scholarships = [];
$errorMessage = '';

// Retrieve active scholarships using a PDO prepared statement.
try {
    $pdo = getDBConnection();

    $stmt = $pdo->prepare(
        'SELECT
            s.scholarship_name,
            s.official_url,
            s.national_or_state_local,
            s.award_amount_max,
            s.application_deadline,
            s.notes,
            p.provider_name,
            st.state_name
         FROM scholarships s
         JOIN providers p ON s.provider_id = p.provider_id
         LEFT JOIN states st ON s.state_id = st.state_id
         WHERE s.status = :status
         ORDER BY s.application_deadline ASC'
    );

    $stmt->execute([':status' => 'active']);
    $scholarships = $stmt->fetchAll();

} catch (Throwable $e) {
    // Log technical errors without exposing them to visitors.
    error_log('Scholarship list error: ' . $e->getMessage());
    $errorMessage = 'Scholarships cannot be loaded right now.';
}

require_once $projectRoot . '/includes/header.php';
require_once $projectRoot . '/includes/nav.php';
require_once $projectRoot . '/includes/dev_banner.php';
?>

<main class="container py-5">
    <h1 class="mb-3">Scholarship Opportunities</h1>

    <p class="lead">
        Browse available scholarship records.
        Search filters will be added in a later iteration.
    </p>

    <?php if ($errorMessage !== ''): ?>

        <div class="alert alert-danger" role="alert">
            <?= e($errorMessage) ?>
        </div>

    <?php elseif (empty($scholarships)): ?>

        <div class="alert alert-info">
            No scholarships are currently available.
        </div>

    <?php else: ?>

        <div class="row g-4">
            <?php foreach ($scholarships as $scholarship): ?>

                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">

                            <h2 class="h5 card-title">
                                <?= e($scholarship['scholarship_name']) ?>
                            </h2>

                            <p class="card-text mb-2">
                                <strong>Provider:</strong>
                                <?= e($scholarship['provider_name']) ?>
                            </p>

                            <p class="card-text mb-2">
                                <strong>State:</strong>
                                <?= e($scholarship['state_name'] ?? 'Not specified') ?>
                            </p>

                            <p class="card-text mb-2">
                                <strong>Type:</strong>
                                <?= e($scholarship['national_or_state_local']) ?>
                            </p>

                            <p class="card-text mb-2">
                                <strong>Award:</strong>
                                <?php if ($scholarship['award_amount_max'] !== null): ?>
                                    $<?= number_format((float) $scholarship['award_amount_max'], 2) ?>
                                <?php else: ?>
                                    Not specified
                                <?php endif; ?>
                            </p>

                            <p class="card-text mb-2">
                                <strong>Deadline:</strong>
                                <?= e($scholarship['application_deadline'] ?? 'Not specified') ?>
                            </p>

                            <?php if (!empty($scholarship['notes'])): ?>
                                <p class="text-muted small">
                                    <?= e($scholarship['notes']) ?>
                                </p>
                            <?php endif; ?>

                        </div>

                        <div class="card-footer bg-white">
                            <?php
                            // Only allow HTTP(S) links to scholarship websites.
                            $url = $scholarship['official_url'];
                            $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
                            ?>

                            <?php if (in_array($scheme, ['http', 'https'], true)): ?>
                                <a
                                    href="<?= e($url) ?>"
                                    class="btn btn-outline-primary"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    Official Website
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
require_once $projectRoot . '/includes/footer.php';
?>
