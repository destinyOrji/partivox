<?php
session_start();

// Include required files
require_once __DIR__ . '/TwitterDbService.php';

// Clear Twitter-related session data
if (isset($_SESSION['twitter_user_id'])) {
    try {
        $twitterDb = new TwitterDbService();
        
        // Find user and revoke tokens
        $user = $twitterDb->findUserByTwitterId($_SESSION['twitter_user_id']);
        if ($user) {
            $twitterDb->revokeTokens($user->_id);
            error_log('[TWITTER_LOGOUT] Revoked tokens for user: ' . $_SESSION['twitter_user_id']);
        }
    } catch (Exception $e) {
        error_log('[TWITTER_LOGOUT] Error revoking tokens: ' . $e->getMessage());
    }
}

// Clear all Twitter-related session variables
unset($_SESSION['twitter_access_token']);
unset($_SESSION['twitter_user_id']);
unset($_SESSION['twitter_screen_name']);
unset($_SESSION['twitter_profile_image_url']);
unset($_SESSION['oauth_token']);
unset($_SESSION['oauth_token_secret']);

// If Twitter was the only auth provider, clear general auth flags
if (isset($_SESSION['auth_provider']) && $_SESSION['auth_provider'] === 'twitter') {
    unset($_SESSION['is_authenticated']);
    unset($_SESSION['auth_provider']);
}

// Clear Twitter cookies
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
           (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

setcookie('tw_oauth_token', '', time() - 3600, '/', '', $isHttps, true);
setcookie('tw_oauth_token_secret', '', time() - 3600, '/', '', $isHttps, true);

// Redirect to user dashboard
$scheme = $isHttps ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
$redirectUrl = $scheme . '://' . $host . '/pages/userDashboard.html';

header('Location: ' . $redirectUrl);
exit;
?>
