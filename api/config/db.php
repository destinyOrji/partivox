<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/FileBasedCollection.php';

// Try to load .env from both possible locations
$rootEnvPath = __DIR__ . '/../../';
$apiEnvPath  = __DIR__ . '/../';

if (file_exists($rootEnvPath . '.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable($rootEnvPath);
    $dotenv->load();
} elseif (file_exists($apiEnvPath . '.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable($apiEnvPath);
    $dotenv->load();
} else {
    error_log("⚠️ No .env file found. Using default values.");
}

// Check if the class is already defined
if (!class_exists('Database')) {
    class Database {
        private static $instance = null;
        private $client;
        private $db;
        private $collections = [];

        private function __construct() {
            try {
                $host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?? 'localhost';
                $port = $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?? 27017;
                $dbName = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?? 'partivox';
                
                // For Render free tier, we'll use a simple file-based approach
                // since MongoDB Atlas requires configuration
                if (getenv('RENDER') === 'true' || getenv('RENDER_EXTERNAL_HOSTNAME')) {
                    // Running on Render - use file-based storage for now
                    $this->db = new stdClass();
                    $this->db->users = new FileBasedCollection('users');
                    $this->db->campaigns = new FileBasedCollection('campaigns');
                    $this->db->transactions = new FileBasedCollection('transactions');
                    $this->db->reports = new FileBasedCollection('reports');
                    $this->db->settings = new FileBasedCollection('settings');
                    $this->db->sessions = new FileBasedCollection('sessions');
                    $this->db->wallets = new FileBasedCollection('wallets');
                    $this->db->activities = new FileBasedCollection('activities');
                    $this->db->twitter_tokens = new FileBasedCollection('twitter_tokens');
                    return;
                }
                
                // Create MongoDB connection string
                $connectionString = "mongodb://{$host}:{$port}";
                
                // Create a new MongoDB client
                $this->client = new MongoDB\Client($connectionString);
                
                // Select the database
                $this->db = $this->client->$dbName;
                
                // Initialize collections
                $this->initializeCollections();
                
                // Create indexes if they don't exist
                $this->createIndexes();
                
            } catch (Exception $e) {
                error_log("Database connection failed: " . $e->getMessage());
                // Fallback to file-based storage
                $this->db = new stdClass();
                $this->db->users = new FileBasedCollection('users');
                $this->db->campaigns = new FileBasedCollection('campaigns');
                $this->db->transactions = new FileBasedCollection('transactions');
                $this->db->reports = new FileBasedCollection('reports');
                $this->db->settings = new FileBasedCollection('settings');
                $this->db->sessions = new FileBasedCollection('sessions');
                $this->db->wallets = new FileBasedCollection('wallets');
                $this->db->activities = new FileBasedCollection('activities');
                $this->db->twitter_tokens = new FileBasedCollection('twitter_tokens');
            }
        }

        public static function getInstance() {
            if (!self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        public static function getDB() {
            return self::getInstance()->db;
        }
        
        public static function getCollection($name) {
            $instance = self::getInstance();
            if (!isset($instance->collections[$name])) {
                $instance->collections[$name] = $instance->db->$name;
            }
            return $instance->collections[$name];
        }

        private function initializeCollections() {
            // Define required collections
            $collections = [
                'users',
                'campaigns',
                'transactions',
                'reports',
                'settings',
                'sessions',  // For JWT token blacklisting if needed
                'wallets',
                'activities',
                'twitter_tokens'  // For Twitter OAuth tokens
            ];
            
            foreach ($collections as $collection) {
                $this->collections[$collection] = $this->db->$collection;
            }
        }

        private function createIndexes() {
            try {
                // Users collection indexes
                $this->collections['users']->createIndex(['email' => 1], ['unique' => true]);
                $this->collections['users']->createIndex(['google_id' => 1], ['unique' => true, 'sparse' => true]);
                $this->collections['users']->createIndex(['twitter_id' => 1], ['unique' => true, 'sparse' => true]);
                
                // Campaigns collection indexes
                $this->collections['campaigns']->createIndex(['status' => 1]);
                $this->collections['campaigns']->createIndex(['created_at' => -1]);
                
                // Transactions collection indexes
                $this->collections['transactions']->createIndex(['user_id' => 1]);
                $this->collections['transactions']->createIndex(['status' => 1]);
                $this->collections['transactions']->createIndex(['transaction_date' => -1]);
                $this->collections['transactions']->createIndex(['created_at' => -1]);
                
                // Wallets collection indexes
                $this->collections['wallets']->createIndex(['user_id' => 1], ['unique' => true]);
                
                // Activities collection indexes
                $this->collections['activities']->createIndex(['user_id' => 1]);
                $this->collections['activities']->createIndex(['created_at' => -1]);
                
                // Reports collection indexes
                $this->collections['reports']->createIndex(['status' => 1]);
                $this->collections['reports']->createIndex(['reported_at' => -1]);
                
                // Sessions collection for JWT blacklist
                $this->collections['sessions']->createIndex(['exp' => 1], ['expireAfterSeconds' => 0]);
                
                // Twitter tokens collection indexes
                $this->collections['twitter_tokens']->createIndex(['user_id' => 1]);
                $this->collections['twitter_tokens']->createIndex(['is_active' => 1]);
                $this->collections['twitter_tokens']->createIndex(['expires_at' => 1], ['expireAfterSeconds' => 0]);
                
            } catch (Exception $e) {
                error_log("Error creating indexes: " . $e->getMessage());
                // Don't throw exception for index creation failures
            }
        }
        
        /**
         * Blacklist a JWT token
         */
        public static function blacklistToken($token, $expiration) {
            $collection = self::getCollection('sessions');
            $collection->insertOne([
                'token' => $token,
                'exp' => $expiration // Store as integer timestamp
            ]);
        }
        
        /**
         * Check if a token is blacklisted
         */
        public static function isTokenBlacklisted($token) {
            $collection = self::getCollection('sessions');
            $result = $collection->findOne(['token' => $token]);
            return $result !== null;
        }
        
        // Prevent cloning of the instance
        private function __clone() {}

        // Prevent unserializing of the instance
        public function __wakeup() {
            throw new Exception("Cannot unserialize a singleton.");
        }
    }
}

// Initialize the database connection when this file is included
try {
    Database::getInstance();
} catch (Exception $e) {
    error_log("Database initialization failed: " . $e->getMessage());
    // Don't throw exception here to prevent cascading failures
}
