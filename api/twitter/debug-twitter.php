<?php
// Twitter API Debug Tool
header('Content-Type: text/html; charset=utf-8');

echo "<h2>🐦 Twitter API Debug Tool</h2>";

// Check if we're on Render
$isRender = getenv('RENDER') === 'true' || getenv('RENDER_EXTERNAL_HOSTNAME');
echo "<p><strong>Environment:</strong> " . ($isRender ? 'Render' : 'Local') . "</p>";

// Check environment variables
echo "<h3>Environment Variables:</h3>";
echo "<ul>";
echo "<li><strong>TWITTER_CONSUMER_KEY:</strong> " . (getenv('TWITTER_CONSUMER_KEY') ? '✅ Set' : '❌ Missing') . "</li>";
echo "<li><strong>TWITTER_CONSUMER_SECRET:</strong> " . (getenv('TWITTER_CONSUMER_SECRET') ? '✅ Set' : '❌ Missing') . "</li>";
echo "<li><strong>TWITTER_OAUTH_CALLBACK:</strong> " . (getenv('TWITTER_OAUTH_CALLBACK') ? '✅ Set' : '❌ Missing') . "</li>";
echo "</ul>";

// Check Twitter config
require_once __DIR__ . '/../config/twitter.php';

echo "<h3>Twitter Configuration:</h3>";
echo "<ul>";
echo "<li><strong>CONSUMER_KEY:</strong> " . (defined('CONSUMER_KEY') && CONSUMER_KEY ? '✅ Set' : '❌ Missing') . "</li>";
echo "<li><strong>CONSUMER_SECRET:</strong> " . (defined('CONSUMER_SECRET') && CONSUMER_SECRET ? '✅ Set' : '❌ Missing') . "</li>";
echo "<li><strong>OAUTH_CALLBACK:</strong> " . (defined('OAUTH_CALLBACK') ? OAUTH_CALLBACK : '❌ Missing') . "</li>";
echo "</ul>";

// Test Twitter API connection
if (defined('CONSUMER_KEY') && defined('CONSUMER_SECRET')) {
    echo "<h3>API Connection Test:</h3>";
    
    try {
        require_once __DIR__ . '/../../vendor/autoload.php';
        $connection = new Abraham\TwitterOAuth\TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET);
        $connection->setTimeouts(10, 30); // Shorter timeouts for testing
        
        // Test basic API access
        $response = $connection->get('account/verify_credentials');
        
        if (isset($response->errors)) {
            echo "<p style='color: red;'>❌ API Error: " . $response->errors[0]->message . "</p>";
        } else {
            echo "<p style='color: green;'>✅ API Connection Successful!</p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Connection Error: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color: red;'>❌ Cannot test API - missing credentials</p>";
}

// Show current server info
echo "<h3>Server Information:</h3>";
echo "<ul>";
echo "<li><strong>HTTP_HOST:</strong> " . ($_SERVER['HTTP_HOST'] ?? 'Not set') . "</li>";
echo "<li><strong>HTTPS:</strong> " . ($_SERVER['HTTPS'] ?? 'Not set') . "</li>";
echo "<li><strong>REQUEST_URI:</strong> " . ($_SERVER['REQUEST_URI'] ?? 'Not set') . "</li>";
echo "<li><strong>SERVER_NAME:</strong> " . ($_SERVER['SERVER_NAME'] ?? 'Not set') . "</li>";
echo "</ul>";

// Show network test
echo "<h3>Network Test:</h3>";
$testUrl = 'https://api.twitter.com/1.1/help/configuration.json';
$context = stream_context_create([
    'http' => [
        'timeout' => 10,
        'method' => 'GET'
    ]
]);

$result = @file_get_contents($testUrl, false, $context);
if ($result !== false) {
    echo "<p style='color: green;'>✅ Can reach Twitter API</p>";
} else {
    echo "<p style='color: red;'>❌ Cannot reach Twitter API</p>";
}

echo "<hr>";
echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li>If credentials are missing, add them to Render environment variables</li>";
echo "<li>If API connection fails, check your Twitter app settings</li>";
echo "<li>If network test fails, there might be a firewall issue</li>";
echo "</ol>";

echo "<p><a href='/api/twitter/twitter-auth.php'>Try Twitter Login Again</a></p>";
?>
