<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

try {
    // Authenticate user
    $user = authenticate();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
        exit();
    }

    // Get request body
    $input = json_decode(file_get_contents('php://input'), true);
    $walletAddress = $input['wallet_address'] ?? '';

    // Basic validation
    if (empty($walletAddress)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Wallet address is required']);
        exit();
    }

    // Validate Ethereum address format
    if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $walletAddress)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid wallet address format']);
        exit();
    }

    // Update user record to remove wallet connection
    $db = Database::getDB();
    $updateData = [
        'wallet_address' => null,
        'wallet_connected' => false,
        'wallet_disconnected_at' => new MongoDB\BSON\UTCDateTime(),
        'updated_at' => new MongoDB\BSON\UTCDateTime()
    ];
    
    $result = $db->users->updateOne(
        ['_id' => new MongoDB\BSON\ObjectId($user['id'])],
        ['$set' => $updateData]
    );

    // Log the disconnection event
    error_log("Wallet disconnected for user {$user['id']}: {$walletAddress}");

    echo json_encode([
        'status' => 'success',
        'message' => 'Wallet disconnected successfully',
        'data' => [
            'wallet_address' => $walletAddress,
            'disconnected_at' => date('Y-m-d H:i:s')
        ]
    ]);

} catch (Exception $e) {
    error_log('Wallet disconnect error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to disconnect wallet']);
}
?>
