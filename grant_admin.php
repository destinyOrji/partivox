<?php
/**
 * Utility script to grant admin role to a user
 * Usage: php grant_admin.php <user_email>
 */

require_once __DIR__ . '/api/config/db.php';
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

if ($argc < 2) {
    echo "Usage: php grant_admin.php <user_email>\n";
    exit(1);
}

$email = $argv[1];

try {
    $db = Database::getDB();
    $users = $db->users;
    
    // Find user by email
    $user = $users->findOne(['email' => $email]);
    
    if (!$user) {
        echo "Error: User with email '$email' not found.\n";
        exit(1);
    }
    
    // Update user role to admin
    $result = $users->updateOne(
        ['_id' => $user->_id],
        ['$set' => [
            'role' => 'admin',
            'admin_granted_at' => new UTCDateTime()
        ]]
    );
    
    if ($result->getModifiedCount() > 0) {
        echo "Success: Admin role granted to user '$email' (ID: {$user->_id})\n";
        echo "User can now access admin dashboard with full privileges.\n";
    } else {
        echo "Error: Failed to update user role.\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
