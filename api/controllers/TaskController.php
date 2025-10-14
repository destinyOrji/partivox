<?php
require_once __DIR__ . '/../config/db.php';
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

class TaskController {
    private $user;
    private $tasks;

    public function __construct($user) {
        $this->user = $user;
        $this->tasks = Database::getCollection('tasks');
    }

    public function list($page, $limit, $status, $campaignId) {
        $filter = ['user_id' => new ObjectId($this->user['id'])];
        if ($status) $filter['status'] = $status;
        if ($campaignId) $filter['campaign_id'] = new ObjectId($campaignId);

        $cursor = $this->tasks->find($filter, [
            'skip' => ($page - 1) * $limit,
            'limit' => $limit,
            'sort' => ['due_date' => 1, 'created_at' => -1],
        ]);

        $items = [];
        foreach ($cursor as $t) {
            $items[] = [
                'id' => (string)$t->_id,
                'title' => $t->title ?? '',
                'description' => $t->description ?? '',
                'due_date' => isset($t->due_date) ? $t->due_date->toDateTime()->format(DateTimeInterface::ATOM) : null,
                'status' => $t->status ?? 'pending',
                'priority' => $t->priority ?? 'medium',
                'campaign_id' => isset($t->campaign_id) ? (string)$t->campaign_id : null,
            ];
        }

        $total = $this->tasks->countDocuments($filter);
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

    public function updateStatus($taskId, $status, $notes) {
        $valid = ['pending', 'in_progress', 'completed', 'blocked'];
        if (!in_array($status, $valid, true)) throw new Exception('Invalid status', 400);

        $task = $this->tasks->findOne(['_id' => new ObjectId($taskId), 'user_id' => new ObjectId($this->user['id'])]);
        if (!$task) throw new Exception('Task not found', 404);

        $res = $this->tasks->updateOne(
            ['_id' => $task->_id],
            ['$set' => ['status' => $status, 'notes' => $notes, 'updated_at' => new UTCDateTime()]]
        );

        if ($res->getMatchedCount() === 0) throw new Exception('Task not found', 404);

        // If marking as completed, credit reward and record transaction + activity
        if ($status === 'completed') {
            $reward = 0;
            if (isset($task->reward_diamonds)) {
                $reward = (int)$task->reward_diamonds;
            } elseif (isset($task->reward)) {
                $reward = (int)$task->reward;
            }
            if ($reward > 0) {
                $wallets = Database::getCollection('wallets');
                $transactions = Database::getCollection('transactions');
                $activities = Database::getCollection('activities');
                $userId = new ObjectId($this->user['id']);

                // Credit diamonds
                $wallets->updateOne(
                    ['user_id' => $userId],
                    ['$inc' => ['diamonds' => $reward], '$set' => ['updated_at' => new UTCDateTime()]]
                );

                // Record transaction
                $transactions->insertOne([
                    'user_id' => $userId,
                    'type' => 'task_earning',
                    'amount' => $reward,
                    'currency' => 'DIAMOND',
                    'task_id' => $task->_id,
                    'status' => 'completed',
                    'created_at' => new UTCDateTime(),
                ]);

                // Activity log
                $title = $task->title ?? 'Task';
                $activities->insertOne([
                    'user_id' => $userId,
                    'type' => 'task_earning',
                    'title' => "Task completed reward: {$reward} diamonds for '{$title}'",
                    'created_at' => new UTCDateTime(),
                ]);
            }
        }

        return ['status' => 'success', 'message' => 'Task status updated successfully'];
    }

    /**
     * Return simple progress data compatible with frontend updateTaskProgress()
     * Each item is an object with { completed, total }
     */
    public function progress() {
        $cursor = $this->tasks->find(
            ['user_id' => new ObjectId($this->user['id'])],
            [
                'limit' => 50,
                'sort' => ['created_at' => -1]
            ]
        );

        $items = [];
        foreach ($cursor as $t) {
            $items[] = [
                'completed' => isset($t->status) && $t->status === 'completed' ? 1 : 0,
                'total' => 1
            ];
        }

        return [
            'status' => 'success',
            'data' => $items
        ];
    }

    /**
     * Claim reward for completing a task/campaign engagement
     */
    public function claimReward($taskId, $username, $proof = null) {
        try {
            // Check if this is a campaign ID (from campaigns used as tasks)
            $campaigns = Database::getCollection('campaigns');
            $campaign = $campaigns->findOne(['_id' => new ObjectId($taskId)]);
            
            if ($campaign) {
                // This is a campaign engagement
                return $this->claimCampaignReward($campaign, $username, $proof);
            }
            
            // Check if it's a regular task
            $task = $this->tasks->findOne(['_id' => new ObjectId($taskId)]);
            if ($task) {
                return $this->claimTaskReward($task, $username, $proof);
            }
            
            throw new Exception('Task or campaign not found', 404);
            
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    private function claimCampaignReward($campaign, $username, $proof = null) {
        $userId = new ObjectId($this->user['id']);
        $campaignId = $campaign->_id;
        
        // Check if user already claimed reward for this campaign
        $taskClaims = Database::getCollection('task_claims');
        $existingClaim = $taskClaims->findOne([
            'user_id' => $userId,
            'campaign_id' => $campaignId
        ]);
        
        if ($existingClaim) {
            $statusText = $existingClaim->status ?? 'pending';
            if ($statusText === 'approved') {
                return [
                    'status' => 'error',
                    'message' => 'You have already been rewarded for this campaign'
                ];
            } else if ($statusText === 'rejected') {
                return [
                    'status' => 'error',
                    'message' => 'Your previous claim for this campaign was rejected'
                ];
            } else {
                return [
                    'status' => 'error',
                    'message' => 'You have already submitted a claim for this campaign. Please wait for review.'
                ];
            }
        }
        
        // Calculate reward based on campaign budget and participants
        $budget = $campaign->budget ?? 0;
        $maxParticipants = $campaign->max_participants ?? 100;
        $rewardPerUser = $maxParticipants > 0 ? floor($budget / $maxParticipants) : 10;
        
        // Get user info for the claim
        $users = Database::getCollection('users');
        $user = $users->findOne(['_id' => $userId]);
        
        // Create task claim record with enhanced data
        $claimData = [
            'user_id' => $userId,
            'campaign_id' => $campaignId,
            'task_id' => null,
            'username' => $username,
            'proof' => $proof,
            'reward_amount' => $rewardPerUser,
            'status' => 'pending', // Admin needs to verify
            'claimed_at' => new UTCDateTime(),
            'verified_at' => null,
            'user_email' => $user->email ?? null,
            'user_handle' => $user->twitter_handle ?? null,
            'campaign_title' => $campaign->title ?? 'Campaign Task',
            'campaign_creator' => $campaign->user_id ?? null
        ];
        
        $result = $taskClaims->insertOne($claimData);
        
        // Log activity
        $activities = Database::getCollection('activities');
        $activities->insertOne([
            'user_id' => $userId,
            'type' => 'task_claim_submitted',
            'title' => "Task claim submitted for: " . ($campaign->title ?? 'Campaign'),
            'description' => "Reward claim submitted for {$rewardPerUser} diamonds",
            'created_at' => new UTCDateTime()
        ]);
        
        return [
            'status' => 'success',
            'message' => 'Reward claim submitted successfully! Your submission is under review.',
            'data' => [
                'claim_id' => (string)$result->getInsertedId(),
                'reward_amount' => $rewardPerUser,
                'status' => 'pending'
            ]
        ];
    }

    private function claimTaskReward($task, $username, $proof = null) {
        $userId = new ObjectId($this->user['id']);
        $taskId = $task->_id;
        
        // Check if user already claimed reward for this task
        $taskClaims = Database::getCollection('task_claims');
        $existingClaim = $taskClaims->findOne([
            'user_id' => $userId,
            'task_id' => $taskId
        ]);
        
        if ($existingClaim) {
            $statusText = $existingClaim->status ?? 'pending';
            if ($statusText === 'approved') {
                return [
                    'status' => 'error',
                    'message' => 'You have already been rewarded for this task'
                ];
            } else if ($statusText === 'rejected') {
                return [
                    'status' => 'error',
                    'message' => 'Your previous claim for this task was rejected'
                ];
            } else {
                return [
                    'status' => 'error',
                    'message' => 'You have already submitted a claim for this task. Please wait for review.'
                ];
            }
        }
        
        $reward = $task->reward ?? $task->reward_diamonds ?? 10;
        
        // Get user info for the claim
        $users = Database::getCollection('users');
        $user = $users->findOne(['_id' => $userId]);
        
        // Create task claim record with enhanced data
        $claimData = [
            'user_id' => $userId,
            'task_id' => $taskId,
            'campaign_id' => $task->campaign_id ?? null,
            'username' => $username,
            'proof' => $proof,
            'reward_amount' => $reward,
            'status' => 'pending',
            'claimed_at' => new UTCDateTime(),
            'verified_at' => null,
            'user_email' => $user->email ?? null,
            'user_handle' => $user->twitter_handle ?? null,
            'task_title' => $task->title ?? 'Task',
            'task_creator' => $task->user_id ?? null
        ];
        
        $result = $taskClaims->insertOne($claimData);
        
        // Log activity
        $activities = Database::getCollection('activities');
        $activities->insertOne([
            'user_id' => $userId,
            'type' => 'task_claim_submitted',
            'title' => "Task claim submitted for: " . ($task->title ?? 'Task'),
            'description' => "Reward claim submitted for {$reward} diamonds",
            'created_at' => new UTCDateTime()
        ]);
        
        return [
            'status' => 'success',
            'message' => 'Reward claim submitted successfully! Your submission is under review.',
            'data' => [
                'claim_id' => (string)$result->getInsertedId(),
                'reward_amount' => $reward,
                'status' => 'pending'
            ]
        ];
    }

    /**
     * Get available tasks/campaigns for engagement
     */
    public function getAvailableTasks($page, $limit, $status) {
        // Get active campaigns as tasks
        $campaigns = Database::getCollection('campaigns');
        $filter = ['status' => $status];
        
        $cursor = $campaigns->find($filter, [
            'skip' => ($page - 1) * $limit,
            'limit' => $limit,
            'sort' => ['created_at' => -1]
        ]);
        
        $items = [];
        foreach ($cursor as $campaign) {
            // Get campaign creator info
            $users = Database::getCollection('users');
            $creator = $users->findOne(['_id' => $campaign->user_id]);
            
            // Count participants who claimed rewards
            $taskClaims = Database::getCollection('task_claims');
            $participantCount = $taskClaims->countDocuments(['campaign_id' => $campaign->_id]);
            
            $items[] = [
                'id' => (string)$campaign->_id,
                'title' => $campaign->title ?? 'Campaign Task',
                'description' => $campaign->description ?? 'Complete this campaign to earn rewards',
                'creator' => $creator->twitter_handle ?? $creator->email ?? 'Unknown',
                'reward' => $this->calculateCampaignReward($campaign),
                'participants_current' => $participantCount,
                'participants_total' => $campaign->max_participants ?? 100,
                'image' => $this->extractCampaignImage($campaign),
                'link' => $this->extractCampaignLink($campaign),
                'status' => $campaign->status ?? 'active',
                'created_at' => isset($campaign->created_at) ? $campaign->created_at->toDateTime()->format(DateTimeInterface::ATOM) : null
            ];
        }
        
        $total = $campaigns->countDocuments($filter);
        
        return [
            'status' => 'success',
            'data' => [
                'items' => $items,
                'page' => $page,
                'limit' => $limit,
                'total' => $total
            ]
        ];
    }

    private function calculateCampaignReward($campaign) {
        $budget = $campaign->budget ?? 0;
        $maxParticipants = $campaign->max_participants ?? 100;
        return $maxParticipants > 0 ? floor($budget / $maxParticipants) : 10;
    }

    private function extractCampaignImage($campaign) {
        if (isset($campaign->assets) && is_array($campaign->assets) && count($campaign->assets) > 0) {
            $asset = $campaign->assets[0];
            if (isset($asset['data'])) {
                return $asset['data'];
            }
            if (isset($asset['url'])) {
                return $asset['url'];
            }
        }
        return null;
    }

    private function extractCampaignLink($campaign) {
        if (isset($campaign->meta)) {
            $meta = $campaign->meta;
            if (isset($meta['retweet_urls']) && is_array($meta['retweet_urls']) && count($meta['retweet_urls']) > 0) {
                return $meta['retweet_urls'][0];
            }
            if (isset($meta['tweet_url'])) {
                return $meta['tweet_url'];
            }
        }
        return '';
    }

    /**
     * Create a new task
     */
    public function createTask($data) {
        $required = ['title', 'description'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Field '$field' is required", 400);
            }
        }

        $userId = new ObjectId($this->user['id']);
        
        $taskData = [
            'user_id' => $userId,
            'title' => trim($data['title']),
            'description' => trim($data['description']),
            'status' => $data['status'] ?? 'pending',
            'priority' => $data['priority'] ?? 'medium',
            'reward' => $data['reward'] ?? 0,
            'due_date' => isset($data['due_date']) ? new UTCDateTime(strtotime($data['due_date']) * 1000) : null,
            'campaign_id' => isset($data['campaign_id']) ? new ObjectId($data['campaign_id']) : null,
            'created_at' => new UTCDateTime(),
            'updated_at' => new UTCDateTime()
        ];

        $result = $this->tasks->insertOne($taskData);
        
        return [
            'status' => 'success',
            'message' => 'Task created successfully',
            'data' => [
                'task_id' => (string)$result->getInsertedId()
            ]
        ];
    }
}
