<?php
session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');

try {
    // Check if user is authenticated
    if (!isset($_SESSION['is_authenticated']) || $_SESSION['is_authenticated'] !== true) {
        echo json_encode([
            'authenticated' => false,
            'message' => 'Not authenticated'
        ]);
        exit;
    }

    $authProvider = $_SESSION['auth_provider'] ?? 'email';
    $response = [
        'authenticated' => true,
        'provider' => $authProvider
    ];

    if ($authProvider === 'twitter') {
        // Twitter authentication
        require_once __DIR__ . '/../twitter/TwitterDbService.php';
        
        $twitterUserId = $_SESSION['twitter_user_id'] ?? null;
        $screenName = $_SESSION['twitter_screen_name'] ?? null;
        
        if ($twitterUserId) {
            try {
                $twitterDb = new TwitterDbService();
                $user = $twitterDb->findUserByTwitterId($twitterUserId);
                
                if ($user) {
                    $response['user'] = [
                        'id' => (string)$user->_id,
                        'name' => $user->name ?? $screenName ?? 'User',
                        'email' => $user->email ?? '',
                        'twitter_handle' => $user->twitter_handle ?? $screenName,
                        'twitter_profile_image_url' => $user->profile_image_url_https ?? ''
                    ];
                    
                    $response['twitter'] = [
                        'screen_name' => $screenName,
                        'profile_image_url' => $user->profile_image_url_https ?? ''
                    ];
                } else {
                    // User not found in database, use session data
                    $response['user'] = [
                        'name' => $screenName ?? 'User',
                        'twitter_handle' => $screenName
                    ];
                    $response['twitter'] = [
                        'screen_name' => $screenName
                    ];
                }
            } catch (Exception $e) {
                error_log('[USER_ME] Twitter user lookup error: ' . $e->getMessage());
                // Fallback to session data
                $response['user'] = [
                    'name' => $screenName ?? 'User',
                    'twitter_handle' => $screenName
                ];
                $response['twitter'] = [
                    'screen_name' => $screenName
                ];
            }
        }
        
    } else {
        // Email authentication
        require_once __DIR__ . '/../config/db.php';
        
        $userId = $_SESSION['user_id'] ?? null;
        $userEmail = $_SESSION['user_email'] ?? null;
        $userName = $_SESSION['user_name'] ?? null;
        
        error_log('[USER_ME] Email auth check - userId: ' . ($userId ?? 'null') . ', email: ' . ($userEmail ?? 'null') . ', name: ' . ($userName ?? 'null'));
        
        if ($userId) {
            try {
                $db = Database::getDB();
                $user = $db->users->findOne(['_id' => new MongoDB\BSON\ObjectId($userId)]);
                
                if ($user) {
                    $displayName = $user->name ?? $userName ?? explode('@', $user->email)[0] ?? 'User';
                    $response['user'] = [
                        'id' => (string)$user->_id,
                        'name' => $displayName,
                        'email' => $user->email,
                        'avatar' => $user->avatar ?? null
                    ];
                    error_log('[USER_ME] Found user in DB: ' . $displayName);
                } else {
                    error_log('[USER_ME] User not found in DB for ID: ' . $userId);
                }
            } catch (Exception $e) {
                error_log('[USER_ME] Email user lookup error: ' . $e->getMessage());
            }
        }
        
        // Fallback to session data if no database user found
        if (!isset($response['user'])) {
            $fallbackName = $userName ?? ($userEmail ? explode('@', $userEmail)[0] : 'User');
            $response['user'] = [
                'name' => $fallbackName,
                'email' => $userEmail ?? ''
            ];
            error_log('[USER_ME] Using fallback user data: ' . $fallbackName);
        }
    }

    echo json_encode($response);

} catch (Exception $e) {
    error_log('[USER_ME] General error: ' . $e->getMessage());
    echo json_encode([
        'authenticated' => false,
        'error' => 'Server error'
    ]);
}
?>
