<?php
// Error reporting for development (disable in production)
// Disable error display to prevent HTML in JSON responses
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output
ini_set('log_errors', 1);     // Log errors to file
ini_set('error_log', __DIR__ . '/error.log');

// Ensure clean output for JSON responses
ob_start();

// Set proper JSON content type and CORS headers
header('Content-Type: application/json; charset=utf-8');

// Handle CORS properly for credentials
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = [
    'http://localhost:8000', 
    'http://127.0.0.1:8000',
    'http://localhost:3000',
    'http://127.0.0.1:3000'
];

if (in_array($origin, $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    header('Access-Control-Allow-Origin: *');
}

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$method = $_SERVER["REQUEST_METHOD"];
$queryString = $_SERVER["QUERY_STRING"] ?? '';

$uri = $_SERVER["REQUEST_URI"];

try {
    // Clean any previous output
    if (ob_get_level()) {
        ob_clean();
    }
    
    $response = handleRequest($uri, $method);
    
    // Ensure we have a valid response array
    if (!is_array($response)) {
        $response = ["status" => "error", "message" => "Invalid response format"];
    }
    
    // Clean output and send JSON
    ob_clean();
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Throwable $e) {
    // Clean any output and send error response
    if (ob_get_level()) {
        ob_clean();
    }
    
    error_log("[API] Fatal error: " . $e->getMessage());
    error_log("[API] Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Internal server error"
    ], JSON_UNESCAPED_UNICODE);
}

function handleRequest($url, $method) {
    try {
        // Remove query string from URL before parsing
        $url = parse_url($url, PHP_URL_PATH);
        $url = trim($url, '/');
        $parts = explode('/', $url);
        
        // Log the request for debugging
        error_log("[API] Request: $method $url");
        error_log("[API] Parts: " . json_encode($parts));
        
        // Expecting /api/{route}/{action} or /api/{route}
        if (count($parts) >= 2 && $parts[0] === 'api') {
            $routeFile = $parts[1]; // e.g. 'auth'
            // For nested routes like /api/admin/users/grant-admin, combine all parts after route
            $action = count($parts) > 2 ? implode('/', array_slice($parts, 2)) : '';
            $fullpath = __DIR__ . "/routes/{$routeFile}.route.php";
            
            if (is_file($fullpath)) {
                require_once $fullpath;
                $function = $routeFile . "Routes";
                
                if (function_exists($function)) {
                    // Handle different types of POST data
                    if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
                        // Check if this is a file upload (multipart/form-data)
                        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
                        if (strpos($contentType, 'multipart/form-data') !== false) {
                            // For file uploads, use $_POST and $_FILES
                            $data = $_POST;
                            error_log("[API] File upload detected - POST data: " . json_encode($data));
                            error_log("[API] Files: " . json_encode(array_keys($_FILES)));
                        } else {
                            // For JSON data
                            $raw = file_get_contents('php://input');
                            $parsed = json_decode($raw, true);
                            $data = is_array($parsed) ? $parsed : [];
                            error_log("[API] JSON POST data: " . json_encode($data));
                        }
                    } else {
                        $data = $_GET;
                    }
                    
                    $response = $function($method, $action, $data);
                    
                    // Ensure response is properly formatted
                    if (!is_array($response)) {
                        $response = ["status" => "error", "message" => "Invalid response format"];
                    }
                    
                } else {
                    $response = ["status" => "error", "message" => "Function {$function} not found"];
                }
            } else {
                $response = ["status" => "error", "message" => "Route file not found: {$routeFile}.route.php"];
            }
        } else {
            $response = ["status" => "error", "message" => "Invalid API path structure"];
        }
        
    } catch (Exception $e) {
        error_log("[API] Exception: " . $e->getMessage());
        $response = ["status" => "error", "message" => "Server error: " . $e->getMessage()];
    }
    
    return $response;
}
