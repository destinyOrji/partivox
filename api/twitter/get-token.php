<?php
session_start();
header('Content-Type: application/json');
// Handle CORS for multiple origins
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = ['http://localhost:8000', 'http://127.0.0.1:8000'];
if (in_array($origin, $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../../vendor/autoload.php';

function generateJWTToken($userData) {
    $secret = $_ENV['JWT_SECRET'] ?? 'your-secret-key';
    $tokenId = base64_encode(random_bytes(32));
    $issuedAt = time();
    $expire = $issuedAt + (24 * 3600); // Token expires in 24 hours
    
    $data = [
        'iat' => $issuedAt,
        'jti' => $tokenId,
        'iss' => 'partivox',
        'nbf' => $issuedAt,
        'exp' => $expire,
        'data' => $userData
    ];
    
    return \Firebase\JWT\JWT::encode($data, $secret, 'HS256');
}

try {
    // Check if user is authenticated via Twitter session
    if (!isset($_SESSION['is_authenticated']) || $_SESSION['is_authenticated'] !== true) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
        exit;
    }

    if ($_SESSION['auth_provider'] === 'twitter' && isset($_SESSION['twitter_user_id'])) {
        // Find user in MongoDB
        $users = Database::getCollection('users');
        $user = $users->findOne(['twitter_id' => $_SESSION['twitter_user_id']]);
        
        if (!$user) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'User not found in database']);
            exit;
        }

        // Generate JWT token for Twitter user
        $token = generateJWTToken([
            'id' => (string)$user->_id,
            'email' => $user->email ?? ($_SESSION['user_name'] . '@twitter.com'),
            'name' => $user->name ?? $_SESSION['user_name'],
            'role' => $user->role ?? 'user',
            'auth_provider' => 'twitter'
        ]);

        // Store token in sessions collection for validation
        $sessions = Database::getCollection('sessions');
        $sessions->insertOne([
            'token' => $token,
            'userId' => $user->_id,
            'expiresAt' => new MongoDB\BSON\UTCDateTime((time() + (24 * 3600)) * 1000),
            'createdAt' => new MongoDB\BSON\UTCDateTime()
        ]);

        echo json_encode([
            'status' => 'success',
            'token' => $token,
            'user' => [
                'id' => (string)$user->_id,
                'email' => $user->email ?? ($_SESSION['user_name'] . '@twitter.com'),
                'name' => $user->name ?? $_SESSION['user_name'],
                'auth_provider' => 'twitter'
            ]
        ]);
    } else {
        // For email users, check if they already have a token
        if (isset($_SESSION['user_id'])) {
            $users = Database::getCollection('users');
            $user = $users->findOne(['_id' => new MongoDB\BSON\ObjectId($_SESSION['user_id'])]);
            
            if ($user) {
                $token = generateJWTToken([
                    'id' => (string)$user->_id,
                    'email' => $user->email,
                    'name' => $user->name ?? '',
                    'role' => $user->role ?? 'user',
                    'auth_provider' => 'email'
                ]);

                // Store token in sessions collection
                $sessions = Database::getCollection('sessions');
                $sessions->insertOne([
                    'token' => $token,
                    'userId' => $user->_id,
                    'expiresAt' => new MongoDB\BSON\UTCDateTime((time() + (24 * 3600)) * 1000),
                    'createdAt' => new MongoDB\BSON\UTCDateTime()
                ]);

                echo json_encode([
                    'status' => 'success',
                    'token' => $token,
                    'user' => [
                        'id' => (string)$user->_id,
                        'email' => $user->email,
                        'name' => $user->name ?? '',
                        'auth_provider' => 'email'
                    ]
                ]);
            } else {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'User not found']);
            }
        } else {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Invalid session']);
        }
    }
} catch (Exception $e) {
    error_log('[GET-TOKEN] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
