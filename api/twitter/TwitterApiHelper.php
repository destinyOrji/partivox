<?php
require_once __DIR__ . '/../config/db.php';

class TwitterApiHelper {
    private $db;
    private $connection;
    private $apiVersion;
    
    public function __construct($connection = null, $apiVersion = '1.1') {
        $this->db = Database::getDB();
        $this->connection = $connection;
        $this->apiVersion = $apiVersion;
    }
    
    public function getUserInfo($twitterId = null) {
        try {
            // If we have a connection, get fresh user info from Twitter API
            if ($this->connection && !$twitterId) {
                return $this->getUserInfoFromAPI();
            }
            
            // Otherwise, get from database
            if ($twitterId) {
                $users = $this->db->users;
                $user = $users->findOne(['twitter_id' => $twitterId]);
                
                if ($user) {
                    return [
                        'id' => (string)$user->_id,
                        'name' => $user->name ?? '',
                        'email' => $user->email ?? '',
                        'twitter_id' => $user->twitter_id ?? '',
                        'twitter_handle' => $user->twitter_handle ?? '',
                        'avatar' => $user->avatar ?? ''
                    ];
                }
            }
            
            return null;
        } catch (Exception $e) {
            error_log('[TwitterApiHelper] Error getting user info: ' . $e->getMessage());
            return null;
        }
    }
    
    public function getUserInfoFromAPI() {
        try {
            if (!$this->connection) {
                throw new Exception('No Twitter connection available');
            }
            
            // Set appropriate timeouts
            $this->connection->setTimeouts(15, 45);
            
            // Get user info from Twitter API
            $user = $this->connection->get('account/verify_credentials', [
                'include_email' => 'true',
                'skip_status' => 'true'
            ]);
            
            if (isset($user->errors)) {
                throw new Exception('Twitter API Error: ' . $user->errors[0]->message);
            }
            
            return $user;
        } catch (Exception $e) {
            error_log('[TwitterApiHelper] Error getting user info from API: ' . $e->getMessage());
            throw $e;
        }
    }
    
    public function saveUserToDatabase($user, $accessToken) {
        try {
            $users = $this->db->users;
            
            // Prepare user data
            $userData = [
                'twitter_id' => (string)$user->id_str,
                'twitter_handle' => $user->screen_name,
                'name' => $user->name,
                'email' => $user->email ?? ($user->screen_name . '@twitter.com'),
                'avatar' => $user->profile_image_url_https ?? '',
                'followers_count' => $user->followers_count ?? 0,
                'location' => $user->location ?? '',
                'created_at' => new MongoDB\BSON\UTCDateTime(),
                'updated_at' => new MongoDB\BSON\UTCDateTime()
            ];
            
            // Save or update user
            $result = $users->updateOne(
                ['twitter_id' => (string)$user->id_str],
                ['$set' => $userData],
                ['upsert' => true]
            );
            
            // Save access token
            $tokens = $this->db->twitter_tokens;
            $tokenData = [
                'user_id' => (string)$user->id_str,
                'oauth_token' => $accessToken['oauth_token'],
                'oauth_token_secret' => $accessToken['oauth_token_secret'],
                'is_active' => true,
                'created_at' => new MongoDB\BSON\UTCDateTime(),
                'updated_at' => new MongoDB\BSON\UTCDateTime()
            ];
            
            $tokens->updateOne(
                ['user_id' => (string)$user->id_str],
                ['$set' => $tokenData],
                ['upsert' => true]
            );
            
            return $result->getUpsertedId() ?? $result->getMatchedCount();
        } catch (Exception $e) {
            error_log('[TwitterApiHelper] Error saving user to database: ' . $e->getMessage());
            throw $e;
        }
    }
}
?>