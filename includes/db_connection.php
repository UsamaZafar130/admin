<?php
// Always include config.php for DB constants and other global settings
require_once __DIR__ . '/../settings/config.php';

// Build DSN (Data Source Name)
$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on errors
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Fetch associative arrays by default
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Use real prepared statements
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // Log the error
    error_log('Database connection failed: ' . $e->getMessage());
    
    // Check if this is an API request or certain specific requests
    $isApiRequest = (
        (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
        (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) ||
        strpos($_SERVER['PHP_SELF'], '/api/') !== false ||
        strpos($_SERVER['PHP_SELF'], 'index.php') !== false // Allow dashboard to load 
    );
    
    if ($isApiRequest) {
        // For API requests and dashboard, return null to let them handle the error gracefully
        $pdo = null;
    } else {
        // For regular page requests, show the error page
        die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
    }
}

return $pdo;