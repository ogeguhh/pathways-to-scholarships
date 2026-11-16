<?php
// Pathways to Scholarships
// Iteration 2: Database Connection

// Load the local database settings.
require_once __DIR__ . '/config.php';

// PDO provides a secure way for PHP to communicate with MySQL.
// This function creates a connection when the application needs one.
function getDBConnection(): PDO
{
    static $pdo = null;

    // Reuse the connection if one already exists.
    if ($pdo !== null) {
        return $pdo;
    }

    // DSN (Data Source Name) tells PDO where the database is located.
    $dsn = 'mysql:host=' . DB_HOST
         . ';port=' . DB_PORT
         . ';dbname=' . DB_NAME
         . ';charset=utf8mb4';

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, [
            // Throw exceptions when database operations fail.
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

            // Return query results as associative arrays.
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

            // Use native MySQL prepared statements.
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return $pdo;

    } catch (PDOException $e) {
        // Log technical details privately, not in the browser.
        error_log('Database connection failed: ' . $e->getMessage());

        throw new RuntimeException(
            'Unable to connect to the database. Please try again later.'
        );
    }
}
