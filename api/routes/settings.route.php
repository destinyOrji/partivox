<?php
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../controllers/SettingsController.php';

function settingsRoutes($method, $action = '', $data = []) {
    error_log('[SettingsRoute] Called with method: ' . $method . ', action: ' . $action);
    
    try {
        $user = authenticate();
        error_log('[SettingsRoute] User authenticated: ' . json_encode($user));
        $controller = new SettingsController($user);
        $action = trim($action, '/');

        switch ($action) {
            case 'get':
                if ($method !== 'GET') throw new Exception('Method not allowed', 405);
                return $controller->get();

            case 'profile':
                if ($method === 'GET') {
                    return $controller->getProfile();
                } elseif ($method === 'PUT') {
                    return $controller->updateProfile($data);
                }
                break;
                
            case 'notifications':
                if ($method === 'GET') {
                    return $controller->getNotificationSettings();
                } elseif ($method === 'PUT') {
                    return $controller->updateNotificationSettings($data);
                }
                break;
                
            case 'privacy':
                if ($method === 'GET') {
                    return $controller->getPrivacySettings();
                } elseif ($method === 'PUT') {
                    return $controller->updatePrivacySettings($data);
                }
                break;
                
            case 'connected-accounts':
                if ($method === 'GET') {
                    return $controller->getConnectedAccounts();
                }
                break;
                
            case 'disconnect':
                if ($method === 'POST') {
                    $accountType = $data['account_type'] ?? '';
                    if (empty($accountType)) {
                        throw new Exception('Account type is required', 400);
                    }
                    return $controller->disconnectAccount($accountType);
                }
                break;
                
            case 'connect-wallet':
                if ($method === 'POST') {
                    return $controller->connectWallet($data);
                }
                break;

            case 'upload-avatar':
                if ($method === 'POST') {
                    return $controller->uploadAvatar();
                } else {
                    throw new Exception('Method not allowed', 405);
                }
                break;
                
            case 'upload-profile-picture':
                if ($method === 'POST') {
                    error_log('[SettingsRoute] Profile picture upload requested');
                    error_log('[SettingsRoute] FILES: ' . json_encode($_FILES));
                    if (!isset($_FILES['profile_picture'])) {
                        error_log('[SettingsRoute] No profile_picture in FILES');
                        throw new Exception('No profile picture file uploaded', 400);
                    }
                    return $controller->uploadProfilePicture($_FILES['profile_picture']);
                } else {
                    throw new Exception('Method not allowed', 405);
                }
                break;
                
            case 'profile-picture':
                if ($method === 'GET') {
                    return $controller->getProfilePicture();
                } else {
                    throw new Exception('Method not allowed', 405);
                }
                break;

            case 'test-upload':
                if ($method === 'POST') {
                    return [
                        'status' => 'success',
                        'message' => 'Test endpoint working',
                        'data' => [
                            'user' => $user,
                            'files' => $_FILES,
                            'post' => $_POST,
                            'method' => $method,
                            'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set',
                            'upload_dir_exists' => is_dir(__DIR__ . '/../../uploads/profile_pictures/'),
                            'upload_dir_writable' => is_writable(__DIR__ . '/../../uploads/profile_pictures/')
                        ]
                    ];
                }
                break;
                
            case 'debug-auth':
                if ($method === 'GET') {
                    return [
                        'status' => 'success',
                        'message' => 'Authentication working',
                        'data' => [
                            'user' => $user,
                            'headers' => getallheaders(),
                            'session' => $_SESSION ?? 'No session'
                        ]
                    ];
                }
                break;
                
            case 'test-api':
                if ($method === 'GET') {
                    return [
                        'status' => 'success',
                        'message' => 'Settings API is working',
                        'data' => [
                            'timestamp' => date('Y-m-d H:i:s'),
                            'php_version' => PHP_VERSION,
                            'user_authenticated' => !empty($user),
                            'user_id' => $user['id'] ?? null
                        ]
                    ];
                }
                break;

            case 'update':
                if ($method !== 'PUT') throw new Exception('Method not allowed', 405);
                return $controller->update($data);

            default:
                throw new Exception('Endpoint not found', 404);
        }
    } catch (Exception $e) {
        error_log('[SettingsRoute] Exception: ' . $e->getMessage());
        error_log('[SettingsRoute] Stack trace: ' . $e->getTraceAsString());
        http_response_code($e->getCode() ?: 500);
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
}
