<?php
require_once __DIR__ . '/../config/db.php';
use MongoDB\BSON\ObjectId;

class DashboardController {
    private $user;
    private $campaigns;
    private $tasks;
    private $activities;

    public function __construct($user) {
        $this->user = $user;
        $this->campaigns = Database::getCollection('campaigns');
        $this->tasks = Database::getCollection('tasks');
        $this->activities = Database::getCollection('activities');
    }

    public function overview() {
        $userId = new ObjectId($this->user['id']);

        $totalCampaigns = $this->campaigns->countDocuments(['user_id' => $userId]);
        $activeCampaigns = $this->campaigns->countDocuments(['user_id' => $userId, 'status' => 'active']);
        $pendingTasks = $this->tasks->countDocuments(['user_id' => $userId, 'status' => 'pending']);

        $cursor = $this->activities->find(['user_id' => $userId], [
            'limit' => 10,
            'sort' => ['created_at' => -1],
        ]);

        $recent = [];
        foreach ($cursor as $a) {
            $recent[] = [
                'id' => (string)$a->_id,
                'type' => $a->type ?? 'info',
                'title' => $a->title ?? '',
                'timestamp' => isset($a->created_at) ? $a->created_at->toDateTime()->format(DateTimeInterface::ATOM) : null,
            ];
        }

        return [
            'status' => 'success',
            'data' => [
                'total_campaigns' => $totalCampaigns,
                'active_campaigns' => $activeCampaigns,
                'pending_tasks' => $pendingTasks,
                'recent_activities' => $recent,
            ],
        ];
    }
}
