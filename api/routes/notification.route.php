<?php

require_once __DIR__ . '/../controllers/NotificationController.php';
require_once __DIR__ . '/../middleware/auth.php';

function notificationRoutes($method, $action, $data) {
    try {
        // Database connection
        require_once __DIR__ . '/../config/database.php';
        $database = $client->selectDatabase('partivox');
        
        $notificationController = new NotificationController($database);
    
        switch ($method) {
            case 'POST':
                if ($action === 'send') {
                    // Send notification (admin only)
                    $user = authenticateUser();
                    if (!$user) {
                        return ['status' => 'error', 'message' => 'Unauthorized'];
                    }
                    
                    // Check if user is admin (for sending notifications)
                    if (!isset($user['role']) || $user['role'] !== 'admin') {
                        return ['status' => 'error', 'message' => 'Admin access required'];
                    }
                    
                    return $notificationController->sendCampaignNotification($data);
                
                } elseif ($action === 'mark-all-read') {
                    // Mark all notifications as read
                    $user = authenticateUser();
                    if (!$user) {
                        return ['status' => 'error', 'message' => 'Unauthorized'];
                    }
                    
                    return $notificationController->markAllAsRead($user['_id']);
                    
                } else {
                    return ['status' => 'error', 'message' => 'Endpoint not found'];
                }
                break;
            
            case 'GET':
                if ($action === '' || $action === 'list') {
                    // Get user notifications
                    $user = authenticateUser();
                    if (!$user) {
                        return ['status' => 'error', 'message' => 'Unauthorized'];
                    }
                    
                    $limit = isset($data['limit']) ? (int)$data['limit'] : 20;
                    $offset = isset($data['offset']) ? (int)$data['offset'] : 0;
                    
                    return $notificationController->getUserNotifications($user['_id'], $limit, $offset);
                    
                } else {
                    return ['status' => 'error', 'message' => 'Endpoint not found'];
                }
                break;
            
            case 'PUT':
                if (strpos($action, 'read') !== false) {
                    // Mark notification as read
                    $user = authenticateUser();
                    if (!$user) {
                        return ['status' => 'error', 'message' => 'Unauthorized'];
                    }
                    
                    $notificationId = $data['notification_id'] ?? null;
                    
                    if (!$notificationId) {
                        return ['status' => 'error', 'message' => 'Notification ID is required'];
                    }
                    
                    return $notificationController->markAsRead($notificationId, $user['_id']);
                    
                } else {
                    return ['status' => 'error', 'message' => 'Endpoint not found'];
                }
                break;
            
            case 'DELETE':
                // Delete notification
                $user = authenticateUser();
                if (!$user) {
                    return ['status' => 'error', 'message' => 'Unauthorized'];
                }
                
                $notificationId = $data['notification_id'] ?? null;
                
                if (!$notificationId) {
                    return ['status' => 'error', 'message' => 'Notification ID is required'];
                }
                
                return $notificationController->deleteNotification($notificationId, $user['_id']);
                break;
                
            default:
                return ['status' => 'error', 'message' => 'Method not allowed'];
                break;
        }
        
    } catch (Exception $e) {
        error_log('[NOTIFICATION API] Error: ' . $e->getMessage());
        return [
            'status' => 'error',
            'message' => 'Internal server error',
            'debug' => $e->getMessage()
        ];
    }
}
