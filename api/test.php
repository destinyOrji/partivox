<?php
// Simple test to check if PHP is outputting any HTML errors
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');

try {
    // Test database connection
    require_once __DIR__ . '/config/db.php';
    $db = Database::getDB();
    
    // Test basic functionality
    $response = [
        'status' => 'success',
        'message' => 'API test successful',
        'data' => [
            'timestamp' => date('Y-m-d H:i:s'),
            'php_version' => PHP_VERSION,
            'database_connected' => !empty($db)
        ]
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Test failed: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
