<?php
require_once __DIR__ . '/../config/db.php';

class TwitterApiHelper {
    private $db;
    
    public function __construct() {
        $this->db = Database::getDB();
    }
    
    public function getUserInfo($twitterId) {
        try {
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
        
        return null;
        } catch (Exception $e) {
            error_log('[TwitterApiHelper] Error getting user info: ' . $e->getMessage());
            return null;
        }
    }
}
?>