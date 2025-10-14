<?php
// Alternative Twitter auth with different callback handling
session_start();

require_once __DIR__ . '/../config/twitter.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Abraham\TwitterOAuth\TwitterOAuth;

// Force specific callback URL that might work better
$altCallback = 'http://127.0.0.1:8000/api/twitter/twitter-auth.php';

echo "<h2>Alternative Twitter Authentication</h2>";
echo "<p>Using callback: <code>$altCallback</code></p>";

try {
    // Clear any existing tokens
    unset($_SESSION['oauth_token']);
    unset($_SESSION['oauth_token_secret']);
    
    $connection = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET);
    $connection->setTimeouts(10, 30);
    
    // Try with the alternative callback
    $request_token = $connection->oauth('oauth/request_token', array('oauth_callback' => $altCallback));
    
    if (isset($request_token['oauth_token'])) {
        $_SESSION['oauth_token'] = $request_token['oauth_token'];
        $_SESSION['oauth_token_secret'] = $request_token['oauth_token_secret'];
        
        echo "<p style='color:green;'>✓ Request token generated successfully!</p>";
        echo "<p>Token: " . substr($request_token['oauth_token'], 0, 15) . "...</p>";
        
        // Build auth URL
        $authUrl = 'https://api.twitter.com/oauth/authenticate?oauth_token=' . $request_token['oauth_token'];
        
        echo "<p><a href='$authUrl' style='display:inline-block;padding:10px 20px;background:#1da1f2;color:white;text-decoration:none;border-radius:5px;'>Authorize with Twitter</a></p>";
        
        echo "<p><small>If this works, your main issue was the callback URL configuration.</small></p>";
        
    } else {
        echo "<p style='color:red;'>✗ Failed to get request token</p>";
        echo "<pre>" . print_r($request_token, true) . "</pre>";
        
        $httpCode = $connection->getLastHttpCode();
        echo "<p>HTTP Status: $httpCode</p>";
        
        if ($httpCode == 403) {
            echo "<div style='background:#ffe6e6;padding:15px;border-radius:5px;'>";
            echo "<h3>403 Forbidden - Your Twitter App Needs Attention</h3>";
            echo "<p><strong>Most likely causes:</strong></p>";
            echo "<ul>";
            echo "<li>Your app is suspended or inactive</li>";
            echo "<li>Callback URL mismatch in Twitter Developer Portal</li>";
            echo "<li>Invalid Consumer Key/Secret</li>";
            echo "</ul>";
            echo "<p><strong>Action needed:</strong> Check your Twitter Developer Portal settings</p>";
            echo "</div>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<br><a href='twitter-quick-fix.php'>← Back to Quick Fix</a>";
echo " | <a href='/api/twitter/twitter-auth.php'>Main Twitter Auth</a>";
?>
