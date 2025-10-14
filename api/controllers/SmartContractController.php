<?php

require_once __DIR__ . '/../config/db.php';

class SmartContractController {
    private $db;
    private $collection;
    
    public function __construct() {
        $this->db = Database::getInstance()->getDatabase();
        $this->collection = $this->db->smart_contract_transactions;
    }
    
    /**
     * Record a smart contract transaction
     */
    public function recordTransaction($data) {
        try {
            $transaction = [
                'user_id' => new MongoDB\BSON\ObjectId($data['user_id']),
                'transaction_hash' => $data['transaction_hash'],
                'transaction_type' => $data['transaction_type'], // 'deposit', 'withdraw', 'engage_task', 'reward'
                'amount' => (float)$data['amount'],
                'currency' => $data['currency'], // 'DIAMOND', 'USDT'
                'from_address' => $data['from_address'] ?? null,
                'to_address' => $data['to_address'] ?? null,
                'contract_address' => $data['contract_address'],
                'network_id' => (int)$data['network_id'],
                'block_number' => isset($data['block_number']) ? (int)$data['block_number'] : null,
                'gas_used' => isset($data['gas_used']) ? (int)$data['gas_used'] : null,
                'gas_price' => isset($data['gas_price']) ? $data['gas_price'] : null,
                'status' => $data['status'] ?? 'pending', // 'pending', 'confirmed', 'failed'
                'metadata' => $data['metadata'] ?? [],
                'created_at' => new MongoDB\BSON\UTCDateTime(),
                'updated_at' => new MongoDB\BSON\UTCDateTime()
            ];
            
            $result = $this->collection->insertOne($transaction);
            
            return [
                'status' => 'success',
                'message' => 'Transaction recorded successfully',
                'data' => [
                    'transaction_id' => (string)$result->getInsertedId(),
                    'transaction_hash' => $data['transaction_hash']
                ]
            ];
            
        } catch (Exception $e) {
            error_log('Error recording smart contract transaction: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Failed to record transaction'
            ];
        }
    }
    
    /**
     * Update transaction status
     */
    public function updateTransactionStatus($transactionHash, $status, $additionalData = []) {
        try {
            $updateData = [
                'status' => $status,
                'updated_at' => new MongoDB\BSON\UTCDateTime()
            ];
            
            // Add additional data if provided
            if (!empty($additionalData)) {
                $updateData = array_merge($updateData, $additionalData);
            }
            
            $result = $this->collection->updateOne(
                ['transaction_hash' => $transactionHash],
                ['$set' => $updateData]
            );
            
            return [
                'status' => 'success',
                'message' => 'Transaction status updated',
                'data' => [
                    'modified_count' => $result->getModifiedCount()
                ]
            ];
            
        } catch (Exception $e) {
            error_log('Error updating transaction status: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Failed to update transaction status'
            ];
        }
    }
    
    /**
     * Get user's smart contract transactions
     */
    public function getUserTransactions($userId, $filters = [], $page = 1, $limit = 10) {
        try {
            $query = ['user_id' => new MongoDB\BSON\ObjectId($userId)];
            
            // Apply filters
            if (!empty($filters['type'])) {
                $query['transaction_type'] = $filters['type'];
            }
            
            if (!empty($filters['status'])) {
                $query['status'] = $filters['status'];
            }
            
            if (!empty($filters['currency'])) {
                $query['currency'] = $filters['currency'];
            }
            
            if (!empty($filters['network_id'])) {
                $query['network_id'] = (int)$filters['network_id'];
            }
            
            // Calculate pagination
            $skip = ($page - 1) * $limit;
            
            // Get total count
            $totalCount = $this->collection->countDocuments($query);
            
            // Get transactions
            $transactions = $this->collection->find(
                $query,
                [
                    'sort' => ['created_at' => -1],
                    'skip' => $skip,
                    'limit' => $limit
                ]
            )->toArray();
            
            // Format transactions
            $formattedTransactions = array_map(function($tx) {
                return [
                    'id' => (string)$tx['_id'],
                    'transaction_hash' => $tx['transaction_hash'],
                    'type' => $tx['transaction_type'],
                    'amount' => $tx['amount'],
                    'currency' => $tx['currency'],
                    'from_address' => $tx['from_address'] ?? null,
                    'to_address' => $tx['to_address'] ?? null,
                    'contract_address' => $tx['contract_address'],
                    'network_id' => $tx['network_id'],
                    'block_number' => $tx['block_number'] ?? null,
                    'status' => $tx['status'],
                    'created_at' => $tx['created_at']->toDateTime()->format('Y-m-d H:i:s'),
                    'metadata' => $tx['metadata'] ?? []
                ];
            }, $transactions);
            
            return [
                'status' => 'success',
                'data' => [
                    'transactions' => $formattedTransactions,
                    'pagination' => [
                        'current_page' => $page,
                        'total_pages' => ceil($totalCount / $limit),
                        'total_count' => $totalCount,
                        'per_page' => $limit
                    ]
                ]
            ];
            
        } catch (Exception $e) {
            error_log('Error getting user transactions: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Failed to get transactions'
            ];
        }
    }
    
    /**
     * Get transaction by hash
     */
    public function getTransactionByHash($transactionHash) {
        try {
            $transaction = $this->collection->findOne(['transaction_hash' => $transactionHash]);
            
            if (!$transaction) {
                return [
                    'status' => 'error',
                    'message' => 'Transaction not found'
                ];
            }
            
            return [
                'status' => 'success',
                'data' => [
                    'id' => (string)$transaction['_id'],
                    'transaction_hash' => $transaction['transaction_hash'],
                    'type' => $transaction['transaction_type'],
                    'amount' => $transaction['amount'],
                    'currency' => $transaction['currency'],
                    'from_address' => $transaction['from_address'] ?? null,
                    'to_address' => $transaction['to_address'] ?? null,
                    'contract_address' => $transaction['contract_address'],
                    'network_id' => $transaction['network_id'],
                    'block_number' => $transaction['block_number'] ?? null,
                    'status' => $transaction['status'],
                    'created_at' => $transaction['created_at']->toDateTime()->format('Y-m-d H:i:s'),
                    'metadata' => $transaction['metadata'] ?? []
                ]
            ];
            
        } catch (Exception $e) {
            error_log('Error getting transaction by hash: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Failed to get transaction'
            ];
        }
    }
    
    /**
     * Get network statistics
     */
    public function getNetworkStats($networkId = null) {
        try {
            $matchStage = [];
            if ($networkId) {
                $matchStage['network_id'] = (int)$networkId;
            }
            
            $pipeline = [];
            if (!empty($matchStage)) {
                $pipeline[] = ['$match' => $matchStage];
            }
            
            $pipeline[] = [
                '$group' => [
                    '_id' => [
                        'network_id' => '$network_id',
                        'transaction_type' => '$transaction_type',
                        'currency' => '$currency'
                    ],
                    'total_amount' => ['$sum' => '$amount'],
                    'transaction_count' => ['$sum' => 1],
                    'avg_amount' => ['$avg' => '$amount']
                ]
            ];
            
            $stats = $this->collection->aggregate($pipeline)->toArray();
            
            return [
                'status' => 'success',
                'data' => [
                    'network_stats' => $stats
                ]
            ];
            
        } catch (Exception $e) {
            error_log('Error getting network stats: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Failed to get network statistics'
            ];
        }
    }
    
    /**
     * Sync user balances with smart contract
     */
    public function syncUserBalance($userId, $contractBalances) {
        try {
            // Update user's wallet balance in the database
            $walletCollection = $this->db->user_wallets;
            
            $updateData = [
                'diamond_balance_contract' => (float)$contractBalances['diamondBalance'],
                'usdt_balance_contract' => (float)$contractBalances['usdtBalance'],
                'last_contract_sync' => new MongoDB\BSON\UTCDateTime(),
                'updated_at' => new MongoDB\BSON\UTCDateTime()
            ];
            
            $result = $walletCollection->updateOne(
                ['user_id' => new MongoDB\BSON\ObjectId($userId)],
                [
                    '$set' => $updateData
                ],
                ['upsert' => true]
            );
            
            return [
                'status' => 'success',
                'message' => 'Balance synced successfully',
                'data' => [
                    'diamond_balance' => $contractBalances['diamondBalance'],
                    'usdt_balance' => $contractBalances['usdtBalance']
                ]
            ];
            
        } catch (Exception $e) {
            error_log('Error syncing user balance: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Failed to sync balance'
            ];
        }
    }
    
    /**
     * Record task engagement on blockchain
     */
    public function recordTaskEngagement($userId, $taskId, $diamondAmount, $transactionHash) {
        try {
            // Record the transaction
            $transactionData = [
                'user_id' => $userId,
                'transaction_hash' => $transactionHash,
                'transaction_type' => 'engage_task',
                'amount' => $diamondAmount,
                'currency' => 'DIAMOND',
                'contract_address' => '', // Will be filled by frontend
                'network_id' => 0, // Will be filled by frontend
                'status' => 'pending',
                'metadata' => [
                    'task_id' => $taskId,
                    'engagement_type' => 'task_participation'
                ]
            ];
            
            $result = $this->recordTransaction($transactionData);
            
            if ($result['status'] === 'success') {
                // Also record in tasks collection for tracking
                $tasksCollection = $this->db->task_engagements;
                $engagement = [
                    'user_id' => new MongoDB\BSON\ObjectId($userId),
                    'task_id' => $taskId,
                    'diamonds_spent' => (float)$diamondAmount,
                    'transaction_hash' => $transactionHash,
                    'status' => 'pending',
                    'created_at' => new MongoDB\BSON\UTCDateTime()
                ];
                
                $tasksCollection->insertOne($engagement);
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log('Error recording task engagement: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Failed to record task engagement'
            ];
        }
    }
    
    /**
     * Record reward distribution
     */
    public function recordReward($userId, $taskId, $diamondAmount, $transactionHash) {
        try {
            // Record the transaction
            $transactionData = [
                'user_id' => $userId,
                'transaction_hash' => $transactionHash,
                'transaction_type' => 'reward',
                'amount' => $diamondAmount,
                'currency' => 'DIAMOND',
                'contract_address' => '', // Will be filled by frontend
                'network_id' => 0, // Will be filled by frontend
                'status' => 'pending',
                'metadata' => [
                    'task_id' => $taskId,
                    'reward_type' => 'task_completion'
                ]
            ];
            
            return $this->recordTransaction($transactionData);
            
        } catch (Exception $e) {
            error_log('Error recording reward: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Failed to record reward'
            ];
        }
    }
}
