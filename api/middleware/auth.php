<?php
require_once __DIR__ . '/../config/db.php';

function authenticate() {
    $headers = getallheaders();
    $token = '';
    
    // Get token from Authorization header
    if (isset($headers['Authorization'])) {
        $authHeader = $headers['Authorization'];
        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $token = $matches[1];
        }
    }
    
    // Fallback to token in query string
    if (empty($token) && isset($_GET['token'])) {
        $token = $_GET['token'];
    }
    
    // If no JWT token, check for session-based authentication (Twitter users)
    if (empty($token)) {
        // Start session without outputting any warnings
        @session_start();
        if (isset($_SESSION['is_authenticated']) && $_SESSION['is_authenticated'] === true) {
            // For Twitter users, we need to find their MongoDB user record
            if ($_SESSION['auth_provider'] === 'twitter' && isset($_SESSION['twitter_user_id'])) {
                try {
                    $users = Database::getCollection('users');
                    $user = $users->findOne(['twitter_id' => $_SESSION['twitter_user_id']]);
                    if ($user) {
                        return [
                            'id' => (string)$user->_id,
                            'email' => $user->email ?? ($_SESSION['user_name'] . '@twitter.com'),
                            'name' => $user->name ?? $_SESSION['user_name'],
                            'auth_provider' => 'twitter'
                        ];
                    }
                } catch (Exception $e) {
                    error_log('[AUTH] Twitter user lookup error: ' . $e->getMessage());
                }
            }
            
            // Fallback to session data
            return [
                'id' => $_SESSION['user_id'] ?? '',
                'email' => $_SESSION['user_email'] ?? '',
                'name' => $_SESSION['user_name'] ?? '',
                'auth_provider' => $_SESSION['auth_provider'] ?? 'session'
            ];
        }
        
        throw new Exception('No authentication provided', 401);
    }
    
    // Try JWT token first
    try {
        require_once __DIR__ . '/../../vendor/autoload.php';
        $secret = $_ENV['JWT_SECRET'] ?? 'your-secret-key';
        $decoded = \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key($secret, 'HS256'));
        
        if (isset($decoded->data)) {
            $data = (array)$decoded->data;
            $userId = $data['id'] ?? null;
            
            if ($userId) {
                $users = Database::getCollection('users');
                $user = $users->findOne(['_id' => new MongoDB\BSON\ObjectId($userId)]);
                
                if ($user) {
                    return [
                        'id' => (string)$user->_id,
                        'email' => $user->email,
                        'name' => $user->name ?? '',
                        'role' => $user->role ?? 'user',
                        'auth_provider' => 'jwt'
                    ];
                }
            }
        }
    } catch (Exception $e) {
        // JWT decode failed, try session-based token
        error_log('[AUTH] JWT decode failed: ' . $e->getMessage());
    }
    
    // Try session-based token
    $sessions = Database::getCollection('sessions');
    $session = $sessions->findOne([
        'token' => $token,
        'expiresAt' => ['$gt' => new MongoDB\BSON\UTCDateTime()]
    ]);
    
    if ($session) {
        $users = Database::getCollection('users');
        $user = $users->findOne(['_id' => $session->userId]);
        
        if ($user) {
            return [
                'id' => (string)$user->_id,
                'email' => $user->email,
                'name' => $user->name ?? '',
                'role' => $user->role ?? 'user',
                'auth_provider' => 'session'
            ];
        }
    }
    
    // If all authentication methods fail
    throw new Exception('Invalid or expired token', 401);
}
?>
