<?php
// Twitter API Routes
// Handles Twitter authentication and token management

// Load environment variables if not already loaded
if (!getenv('TWITTER_CONSUMER_KEY') && file_exists(__DIR__ . '/../../.env')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
    $dotenv->load();
}

// Debug environment variables
error_log("=== TWITTER ROUTE DEBUG ===");
error_log("TWITTER_CONSUMER_KEY: " . (getenv('TWITTER_CONSUMER_KEY') ?: 'NOT SET'));
error_log("TWITTER_CONSUMER_SECRET: " . (getenv('TWITTER_CONSUMER_SECRET') ? 'SET (hidden)' : 'NOT SET'));
error_log("TWITTER_OAUTH_CALLBACK: " . (getenv('TWITTER_OAUTH_CALLBACK') ?: 'NOT SET'));
error_log("MONGODB_URI: " . (getenv('MONGODB_URI') ? 'SET (hidden)' : 'NOT SET'));
error_log("JWT_SECRET: " . (getenv('JWT_SECRET') ? 'SET (hidden)' : 'NOT SET'));

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/twitter.php';
require_once __DIR__ . '/../twitter/TwitterDbService.php';
require_once __DIR__ . '/../twitter/TwitterApiHelper.php';

function twitterRoutes($method, $action, $data) {
    try {
        error_log("[TWITTER_ROUTES] Method: $method, Action: $action");
        error_log("[TWITTER_ROUTES] Data: " . json_encode($data));
        
        switch ($action) {
            case 'get-token.php':
            case 'get-token':
                return handleGetToken($method, $data);
                
            case 'logout.php':
            case 'logout':
                return handleLogout($method, $data);
                
            case 'auth.php':
            case 'auth':
                return handleAuth($method, $data);
                
            case 'callback.php':
            case 'callback':
                return handleCallback($method, $data);
                
            default:
                return [
                    "status" => "error",
                    "message" => "Twitter endpoint not found: $action"
                ];
        }
        
    } catch (Exception $e) {
        error_log("[TWITTER_ROUTES] Exception: " . $e->getMessage());
        return [
            "status" => "error",
            "message" => "Twitter API error: " . $e->getMessage()
        ];
    }
}

// Handle get-token requests
function handleGetToken($method, $data) {
    if ($method !== 'GET') {
        return ["status" => "error", "message" => "Method not allowed"];
    }
    
    try {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        error_log("[TWITTER_GET_TOKEN] Session data: " . json_encode($_SESSION));
        
        // Check if user is authenticated via Twitter
        if (!isset($_SESSION['twitter_user_id']) || !isset($_SESSION['is_authenticated'])) {
            return [
                "status" => "error",
                "message" => "Not authenticated with Twitter"
            ];
        }
        
        $twitterDb = new TwitterDbService();
        $user = $twitterDb->findUserByTwitterId($_SESSION['twitter_user_id']);
        
        if (!$user) {
            return [
                "status" => "error", 
                "message" => "User not found"
            ];
        }
        
        // Generate JWT token for Twitter user
        $payload = [
            'user_id' => (string)$user->_id,
            'twitter_id' => $_SESSION['twitter_user_id'],
            'screen_name' => $_SESSION['twitter_screen_name'] ?? '',
            'auth_provider' => 'twitter',
            'iat' => time(),
            'exp' => time() + (24 * 60 * 60) // 24 hours
        ];
        
        // Simple JWT creation (you may want to use a proper JWT library)
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload_json = json_encode($payload);
        
        $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload_json));
        
        $jwtSecret = getenv('JWT_SECRET') ?: 'a_very_strong_default_secret_for_development_only'; // Fallback for local dev, but MUST be set in production
        $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, $jwtSecret, true);
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        $jwt = $base64Header . "." . $base64Payload . "." . $base64Signature;
        
        error_log("[TWITTER_GET_TOKEN] Generated JWT for user: " . $_SESSION['twitter_user_id']);
        
        return [
            "status" => "success",
            "token" => $jwt,
            "user" => [
                "id" => (string)$user->_id,
                "twitter_id" => $_SESSION['twitter_user_id'],
                "screen_name" => $_SESSION['twitter_screen_name'] ?? '',
                "auth_provider" => "twitter"
            ]
        ];
        
    } catch (Exception $e) {
        error_log("[TWITTER_GET_TOKEN] Error: " . $e->getMessage());
        return [
            "status" => "error",
            "message" => "Failed to generate token"
        ];
    }
}

// Handle logout requests
function handleLogout($method, $data) {
    try {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
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
        
        return [
            "status" => "success",
            "message" => "Logged out successfully"
        ];
        
    } catch (Exception $e) {
        error_log("[TWITTER_LOGOUT] Error: " . $e->getMessage());
        return [
            "status" => "error",
            "message" => "Logout failed"
        ];
    }
}

// Handle auth requests (redirect to Twitter)
function handleAuth($method, $data) {
    if ($method !== 'GET') {
        return ["status" => "error", "message" => "Method not allowed"];
    }
    
    try {
        // Redirect to the actual Twitter auth file
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
        $redirectUrl = $scheme . '://' . $host . '/api/twitter/twitter-auth.php';
        
        return [
            "status" => "redirect",
            "url" => $redirectUrl
        ];
        
    } catch (Exception $e) {
        error_log("[TWITTER_AUTH] Error: " . $e->getMessage());
        return [
            "status" => "error",
            "message" => "Auth redirect failed"
        ];
    }
}

// Handle callback requests
function handleCallback($method, $data) {
    // This would handle Twitter OAuth callback
    // For now, redirect to the existing callback handler
    try {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
        $redirectUrl = $scheme . '://' . $host . '/api/twitter/twitter-auth.php';
        
        return [
            "status" => "redirect", 
            "url" => $redirectUrl
        ];
        
    } catch (Exception $e) {
        error_log("[TWITTER_CALLBACK] Error: " . $e->getMessage());
        return [
            "status" => "error",
            "message" => "Callback failed"
        ];
    }
}

// Remove the debug logs from the end of the file
?>
