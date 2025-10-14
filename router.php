<?php
// Router for PHP built-in server to handle API routes
$uri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

// Handle API routes
if (preg_match('/^\/api\/(.+)$/', $uri, $matches)) {
    // Set the REQUEST_URI to what the API expects
    $_SERVER['REQUEST_URI'] = '/api/' . $matches[1];
    require_once 'api/index.php';
    return true;
}

// For all other requests, serve the file if it exists
$path = parse_url($uri, PHP_URL_PATH);
$file = __DIR__ . $path;

if (is_file($file)) {
    return false; // Serve the file normally
}

// If file doesn't exist, serve index.html
if (file_exists(__DIR__ . '/index.html')) {
    require_once __DIR__ . '/index.html';
    return true;
}

// 404
http_response_code(404);
echo "404 Not Found";
return true;
?>
