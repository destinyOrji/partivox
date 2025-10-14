<?php
require_once __DIR__ . '/../config/db.php';
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

class WalletController {
    private $user;
    private $wallets;
    private $transactions;
    private $activities;

    // Rates (could be moved to settings)
    const DIAMOND_PRICE_USD = 0.05;     // price to buy 1 diamond in USD
    const DIAMOND_TO_USDT_RATE = 0.05;  // conversion rate 1 diamond => 0.05 USDT
    const WITHDRAW_FEE_RATE = 0.05;     // 5% withdrawal fee
    const DIAMONDS_PER_USDT_ONCHAIN = 5; // 1 USDT = 5 diamonds

    public function __construct($user) {
        $this->user = $user;
        $this->wallets = Database::getCollection('wallets');
        $this->transactions = Database::getCollection('transactions');
        $this->activities = Database::getCollection('activities');
        $this->ensureWallet();
    }

    private function ensureWallet() {
        $userId = new ObjectId($this->user['id']);
        $wallet = $this->wallets->findOne(['user_id' => $userId]);
        if (!$wallet) {
            $this->wallets->insertOne([
                'user_id' => $userId,
                'diamonds' => 100, // Give new users 100 diamonds to start
                'usdt' => 0.0,
                'created_at' => new UTCDateTime(),
                'updated_at' => new UTCDateTime(),
            ]);
        }
    }

    public function getBalance() {
        // Ensure wallet exists and create with initial balance if needed
        $this->ensureWallet();
        
        $wallet = $this->wallets->findOne(['user_id' => new ObjectId($this->user['id'])]);
        return [
            'status' => 'success',
            'data' => [
                'diamonds' => (int)($wallet->diamonds ?? 0),
                'usdt' => (float)($wallet->usdt ?? 0.0),
                'rates' => [
                    'diamond_price_usd' => self::DIAMOND_PRICE_USD,
                    'diamond_to_usdt_rate' => self::DIAMOND_TO_USDT_RATE,
                    'withdraw_fee_rate' => self::WITHDRAW_FEE_RATE,
                ],
            ],
        ];
    }

    public function buyDiamonds(int $quantity) {
        if ($quantity <= 0) throw new Exception('Quantity must be positive', 400);

        $userId = new ObjectId($this->user['id']);
        $costUsd = $quantity * self::DIAMOND_PRICE_USD;

        // In real systems, integrate payment gateway. Here, we just credit diamonds.
        $this->wallets->updateOne(
            ['user_id' => $userId],
            ['$inc' => ['diamonds' => $quantity], '$set' => ['updated_at' => new UTCDateTime()]]
        );

        $txId = $this->transactions->insertOne([
            'user_id' => $userId,
            'type' => 'buy_diamonds',
            'amount' => $quantity,
            'currency' => 'DIAMOND',
            'fiat_value_usd' => $costUsd,
            'status' => 'completed',
            'created_at' => new UTCDateTime(),
        ])->getInsertedId();

        // Log activity
        $this->activities->insertOne([
            'user_id' => $userId,
            'type' => 'wallet_buy',
            'title' => "Bought {$quantity} diamonds",
            'created_at' => new UTCDateTime(),
        ]);

        return [
            'status' => 'success',
            'message' => 'Diamonds purchased successfully',
            'data' => [
                'quantity' => $quantity,
                'cost_usd' => $costUsd,
                'transaction_id' => (string)$txId,
            ],
        ];
    }

    public function convertToUsdt(int $diamonds) {
        if ($diamonds <= 0) throw new Exception('Diamonds must be positive', 400);
        $userId = new ObjectId($this->user['id']);

        $wallet = $this->wallets->findOne(['user_id' => $userId]);
        $currentDiamonds = (int)($wallet->diamonds ?? 0);
        if ($currentDiamonds < $diamonds) throw new Exception('Insufficient diamonds', 400);

        $usdt = round($diamonds * self::DIAMOND_TO_USDT_RATE, 2);

        $this->wallets->updateOne(
            ['user_id' => $userId],
            ['$inc' => ['diamonds' => -$diamonds, 'usdt' => $usdt], '$set' => ['updated_at' => new UTCDateTime()]]
        );

        $txId = $this->transactions->insertOne([
            'user_id' => $userId,
            'type' => 'convert_to_usdt',
            'amount' => $diamonds,
            'currency' => 'DIAMOND',
            'usdt_value' => $usdt,
            'status' => 'completed',
            'created_at' => new UTCDateTime(),
        ])->getInsertedId();

        // Log activity
        $this->activities->insertOne([
            'user_id' => $userId,
            'type' => 'wallet_convert',
            'title' => "Converted {$diamonds} diamonds to ".$usdt." USDT",
            'created_at' => new UTCDateTime(),
        ]);

        return [
            'status' => 'success',
            'message' => 'Conversion successful',
            'data' => [
                'diamonds_spent' => $diamonds,
                'usdt_received' => $usdt,
                'transaction_id' => (string)$txId,
            ],
        ];
    }

    public function withdrawUsdt(float $amount, string $to = '') {
        if ($amount <= 0) throw new Exception('Amount must be positive', 400);

        $userId = new ObjectId($this->user['id']);
        $wallet = $this->wallets->findOne(['user_id' => $userId]);
        $currentUsdt = (float)($wallet->usdt ?? 0.0);

        $fee = round($amount * self::WITHDRAW_FEE_RATE, 2);
        $totalDebit = round($amount + $fee, 2);
        if ($currentUsdt < $totalDebit) throw new Exception('Insufficient USDT for withdrawal + fee', 400);

        $this->wallets->updateOne(
            ['user_id' => $userId],
            ['$inc' => ['usdt' => -$totalDebit], '$set' => ['updated_at' => new UTCDateTime()]]
        );

        $txId = $this->transactions->insertOne([
            'user_id' => $userId,
            'type' => 'withdraw_usdt',
            'amount' => $amount,
            'currency' => 'USDT',
            'fee' => $fee,
            'to' => $to,
            'status' => 'completed',
            'created_at' => new UTCDateTime(),
        ])->getInsertedId();

        // Log activity
        $this->activities->insertOne([
            'user_id' => $userId,
            'type' => 'wallet_withdraw',
            'title' => "Withdrew $".number_format($amount, 2)." USDT",
            'created_at' => new UTCDateTime(),
        ]);

        return [
            'status' => 'success',
            'message' => 'Withdrawal requested',
            'data' => [
                'amount' => $amount,
                'fee' => $fee,
                'debited' => $totalDebit,
                'transaction_id' => (string)$txId,
            ],
        ];
    }

    public function listTransactions(int $page, int $limit, ?string $type) {
        $filter = ['user_id' => new ObjectId($this->user['id'])];
        if ($type) $filter['type'] = $type;

        $cursor = $this->transactions->find($filter, [
            'skip' => ($page - 1) * $limit,
            'limit' => $limit,
            'sort' => ['created_at' => -1],
        ]);

        $items = [];
        foreach ($cursor as $t) {
            $items[] = [
                'id' => (string)$t->_id,
                'type' => $t->type ?? '',
                'amount' => (float)($t->amount ?? 0),
                'currency' => $t->currency ?? '',
                'status' => $t->status ?? 'completed',
                'created_at' => isset($t->created_at) ? $t->created_at->toDateTime()->format(DateTimeInterface::ATOM) : null,
                'meta' => [
                    'fiat_value_usd' => isset($t->fiat_value_usd) ? (float)$t->fiat_value_usd : null,
                    'usdt_value' => isset($t->usdt_value) ? (float)$t->usdt_value : null,
                    'fee' => isset($t->fee) ? (float)$t->fee : null,
                    'to' => $t->to ?? null,
                    'tx_hash' => $t->tx_hash ?? null,
                    'onchain' => $t->onchain ?? null,
                ],
            ];
        }

        $total = $this->transactions->countDocuments($filter);
        return [
            'status' => 'success',
            'data' => [
                'items' => $items,
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
            ],
        ];
    }

    // Confirm an EVM on-chain purchase and credit diamonds
    public function confirmEvmOnchainPurchase(string $txHash, ?float $usdtAmount = null) {
        $rpcUrl = getenv('EVM_RPC_URL');
        if (!$rpcUrl) {
            // Proceed without on-chain verification if RPC not set (dev fallback)
            if ($usdtAmount === null) throw new Exception('Server misconfigured: EVM_RPC_URL not set and usdtAmount missing', 500);
        }

        $statusOk = true;
        if ($rpcUrl) {
            // Minimal receipt check
            $payload = [
                'jsonrpc' => '2.0',
                'method' => 'eth_getTransactionReceipt',
                'params' => [$txHash],
                'id' => 1
            ];
            $ch = curl_init($rpcUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            $resp = curl_exec($ch);
            if ($resp === false) {
                curl_close($ch);
                throw new Exception('Failed to contact EVM RPC');
            }
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($code < 200 || $code >= 300) throw new Exception('EVM RPC error');
            $json = json_decode($resp, true);
            if (!isset($json['result'])) throw new Exception('Receipt not found');
            $receipt = $json['result'];
            // status should be 0x1 for success
            $statusOk = isset($receipt['status']) && strtolower($receipt['status']) === '0x1';
            if (!$statusOk) throw new Exception('Transaction failed on-chain');
            // Note: Full verification (method, logs, token amounts) can be added when USDT/Treasury addresses are configured.
        }

        // Use provided usdtAmount to compute diamonds if we cannot parse logs yet
        if ($usdtAmount === null) throw new Exception('usdtAmount is required until full on-chain parsing is configured', 400);

        $diamonds = (int) floor($usdtAmount * self::DIAMONDS_PER_USDT_ONCHAIN);
        if ($diamonds <= 0) throw new Exception('Computed diamonds is zero', 400);

        $userId = new ObjectId($this->user['id']);
        $this->wallets->updateOne(
            ['user_id' => $userId],
            ['$inc' => ['diamonds' => $diamonds], '$set' => ['updated_at' => new UTCDateTime()]]
        );

        $txId = $this->transactions->insertOne([
            'user_id' => $userId,
            'type' => 'buy_diamonds',
            'amount' => $diamonds,
            'currency' => 'DIAMOND',
            'status' => 'completed',
            'created_at' => new UTCDateTime(),
            'tx_hash' => $txHash,
            'onchain' => true,
            'usdt_value' => $usdtAmount,
            'rate' => self::DIAMONDS_PER_USDT_ONCHAIN,
        ])->getInsertedId();

        $this->activities->insertOne([
            'user_id' => $userId,
            'type' => 'wallet_buy',
            'title' => "Bought {$diamonds} diamonds (on-chain)",
            'created_at' => new UTCDateTime(),
        ]);

        return [
            'status' => 'success',
            'message' => 'On-chain purchase confirmed',
            'data' => [
                'diamonds' => $diamonds,
                'transaction_id' => (string)$txId,
            ],
        ];
    }
    
    public function updateWalletAddress($data) {
        try {
            if (empty($data['wallet_address'])) {
                throw new Exception('Wallet address is required');
            }
            
            // Validate Ethereum address format
            if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $data['wallet_address'])) {
                throw new Exception('Invalid wallet address format');
            }
            
            $db = Database::getDB();
            $updateData = [
                'wallet_address' => $data['wallet_address'],
                'wallet_connected' => true,
                'wallet_connected_at' => new UTCDateTime(),
                'updated_at' => new UTCDateTime()
            ];
            
            $result = $db->users->updateOne(
                ['_id' => new ObjectId($this->user['id'])],
                ['$set' => $updateData]
            );
            
            if ($result->getModifiedCount() > 0) {
                return ['status' => 'success', 'message' => 'Wallet address updated successfully'];
            } else {
                return ['status' => 'success', 'message' => 'Wallet address already up to date'];
            }
        } catch (Exception $e) {
            throw new Exception("Failed to update wallet address: " . $e->getMessage());
        }
    }
}
