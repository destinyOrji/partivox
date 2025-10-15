<?php
// Twitter API Connection Test
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Load configuration
    require_once __DIR__ . '/../config/twitter.php';
    require_once __DIR__ . '/../../vendor/autoload.php';
    
    $response = [
        'status' => 'testing',
        'timestamp' => date('Y-m-d H:i:s'),
        'environment' => [
            'consumer_key_set' => defined('CONSUMER_KEY') && !empty(CONSUMER_KEY),
            'consumer_secret_set' => defined('CONSUMER_SECRET') && !empty(CONSUMER_SECRET),
            'callback_set' => defined('OAUTH_CALLBACK') && !empty(OAUTH_CALLBACK),
            'server_host' => $_SERVER['HTTP_HOST'] ?? 'unknown',
            'is_https' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        ]
    ];
    
    // Test basic API connection
    if (defined('CONSUMER_KEY') && defined('CONSUMER_SECRET')) {
        try {
            $connection = new Abraham\TwitterOAuth\TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET);
            $connection->setTimeouts(10, 30);
            
            // Test API endpoint
            $apiResponse = $connection->get('help/configuration');
            
            if (isset($apiResponse->errors)) {
                $response['api_test'] = [
                    'status' => 'error',
                    'message' => $apiResponse->errors[0]->message ?? 'Unknown API error',
                    'code' => $apiResponse->errors[0]->code ?? 'unknown'
                ];
            } else {
                $response['api_test'] = [
                    'status' => 'success',
                    'message' => 'Twitter API connection successful'
                ];
            }
            
        } catch (Exception $e) {
            $response['api_test'] = [
                'status' => 'error',
                'message' => $e->getMessage(),
                'type' => get_class($e)
            ];
        }
    } else {
        $response['api_test'] = [
            'status' => 'error',
            'message' => 'Missing Twitter API credentials'
        ];
    }
    
    // Test network connectivity
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'method' => 'GET'
        ]
    ]);
    
    $networkTest = @file_get_contents('https://api.twitter.com/1.1/help/configuration.json', false, $context);
    $response['network_test'] = [
        'status' => $networkTest !== false ? 'success' : 'error',
        'message' => $networkTest !== false ? 'Can reach Twitter API' : 'Cannot reach Twitter API'
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Test failed: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>
