<?php
// Health check endpoint for Render
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Basic health check
    $status = [
        'status' => 'healthy',
        'timestamp' => date('Y-m-d H:i:s'),
        'version' => '1.0.0',
        'environment' => 'production'
    ];
    
    // Try to check database connection
    if (file_exists(__DIR__ . '/config/db.php')) {
        require_once __DIR__ . '/config/db.php';
        try {
            Database::getDB();
            $status['database'] = 'connected';
        } catch (Exception $e) {
            $status['database'] = 'disconnected';
            $status['database_error'] = $e->getMessage();
        }
    }
    
    // Check Twitter API configuration
    if (file_exists(__DIR__ . '/config/twitter.php')) {
        require_once __DIR__ . '/config/twitter.php';
        $status['twitter'] = [
            'consumer_key_set' => defined('CONSUMER_KEY') && !empty(CONSUMER_KEY),
            'consumer_secret_set' => defined('CONSUMER_SECRET') && !empty(CONSUMER_SECRET),
            'callback_set' => defined('OAUTH_CALLBACK') && !empty(OAUTH_CALLBACK)
        ];
    }
    
    echo json_encode($status, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Health check failed: ' . $e->getMessage()
    ]);
}
?>
