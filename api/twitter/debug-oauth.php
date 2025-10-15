<?php
// Twitter OAuth Debug Tool
header('Content-Type: text/html; charset=utf-8');

// Start session to check current state
session_start();

echo "<h2>🐦 Twitter OAuth Debug Tool</h2>";

// Show current session state
echo "<h3>Current Session State:</h3>";
echo "<ul>";
echo "<li><strong>Session ID:</strong> " . session_id() . "</li>";
echo "<li><strong>OAuth Token:</strong> " . ($_SESSION['oauth_token'] ?? 'Not set') . "</li>";
echo "<li><strong>OAuth Token Secret:</strong> " . (isset($_SESSION['oauth_token_secret']) ? 'Set (hidden)' : 'Not set') . "</li>";
echo "<li><strong>Token Time:</strong> " . (isset($_SESSION['oauth_token_time']) ? date('Y-m-d H:i:s', $_SESSION['oauth_token_time']) : 'Not set') . "</li>";
echo "<li><strong>Token Age:</strong> " . (isset($_SESSION['oauth_token_time']) ? (time() - $_SESSION['oauth_token_time']) . ' seconds' : 'N/A') . "</li>";
echo "<li><strong>Access Token:</strong> " . (isset($_SESSION['twitter_access_token']) ? 'Set' : 'Not set') . "</li>";
echo "</ul>";

// Show current cookies
echo "<h3>Current Cookies:</h3>";
echo "<ul>";
echo "<li><strong>tw_oauth_token:</strong> " . ($_COOKIE['tw_oauth_token'] ?? 'Not set') . "</li>";
echo "<li><strong>tw_oauth_token_secret:</strong> " . (isset($_COOKIE['tw_oauth_token_secret']) ? 'Set (hidden)' : 'Not set') . "</li>";
echo "</ul>";

// Show GET parameters
echo "<h3>Current GET Parameters:</h3>";
echo "<ul>";
echo "<li><strong>oauth_token:</strong> " . ($_GET['oauth_token'] ?? 'Not set') . "</li>";
echo "<li><strong>oauth_verifier:</strong> " . ($_GET['oauth_verifier'] ?? 'Not set') . "</li>";
echo "<li><strong>denied:</strong> " . ($_GET['denied'] ?? 'Not set') . "</li>";
echo "</ul>";

// Check token matching
if (isset($_GET['oauth_token'])) {
    $callbackToken = $_GET['oauth_token'];
    $sessionToken = $_SESSION['oauth_token'] ?? null;
    $cookieToken = $_COOKIE['tw_oauth_token'] ?? null;
    
    echo "<h3>Token Validation:</h3>";
    echo "<ul>";
    echo "<li><strong>Callback Token:</strong> " . $callbackToken . "</li>";
    echo "<li><strong>Session Token:</strong> " . ($sessionToken ?? 'Not set') . "</li>";
    echo "<li><strong>Cookie Token:</strong> " . ($cookieToken ?? 'Not set') . "</li>";
    echo "<li><strong>Session Match:</strong> " . ($callbackToken === $sessionToken ? '✅ Match' : '❌ No Match') . "</li>";
    echo "<li><strong>Cookie Match:</strong> " . ($callbackToken === $cookieToken ? '✅ Match' : '❌ No Match') . "</li>";
    echo "</ul>";
}

// Show server info
echo "<h3>Server Information:</h3>";
echo "<ul>";
echo "<li><strong>HTTP_HOST:</strong> " . ($_SERVER['HTTP_HOST'] ?? 'Not set') . "</li>";
echo "<li><strong>HTTPS:</strong> " . ($_SERVER['HTTPS'] ?? 'Not set') . "</li>";
echo "<li><strong>REQUEST_URI:</strong> " . ($_SERVER['REQUEST_URI'] ?? 'Not set') . "</li>";
echo "<li><strong>HTTP_REFERER:</strong> " . ($_SERVER['HTTP_REFERER'] ?? 'Not set') . "</li>";
echo "</ul>";

// Actions
echo "<h3>Actions:</h3>";
echo "<ul>";
echo "<li><a href='/api/twitter/twitter-auth.php'>Start Fresh Twitter Login</a></li>";
echo "<li><a href='/api/twitter/debug-oauth.php?clear=1'>Clear Session & Cookies</a></li>";
echo "<li><a href='/api/twitter/test-connection.php'>Test Twitter API Connection</a></li>";
echo "</ul>";

// Handle clear action
if (isset($_GET['clear'])) {
    session_destroy();
    setcookie('tw_oauth_token', '', time() - 3600, '/');
    setcookie('tw_oauth_token_secret', '', time() - 3600, '/');
    echo "<div style='color:green;font-weight:bold;'>✅ Session and cookies cleared!</div>";
    echo "<script>setTimeout(() => window.location.href='/api/twitter/debug-oauth.php', 2000);</script>";
}

// Show recent error logs if available
if (file_exists(__DIR__ . '/../../api/error.log')) {
    echo "<h3>Recent Error Logs:</h3>";
    $logs = file_get_contents(__DIR__ . '/../../api/error.log');
    $lines = explode("\n", $logs);
    $recentLines = array_slice($lines, -10);
    echo "<pre style='background:#f5f5f5;padding:10px;border-radius:5px;font-size:12px;'>";
    echo htmlspecialchars(implode("\n", $recentLines));
    echo "</pre>";
}
?>
