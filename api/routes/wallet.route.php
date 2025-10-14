<?php
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../controllers/WalletController.php';

function walletRoutes($method, $action = '', $data = []) {
    error_log('[WalletRoute] Called with method: ' . $method . ', action: ' . $action);
    
    try {
        $user = authenticate();
        error_log('[WalletRoute] User authenticated: ' . json_encode($user));
        $controller = new WalletController($user);
        
        $action = trim($action, '/');
        error_log('[WalletRoute] Processing action: ' . $action);

        switch ($action) {
            case 'balance':
                if ($method !== 'GET') throw new Exception('Method not allowed', 405);
                return $controller->getBalance();

            case 'buy':
                if ($method !== 'POST') throw new Exception('Method not allowed', 405);
                $qty = isset($data['quantity']) ? (int)$data['quantity'] : 0;
                if ($qty <= 0) throw new Exception('Quantity must be a positive integer', 400);
                return $controller->buyDiamonds($qty);

            case 'convert':
                if ($method !== 'POST') throw new Exception('Method not allowed', 405);
                $diamonds = isset($data['diamonds']) ? (int)$data['diamonds'] : 0;
                if ($diamonds <= 0) throw new Exception('Diamonds must be a positive integer', 400);
                return $controller->convertToUsdt($diamonds);

            case 'withdraw':
                if ($method !== 'POST') throw new Exception('Method not allowed', 405);
                $amount = isset($data['amount']) ? (float)$data['amount'] : 0.0;
                $to = isset($data['to']) ? trim((string)$data['to']) : '';
                if ($amount <= 0) throw new Exception('Amount must be greater than 0', 400);
                return $controller->withdrawUsdt($amount, $to);

            case 'transactions':
                if ($method !== 'GET') throw new Exception('Method not allowed', 405);
                $page  = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
                $limit = isset($_GET['limit']) ? min(50, max(1, (int)$_GET['limit'])) : 10;
                $type = $_GET['type'] ?? null; // buy_diamonds, convert_to_usdt, withdraw_usdt, campaign_spend, task_earning
                return $controller->listTransactions($page, $limit, $type);

            case 'onchain/evm/confirm':
                if ($method !== 'POST') throw new Exception('Method not allowed', 405);
                $txHash = isset($data['txHash']) ? trim((string)$data['txHash']) : '';
                $usdtAmount = isset($data['usdtAmount']) ? (float)$data['usdtAmount'] : null; // optional fallback
                if (!$txHash) throw new Exception('txHash is required', 400);
                return $controller->confirmEvmOnchainPurchase($txHash, $usdtAmount);

            case 'update-address':
                if ($method !== 'POST') throw new Exception('Method not allowed', 405);
                return $controller->updateWalletAddress($data);
                
            case 'test':
                if ($method !== 'GET') throw new Exception('Method not allowed', 405);
                return [
                    'status' => 'success',
                    'message' => 'Wallet API is working',
                    'data' => [
                        'timestamp' => date('Y-m-d H:i:s'),
                        'user_id' => $user['id'] ?? null,
                        'available_endpoints' => ['balance', 'buy', 'convert', 'withdraw', 'transactions']
                    ]
                ];

            default:
                throw new Exception('Endpoint not found', 404);
        }
    } catch (Exception $e) {
        error_log('[WalletRoute] Exception: ' . $e->getMessage());
        error_log('[WalletRoute] Stack trace: ' . $e->getTraceAsString());
        http_response_code($e->getCode() ?: 500);
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
}
