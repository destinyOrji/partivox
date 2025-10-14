<?php
require_once __DIR__ . '/../config/db.php';
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

class CampaignController {
    private $user;
    private $campaigns;
    private $tasks;

    public function __construct($user) {
        $this->user = $user;
        $this->campaigns = Database::getCollection('campaigns');
        $this->tasks = Database::getCollection('tasks');
    }

    public function upload($data) {
        $required = ['title', 'start_date', 'end_date'];
        foreach ($required as $key) {
            if (empty($data[$key])) throw new Exception("Field '$key' is required", 400);
        }

        $budgetDiamonds = isset($data['budget']) ? (int)$data['budget'] : 0;
        if ($budgetDiamonds < 0) throw new Exception('Budget must be a non-negative integer', 400);

        // Get user ID - handle both string and ObjectId formats
        $userIdStr = is_string($this->user['id']) ? $this->user['id'] : (string)$this->user['id'];
        $userId = new ObjectId($userIdStr);
        
        error_log('[CAMPAIGN UPLOAD] User ID: ' . $userIdStr);
        error_log('[CAMPAIGN UPLOAD] Campaign data: ' . json_encode($data));
        $wallets = Database::getCollection('wallets');
        $transactions = Database::getCollection('transactions');
        $activities = Database::getCollection('activities');

        // Ensure user has a wallet (create if doesn't exist)
        $wallet = $wallets->findOne(['user_id' => $userId]);
        if (!$wallet) {
            // Create initial wallet for user
            $wallets->insertOne([
                'user_id' => $userId,
                'diamonds' => 100, // Give new users 100 diamonds to start
                'usdt' => 0.0,
                'created_at' => new UTCDateTime(),
                'updated_at' => new UTCDateTime()
            ]);
            $wallet = $wallets->findOne(['user_id' => $userId]);
        }
        
        // Validate wallet balance if there is a budget
        if ($budgetDiamonds > 0) {
            $currentDiamonds = (int)($wallet->diamonds ?? 0);
            if ($currentDiamonds < $budgetDiamonds) {
                throw new Exception("Insufficient diamonds to fund campaign budget. You have {$currentDiamonds} diamonds, need {$budgetDiamonds}", 400);
            }
        }

        $doc = [
            'user_id' => $userId,
            'title' => trim($data['title']),
            'description' => $data['description'] ?? '',
            'start_date' => new UTCDateTime(strtotime($data['start_date']) * 1000),
            'end_date' => new UTCDateTime(strtotime($data['end_date']) * 1000),
            'target_audience' => $data['target_audience'] ?? [],
            'budget' => $budgetDiamonds,
            'assets' => $data['assets'] ?? [],
            'actions' => $data['actions'] ?? [], // FIXED: Save engagement URLs
            'participants' => $data['participants'] ?? null,
            'max_participants' => isset($data['participants']) ? (int)$data['participants'] : 100,
            'status' => $data['status'] ?? 'draft',
            'created_at' => new UTCDateTime(),
            'updated_at' => new UTCDateTime(),
            'meta' => $data['meta'] ?? new \stdClass(),
        ];

        // Create campaign first
        error_log('[CAMPAIGN UPLOAD] Creating campaign with doc: ' . json_encode($doc));
        $result = $this->campaigns->insertOne($doc);
        error_log('[CAMPAIGN UPLOAD] Campaign created with ID: ' . (string)$result->getInsertedId());
        $campaignId = $result->getInsertedId();

        // Deduct budget and record transaction + activity
        if ($budgetDiamonds > 0) {
            // Deduct diamonds from wallet
            $wallets->updateOne(
                ['user_id' => $userId],
                ['$inc' => ['diamonds' => -$budgetDiamonds], '$set' => ['updated_at' => new UTCDateTime()]]
            );

            // Record transaction
            $transactions->insertOne([
                'user_id' => $userId,
                'type' => 'campaign_spend',
                'amount' => $budgetDiamonds,
                'currency' => 'DIAMOND',
                'campaign_id' => $campaignId,
                'status' => 'completed',
                'created_at' => new UTCDateTime(),
            ]);

            // Activity log
            $title = $doc['title'] ?? '';
            $activities->insertOne([
                'user_id' => $userId,
                'type' => 'campaign_spend',
                'title' => "Campaign spend: {$budgetDiamonds} diamonds on '{$title}'",
                'created_at' => new UTCDateTime(),
            ]);
        }

        return [
            'status' => 'success',
            'message' => 'Campaign uploaded successfully',
            'campaign_id' => (string)$campaignId,
        ];
    }

    public function getById($id) {
        $campaign = $this->campaigns->findOne([
            '_id' => new ObjectId($id),
            'user_id' => new ObjectId($this->user['id']),
        ]);
        if (!$campaign) throw new Exception('Campaign not found', 404);

        return [
            'status' => 'success',
            'data' => $this->serializeCampaign($campaign)
        ];
    }

    public function getProgress($id) {
        $campaign = $this->campaigns->findOne([
            '_id' => new ObjectId($id),
            'user_id' => new ObjectId($this->user['id']),
        ]);
        if (!$campaign) throw new Exception('Campaign not found', 404);

        $total = $this->tasks->countDocuments(['campaign_id' => new ObjectId($id)]);
        $completed = $this->tasks->countDocuments(['campaign_id' => new ObjectId($id), 'status' => 'completed']);
        $inProgress = $this->tasks->countDocuments(['campaign_id' => new ObjectId($id), 'status' => 'in_progress']);
        $pending = $this->tasks->countDocuments(['campaign_id' => new ObjectId($id), 'status' => 'pending']);

        $percentage = $total > 0 ? round(($completed / $total) * 100) : 0;

        return [
            'status' => 'success',
            'data' => [
                'campaign_id' => $id,
                'total_tasks' => $total,
                'completed_tasks' => $completed,
                'in_progress_tasks' => $inProgress,
                'pending_tasks' => $pending,
                'completion_percentage' => $percentage,
                'last_updated' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
            ],
        ];
    }

    public function list($page, $limit, $status, $search) {
        $filter = ['user_id' => new ObjectId($this->user['id'])];
        if ($status) $filter['status'] = $status;
        if ($search) $filter['title'] = ['$regex' => $search, '$options' => 'i'];

        $cursor = $this->campaigns->find($filter, [
            'skip' => ($page - 1) * $limit,
            'limit' => $limit,
            'sort' => ['created_at' => -1],
        ]);

        $items = [];
        foreach ($cursor as $doc) $items[] = $this->serializeCampaign($doc);

        $total = $this->campaigns->countDocuments($filter);

        return [
            'status' => 'success',
            'data' => $items, // Return items directly for compatibility with frontend
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
            ],
        ];
    }

    public function listAll($page, $limit, $status, $search) {
        $filter = [];
        if ($status) $filter['status'] = $status;
        if ($search) $filter['title'] = ['$regex' => $search, '$options' => 'i'];

        $cursor = $this->campaigns->find($filter, [
            'skip' => ($page - 1) * $limit,
            'limit' => $limit,
            'sort' => ['created_at' => -1],
        ]);

        $items = [];
        foreach ($cursor as $doc) $items[] = $this->serializeCampaignWithUser($doc);

        $total = $this->campaigns->countDocuments($filter);

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

    private function serializeCampaign($c) {
        // Get user info for creator field
        $users = Database::getCollection('users');
        $user = $users->findOne(['_id' => $c->user_id]);
        
        // Count tasks for this campaign
        $totalTasks = $this->tasks->countDocuments(['campaign_id' => $c->_id]);
        $completedTasks = $this->tasks->countDocuments(['campaign_id' => $c->_id, 'status' => 'completed']);
        
        return [
            'id' => (string)$c->_id,
            '_id' => (string)$c->_id, // Compatibility
            'title' => $c->title ?? '',
            'description' => $c->description ?? '',
            'status' => $c->status ?? 'draft',
            'start_date' => isset($c->start_date) ? $c->start_date->toDateTime()->format(DateTimeInterface::ATOM) : null,
            'end_date' => isset($c->end_date) ? $c->end_date->toDateTime()->format(DateTimeInterface::ATOM) : null,
            'budget' => $c->budget ?? 0,
            'diamonds' => $c->budget ?? 0, // Compatibility
            'assets' => $c->assets ?? [],
            'actions' => $c->actions ?? [], // FIXED: Include engagement URLs in response
            'created_at' => isset($c->created_at) ? $c->created_at->toDateTime()->format(DateTimeInterface::ATOM) : null,
            'updated_at' => isset($c->updated_at) ? $c->updated_at->toDateTime()->format(DateTimeInterface::ATOM) : null,
            'timestamp' => isset($c->created_at) ? $c->created_at->toDateTime()->format(DateTimeInterface::ATOM) : null,
            'creator' => $user->twitter_handle ?? $user->email ?? 'Unknown',
            'user_id' => (string)$c->user_id,
            'twitter_handle' => $user->twitter_handle ?? null,
            'participants' => $completedTasks,
            'participant_count' => $completedTasks,
            'max_participants' => $c->max_participants ?? 100,
            'target_participants' => $c->max_participants ?? 100,
            'meta' => $c->meta ?? new \stdClass(),
            'image_url' => $this->extractImageUrl($c->assets ?? []),
            'image_data' => $this->extractImageUrl($c->assets ?? []),
            'tweet_url' => $c->tweet_url ?? ($c->meta->tweet_url ?? ''),
            'notes' => $c->description ?? '', // Compatibility
        ];
    }
    
    private function extractImageUrl($assets) {
        if (empty($assets) || !is_array($assets)) {
            error_log("CampaignController: No assets provided or not an array");
            return null;
        }
        
        error_log("CampaignController: Processing " . count($assets) . " assets");
        
        foreach ($assets as $index => $asset) {
            error_log("CampaignController: Asset $index: " . json_encode($asset));
            
            if (isset($asset['data']) && strpos($asset['data'], 'data:image/') === 0) {
                error_log("CampaignController: Found base64 image data");
                return $asset['data'];
            }
            if (isset($asset['url'])) {
                error_log("CampaignController: Found image URL: " . $asset['url']);
                return $asset['url'];
            }
        }
        
        error_log("CampaignController: No valid image found in assets");
        return null;
    }

    private function serializeCampaignWithUser($c) {
        $users = Database::getCollection('users');
        $user = $users->findOne(['_id' => $c->user_id]);
        
        $campaignData = $this->serializeCampaign($c);
        $campaignData['creator'] = [
            'id' => (string)$c->user_id,
            'name' => $user->name ?? 'Unknown User',
            'twitter_handle' => $user->twitter_handle ?? null,
            'email' => $user->email ?? null
        ];
        
        return $campaignData;
    }
}
