<?php

class TwitterApiHelper {
    private $connection;
    private $apiVersion;
    
    public function __construct($connection, $apiVersion = '2.0') {
        $this->connection = $connection;
        $this->apiVersion = $apiVersion;
    }
    
    /**
     * Get user information using the appropriate API version
     */
    public function getUserInfo() {
        try {
            if ($this->apiVersion === '2.0') {
                return $this->getUserInfoV2();
            } else {
                return $this->getUserInfoV1();
            }
        } catch (Exception $e) {
            error_log('[TWITTER_API_HELPER] Error getting user info: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get user info using Twitter API v2.0
     */
    private function getUserInfoV2() {
        $user = $this->connection->get('2/users/me', [
            'user.fields' => 'id,name,username,profile_image_url,public_metrics,location,description,verified'
        ]);
        
        $httpStatus = $this->connection->getLastHttpCode();
        error_log('[TWITTER_API_V2] HTTP status: ' . $httpStatus);
        
        if ($httpStatus !== 200) {
            $this->handleApiError($httpStatus, $user);
            return null;
        }
        
        // Transform v2 response to v1.1 compatible format
        if (isset($user->data)) {
            $userData = $user->data;
            return (object) [
                'id_str' => $userData->id,
                'screen_name' => $userData->username,
                'name' => $userData->name,
                'description' => $userData->description ?? '',
                'profile_image_url_https' => $userData->profile_image_url ?? '',
                'followers_count' => $userData->public_metrics->followers_count ?? 0,
                'friends_count' => $userData->public_metrics->following_count ?? 0,
                'statuses_count' => $userData->public_metrics->tweet_count ?? 0,
                'location' => $userData->location ?? '',
                'verified' => $userData->verified ?? false
            ];
        }
        
        return null;
    }
    
    /**
     * Get user info using Twitter API v1.1 (fallback)
     */
    private function getUserInfoV1() {
        $user = $this->connection->get('account/verify_credentials', [
            'skip_status' => true,
            'include_entities' => false
        ]);
        
        $httpStatus = $this->connection->getLastHttpCode();
        error_log('[TWITTER_API_V1] HTTP status: ' . $httpStatus);
        
        if ($httpStatus !== 200) {
            $this->handleApiError($httpStatus, $user);
            return null;
        }
        
        return $user;
    }
    
    /**
     * Handle API errors with specific status codes
     */
    private function handleApiError($httpStatus, $response) {
        $errorMessage = 'Unknown error';
        
        if (is_object($response) && property_exists($response, 'errors') && !empty($response->errors)) {
            $errorMessage = $response->errors[0]->message ?? 'API error';
        }
        
        switch ($httpStatus) {
            case 401:
                error_log('[TWITTER_API] Unauthorized - token invalid or expired');
                throw new Exception('Twitter token is invalid or expired');
                break;
            case 403:
                error_log('[TWITTER_API] Forbidden - access denied');
                throw new Exception('Twitter access forbidden');
                break;
            case 429:
                error_log('[TWITTER_API] Rate limit exceeded');
                throw new Exception('Twitter API rate limit exceeded');
                break;
            case 500:
            case 502:
            case 503:
                error_log('[TWITTER_API] Server error: ' . $httpStatus);
                throw new Exception('Twitter server error');
                break;
            default:
                error_log('[TWITTER_API] HTTP ' . $httpStatus . ': ' . $errorMessage);
                throw new Exception('Twitter API error: ' . $errorMessage);
        }
    }
    
    /**
     * Check if the API response indicates rate limiting
     */
    public function isRateLimited() {
        return $this->connection->getLastHttpCode() === 429;
    }
    
    /**
     * Get rate limit information from response headers
     */
    public function getRateLimitInfo() {
        $headers = $this->connection->getLastApiPath();
        // This would need to be implemented based on the TwitterOAuth library's header access
        return [
            'limit' => null,
            'remaining' => null,
            'reset' => null
        ];
    }
}
