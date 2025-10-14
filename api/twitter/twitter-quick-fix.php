<?php
// Quick fix for Twitter 403 errors
// This bypasses some common issues temporarily

session_start();

// Clear any existing Twitter session data
unset($_SESSION['twitter_access_token']);
unset($_SESSION['oauth_token']);
unset($_SESSION['oauth_token_secret']);

// Clear Twitter cookies
$isHttps = false; // Force HTTP for localhost
setcookie('tw_oauth_token', '', time() - 3600, '/', '', $isHttps, true);
setcookie('tw_oauth_token_secret', '', time() - 3600, '/', '', $isHttps, true);

echo "<h2>Twitter Auth Quick Fix</h2>";
echo "<p>Session and cookies cleared. Try these options:</p>";

echo "<h3>Option 1: Use Alternative Callback</h3>";
echo "<p>Try with a different callback URL format:</p>";
echo "<a href='twitter-auth-alt.php' style='display:inline-block;padding:10px 20px;background:#1da1f2;color:white;text-decoration:none;border-radius:5px;'>Try Alternative Auth</a>";

echo "<h3>Option 2: Manual OAuth Flow</h3>";
echo "<p>If the above doesn't work, we'll need to check your Twitter app settings:</p>";
echo "<ol>";
echo "<li>Go to <a href='https://developer.twitter.com/en/portal/dashboard' target='_blank'>Twitter Developer Portal</a></li>";
echo "<li>Find your app with Consumer Key: qi4jxjcbaRs2vw5QavLrqtuo7</li>";
echo "<li>Check if it's Active (not suspended)</li>";
echo "<li>Update callback URLs to include both:</li>";
echo "<ul>";
echo "<li><code>http://127.0.0.1:8000/api/twitter/twitter-auth.php</code></li>";
echo "<li><code>http://localhost:8000/api/twitter/twitter-auth.php</code></li>";
echo "</ul>";
echo "</ol>";

echo "<h3>Option 3: Create New Twitter App</h3>";
echo "<p>If your current app is suspended or has issues:</p>";
echo "<ol>";
echo "<li>Create a new app at <a href='https://developer.twitter.com/en/portal/dashboard' target='_blank'>Twitter Developer Portal</a></li>";
echo "<li>Get new Consumer Key and Secret</li>";
echo "<li>Update your .env file with new credentials</li>";
echo "</ol>";

echo "<br><a href='/api/twitter/twitter-auth.php'>← Back to Twitter Auth</a>";
?>
