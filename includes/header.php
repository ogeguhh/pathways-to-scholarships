<?php
// Pathways to Scholarships
// Shared HTML header used by every page.

// Use the page title provided by the page, or a default.
$pageTitle = $pageTitle ?? 'Pathways to Scholarships';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>

    <!-- Bootstrap 5 provides responsive styling and layout components. -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- Our custom CSS will go here as the project develops. -->
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
