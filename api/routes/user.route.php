<?php
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../controllers/UserController.php';

function userRoutes($method, $action = '', $data = []) {
    // Debug logging
    error_log("[USER ROUTE] Method: $method, Action: '$action'");
    
    try {
        $user = authenticate();
        error_log("[USER ROUTE] Authentication successful for user: " . json_encode($user));
    } catch (Exception $e) {
        error_log("[USER ROUTE] Authentication failed: " . $e->getMessage());
        http_response_code(401);
        return ['status' => 'error', 'message' => 'Authentication required: ' . $e->getMessage()];
    }
    
    $controller = new UserController($user);

    try {
        $action = trim($action, '/');

        switch ($action) {
            case 'stats':
                if ($method !== 'GET') throw new Exception('Method not allowed', 405);
                return $controller->getUserStats();

            case 'profile':
                if ($method === 'GET') {
                    return $controller->getProfile();
                } elseif ($method === 'PUT') {
                    return $controller->updateProfile($data);
                } else {
                    throw new Exception('Method not allowed', 405);
                }

            case '':
                // Default endpoint - return user stats for dashboard
                if ($method !== 'GET') throw new Exception('Method not allowed', 405);
                return $controller->getUserStats();

            default:
                throw new Exception('Endpoint not found', 404);
        }
    } catch (Exception $e) {
        http_response_code($e->getCode() ?: 500);
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
}
