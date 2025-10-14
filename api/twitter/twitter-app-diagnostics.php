<?php
require_once __DIR__ . '/../config/twitter.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Abraham\TwitterOAuth\TwitterOAuth;

echo "<h2>Twitter App Diagnostics</h2>";
echo "<style>
.success { color: green; font-weight: bold; }
.error { color: red; font-weight: bold; }
.warning { color: orange; font-weight: bold; }
.info { color: blue; }
pre { background: #f5f5f5; padding: 10px; border-radius: 5px; }
</style>";

// Check environment variables
echo "<h3>1. Environment Variables Check</h3>";
$consumerKey = getenv('TWITTER_CONSUMER_KEY');
$consumerSecret = getenv('TWITTER_CONSUMER_SECRET');
$oauthCallback = getenv('TWITTER_OAUTH_CALLBACK');

echo "<pre>";
echo "TWITTER_CONSUMER_KEY: " . ($consumerKey ? substr($consumerKey, 0, 8) . "..." : "<span class='error'>NOT SET</span>") . "\n";
echo "TWITTER_CONSUMER_SECRET: " . ($consumerSecret ? substr($consumerSecret, 0, 8) . "..." : "<span class='error'>NOT SET</span>") . "\n";
echo "TWITTER_OAUTH_CALLBACK: " . ($oauthCallback ?: "<span class='error'>NOT SET</span>") . "\n";
echo "</pre>";

// Check constants
echo "<h3>2. Configuration Constants</h3>";
echo "<pre>";
echo "CONSUMER_KEY: " . (defined('CONSUMER_KEY') ? substr(CONSUMER_KEY, 0, 8) . "..." : "<span class='error'>NOT DEFINED</span>") . "\n";
echo "CONSUMER_SECRET: " . (defined('CONSUMER_SECRET') ? substr(CONSUMER_SECRET, 0, 8) . "..." : "<span class='error'>NOT DEFINED</span>") . "\n";
echo "OAUTH_CALLBACK: " . (defined('OAUTH_CALLBACK') ? OAUTH_CALLBACK : "<span class='error'>NOT DEFINED</span>") . "\n";
echo "</pre>";

// Test basic TwitterOAuth connection
echo "<h3>3. TwitterOAuth Library Test</h3>";
try {
    if (!defined('CONSUMER_KEY') || !defined('CONSUMER_SECRET')) {
        throw new Exception("Missing consumer credentials");
    }
    
    $connection = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET);
    echo "<span class='success'>✓ TwitterOAuth object created successfully</span><br>";
    
    // Test request token generation
    echo "<h4>Request Token Test:</h4>";
    try {
        $connection->setTimeouts(10, 30);
        $request_token = $connection->oauth('oauth/request_token', array('oauth_callback' => OAUTH_CALLBACK));
        
        if (isset($request_token['oauth_token'])) {
            echo "<span class='success'>✓ Request token generated successfully</span><br>";
            echo "OAuth Token: " . substr($request_token['oauth_token'], 0, 10) . "...<br>";
            
            // Test the authorize URL
            $authUrl = 'https://api.twitter.com/oauth/authenticate?oauth_token=' . $request_token['oauth_token'];
            echo "Authorize URL: <a href='$authUrl' target='_blank'>$authUrl</a><br>";
            
        } else {
            echo "<span class='error'>✗ Failed to generate request token</span><br>";
            echo "Response: <pre>" . print_r($request_token, true) . "</pre>";
        }
        
    } catch (Exception $e) {
        echo "<span class='error'>✗ Request token error: " . $e->getMessage() . "</span><br>";
        
        // Check HTTP status
        $httpCode = $connection->getLastHttpCode();
        echo "HTTP Status Code: $httpCode<br>";
        
        switch ($httpCode) {
            case 401:
                echo "<span class='error'>401 Unauthorized - Check your Consumer Key and Secret</span><br>";
                break;
            case 403:
                echo "<span class='error'>403 Forbidden - Your app may be suspended or restricted</span><br>";
                break;
            case 429:
                echo "<span class='warning'>429 Rate Limited - Too many requests</span><br>";
                break;
            default:
                echo "Unexpected HTTP status: $httpCode<br>";
        }
    }
    
} catch (Exception $e) {
    echo "<span class='error'>✗ TwitterOAuth setup error: " . $e->getMessage() . "</span><br>";
}

// Check callback URL accessibility
echo "<h3>4. Callback URL Check</h3>";
$callbackUrl = OAUTH_CALLBACK ?? 'Not set';
echo "Callback URL: $callbackUrl<br>";

if ($callbackUrl !== 'Not set') {
    // Parse the URL
    $parsedUrl = parse_url($callbackUrl);
    echo "<pre>";
    echo "Scheme: " . ($parsedUrl['scheme'] ?? 'missing') . "\n";
    echo "Host: " . ($parsedUrl['host'] ?? 'missing') . "\n";
    echo "Port: " . ($parsedUrl['port'] ?? 'default') . "\n";
    echo "Path: " . ($parsedUrl['path'] ?? '/') . "\n";
    echo "</pre>";
    
    // Check if it's localhost
    if (isset($parsedUrl['host']) && in_array($parsedUrl['host'], ['localhost', '127.0.0.1'])) {
        echo "<span class='warning'>⚠ Using localhost - Twitter may have issues with local development URLs</span><br>";
        echo "<span class='info'>Consider using ngrok or a public URL for testing</span><br>";
    }
}

// Troubleshooting suggestions
echo "<h3>5. Troubleshooting Suggestions</h3>";
echo "<div style='background: #f0f8ff; padding: 15px; border-radius: 5px;'>";
echo "<h4>Common 403 Forbidden Causes:</h4>";
echo "<ul>";
echo "<li><strong>Invalid Credentials:</strong> Double-check your Consumer Key and Secret in Twitter Developer Portal</li>";
echo "<li><strong>App Suspended:</strong> Check if your Twitter app is active in the Developer Portal</li>";
echo "<li><strong>Callback URL Mismatch:</strong> Ensure the callback URL in your app settings matches your .env file</li>";
echo "<li><strong>App Permissions:</strong> Make sure your app has 'Read' permissions at minimum</li>";
echo "<li><strong>Localhost Issues:</strong> Twitter may block localhost URLs - try using ngrok</li>";
echo "</ul>";

echo "<h4>Next Steps:</h4>";
echo "<ol>";
echo "<li>Visit <a href='https://developer.twitter.com/en/portal/dashboard' target='_blank'>Twitter Developer Portal</a></li>";
echo "<li>Check your app status and settings</li>";
echo "<li>Verify callback URLs match exactly</li>";
echo "<li>Regenerate keys if necessary</li>";
echo "<li>Consider using ngrok for local development: <code>ngrok http 8000</code></li>";
echo "</ol>";
echo "</div>";

echo "<br><a href='/api/twitter/twitter-auth.php'>← Back to Twitter Auth</a>";
?>
