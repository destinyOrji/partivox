<?php

require_once __DIR__ . '/../controllers/SmartContractController.php';
require_once __DIR__ . '/../middleware/auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Get the request method and path
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$pathParts = explode('/', trim($path, '/'));

// Extract the endpoint from the path
$endpoint = end($pathParts);

// Initialize controller
$controller = new SmartContractController();

// Authentication middleware
$authResult = authenticateUser();
if (!$authResult['success']) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Authentication required'
    ]);
    exit();
}

$userId = $authResult['user']['_id'];

try {
    switch ($method) {
        case 'GET':
            handleGetRequest($controller, $endpoint, $userId);
            break;
            
        case 'POST':
            handlePostRequest($controller, $endpoint, $userId);
            break;
            
        case 'PUT':
            handlePutRequest($controller, $endpoint, $userId);
            break;
            
        default:
            http_response_code(405);
            echo json_encode([
                'status' => 'error',
                'message' => 'Method not allowed'
            ]);
            break;
    }
} catch (Exception $e) {
    error_log('Smart contract API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Internal server error'
    ]);
}

function handleGetRequest($controller, $endpoint, $userId) {
    switch ($endpoint) {
        case 'transactions':
            // GET /api/smartcontract/transactions
            $filters = [
                'type' => $_GET['type'] ?? null,
                'status' => $_GET['status'] ?? null,
                'currency' => $_GET['currency'] ?? null,
                'network_id' => $_GET['network_id'] ?? null
            ];
            
            $page = (int)($_GET['page'] ?? 1);
            $limit = (int)($_GET['limit'] ?? 10);
            
            $result = $controller->getUserTransactions($userId, $filters, $page, $limit);
            echo json_encode($result);
            break;
            
        case 'transaction':
            // GET /api/smartcontract/transaction?hash=0x...
            $hash = $_GET['hash'] ?? null;
            if (!$hash) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Transaction hash is required'
                ]);
                return;
            }
            
            $result = $controller->getTransactionByHash($hash);
            echo json_encode($result);
            break;
            
        case 'stats':
            // GET /api/smartcontract/stats
            $networkId = $_GET['network_id'] ?? null;
            $result = $controller->getNetworkStats($networkId);
            echo json_encode($result);
            break;
            
        default:
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'message' => 'Endpoint not found'
            ]);
            break;
    }
}

function handlePostRequest($controller, $endpoint, $userId) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid JSON input'
        ]);
        return;
    }
    
    switch ($endpoint) {
        case 'record-transaction':
            // POST /api/smartcontract/record-transaction
            $requiredFields = ['transaction_hash', 'transaction_type', 'amount', 'currency', 'contract_address', 'network_id'];
            
            foreach ($requiredFields as $field) {
                if (!isset($input[$field])) {
                    http_response_code(400);
                    echo json_encode([
                        'status' => 'error',
                        'message' => "Missing required field: $field"
                    ]);
                    return;
                }
            }
            
            $input['user_id'] = $userId;
            $result = $controller->recordTransaction($input);
            echo json_encode($result);
            break;
            
        case 'sync-balance':
            // POST /api/smartcontract/sync-balance
            $requiredFields = ['diamondBalance', 'usdtBalance'];
            
            foreach ($requiredFields as $field) {
                if (!isset($input[$field])) {
                    http_response_code(400);
                    echo json_encode([
                        'status' => 'error',
                        'message' => "Missing required field: $field"
                    ]);
                    return;
                }
            }
            
            $result = $controller->syncUserBalance($userId, $input);
            echo json_encode($result);
            break;
            
        case 'engage-task':
            // POST /api/smartcontract/engage-task
            $requiredFields = ['task_id', 'diamond_amount', 'transaction_hash'];
            
            foreach ($requiredFields as $field) {
                if (!isset($input[$field])) {
                    http_response_code(400);
                    echo json_encode([
                        'status' => 'error',
                        'message' => "Missing required field: $field"
                    ]);
                    return;
                }
            }
            
            $result = $controller->recordTaskEngagement(
                $userId,
                $input['task_id'],
                $input['diamond_amount'],
                $input['transaction_hash']
            );
            echo json_encode($result);
            break;
            
        case 'record-reward':
            // POST /api/smartcontract/record-reward
            $requiredFields = ['task_id', 'diamond_amount', 'transaction_hash'];
            
            foreach ($requiredFields as $field) {
                if (!isset($input[$field])) {
                    http_response_code(400);
                    echo json_encode([
                        'status' => 'error',
                        'message' => "Missing required field: $field"
                    ]);
                    return;
                }
            }
            
            $result = $controller->recordReward(
                $userId,
                $input['task_id'],
                $input['diamond_amount'],
                $input['transaction_hash']
            );
            echo json_encode($result);
            break;
            
        default:
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'message' => 'Endpoint not found'
            ]);
            break;
    }
}

function handlePutRequest($controller, $endpoint, $userId) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid JSON input'
        ]);
        return;
    }
    
    switch ($endpoint) {
        case 'update-status':
            // PUT /api/smartcontract/update-status
            $requiredFields = ['transaction_hash', 'status'];
            
            foreach ($requiredFields as $field) {
                if (!isset($input[$field])) {
                    http_response_code(400);
                    echo json_encode([
                        'status' => 'error',
                        'message' => "Missing required field: $field"
                    ]);
                    return;
                }
            }
            
            $additionalData = $input['additional_data'] ?? [];
            $result = $controller->updateTransactionStatus(
                $input['transaction_hash'],
                $input['status'],
                $additionalData
            );
            echo json_encode($result);
            break;
            
        default:
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'message' => 'Endpoint not found'
            ]);
            break;
    }
}
