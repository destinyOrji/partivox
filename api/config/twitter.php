<?php
// Load environment variables from .env
if (file_exists(__DIR__ . '/../../.env')) {
    $lines = file(__DIR__ . '/../../.env');
    foreach ($lines as $line) {
        if (preg_match('/^([A-Z0-9_]+)=(.*)$/', trim($line), $matches)) {
            $name = $matches[1];
            $value = trim($matches[2], "'\"");
            putenv("$name=$value");
        }
    }
}

// Allow dynamic callback fallback if env not set
$envCallback = getenv('TWITTER_OAUTH_CALLBACK');
if (!$envCallback) {
    // Best-effort build absolute callback (assumes script runs under /api/twitter/)
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Handle ngrok URLs (they use HTTPS by default)
    if (strpos($host, 'ngrok') !== false || strpos($host, 'ngrok.io') !== false) {
        $scheme = 'https';
    }
    
    $envCallback = $scheme . '://' . $host . '/api/twitter/twitter-auth.php';
    
    // Log the callback URL for debugging
    error_log('[TWITTER_CONFIG] Using dynamic callback: ' . $envCallback);
}

// OAuth 1.0a credentials (for existing TwitterOAuth library)
define('CONSUMER_KEY', getenv('TWITTER_CONSUMER_KEY'));
define('CONSUMER_SECRET', getenv('TWITTER_CONSUMER_SECRET'));
define('OAUTH_CALLBACK', $envCallback);

// OAuth 2.0 credentials (for future migration)
define('TWITTER_CLIENT_ID', getenv('TWITTER_CLIENT_ID'));
define('TWITTER_CLIENT_SECRET', getenv('TWITTER_CLIENT_SECRET'));

// API version flag
define('TWITTER_API_VERSION', '2.0'); // Can be '1.1' or '2.0'
