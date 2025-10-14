<?php
session_start();

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/TwitterDbService.php';
require_once __DIR__ . '/../config/db.php';

echo "<h2>Twitter Authentication Debug</h2>";

// Check session data
echo "<h3>Session Data:</h3>";
echo "<pre>";
echo "Session ID: " . session_id() . "\n";
echo "Twitter Access Token Present: " . (isset($_SESSION['twitter_access_token']) ? 'YES' : 'NO') . "\n";

if (isset($_SESSION['twitter_access_token'])) {
    echo "Access Token Data:\n";
    foreach ($_SESSION['twitter_access_token'] as $key => $value) {
        if (in_array($key, ['oauth_token', 'oauth_token_secret'])) {
            echo "  $key: " . substr($value, 0, 10) . "...\n";
        } else {
            echo "  $key: $value\n";
        }
    }
}

echo "Twitter User ID in Session: " . ($_SESSION['twitter_user_id'] ?? 'NOT SET') . "\n";
echo "Screen Name in Session: " . ($_SESSION['twitter_screen_name'] ?? 'NOT SET') . "\n";
echo "Auth Provider: " . ($_SESSION['auth_provider'] ?? 'NOT SET') . "\n";
echo "Is Authenticated: " . ($_SESSION['is_authenticated'] ?? 'NOT SET') . "\n";
echo "</pre>";

// Check database connection
echo "<h3>Database Connection:</h3>";
try {
    $db = Database::getDB();
    echo "<span style='color:green'>✓ Database connection successful</span><br>";
    echo "Database name: " . $db->getDatabaseName() . "<br>";
} catch (Exception $e) {
    echo "<span style='color:red'>✗ Database connection failed: " . $e->getMessage() . "</span><br>";
}

// Check TwitterDbService
echo "<h3>TwitterDbService Test:</h3>";
try {
    $twitterDb = new TwitterDbService();
    echo "<span style='color:green'>✓ TwitterDbService initialized successfully</span><br>";
    
    // Get stats
    $stats = $twitterDb->getTwitterUserStats();
    echo "Total Twitter users in DB: " . $stats['total_twitter_users'] . "<br>";
    echo "Active tokens: " . $stats['active_tokens'] . "<br>";
    
} catch (Exception $e) {
    echo "<span style='color:red'>✗ TwitterDbService error: " . $e->getMessage() . "</span><br>";
}

// Test user lookup if we have a Twitter user ID
if (isset($_SESSION['twitter_access_token']['user_id'])) {
    echo "<h3>User Lookup Test:</h3>";
    $twitterUserId = $_SESSION['twitter_access_token']['user_id'];
    echo "Looking for Twitter User ID: " . $twitterUserId . "<br>";
    
    try {
        $twitterDb = new TwitterDbService();
        $user = $twitterDb->findUserByTwitterId((string)$twitterUserId);
        
        if ($user) {
            echo "<span style='color:green'>✓ User found in database</span><br>";
            echo "User ID: " . $user->_id . "<br>";
            echo "Name: " . ($user->name ?? 'N/A') . "<br>";
            echo "Twitter Handle: " . ($user->twitter_handle ?? 'N/A') . "<br>";
            echo "Created: " . ($user->created_at ?? 'N/A') . "<br>";
        } else {
            echo "<span style='color:red'>✗ User NOT found in database</span><br>";
            
            // Check if there are any Twitter users at all
            echo "<h4>All Twitter Users in DB:</h4>";
            $collection = Database::getCollection('users');
            $twitterUsers = $collection->find(['auth_provider' => 'twitter'])->toArray();
            
            if (empty($twitterUsers)) {
                echo "No Twitter users found in database.<br>";
            } else {
                echo "Found " . count($twitterUsers) . " Twitter users:<br>";
                foreach ($twitterUsers as $user) {
                    echo "- ID: " . ($user->twitter_id ?? 'N/A') . 
                         ", Handle: " . ($user->twitter_handle ?? 'N/A') . 
                         ", Name: " . ($user->name ?? 'N/A') . "<br>";
                }
            }
        }
        
    } catch (Exception $e) {
        echo "<span style='color:red'>✗ User lookup error: " . $e->getMessage() . "</span><br>";
    }
}

// Test database collections
echo "<h3>Database Collections:</h3>";
try {
    $db = Database::getDB();
    $collections = $db->listCollections();
    echo "Available collections:<br>";
    foreach ($collections as $collection) {
        $name = $collection->getName();
        $count = $db->$name->countDocuments();
        echo "- $name: $count documents<br>";
    }
} catch (Exception $e) {
    echo "<span style='color:red'>✗ Error listing collections: " . $e->getMessage() . "</span><br>";
}

echo "<br><a href='/api/twitter/twitter-auth.php'>← Back to Twitter Auth</a>";
?>
