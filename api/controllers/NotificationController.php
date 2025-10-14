<?php

class NotificationController {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Send notification to user about campaign status change
     */
    public function sendCampaignNotification($data) {
        try {
            $campaignId = $data['campaign_id'] ?? null;
            $status = $data['status'] ?? null;
            $message = $data['message'] ?? '';
            $title = $data['title'] ?? 'Campaign Update';
            $priority = $data['priority'] ?? 'medium';
            
            if (!$campaignId) {
                return [
                    'status' => 'error',
                    'message' => 'Campaign ID is required'
                ];
            }
            
            // Get campaign details to find the user
            $campaign = $this->db->campaigns->findOne(['_id' => new MongoDB\BSON\ObjectId($campaignId)]);
            
            if (!$campaign) {
                return [
                    'status' => 'error',
                    'message' => 'Campaign not found'
                ];
            }
            
            $userId = $campaign['user_id'];
            
            // Create notification document
            $notification = [
                'user_id' => $userId,
                'campaign_id' => new MongoDB\BSON\ObjectId($campaignId),
                'type' => 'campaign_status_change',
                'status' => $status,
                'title' => $title,
                'message' => $message,
                'priority' => $priority,
                'read' => false,
                'created_at' => new MongoDB\BSON\UTCDateTime(),
                'updated_at' => new MongoDB\BSON\UTCDateTime()
            ];
            
            // Insert notification
            $result = $this->db->notifications->insertOne($notification);
            
            if ($result->getInsertedCount() > 0) {
                // Also update the campaign with notification status
                $this->db->campaigns->updateOne(
                    ['_id' => new MongoDB\BSON\ObjectId($campaignId)],
                    [
                        '$set' => [
                            'notification_sent' => true,
                            'notification_sent_at' => new MongoDB\BSON\UTCDateTime(),
                            'last_status_change' => new MongoDB\BSON\UTCDateTime()
                        ]
                    ]
                );
                
                return [
                    'status' => 'success',
                    'message' => 'Notification sent successfully',
                    'notification_id' => (string)$result->getInsertedId()
                ];
            } else {
                return [
                    'status' => 'error',
                    'message' => 'Failed to create notification'
                ];
            }
            
        } catch (Exception $e) {
            error_log('[NOTIFICATION] Error sending notification: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Failed to send notification: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get notifications for a user
     */
    public function getUserNotifications($userId, $limit = 20, $offset = 0) {
        try {
            $filter = ['user_id' => new MongoDB\BSON\ObjectId($userId)];
            
            $options = [
                'sort' => ['created_at' => -1],
                'limit' => $limit,
                'skip' => $offset
            ];
            
            $notifications = $this->db->notifications->find($filter, $options)->toArray();
            
            // Convert ObjectIds to strings for JSON response
            $notifications = array_map(function($notification) {
                $notification['_id'] = (string)$notification['_id'];
                $notification['user_id'] = (string)$notification['user_id'];
                $notification['campaign_id'] = (string)$notification['campaign_id'];
                $notification['created_at'] = $notification['created_at']->toDateTime()->format('Y-m-d H:i:s');
                return $notification;
            }, $notifications);
            
            // Get unread count
            $unreadCount = $this->db->notifications->countDocuments([
                'user_id' => new MongoDB\BSON\ObjectId($userId),
                'read' => false
            ]);
            
            return [
                'status' => 'success',
                'data' => [
                    'notifications' => $notifications,
                    'unread_count' => $unreadCount,
                    'total' => count($notifications)
                ]
            ];
            
        } catch (Exception $e) {
            error_log('[NOTIFICATION] Error getting notifications: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Failed to get notifications: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Mark notification as read
     */
    public function markAsRead($notificationId, $userId) {
        try {
            $result = $this->db->notifications->updateOne(
                [
                    '_id' => new MongoDB\BSON\ObjectId($notificationId),
                    'user_id' => new MongoDB\BSON\ObjectId($userId)
                ],
                [
                    '$set' => [
                        'read' => true,
                        'read_at' => new MongoDB\BSON\UTCDateTime()
                    ]
                ]
            );
            
            if ($result->getModifiedCount() > 0) {
                return [
                    'status' => 'success',
                    'message' => 'Notification marked as read'
                ];
            } else {
                return [
                    'status' => 'error',
                    'message' => 'Notification not found or already read'
                ];
            }
            
        } catch (Exception $e) {
            error_log('[NOTIFICATION] Error marking notification as read: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Failed to mark notification as read: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Mark all notifications as read for a user
     */
    public function markAllAsRead($userId) {
        try {
            $result = $this->db->notifications->updateMany(
                [
                    'user_id' => new MongoDB\BSON\ObjectId($userId),
                    'read' => false
                ],
                [
                    '$set' => [
                        'read' => true,
                        'read_at' => new MongoDB\BSON\UTCDateTime()
                    ]
                ]
            );
            
            return [
                'status' => 'success',
                'message' => 'All notifications marked as read',
                'modified_count' => $result->getModifiedCount()
            ];
            
        } catch (Exception $e) {
            error_log('[NOTIFICATION] Error marking all notifications as read: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Failed to mark all notifications as read: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Delete notification
     */
    public function deleteNotification($notificationId, $userId) {
        try {
            $result = $this->db->notifications->deleteOne([
                '_id' => new MongoDB\BSON\ObjectId($notificationId),
                'user_id' => new MongoDB\BSON\ObjectId($userId)
            ]);
            
            if ($result->getDeletedCount() > 0) {
                return [
                    'status' => 'success',
                    'message' => 'Notification deleted successfully'
                ];
            } else {
                return [
                    'status' => 'error',
                    'message' => 'Notification not found'
                ];
            }
            
        } catch (Exception $e) {
            error_log('[NOTIFICATION] Error deleting notification: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Failed to delete notification: ' . $e->getMessage()
            ];
        }
    }
}
