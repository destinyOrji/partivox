<?php

require_once __DIR__ . '/../config/db.php';

class TwitterDbService {
    private $db;
    private $usersCollection;
    private $tokensCollection;
    
    public function __construct() {
        $this->db = Database::getDB();
        $this->usersCollection = Database::getCollection('users');
        $this->tokensCollection = Database::getCollection('twitter_tokens');
    }
    
    /**
     * Save or update Twitter user in database
     */
    public function saveTwitterUser($twitterUser, $accessToken) {
        try {
            if (!$twitterUser || !isset($twitterUser->id_str)) {
                throw new Exception('Invalid Twitter user data');
            }
            
            $twitterId = $twitterUser->id_str;
            $now = new \MongoDB\BSON\UTCDateTime();
            
            // Check if user already exists
            $existingUser = $this->usersCollection->findOne(['twitter_id' => $twitterId]);
            
            $userData = [
                'name' => $twitterUser->name ?? '',
                'twitter_handle' => $twitterUser->screen_name ?? '',
                'twitter_id' => $twitterId,
                'twitter_profile_image_url' => $twitterUser->profile_image_url_https ?? ($twitterUser->profile_image_url ?? ''),
                'twitter_followers_count' => $twitterUser->followers_count ?? 0,
                'twitter_friends_count' => $twitterUser->friends_count ?? 0,
                'twitter_statuses_count' => $twitterUser->statuses_count ?? 0,
                'location' => $twitterUser->location ?? '',
                'description' => $twitterUser->description ?? '',
                'verified' => $twitterUser->verified ?? false,
                'email' => 'tw_' . $twitterId . '@twitter.local', // Synthetic email
                'is_verified' => true,
                'status' => 'active',
                'auth_provider' => 'twitter',
                'updated_at' => $now
            ];
            
            if ($existingUser) {
                // Update existing user
                $result = $this->usersCollection->updateOne(
                    ['_id' => $existingUser->_id],
                    ['$set' => $userData]
                );
                
                $userId = $existingUser->_id;
                error_log('[TWITTER_DB] Updated existing user: ' . $twitterId);
            } else {
                // Create new user
                $userData['created_at'] = $now;
                $result = $this->usersCollection->insertOne($userData);
                $userId = $result->getInsertedId();
                error_log('[TWITTER_DB] Created new user: ' . $twitterId);
            }
            
            // Save/update access token
            $this->saveAccessToken($userId, $accessToken);
            
            return $this->usersCollection->findOne(['_id' => $userId]);
            
        } catch (Exception $e) {
            error_log('[TWITTER_DB] Error saving user: ' . $e->getMessage());
            throw new Exception('Failed to save Twitter user: ' . $e->getMessage());
        }
    }
    
    /**
     * Save Twitter access token for user
     */
    public function saveAccessToken($userId, $accessToken) {
        try {
            $now = new \MongoDB\BSON\UTCDateTime();
            $expiresAt = isset($accessToken['created_at']) 
                ? new \MongoDB\BSON\UTCDateTime(($accessToken['created_at'] + 86400) * 1000) // 24 hours
                : new \MongoDB\BSON\UTCDateTime((time() + 86400) * 1000);
            
            $tokenData = [
                'user_id' => $userId,
                'oauth_token' => $accessToken['oauth_token'] ?? '',
                'oauth_token_secret' => $accessToken['oauth_token_secret'] ?? '',
                'user_id_twitter' => $accessToken['user_id'] ?? '',
                'screen_name' => $accessToken['screen_name'] ?? '',
                'created_at' => $now,
                'expires_at' => $expiresAt,
                'is_active' => true
            ];
            
            // Remove old tokens for this user
            $this->tokensCollection->updateMany(
                ['user_id' => $userId],
                ['$set' => ['is_active' => false]]
            );
            
            // Insert new token
            $this->tokensCollection->insertOne($tokenData);
            
            error_log('[TWITTER_DB] Saved access token for user: ' . $userId);
            
        } catch (Exception $e) {
            error_log('[TWITTER_DB] Error saving token: ' . $e->getMessage());
            throw new Exception('Failed to save access token: ' . $e->getMessage());
        }
    }
    
    /**
     * Get active Twitter token for user
     */
    public function getActiveToken($userId) {
        try {
            $token = $this->tokensCollection->findOne([
                'user_id' => $userId,
                'is_active' => true,
                'expires_at' => ['$gt' => new \MongoDB\BSON\UTCDateTime()]
            ]);
            
            return $token;
            
        } catch (Exception $e) {
            error_log('[TWITTER_DB] Error getting token: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Find user by Twitter ID
     */
    public function findUserByTwitterId($twitterId) {
        try {
            error_log('[TWITTER_DB] Searching for Twitter ID: ' . $twitterId);
            $user = $this->usersCollection->findOne(['twitter_id' => $twitterId]);
            
            if ($user) {
                error_log('[TWITTER_DB] User found: ' . ($user->name ?? 'unnamed'));
            } else {
                error_log('[TWITTER_DB] User not found for Twitter ID: ' . $twitterId);
                
                // Debug: Check if there are any users with similar IDs
                $similarUsers = $this->usersCollection->find([
                    'twitter_id' => ['$regex' => '.*' . substr($twitterId, -5) . '.*']
                ])->toArray();
                
                if (!empty($similarUsers)) {
                    error_log('[TWITTER_DB] Found similar Twitter IDs: ' . 
                        implode(', ', array_map(function($u) { return $u->twitter_id ?? 'null'; }, $similarUsers)));
                }
            }
            
            return $user;
        } catch (Exception $e) {
            error_log('[TWITTER_DB] Error finding user: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Revoke/deactivate Twitter tokens for user
     */
    public function revokeTokens($userId) {
        try {
            $this->tokensCollection->updateMany(
                ['user_id' => $userId],
                ['$set' => ['is_active' => false, 'revoked_at' => new \MongoDB\BSON\UTCDateTime()]]
            );
            
            error_log('[TWITTER_DB] Revoked tokens for user: ' . $userId);
            return true;
            
        } catch (Exception $e) {
            error_log('[TWITTER_DB] Error revoking tokens: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Clean up expired tokens
     */
    public function cleanupExpiredTokens() {
        try {
            $result = $this->tokensCollection->deleteMany([
                'expires_at' => ['$lt' => new \MongoDB\BSON\UTCDateTime()],
                'is_active' => false
            ]);
            
            error_log('[TWITTER_DB] Cleaned up ' . $result->getDeletedCount() . ' expired tokens');
            return $result->getDeletedCount();
            
        } catch (Exception $e) {
            error_log('[TWITTER_DB] Error cleaning tokens: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get Twitter user statistics
     */
    public function getTwitterUserStats() {
        try {
            $totalUsers = $this->usersCollection->countDocuments(['auth_provider' => 'twitter']);
            $activeTokens = $this->tokensCollection->countDocuments([
                'is_active' => true,
                'expires_at' => ['$gt' => new \MongoDB\BSON\UTCDateTime()]
            ]);
            
            return [
                'total_twitter_users' => $totalUsers,
                'active_tokens' => $activeTokens
            ];
            
        } catch (Exception $e) {
            error_log('[TWITTER_DB] Error getting stats: ' . $e->getMessage());
            return ['total_twitter_users' => 0, 'active_tokens' => 0];
        }
    }
}
