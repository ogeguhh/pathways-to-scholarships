
<?php
// Connect to the MySQL database using PDO

function getDBConnection() {
    // Load local database settings
    $config = require __DIR__ . '/config.php';

    $dsn = "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4";

    try {
        $pdo = new PDO(
            $dsn,
            $config['username'],
            $config['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );

        return $pdo;

    } catch (PDOException $e) {
        // Do not display database credentials or error details
        error_log($e->getMessage());
        throw new RuntimeException("Database connection failed.");
    }
}
