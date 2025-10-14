<?php
require_once __DIR__ . '/../config/db.php';
use MongoDB\BSON\ObjectId;

class UserController {
    private $user;
    private $db;

    public function __construct($user) {
        $this->user = $user;
        $this->db = Database::getDB();
    }

    public function getUserStats() {
        try {
            // Auth middleware provides 'id' as a string ObjectId
            $userId = new ObjectId($this->user['id']);
            
            // Get user's campaigns count
            $campaignsCount = $this->db->campaigns->countDocuments(['user_id' => $userId]);
            
            // Get user's tasks count (assuming tasks are stored in a tasks collection)
            $tasksCount = $this->db->tasks->countDocuments(['user_id' => $userId]);
            
            // Get user's total earnings from wallet transactions
            $earningsResult = $this->db->transactions->aggregate([
                [
                    '$match' => [
                        'user_id' => $userId,
                        'type' => ['$in' => ['task_earning', 'campaign_reward']],
                        'status' => 'completed'
                    ]
                ],
                [
                    '$group' => [
                        '_id' => null,
                        'total' => ['$sum' => '$amount']
                    ]
                ]
            ])->toArray();
            
            // Aggregation returns array of BSON documents (objects), not arrays
            $totalEarnings = !empty($earningsResult) ? ($earningsResult[0]->total ?? 0) : 0;
            
            // Get user's current wallet balance
            $walletBalance = $this->db->wallets->findOne(['user_id' => $userId]);
            $diamonds = $walletBalance->diamonds ?? 0;
            $usdt = $walletBalance->usdt ?? 0;
            
            return [
                'status' => 'success',
                'data' => [
                    'campaigns' => $campaignsCount,
                    'tasks' => $tasksCount,
                    'earnings' => $totalEarnings,
                    'diamonds' => $diamonds,
                    'usdt' => $usdt
                ]
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Failed to get user stats: ' . $e->getMessage()
            ];
        }
    }

    public function getProfile() {
        try {
            // Return user profile without sensitive data
            $profile = [
                '_id' => $this->user['id'],
                'name' => $this->user['name'] ?? '',
                'email' => $this->user['email'] ?? '',
                'twitter_handle' => $this->user['twitter_handle'] ?? '',
                'avatar' => $this->user['avatar'] ?? '',
                'created_at' => $this->user['created_at'] ?? null,
                'role' => $this->user['role'] ?? 'user'
            ];

            return [
                'status' => 'success',
                'data' => $profile
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Failed to get profile: ' . $e->getMessage()
            ];
        }
    }

    public function updateProfile($data) {
        try {
            $userId = new ObjectId($this->user['_id']);
            $updateData = [];

            // Only allow certain fields to be updated
            $allowedFields = ['name', 'avatar'];
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updateData[$field] = $data[$field];
                }
            }

            if (empty($updateData)) {
                return [
                    'status' => 'error',
                    'message' => 'No valid fields to update'
                ];
            }

            $result = $this->db->users->updateOne(
                ['_id' => $userId],
                ['$set' => $updateData]
            );

            if ($result->getModifiedCount() > 0) {
                return [
                    'status' => 'success',
                    'message' => 'Profile updated successfully'
                ];
            } else {
                return [
                    'status' => 'error',
                    'message' => 'No changes made to profile'
                ];
            }
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Failed to update profile: ' . $e->getMessage()
            ];
        }
    }
}
