<?php
// Load environment variables first
require_once __DIR__ . '/vendor/autoload.php';

if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

// Router for PHP built-in server to handle all requests
$uri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

// Remove query string for path matching
$path = parse_url($uri, PHP_URL_PATH);

// Handle health check endpoint
if ($path === '/api/health' || $path === '/api/health.php') {
    require_once __DIR__ . '/api/health.php';
    return true;
}

// Handle API routes
if (strpos($path, '/api/') === 0) {
    // Extract the API path after /api/
    $apiPath = substr($path, 4); // Remove '/api' prefix
    
    // Set the REQUEST_URI to what the API expects
    $_SERVER['REQUEST_URI'] = '/api/' . $apiPath;
    
    // Include the API index
    require_once 'api/index.php';
    return true;
}

// Handle static files
$file = __DIR__ . $path;
if (is_file($file) && $path !== '/') {
    // Serve static files directly
    $mimeType = mime_content_type($file);
    if ($mimeType) {
        header('Content-Type: ' . $mimeType);
    }
    readfile($file);
    return true;
}

// Handle directory requests
if (is_dir($file)) {
    $indexFile = $file . '/index.html';
    if (is_file($indexFile)) {
        readfile($indexFile);
        return true;
    }
}

// Default: serve index.html for all other requests
if (file_exists(__DIR__ . '/index.html')) {
    readfile(__DIR__ . '/index.html');
    return true;
}

// 404 Not Found
http_response_code(404);
echo "404 Not Found - " . $path;
return true;
?>
