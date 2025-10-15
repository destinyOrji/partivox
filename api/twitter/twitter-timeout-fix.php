<?php
// Twitter API Timeout and Connection Fix
// This file provides enhanced timeout handling and connection management

class TwitterConnectionManager {
    private $connection;
    private $maxRetries = 3;
    private $timeoutSeconds = 30;
    
    public function __construct($consumerKey, $consumerSecret, $oauthToken = null, $oauthTokenSecret = null) {
        require_once __DIR__ . '/../../vendor/autoload.php';
        
        $this->connection = new Abraham\TwitterOAuth\TwitterOAuth(
            $consumerKey, 
            $consumerSecret, 
            $oauthToken, 
            $oauthTokenSecret
        );
        
        // Set aggressive timeouts for better reliability
        $this->connection->setTimeouts(15, $this->timeoutSeconds);
    }
    
    public function makeRequest($endpoint, $params = [], $method = 'GET') {
        $attempt = 0;
        $lastError = null;
        
        while ($attempt < $this->maxRetries) {
            try {
                $attempt++;
                error_log("[TwitterConnectionManager] Attempt $attempt for $endpoint");
                
                // Reset timeouts for each attempt
                $this->connection->setTimeouts(15, $this->timeoutSeconds);
                
                if ($method === 'GET') {
                    $response = $this->connection->get($endpoint, $params);
                } else {
                    $response = $this->connection->post($endpoint, $params);
                }
                
                // Check for API errors
                if (isset($response->errors)) {
                    $error = $response->errors[0];
                    error_log("[TwitterConnectionManager] API Error: " . $error->message);
                    
                    // Don't retry on authentication errors
                    if (in_array($error->code, [32, 89, 135])) {
                        throw new Exception('Authentication error: ' . $error->message);
                    }
                    
                    // Don't retry on rate limit errors
                    if ($error->code === 88) {
                        throw new Exception('Rate limit exceeded: ' . $error->message);
                    }
                    
                    throw new Exception('API error: ' . $error->message);
                }
                
                // Success
                error_log("[TwitterConnectionManager] Success on attempt $attempt");
                return $response;
                
            } catch (Exception $e) {
                $lastError = $e;
                error_log("[TwitterConnectionManager] Attempt $attempt failed: " . $e->getMessage());
                
                // Don't retry on certain errors
                if (strpos($e->getMessage(), 'Authentication error') !== false ||
                    strpos($e->getMessage(), 'Rate limit') !== false) {
                    break;
                }
                
                // Wait before retry (exponential backoff)
                if ($attempt < $this->maxRetries) {
                    $waitTime = pow(2, $attempt) * 1000000; // microseconds
                    usleep($waitTime);
                }
            }
        }
        
        throw new Exception("Failed after $this->maxRetries attempts. Last error: " . $lastError->getMessage());
    }
    
    public function getRequestToken($callbackUrl) {
        $response = $this->makeRequest('oauth/request_token', ['oauth_callback' => $callbackUrl]);
        
        // Validate the response structure
        if (!is_array($response) || !isset($response['oauth_token']) || !isset($response['oauth_token_secret'])) {
            error_log('[TwitterConnectionManager] Invalid request token response: ' . json_encode($response));
            throw new Exception('Invalid request token response from Twitter API');
        }
        
        // Validate token format (should be alphanumeric)
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $response['oauth_token'])) {
            error_log('[TwitterConnectionManager] Invalid oauth_token format: ' . $response['oauth_token']);
            throw new Exception('Invalid oauth_token format received from Twitter');
        }
        
        return $response;
    }
    
    public function getAccessToken($oauthVerifier) {
        return $this->makeRequest('oauth/access_token', ['oauth_verifier' => $oauthVerifier]);
    }
    
    public function verifyCredentials() {
        return $this->makeRequest('account/verify_credentials', [
            'include_email' => 'true',
            'skip_status' => 'true'
        ]);
    }
    
    public function getLastHttpCode() {
        return $this->connection->getLastHttpCode();
    }
}

// Helper function to create a connection manager
function createTwitterConnection($oauthToken = null, $oauthTokenSecret = null) {
    require_once __DIR__ . '/../config/twitter.php';
    
    if (!defined('CONSUMER_KEY') || !defined('CONSUMER_SECRET')) {
        throw new Exception('Twitter API credentials not configured');
    }
    
    return new TwitterConnectionManager(
        CONSUMER_KEY, 
        CONSUMER_SECRET, 
        $oauthToken, 
        $oauthTokenSecret
    );
}

// Helper function to handle Twitter API errors gracefully
function handleTwitterError($error, $context = '') {
    $errorMessage = $error->getMessage();
    $httpCode = 500;
    
    // Map common Twitter errors to user-friendly messages
    if (strpos($errorMessage, 'timeout') !== false || strpos($errorMessage, 'timed out') !== false) {
        $userMessage = 'Twitter is taking too long to respond. Please try again.';
        $httpCode = 408;
    } elseif (strpos($errorMessage, 'Authentication error') !== false) {
        $userMessage = 'Twitter authentication failed. Please log in again.';
        $httpCode = 401;
    } elseif (strpos($errorMessage, 'Rate limit') !== false) {
        $userMessage = 'Too many requests to Twitter. Please try again in 15 minutes.';
        $httpCode = 429;
    } elseif (strpos($errorMessage, 'Connection') !== false) {
        $userMessage = 'Unable to reach Twitter right now. Please try again.';
        $httpCode = 503;
    } else {
        $userMessage = 'Twitter service is temporarily unavailable. Please try again.';
        $httpCode = 503;
    }
    
    error_log("[TwitterError] $context: $errorMessage");
    
    return [
        'error' => true,
        'message' => $userMessage,
        'http_code' => $httpCode,
        'retry_after' => $httpCode === 429 ? 900 : 60 // 15 minutes for rate limit, 1 minute for others
    ];
}
?>
