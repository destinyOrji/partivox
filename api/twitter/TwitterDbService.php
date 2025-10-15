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
}
?>