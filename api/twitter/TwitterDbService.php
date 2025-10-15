<?php
require_once __DIR__ . '/../config/db.php';

class TwitterDbService {
    private $db;
    
    public function __construct() {
        $this->db = Database::getDB();
    }
    
    public function findUserByTwitterId($twitterId) {
        try {
            $users = $this->db->users;
            return $users->findOne(['twitter_id' => $twitterId]);
        } catch (Exception $e) {
            error_log('[TwitterDbService] Error finding user: ' . $e->getMessage());
            return null;
        }
    }
    
    public function revokeTokens($userId) {
        try {
            $tokens = $this->db->twitter_tokens;
            $result = $tokens->updateMany(
                ['user_id' => $userId],
                ['$set' => ['is_active' => false]]
            );
            return $result->getModifiedCount();
        } catch (Exception $e) {
            error_log('[TwitterDbService] Error revoking tokens: ' . $e->getMessage());
            return 0;
        }
    }
    
    public function saveTwitterUser($user, $accessToken) {
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
            error_log('[TwitterDbService] Error saving user: ' . $e->getMessage());
            throw $e;
        }
    }
}
?>