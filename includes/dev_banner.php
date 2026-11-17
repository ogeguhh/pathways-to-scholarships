<?php
// Pathways to Scholarships
// Development-mode banner.

// Load the configuration settings.
require_once __DIR__ . '/config.php';

// Show the banner only when development mode is enabled.
if (defined('APP_DEBUG') && APP_DEBUG === true):
?>

<div class="alert alert-warning text-center rounded-0 mb-0" role="alert">
    <strong>Development Mode:</strong>
    This website is currently running in a testing environment.
</div>

<?php endif; ?>
