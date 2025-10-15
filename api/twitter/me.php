<?php
// Simple me.php endpoint for user authentication check
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

    // Check if user is authenticated
    if (isset($_SESSION['is_authenticated']) && $_SESSION['is_authenticated'] === true) {
        $user = [
            'authenticated' => true,
            'provider' => $_SESSION['auth_provider'] ?? 'session',
            'user' => [
                'name' => $_SESSION['user_name'] ?? 'User',
                'email' => $_SESSION['user_email'] ?? '',
                'twitter_id' => $_SESSION['twitter_user_id'] ?? null,
                'twitter_profile_image_url' => $_SESSION['twitter_profile_image_url'] ?? null
            ],
            'twitter' => [
                'screen_name' => $_SESSION['twitter_screen_name'] ?? '',
                'profile_image_url' => $_SESSION['twitter_profile_image_url'] ?? null
            ]
        ];
        
        echo json_encode($user);
    } else {
    echo json_encode([
            'authenticated' => false,
            'message' => 'Not authenticated'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
echo json_encode([
        'status' => 'error',
        'message' => 'Authentication check failed: ' . $e->getMessage()
    ]);
}
?>