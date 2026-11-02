<?php
declare(strict_types=1);

/*
 * ICS 325 - Pathways to Scholarships
 * Iteration 5 - Bullet 3: AJAX Search Endpoint
 *
 * Receives search filters from jQuery.
 * Uses the existing scholarship search helper.
 * Returns matching scholarships as JSON.
 *
 * JSON allows JavaScript to update the page
 * without reloading the entire website.
 */

// Tell the browser this endpoint returns JSON.
header('Content-Type: application/json; charset=utf-8');

// Prevent browsers from caching outdated search results.
header('Cache-Control: no-store');

// Load the existing search functions.
require_once __DIR__ . '/includes/scholarship_search.php';

try {
    // Reuse the team's PDO database connection.
    require_once __DIR__ . '/includes/db.php';

    // Load valid dropdown options for input validation.
    $options = scholarship_search_options($pdo);

    // Validate the filters sent by the browser.
    $validation = scholarship_search_filters($_GET, $options);

    if ($validation['errors'] !== []) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'errors' => array_values($validation['errors'])
        ], JSON_THROW_ON_ERROR);

        exit;
    }

    // Reuse the same search logic as scholarships.php.
    $results = scholarship_search_results(
        $pdo,
        $validation['filters']
    );

    // Return scholarships and pagination information.
    echo json_encode([
        'success' => true,
        'scholarships' => $results['scholarships'],
        'total' => $results['total'],
        'page' => $results['page'],
        'per_page' => $results['per_page'],
        'total_pages' => $results['total_pages']
    ], JSON_THROW_ON_ERROR);

} catch (Throwable $exception) {

    // Log technical details on the server only.
    error_log(
        'Scholarship AJAX error: ' . $exception->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'errors' => [
            'Scholarships cannot be loaded right now.'
        ]
    ]);
}
