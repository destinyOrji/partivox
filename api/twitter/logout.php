<?php
// Destroy Twitter auth session and redirect to home
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL & ~E_DEPRECATED);

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Unset all session variables related to auth
$_SESSION = [];

// Remove the session cookie
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

// Also clear any Twitter request token cookies if present
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
setcookie('tw_oauth_token', '', time() - 3600, '/', '', $isHttps, true);
setcookie('tw_oauth_token_secret', '', time() - 3600, '/', '', $isHttps, true);

// Finally destroy the session
session_destroy();

// Safe redirect helper
function safe_redirect($url) {
    if (!headers_sent()) {
        header('Location: ' . $url);
    } else {
        echo "<script>window.location.href='" . htmlspecialchars($url, ENT_QUOTES) . "';</script>";
        echo "<noscript><a href='" . htmlspecialchars($url, ENT_QUOTES) . "'>Continue</a></noscript>";
    }
    exit;
}

safe_redirect('/index.html');
