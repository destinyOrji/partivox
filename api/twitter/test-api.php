<?php
// Simple Twitter API Test
header('Content-Type: application/json');

try {
    // Load Twitter config
    require_once __DIR__ . '/../config/twitter.php';
    require_once __DIR__ . '/../../vendor/autoload.php';
    
    // Check if credentials are set
    if (!defined('CONSUMER_KEY') || !defined('CONSUMER_SECRET') || !CONSUMER_KEY || !CONSUMER_SECRET) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Twitter API credentials not configured',
            'details' => [
                'consumer_key_set' => defined('CONSUMER_KEY') && CONSUMER_KEY,
                'consumer_secret_set' => defined('CONSUMER_SECRET') && CONSUMER_SECRET,
                'callback_set' => defined('OAUTH_CALLBACK') && OAUTH_CALLBACK
            ]
        ]);
        exit;
    }
    
    // Test API connection
    $connection = new Abraham\TwitterOAuth\TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET);
    $connection->setTimeouts(10, 30);
    
    // Test with a simple API call
    $response = $connection->get('help/configuration');
    
    if (isset($response->errors)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Twitter API error: ' . $response->errors[0]->message,
            'error_code' => $response->errors[0]->code ?? 'unknown'
        ]);
    } else {
        echo json_encode([
            'status' => 'success',
            'message' => 'Twitter API connection successful',
            'callback_url' => OAUTH_CALLBACK,
            'api_response' => $response
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Connection failed: ' . $e->getMessage()
    ]);
}
?>
