<?php
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../controllers/CampaignController.php';

function campaignsRoutes($method, $action = '', $data = []) {
    $user = authenticate();
    $controller = new CampaignController($user);

    try {
        $action = trim($action, '/');

        switch ($action) {
            case 'upload':
                if ($method !== 'POST') throw new Exception('Method not allowed', 405);
                return $controller->upload($data);

            case 'view':
                if ($method !== 'GET') throw new Exception('Method not allowed', 405);
                $id = $_GET['id'] ?? null;
                if (!$id) throw new Exception('Campaign id is required', 400);
                return $controller->getById($id);

            case 'progress':
                if ($method !== 'GET') throw new Exception('Method not allowed', 405);
                $id = $_GET['id'] ?? null;
                if (!$id) throw new Exception('Campaign id is required', 400);
                return $controller->getProgress($id);

            case 'list':
                if ($method !== 'GET') throw new Exception('Method not allowed', 405);
                $page  = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
                $limit = isset($_GET['limit']) ? min(50, max(1, (int)$_GET['limit'])) : 10;
                $status = $_GET['status'] ?? null;
                $search = $_GET['search'] ?? null;
                return $controller->list($page, $limit, $status, $search);

            case 'list-all':
                if ($method !== 'GET') throw new Exception('Method not allowed', 405);
                $page  = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
                $limit = isset($_GET['limit']) ? min(50, max(1, (int)$_GET['limit'])) : 10;
                $status = $_GET['status'] ?? null;
                $search = $_GET['search'] ?? null;
                return $controller->listAll($page, $limit, $status, $search);

            case '':
                // Default endpoint - return user's campaigns list
                if ($method !== 'GET') throw new Exception('Method not allowed', 405);
                $page  = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
                $limit = isset($_GET['limit']) ? min(50, max(1, (int)$_GET['limit'])) : 10;
                $status = $_GET['status'] ?? null;
                $search = $_GET['search'] ?? null;
                return $controller->list($page, $limit, $status, $search);

            default:
                throw new Exception('Endpoint not found', 404);
        }
    } catch (Exception $e) {
        http_response_code($e->getCode() ?: 500);
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
}
