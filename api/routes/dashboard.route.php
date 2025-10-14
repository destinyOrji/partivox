<?php
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../controllers/DashboardController.php';

function dashboardRoutes($method, $action = '', $data = []) {
    $user = authenticate();
    $controller = new DashboardController($user);

    try {
        $action = trim($action, '/');

        switch ($action) {
            case 'overview':
                if ($method !== 'GET') throw new Exception('Method not allowed', 405);
                return $controller->overview();

            default:
                throw new Exception('Endpoint not found', 404);
        }
    } catch (Exception $e) {
        http_response_code($e->getCode() ?: 500);
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
}
