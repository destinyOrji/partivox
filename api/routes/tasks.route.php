<?php
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../controllers/TaskController.php';

function tasksRoutes($method, $action = '', $data = []) {
    $user = authenticate();
    $controller = new TaskController($user);

    try {
        $action = trim($action, '/');

        switch ($action) {
            case 'list':
                if ($method !== 'GET') throw new Exception('Method not allowed', 405);
                $status = $_GET['status'] ?? null;
                $campaignId = $_GET['campaign_id'] ?? null;
                $page  = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
                $limit = isset($_GET['limit']) ? min(50, max(1, (int)$_GET['limit'])) : 10;
                return $controller->list($page, $limit, $status, $campaignId);

            case 'progress':
                if ($method !== 'GET') throw new Exception('Method not allowed', 405);
                return $controller->progress();

            case 'status':
                if ($method !== 'PUT') throw new Exception('Method not allowed', 405);
                $taskId = $data['id'] ?? null;
                $status = $data['status'] ?? null;
                $notes  = $data['notes'] ?? '';
                if (!$taskId || !$status) throw new Exception('Task id and status are required', 400);
                return $controller->updateStatus($taskId, $status, $notes);

            case 'claim-reward':
                if ($method !== 'POST') throw new Exception('Method not allowed', 405);
                $taskId = $data['task_id'] ?? null;
                $username = $data['username'] ?? '';
                $proof = $data['proof'] ?? null;
                if (!$taskId) throw new Exception('Task ID is required', 400);
                if (!$username) throw new Exception('Username is required', 400);
                return $controller->claimReward($taskId, $username, $proof);

            case 'create':
                if ($method !== 'POST') throw new Exception('Method not allowed', 405);
                return $controller->createTask($data);

            case 'available':
                if ($method !== 'GET') throw new Exception('Method not allowed', 405);
                $page  = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
                $limit = isset($_GET['limit']) ? min(50, max(1, (int)$_GET['limit'])) : 20;
                $status = $_GET['status'] ?? 'active';
                return $controller->getAvailableTasks($page, $limit, $status);

            default:
                throw new Exception('Endpoint not found', 404);
        }
    } catch (Exception $e) {
        http_response_code($e->getCode() ?: 500);
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
}
