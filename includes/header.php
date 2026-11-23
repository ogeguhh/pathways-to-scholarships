<?php
// Pathways to Scholarships
// Shared HTML header used by every page.

require_once __DIR__ . '/security.php';

// Initialize authentication before sending HTML to the browser.
ensure_session();

current_user();

// Use the supplied page title or the default website title.
$pageTitle = $pageTitle ?? 'Pathways to Scholarships';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>

    <!-- Bootstrap provides responsive styling and components. -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- Shared custom website styling. -->
    <link rel="stylesheet" href="/assets/css/style.css">

</head>
<body>
