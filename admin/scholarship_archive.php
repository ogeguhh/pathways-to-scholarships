<?php
declare(strict_types=1);

/*
 * Iteration 4 - Archive Scholarship
 *
 * Administrators can archive a scholarship without deleting it.
 * The scholarship and its relationships remain in MySQL.
 *
 * Development mode only until real authentication is added.
 */

// Load security and database functions.
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';

// Call the project's development authorization placeholder.
require_role('Administrator');

// Read the scholarship ID from the URL.
$id = filter_var(
    $_GET['id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($id === false || $id === null) {
    http_response_code(400);
    exit('A valid scholarship ID is required.');
}

// Store messages displayed to the administrator.
$error = '';
$success = '';

// Load the scholarship before displaying the page.
$stmt = $pdo->prepare("
    SELECT scholarship_id, scholarship_name, status
    FROM scholarships
    WHERE scholarship_id = ?
    LIMIT 1
");

$stmt->execute([$id]);
$scholarship = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$scholarship) {
    http_response_code(404);
    exit('Scholarship not found.');
}

// Process the archive confirmation form.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Prevent unauthorized cross-site form submissions.
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        $error = 'Invalid security token. Reload and try again.';

    } elseif (($_POST['confirm_archive'] ?? '') !== 'yes') {
        $error = 'Please confirm that you want to archive this scholarship.';

    } else {

        try {
            // Start a transaction so changes can be rolled back.
            $pdo->beginTransaction();

            // Lock the scholarship while checking its current status.
            $lock = $pdo->prepare("
                SELECT status
                FROM scholarships
                WHERE scholarship_id = ?
                FOR UPDATE
            ");

            $lock->execute([$id]);
            $currentStatus = $lock->fetchColumn();

            if ($currentStatus === false) {
                throw new RuntimeException('Scholarship no longer exists.');
            }

            if ($currentStatus === 'archived') {
                // Nothing needs to change if it is already archived.
                $pdo->commit();

            } else {
                // Update status without deleting the scholarship.
                $update = $pdo->prepare("
                    UPDATE scholarships
                    SET status = 'archived',
                        updated_by = ?
                    WHERE scholarship_id = ?
                      AND status = 'active'
                ");

                $update->execute([
                    current_user_id(),
                    $id
                ]);

                // Commit only after the update succeeds.
                $pdo->commit();
            }

            // Redirect to prevent repeated POST submissions.
            header(
                'Location: scholarship_archive.php?id=' .
                $id . '&archived=1',
                true,
                303
            );
            exit;

        } catch (Throwable $e) {

            // Undo any unfinished database changes.
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            // Log technical details without displaying them publicly.
            error_log(
                'Scholarship archive failed: ' . $e->getMessage()
            );

            $error = 'Unable to archive the scholarship. Please try again.';
        }
    }
}

// Show the success message after a completed archive.
if (
    isset($_GET['archived']) &&
    $scholarship['status'] === 'archived'
) {
    $success = 'Scholarship archived successfully!';
}
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>Archive Scholarship | Pathways to Scholarships</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
</head>

<body class="bg-light">

<main class="container py-5" style="max-width:750px">

    <a href="scholarship_view.php?id=<?= e($id) ?>"
       class="text-decoration-none">
        &larr; Back to Scholarship
    </a>

    <h1 class="mt-3">Archive Scholarship</h1>

    <div class="alert alert-warning">
        <strong>Development mode — no login required.</strong>
        This administrator page is intended for local testing only.
    </div>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($success !== ''): ?>
        <div class="alert alert-success">
            <?= e($success) ?>
        </div>
    <?php endif; ?>

    <section class="card">
        <div class="card-body">

            <h2 class="h5">
                <?= e($scholarship['scholarship_name']) ?>
            </h2>

            <p>
                <strong>Scholarship ID:</strong>
                <?= e($scholarship['scholarship_id']) ?>
            </p>

            <p>
                <strong>Current Status:</strong>
                <?= e(ucfirst($scholarship['status'])) ?>
            </p>

            <?php if ($scholarship['status'] === 'archived'): ?>

                <div class="alert alert-secondary">
                    This scholarship is already archived.
                    Its information remains in the database.
                </div>

                <a
                    href="scholarship_view.php?id=<?= e($id) ?>"
                    class="btn btn-primary"
                >
                    View Scholarship
                </a>

            <?php else: ?>

                <p class="text-muted">
                    Archiving removes this scholarship from active
                    listings without permanently deleting its record.
                    Its grades, categories, and fields are preserved.
                </p>

                <!-- POST is required to change database records. -->
                <form method="post"
                      action="scholarship_archive.php?id=<?= e($id) ?>">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e(csrf_token()) ?>"
                    >

                    <div class="form-check mb-4">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            name="confirm_archive"
                            id="confirm_archive"
                            value="yes"
                            required
                        >

                        <label class="form-check-label"
                               for="confirm_archive">
                            I confirm that I want to archive this scholarship.
                        </label>
                    </div>

                    <div class="d-flex gap-2">
                        <button
                            type="submit"
                            class="btn btn-danger"
                        >
                            Archive Scholarship
                        </button>

                        <a
                            href="scholarship_view.php?id=<?= e($id) ?>"
                            class="btn btn-outline-secondary"
                        >
                            Cancel
                        </a>
                    </div>

                </form>

            <?php endif; ?>

        </div>
    </section>

</main>

</body>
</html>
