<?php
/**
 * Twitter Token Maintenance Script
 * Run this periodically to clean up expired tokens and maintain database health
 */

require_once __DIR__ . '/TwitterDbService.php';

class TwitterMaintenance {
    private $twitterDb;
    
    public function __construct() {
        $this->twitterDb = new TwitterDbService();
    }
    
    /**
     * Run all maintenance tasks
     */
    public function runMaintenance() {
        echo "Starting Twitter maintenance tasks...\n";
        
        $results = [
            'expired_tokens_cleaned' => $this->cleanupExpiredTokens(),
            'stats' => $this->getStats(),
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        echo "Maintenance completed.\n";
        echo "Results: " . json_encode($results, JSON_PRETTY_PRINT) . "\n";
        
        return $results;
    }
    
    /**
     * Clean up expired tokens
     */
    private function cleanupExpiredTokens() {
        try {
            $deletedCount = $this->twitterDb->cleanupExpiredTokens();
            echo "Cleaned up {$deletedCount} expired tokens.\n";
            return $deletedCount;
        } catch (Exception $e) {
            echo "Error cleaning up tokens: " . $e->getMessage() . "\n";
            return 0;
        }
    }
    
    /**
     * Get Twitter user statistics
     */
    private function getStats() {
        try {
            $stats = $this->twitterDb->getTwitterUserStats();
            echo "Twitter Users: {$stats['total_twitter_users']}, Active Tokens: {$stats['active_tokens']}\n";
            return $stats;
        } catch (Exception $e) {
            echo "Error getting stats: " . $e->getMessage() . "\n";
            return ['total_twitter_users' => 0, 'active_tokens' => 0];
        }
    }
}

// Run maintenance if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $maintenance = new TwitterMaintenance();
    $maintenance->runMaintenance();
}
?>
