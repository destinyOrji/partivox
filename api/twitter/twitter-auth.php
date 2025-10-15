<?php
// Show all errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL & ~E_DEPRECATED);

// Determine if current request is HTTPS
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
           (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

// Session cookie params MUST be set before session_start()
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '', // Let browser default to request host
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Config + Autoload
require_once __DIR__ . '/../config/twitter.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/TwitterApiHelper.php';
require_once __DIR__ . '/TwitterDbService.php';
require_once __DIR__ . '/twitter-timeout-fix.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Abraham\TwitterOAuth\TwitterOAuth;

// Helpers
function safe_redirect($url) {
    if (!headers_sent()) {
        header('Location: ' . $url);
    } else {
        echo "<script>window.location.href='" . htmlspecialchars($url, ENT_QUOTES) . "';</script>";
        echo "<noscript><a href='" . htmlspecialchars($url, ENT_QUOTES) . "'>Continue</a></noscript>";
    }
    exit;
}

function clear_oauth_session() {
    // Clear OAuth-related session data
    unset($_SESSION['oauth_token'], $_SESSION['oauth_token_secret'], $_SESSION['oauth_token_time']);
    
    // Clear OAuth-related cookies
    if (isset($_COOKIE['tw_oauth_token'])) {
        setcookie('tw_oauth_token', '', time() - 3600, '/', '', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'), true);
    }
    if (isset($_COOKIE['tw_oauth_token_secret'])) {
        setcookie('tw_oauth_token_secret', '', time() - 3600, '/', '', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'), true);
    }
    
    error_log('[TWITTER_AUTH][SESSION_CLEAR] OAuth session data cleared');
}

function mask_for_log($s, $keep = 6) {
    if (!$s) return null;
    $len = strlen($s);
    if ($len <= $keep) return str_repeat('*', $len);
    return substr($s, 0, $keep) . str_repeat('*', max(0, $len - $keep));
}

function log_state($stage) {
    $sid = session_id();
    $sessOauth = [
        'oauth_token' => mask_for_log($_SESSION['oauth_token'] ?? null),
        'oauth_token_secret' => mask_for_log($_SESSION['oauth_token_secret'] ?? null),
        'twitter_access_token_present' => isset($_SESSION['twitter_access_token'])
    ];
    $cookieOauth = [
        'tw_oauth_token' => mask_for_log($_COOKIE['tw_oauth_token'] ?? null),
        'tw_oauth_token_secret' => mask_for_log($_COOKIE['tw_oauth_token_secret'] ?? null)
    ];
    error_log('[TWITTER_AUTH][' . $stage . '] session_id=' . $sid . ' session=' . json_encode($sessOauth) . ' cookies=' . json_encode($cookieOauth));
}

function is_token_expired($accessToken) {
    // Check if token has expiration timestamp
    if (isset($accessToken['expires_at'])) {
        return time() >= $accessToken['expires_at'];
    }
    
    // For OAuth 1.0a tokens, check if token was created more than 24 hours ago
    if (isset($accessToken['created_at'])) {
        return (time() - $accessToken['created_at']) > 86400; // 24 hours
    }
    
    // If no timestamp info, assume token might be expired after 1 hour
    return false;
}

function refresh_twitter_token($connection, $accessToken) {
    try {
        // For OAuth 1.0a, we need to re-authenticate
        // This is a limitation of OAuth 1.0a - tokens don't refresh
        error_log('[TWITTER_AUTH][TOKEN_REFRESH] OAuth 1.0a tokens cannot be refreshed, need re-authentication');
        return false;
    } catch (Exception $e) {
        error_log('[TWITTER_AUTH][TOKEN_REFRESH_ERROR] ' . $e->getMessage());
        return false;
    }
}

// Basic validation of config constants
if (!defined('CONSUMER_KEY') || !defined('CONSUMER_SECRET') || !defined('OAUTH_CALLBACK')) {
    error_log('[TWITTER_AUTH][CONFIG_ERROR] Missing twitter config constants');
    echo "<div style='color:red;font-weight:bold;'>Server configuration error (missing Twitter keys). Contact admin.</div>";
    exit;
}

if (isset($_SESSION['twitter_access_token']) && $_SESSION['twitter_access_token']) {
    // Check if token is expired
    if (is_token_expired($_SESSION['twitter_access_token'])) {
        error_log('[TWITTER_AUTH][TOKEN_EXPIRED] Access token has expired, clearing session');
        unset($_SESSION['twitter_access_token']);
        unset($_SESSION['oauth_token']);
        unset($_SESSION['oauth_token_secret']);
        $isLoggedIn = false;
    } else {
        $isLoggedIn = true;
        log_state('HAS_ACCESS_TOKEN');
    }
} elseif (isset($_GET['oauth_verifier']) && isset($_GET['oauth_token'])) {
    // Validate that we have the matching token in session or cookies
    $sessionToken = $_SESSION['oauth_token'] ?? null;
    $cookieToken = $_COOKIE['tw_oauth_token'] ?? null;
    $callbackToken = $_GET['oauth_token'] ?? null;
    
    // Check if the callback token matches our stored token
    $tokenMatch = false;
    if ($sessionToken && $callbackToken === $sessionToken) {
        $tokenMatch = true;
        error_log('[TWITTER_AUTH][TOKEN_MATCH] Session token matches callback token');
    } elseif ($cookieToken && $callbackToken === $cookieToken) {
        $tokenMatch = true;
        error_log('[TWITTER_AUTH][TOKEN_MATCH] Cookie token matches callback token');
    } else {
        error_log('[TWITTER_AUTH][TOKEN_MISMATCH] Callback token: ' . $callbackToken . ', Session: ' . $sessionToken . ', Cookie: ' . $cookieToken);
    }
    
    if ($tokenMatch) {
    try {
        log_state('CALLBACK_START');
        // Fallback to cookie if session is missing (e.g., session not persisted cross-domain/port)
        $rtToken = $_SESSION['oauth_token'] ?? ($_COOKIE['tw_oauth_token'] ?? null);
        $rtSecret = $_SESSION['oauth_token_secret'] ?? ($_COOKIE['tw_oauth_token_secret'] ?? null);
        
        // Read and validate callback params (avoid deprecated FILTER_SANITIZE_STRING)
        $oauthVerifier = (isset($_GET['oauth_verifier']) && preg_match('/^[A-Za-z0-9_-]+$/', $_GET['oauth_verifier']))
            ? $_GET['oauth_verifier']
            : null;
        $callbackToken = (isset($_GET['oauth_token']) && preg_match('/^[A-Za-z0-9_-]+$/', $_GET['oauth_token']))
            ? $_GET['oauth_token']
            : null;
            
        try {
            $connectionManager = createTwitterConnection($rtToken, $rtSecret);
            $access_token = $connectionManager->getAccessToken($oauthVerifier);
        } catch (Exception $e) {
            $errorInfo = handleTwitterError($e, 'ACCESS_TOKEN');
            error_log('Twitter access_token exchange failed: ' . $e->getMessage());
            echo "<div style='color:red;font-weight:bold;'>" . $errorInfo['message'] . "</div>";
            echo "<a href='/api/twitter/twitter-auth.php' style='display:inline-block;padding:8px 14px;background:#1da1f2;color:#fff;border-radius:4px;text-decoration:none;'>Retry Twitter Login</a>";
            exit;
        }
        // Add timestamp for token expiration tracking
        $access_token['created_at'] = time();
        $_SESSION['twitter_access_token'] = $access_token;
        
        error_log('[TWITTER_AUTH][ACCESS_TOKEN] obtained for user_id=' . ($access_token['user_id'] ?? 'n/a') . ' screen_name=' . ($access_token['screen_name'] ?? 'n/a'));
        
        // IMMEDIATELY save user to database to prevent lookup failures
        if (isset($access_token['user_id']) && isset($access_token['screen_name'])) {
            try {
                // Create minimal user object for immediate saving
                $tempUser = (object) [
                    'id_str' => (string)$access_token['user_id'],
                    'screen_name' => (string)$access_token['screen_name'],
                    'name' => (string)$access_token['screen_name'], // Use screen_name as fallback
                    'profile_image_url_https' => '',
                    'followers_count' => 0,
                    'location' => ''
                ];
                
                $twitterDb = new TwitterDbService();
                $savedUser = $twitterDb->saveTwitterUser($tempUser, $access_token);
                error_log('[TWITTER_AUTH][IMMEDIATE_SAVE] User saved immediately after token exchange: ' . $tempUser->screen_name);
            } catch (Exception $e) {
                error_log('[TWITTER_AUTH][IMMEDIATE_SAVE_ERROR] ' . $e->getMessage());
            }
        }
        
        // Establish auth flags early to avoid dashboard seeing a missing session
        if (session_status() === PHP_SESSION_ACTIVE) {
            // Regenerate ID on privilege change
            @session_regenerate_id(true);
        }
        $_SESSION['is_authenticated'] = true;
        $_SESSION['auth_provider'] = 'twitter';
        if (isset($access_token['user_id'])) {
            $_SESSION['twitter_user_id'] = (string)$access_token['user_id'];
            $_SESSION['user_id'] = (string)$access_token['user_id']; // For auth middleware
        }
        if (isset($access_token['screen_name'])) {
            $_SESSION['twitter_screen_name'] = (string)$access_token['screen_name'];
            $_SESSION['user_name'] = (string)$access_token['screen_name']; // For auth middleware
            $_SESSION['user_email'] = $access_token['screen_name'] . '@twitter.com'; // Fake email for consistency
        }
        // Clear request token cookies once exchanged
        if (isset($_COOKIE['tw_oauth_token'])) {
            setcookie('tw_oauth_token', '', time() - 3600, '/', '', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'), true);
        }
        if (isset($_COOKIE['tw_oauth_token_secret'])) {
            setcookie('tw_oauth_token_secret', '', time() - 3600, '/', '', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'), true);
        }
        $isLoggedIn = true;
    } catch (\Abraham\TwitterOAuth\TwitterOAuthException $e) {
        error_log('Twitter access_token exchange failed: ' . $e->getMessage());
        echo "<div style='color:red;font-weight:bold;'>Twitter is taking too long to respond. Please try again.</div>";
        echo "<a href='/api/twitter/twitter-auth.php' style='display:inline-block;padding:8px 14px;background:#1da1f2;color:#fff;border-radius:4px;text-decoration:none;'>Retry Twitter Login</a>";
        exit;
    }
    } else {
        // Token mismatch - clear session and restart OAuth flow
        error_log('[TWITTER_AUTH][TOKEN_MISMATCH] Clearing session and restarting OAuth flow');
        clear_oauth_session();
        
        echo "<div style='color:orange;font-weight:bold;'>Twitter session expired. Please try logging in again.</div>";
        echo "<a href='/api/twitter/twitter-auth.php' style='display:inline-block;padding:8px 14px;background:#1da1f2;color:#fff;border-radius:4px;text-decoration:none;'>Login with Twitter</a>";
        echo "<br><br><a href='/api/twitter/debug-oauth.php' style='display:inline-block;padding:8px 14px;background:#6c757d;color:#fff;border-radius:4px;text-decoration:none;'>Debug OAuth Issues</a>";
        exit;
    }
} else {
    try {
        $reuseWindowSeconds = 60; // reuse recent request token for up to 1 minute (reduced from 2 minutes)
        $useExisting = false;
        if (isset($_SESSION['oauth_token'], $_SESSION['oauth_token_secret'], $_SESSION['oauth_token_time'])) {
            $age = time() - (int)$_SESSION['oauth_token_time'];
            if ($age >= 0 && $age < $reuseWindowSeconds) {
                $useExisting = true;
                $request_token = [
                    'oauth_token' => $_SESSION['oauth_token'],
                    'oauth_token_secret' => $_SESSION['oauth_token_secret']
                ];
                error_log('[TWITTER_AUTH][REUSING_TOKEN] Reusing existing token, age: ' . $age . ' seconds');
            } else {
                error_log('[TWITTER_AUTH][TOKEN_EXPIRED] Token too old, age: ' . $age . ' seconds, max: ' . $reuseWindowSeconds);
                // Clear expired tokens
                clear_oauth_session();
            }
        }

        if (!isset($request_token)) {
            try {
                $connectionManager = createTwitterConnection();
                $request_token = $connectionManager->getRequestToken(OAUTH_CALLBACK);
                $_SESSION['oauth_token'] = $request_token['oauth_token'];
                $_SESSION['oauth_token_secret'] = $request_token['oauth_token_secret'];
                $_SESSION['oauth_token_time'] = time();
            } catch (Exception $e) {
                $errorInfo = handleTwitterError($e, 'REQUEST_TOKEN');
                error_log('Twitter request_token failed: ' . $e->getMessage());
                echo "<div style='color:red;font-weight:bold;'>" . $errorInfo['message'] . "</div>";
                echo "<a href='/api/twitter/twitter-auth.php' style='display:inline-block;padding:8px 14px;background:#1da1f2;color:#fff;border-radius:4px;text-decoration:none;'>Retry Twitter Login</a>";
                exit;
            }
        }
        log_state('REQUEST_TOKEN');
        // Also persist/refresh in short-lived cookies to survive session issues on callback
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie('tw_oauth_token', $request_token['oauth_token'], [
            'expires' => time() + 600,
            'path' => '/',
            'domain' => '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        setcookie('tw_oauth_token_secret', $request_token['oauth_token_secret'], [
            'expires' => time() + 600,
            'path' => '/',
            'domain' => '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        $isLoggedIn = false;
    } catch (\Abraham\TwitterOAuth\TwitterOAuthException $e) {
        error_log('Twitter request_token failed: ' . $e->getMessage());
        echo "<div style='color:red;font-weight:bold;'>Unable to reach Twitter right now (timeout). Please try again.</div>";
        echo "<a href='/api/twitter/twitter-auth.php' style='display:inline-block;padding:8px 14px;background:#1da1f2;color:#fff;border-radius:4px;text-decoration:none;'>Retry Twitter Login</a>";
        exit;
    }
}

if ($isLoggedIn) {
    // If session exists but DB user was deleted, force fresh OAuth flow
    if (isset($_SESSION['twitter_access_token']['user_id'])) {
        try {
            $twitterDb = new TwitterDbService();
            $twitterUserId = (string)$_SESSION['twitter_access_token']['user_id'];
            error_log('[TWITTER_AUTH][DB_CHECK] Checking for user with Twitter ID: ' . $twitterUserId);
            
            $existingUser = $twitterDb->findUserByTwitterId($twitterUserId);
            
            if (!$existingUser) {
                error_log('[TWITTER_AUTH][USER_NOT_FOUND] User deleted from DB, restarting OAuth for ID: ' . $twitterUserId);
                
                // Show debug link in development
                if (isset($_GET['debug'])) {
                    echo "<div style='color:orange;font-weight:bold;'>Debug Mode: User not found in database.</div>";
                    echo "<a href='/api/twitter/debug-twitter-auth.php'>Debug Twitter Auth</a><br><br>";
                }
                
                // Clear session and restart OAuth authorize
                $_SESSION = [];
                try {
                    $connection = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET);
                    $connection->setTimeouts(15, 45);
                    $request_token = $connection->oauth('oauth/request_token', array('oauth_callback' => OAUTH_CALLBACK));
                    $_SESSION['oauth_token'] = $request_token['oauth_token'];
                    $_SESSION['oauth_token_secret'] = $request_token['oauth_token_secret'];
                    $authUrl = $connection->url('oauth/authenticate', array('oauth_token' => $request_token['oauth_token']));
                    safe_redirect($authUrl);
                    exit;
                } catch (\Abraham\TwitterOAuth\TwitterOAuthException $e) {
                    error_log('Twitter request_token (restart) failed: ' . $e->getMessage());
                    echo "<div style='color:red;font-weight:bold;'>Unable to reach Twitter right now. Please try again.</div>";
                    echo "<a href='/api/twitter/twitter-auth.php' style='display:inline-block;padding:8px 14px;background:#1da1f2;color:#fff;border-radius:4px;text-decoration:none;'>Retry Twitter Login</a>";
                    exit;
                }
            } else {
                error_log('[TWITTER_AUTH][USER_FOUND] User exists in DB: ' . ($existingUser->name ?? 'unnamed'));
            }
        } catch (Exception $e) {
            error_log('[TWITTER_AUTH][DB_CHECK_ERROR] ' . $e->getMessage());
            // Continue with authentication flow
        }
    }
    $oauthToken = $_SESSION['twitter_access_token']['oauth_token'];
    $oauthTokenSecret = $_SESSION['twitter_access_token']['oauth_token_secret'];
    
    // Use improved connection manager for better API handling
    try {
        $connectionManager = createTwitterConnection($oauthToken, $oauthTokenSecret);
        $user = $connectionManager->verifyCredentials();
        
        if ($user === null) {
            throw new Exception('Failed to get user information from Twitter API');
        }
        
        error_log('[TWITTER_AUTH][API_SUCCESS] Successfully retrieved user info for: ' . ($user->screen_name ?? 'unknown'));
        
    } catch (Exception $e) {
        $errorInfo = handleTwitterError($e, 'VERIFY_CREDENTIALS');
        error_log('[TWITTER_AUTH][API_ERROR] ' . $e->getMessage());
        
        // Handle specific error types
        if (strpos($e->getMessage(), 'rate limit') !== false) {
            echo "<div style='color:orange;font-weight:bold;'>Twitter API rate limit exceeded. Please try again in 15 minutes.</div>";
            echo "<a href='/api/twitter/twitter-auth.php' style='display:inline-block;padding:8px 14px;background:#1da1f2;color:#fff;border-radius:4px;text-decoration:none;'>Retry Twitter Login</a>";
            exit;
        } elseif (strpos($e->getMessage(), 'invalid or expired') !== false) {
            // Clear invalid tokens and restart OAuth flow
            unset($_SESSION['twitter_access_token']);
            unset($_SESSION['oauth_token']);
            unset($_SESSION['oauth_token_secret']);
            echo "<div style='color:red;font-weight:bold;'>Your Twitter session has expired. Please log in again.</div>";
            echo "<a href='/api/twitter/twitter-auth.php' style='display:inline-block;padding:8px 14px;background:#1da1f2;color:#fff;border-radius:4px;text-decoration:none;'>Login with Twitter</a>";
            exit;
        }
        
        $user = null;
    }

    if ($user === null || (is_object($user) && property_exists($user, 'errors'))) {
        // Enhanced error handling with specific error messages
        $httpStatus = $connection->getLastHttpCode();
        $errorMessage = 'Unknown error';
        
        if (is_object($user) && property_exists($user, 'errors') && !empty($user->errors)) {
            $errorMessage = $user->errors[0]->message ?? 'Twitter API error';
            error_log('[TWITTER_AUTH][API_ERROR] ' . $errorMessage);
        }
        
        // Handle specific HTTP status codes
        switch ($httpStatus) {
            case 401:
                error_log('[TWITTER_AUTH][TOKEN_INVALID] Access token is invalid or expired');
                // Clear invalid tokens and restart OAuth flow
                unset($_SESSION['twitter_access_token']);
                unset($_SESSION['oauth_token']);
                unset($_SESSION['oauth_token_secret']);
                echo "<div style='color:red;font-weight:bold;'>Your Twitter session has expired. Please log in again.</div>";
                break;
            case 403:
                error_log('[TWITTER_AUTH][FORBIDDEN] Access forbidden - app may be suspended');
                echo "<div style='color:red;font-weight:bold;'>Twitter access forbidden. Please contact support.</div>";
                break;
            case 429:
                error_log('[TWITTER_AUTH][RATE_LIMIT] Rate limit exceeded');
                echo "<div style='color:orange;font-weight:bold;'>Too many requests. Please try again in 15 minutes.</div>";
                break;
            default:
                // Fallback: construct minimal user from access_token if available
                $tokenUserId = isset($_SESSION['twitter_access_token']['user_id']) ? (string)$_SESSION['twitter_access_token']['user_id'] : null;
                $tokenScreenName = $_SESSION['twitter_access_token']['screen_name'] ?? '';
                if ($tokenUserId) {
                    error_log('[TWITTER_AUTH][VERIFY_FALLBACK] Using token-derived user due to API failure. HTTP=' . $httpStatus);
                    $user = (object) [
                        'id_str' => $tokenUserId,
                        'screen_name' => $tokenScreenName,
                        'name' => $tokenScreenName,
                        'profile_image_url_https' => '',
                        'followers_count' => 0,
                        'location' => ''
                    ];
                    break;
                }
                echo "<div style='color:red;font-weight:bold;'>Twitter login failed: " . htmlspecialchars($errorMessage) . "</div>";
        }
        
        if (!isset($user) || $user === null) {
            echo "<a href='/api/twitter/twitter-auth.php' style='display:inline-block;padding:8px 14px;background:#1da1f2;color:#fff;border-radius:4px;text-decoration:none;'>Retry Twitter Login</a>";
            exit;
        }
    }

    {
        // Save user to MongoDB using TwitterDbService
        try {
            $twitterDb = new TwitterDbService();
            $savedUser = $twitterDb->saveTwitterUser($user, $_SESSION['twitter_access_token']);
            
            if ($savedUser) {
                error_log('[TWITTER_AUTH][DB_SUCCESS] User saved successfully: ' . ($user->screen_name ?? 'unknown'));
            }
        } catch (Exception $e) {
            error_log('[TWITTER_AUTH][DB_ERROR] Failed to save user: ' . $e->getMessage());
            // Continue; do not block login on DB issues
        }
        // Mark session as authenticated for app use (ensure latest values)
        $_SESSION['is_authenticated'] = true;
        $_SESSION['auth_provider'] = 'twitter';
        $_SESSION['twitter_user_id'] = $user->id_str ?? ($_SESSION['twitter_user_id'] ?? null);
        $_SESSION['twitter_screen_name'] = $user->screen_name ?? ($_SESSION['twitter_screen_name'] ?? '');
        $_SESSION['twitter_profile_image_url'] = isset($user->profile_image_url_https)
            ? $user->profile_image_url_https
            : (isset($user->profile_image_url) ? $user->profile_image_url : ($_SESSION['twitter_profile_image_url'] ?? null));
        log_state('BEFORE_DASHBOARD_REDIRECT');
        // Build absolute dashboard URL (more reliable under various servers)
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
        $dashboardUrl = $scheme . '://' . $host . '/pages/userDashboard.html';
        error_log('[TWITTER_AUTH][REDIRECT] -> ' . $dashboardUrl);
        // Ensure session is written before redirect
        session_write_close();
        // Redirect to user dashboard page after successful signup/login
        safe_redirect($dashboardUrl);
    }
} else {
    // Build authenticate URL directly (no need for a TwitterOAuth instance)
    $authUrl = 'https://api.twitter.com/oauth/authenticate?oauth_token=' . rawurlencode($request_token['oauth_token']);
    safe_redirect($authUrl);
}
