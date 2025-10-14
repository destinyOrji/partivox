<?php
// Returns current authenticated Twitter user info from session
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Dev-friendly CORS: allow credentialed requests from the requesting origin only
if (!headers_sent() && isset($_SERVER['HTTP_ORIGIN'])) {
    // Echo back the requesting origin. In dev, this will typically be http://localhost:8000
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
    header('Vary: Origin');
}

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

require_once __DIR__ . '/../models/User.php';

// Optional debug: return raw session and headers if explicitly requested
if (isset($_GET['debug']) && $_GET['debug'] === '1') {
    http_response_code(200);
    echo json_encode([
        'session_id' => session_id(),
        'session' => $_SESSION,
        'cookies' => $_COOKIE,
        'headers' => [
            'origin' => $_SERVER['HTTP_ORIGIN'] ?? null,
            'host' => $_SERVER['HTTP_HOST'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ],
    ], JSON_PRETTY_PRINT);
    exit;
}

// Auto-heal: if OAuth access token exists but auth flags are missing, infer them
if (!isset($_SESSION['is_authenticated']) && isset($_SESSION['twitter_access_token']) && is_array($_SESSION['twitter_access_token'])) {
    $_SESSION['is_authenticated'] = true;
    $_SESSION['auth_provider'] = 'twitter';
    if (!isset($_SESSION['twitter_user_id']) && isset($_SESSION['twitter_access_token']['user_id'])) {
        $_SESSION['twitter_user_id'] = (string)$_SESSION['twitter_access_token']['user_id'];
    }
    if (!isset($_SESSION['twitter_screen_name']) && isset($_SESSION['twitter_access_token']['screen_name'])) {
        $_SESSION['twitter_screen_name'] = (string)$_SESSION['twitter_access_token']['screen_name'];
    }
}

$isAuthed = isset($_SESSION['is_authenticated']) && $_SESSION['is_authenticated'] === true;
$provider = $_SESSION['auth_provider'] ?? null;
$twitterId = $_SESSION['twitter_user_id'] ?? null;
$twitterHandle = $_SESSION['twitter_screen_name'] ?? null;

// Debug: log session presence for dashboard fetch
error_log('[TWITTER_ME] sid=' . session_id() . ' authed=' . ($isAuthed ? '1' : '0') . ' provider=' . ($provider ?? 'null') . ' uid=' . ($twitterId ?? 'null'));

$userDoc = null;
if ($isAuthed && $provider === 'twitter' && $twitterId) {
    $userModel = new User();
    $userDoc = $userModel->findByTwitterId($twitterId);
}

http_response_code(200);
echo json_encode([
    'authenticated' => $isAuthed,
    'provider' => $provider,
    'twitter' => [
        'id' => $twitterId,
        'screen_name' => $twitterHandle,
        'profile_image_url' => $_SESSION['twitter_profile_image_url'] ?? null,
    ],
    'user' => $userDoc ? [
        'name' => $userDoc['name'] ?? '',
        'twitter_handle' => $userDoc['twitter_handle'] ?? $twitterHandle,
        'twitter_profile_image_url' => $userDoc['twitter_profile_image_url'] ?? null,
        'email' => $userDoc['email'] ?? null,
    ] : null
]);
exit;


