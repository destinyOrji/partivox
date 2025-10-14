<?php
require_once __DIR__ . '/../config/db.php';
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

class SettingsController {
    private $user;
    private $settings;

    public function __construct($user) {
        $this->user = $user;
        $this->settings = Database::getCollection('settings');
    }

    public function get() {
        $doc = $this->settings->findOne(['user_id' => new ObjectId($this->user['id'])]) ?? (object)[];
        return [
            'status' => 'success',
            'data' => [
                'notifications' => $doc->notifications ?? ['email' => true, 'push' => false, 'sms' => false],
                'preferences' => $doc->preferences ?? ['theme' => 'light', 'timezone' => 'UTC'],
            ],
        ];
    }

    public function update($data) {
        $update = [
            'notifications' => $data['notifications'] ?? ['email' => true, 'push' => false, 'sms' => false],
            'preferences' => $data['preferences'] ?? ['theme' => 'light', 'timezone' => 'UTC'],
            'updated_at' => new UTCDateTime(),
        ];

        $this->settings->updateOne(
            ['user_id' => new ObjectId($this->user['id'])],
            ['$set' => $update, '$setOnInsert' => ['user_id' => new ObjectId($this->user['id']), 'created_at' => new UTCDateTime()]],
            ['upsert' => true]
        );

        return ['status' => 'success', 'message' => 'Settings updated successfully'];
    }
    
    public function getProfile() {
        try {
            $db = Database::getDB();
            $user = $db->users->findOne(['_id' => new ObjectId($this->user['id'])]);
            
            if (!$user) {
                throw new Exception('User not found');
            }
            
            return [
                'status' => 'success',
                'data' => [
                    'name' => $user->name ?? '',
                    'email' => $user->email ?? '',
                    'bio' => $user->bio ?? '',
                    'avatar' => $user->avatar ?? null,
                    'profile_picture' => $user->profile_picture ?? null,
                    'twitter_handle' => $user->twitter_handle ?? null,
                    'wallet_address' => $user->wallet_address ?? null
                ]
            ];
        } catch (Exception $e) {
            throw new Exception("Failed to get profile: " . $e->getMessage());
        }
    }
    
    public function updateProfile($data) {
        try {
            $db = Database::getDB();
            
            $updateData = [];
            if (isset($data['name'])) $updateData['name'] = $data['name'];
            if (isset($data['bio'])) $updateData['bio'] = $data['bio'];
            if (isset($data['avatar'])) $updateData['avatar'] = $data['avatar'];
            if (isset($data['profile_picture'])) $updateData['profile_picture'] = $data['profile_picture'];
            
            if (!empty($updateData)) {
                $updateData['updated_at'] = new UTCDateTime();
                
                $result = $db->users->updateOne(
                    ['_id' => new ObjectId($this->user['id'])],
                    ['$set' => $updateData]
                );
                
                if ($result->getModifiedCount() > 0) {
                    return ['status' => 'success', 'message' => 'Profile updated successfully'];
                }
            }
            
            return ['status' => 'success', 'message' => 'No changes made'];
        } catch (Exception $e) {
            throw new Exception("Failed to update profile: " . $e->getMessage());
        }
    }
    
    public function uploadProfilePicture($file) {
        try {
            error_log('[SettingsController] uploadProfilePicture called');
            error_log('[SettingsController] File data: ' . json_encode($file));
            error_log('[SettingsController] User: ' . json_encode($this->user));
            
            // Validate file
            if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
                error_log('[SettingsController] File validation failed');
                throw new Exception('No file uploaded or invalid file');
            }
            
            // Check file size (max 5MB)
            if ($file['size'] > 5 * 1024 * 1024) {
                throw new Exception('File size must be less than 5MB');
            }
            
            // Check file type using alternative methods
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            // Get file extension
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, $allowedExtensions)) {
                throw new Exception('Invalid file extension. Only JPG, PNG, GIF, and WebP are allowed');
            }
            
            // Try to get MIME type using multiple methods
            $mimeType = null;
            
            // Method 1: Use finfo if available
            if (function_exists('finfo_open')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                if ($finfo) {
                    $mimeType = finfo_file($finfo, $file['tmp_name']);
                    finfo_close($finfo);
                }
            }
            
            // Method 2: Use mime_content_type if available
            if (!$mimeType && function_exists('mime_content_type')) {
                $mimeType = mime_content_type($file['tmp_name']);
            }
            
            // Method 3: Use getimagesize as fallback
            if (!$mimeType) {
                $imageInfo = getimagesize($file['tmp_name']);
                if ($imageInfo !== false) {
                    $mimeType = $imageInfo['mime'];
                }
            }
            
            // Validate MIME type if we got one
            if ($mimeType && !in_array($mimeType, $allowedTypes)) {
                throw new Exception('Invalid file type detected: ' . $mimeType . '. Only JPEG, PNG, GIF, and WebP are allowed');
            }
            
            // Additional validation: ensure it's actually an image
            if (!getimagesize($file['tmp_name'])) {
                throw new Exception('File is not a valid image');
            }
            
            // Create uploads directory if it doesn't exist
            $uploadDir = __DIR__ . '/../../uploads/profile_pictures/';
            error_log('[SettingsController] Upload directory: ' . $uploadDir);
            if (!is_dir($uploadDir)) {
                error_log('[SettingsController] Creating upload directory');
                if (!mkdir($uploadDir, 0755, true)) {
                    throw new Exception('Failed to create upload directory');
                }
            }
            
            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = $this->user['id'] . '_' . time() . '.' . $extension;
            $filePath = $uploadDir . $filename;
            
            // Move uploaded file
            error_log('[SettingsController] Moving file from ' . $file['tmp_name'] . ' to ' . $filePath);
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                error_log('[SettingsController] Failed to move uploaded file');
                throw new Exception('Failed to save uploaded file');
            }
            error_log('[SettingsController] File uploaded successfully');
            
            // Generate URL for the uploaded file
            $fileUrl = '/uploads/profile_pictures/' . $filename;
            
            // Update user profile with new picture URL
            $this->updateProfile(['profile_picture' => $fileUrl]);
            
            return [
                'status' => 'success',
                'message' => 'Profile picture uploaded successfully',
                'data' => [
                    'profile_picture_url' => $fileUrl
                ]
            ];
            
        } catch (Exception $e) {
            error_log('[SettingsController] Upload error: ' . $e->getMessage());
            throw new Exception('Failed to upload profile picture: ' . $e->getMessage());
        }
    }
    
    public function getProfilePicture() {
        try {
            $db = Database::getDB();
            $user = $db->users->findOne(['_id' => new ObjectId($this->user['id'])]);
            
            if (!$user) {
                throw new Exception('User not found');
            }
            
            $profilePictureUrl = $user->profile_picture ?? null;
            
            return [
                'status' => 'success',
                'data' => [
                    'profile_picture_url' => $profilePictureUrl
                ]
            ];
            
        } catch (Exception $e) {
            throw new Exception('Failed to get profile picture: ' . $e->getMessage());
        }
    }
    
    public function getNotificationSettings() {
        try {
            $db = Database::getDB();
            $user = $db->users->findOne(['_id' => new ObjectId($this->user['id'])]);
            
            $settings = $user->notification_settings ?? [
                'email_notifications' => true,
                'push_notifications' => true,
                'campaign_updates' => true,
                'task_reminders' => true
            ];
            
            return ['status' => 'success', 'data' => $settings];
        } catch (Exception $e) {
            throw new Exception("Failed to get notification settings: " . $e->getMessage());
        }
    }
    
    public function updateNotificationSettings($data) {
        try {
            $db = Database::getDB();
            
            $result = $db->users->updateOne(
                ['_id' => new ObjectId($this->user['id'])],
                ['$set' => [
                    'notification_settings' => $data,
                    'updated_at' => new UTCDateTime()
                ]]
            );
            
            return ['status' => 'success', 'message' => 'Notification settings updated'];
        } catch (Exception $e) {
            throw new Exception("Failed to update notification settings: " . $e->getMessage());
        }
    }
    
    public function getPrivacySettings() {
        try {
            $db = Database::getDB();
            $user = $db->users->findOne(['_id' => new ObjectId($this->user['id'])]);
            
            $settings = $user->privacy_settings ?? [
                'profile_visibility' => 'public',
                'show_activity' => true,
                'allow_messages' => true
            ];
            
            return ['status' => 'success', 'data' => $settings];
        } catch (Exception $e) {
            throw new Exception("Failed to get privacy settings: " . $e->getMessage());
        }
    }
    
    public function updatePrivacySettings($data) {
        try {
            $db = Database::getDB();
            
            $result = $db->users->updateOne(
                ['_id' => new ObjectId($this->user['id'])],
                ['$set' => [
                    'privacy_settings' => $data,
                    'updated_at' => new UTCDateTime()
                ]]
            );
            
            return ['status' => 'success', 'message' => 'Privacy settings updated'];
        } catch (Exception $e) {
            throw new Exception("Failed to update privacy settings: " . $e->getMessage());
        }
    }
    
    public function getConnectedAccounts() {
        try {
            $db = Database::getDB();
            $user = $db->users->findOne(['_id' => new ObjectId($this->user['id'])]);
            
            if (!$user) {
                throw new Exception('User not found');
            }
            
            $accounts = [];
            
            // Check Twitter connection
            if (!empty($user->twitter_id) && !empty($user->twitter_handle)) {
                $accounts['twitter'] = [
                    'connected' => true,
                    'handle' => $user->twitter_handle,
                    'display_name' => $user->name ?? $user->twitter_handle,
                    'profile_image' => $user->twitter_profile_image_url ?? null
                ];
            } else {
                $accounts['twitter'] = ['connected' => false];
            }
            
            // Check MetaMask/Wallet connection
            if (!empty($user->wallet_address)) {
                $accounts['metamask'] = [
                    'connected' => true,
                    'address' => $user->wallet_address,
                    'short_address' => substr($user->wallet_address, 0, 6) . '...' . substr($user->wallet_address, -4)
                ];
            } else {
                $accounts['metamask'] = ['connected' => false];
            }
            
            return ['status' => 'success', 'data' => $accounts];
        } catch (Exception $e) {
            throw new Exception("Failed to get connected accounts: " . $e->getMessage());
        }
    }
    
    public function disconnectAccount($accountType) {
        try {
            $db = Database::getDB();
            $updateData = ['updated_at' => new UTCDateTime()];
            
            switch ($accountType) {
                case 'twitter':
                    $updateData['twitter_id'] = null;
                    $updateData['twitter_handle'] = null;
                    $updateData['twitter_profile_image_url'] = null;
                    $updateData['twitter_followers_count'] = null;
                    break;
                    
                case 'metamask':
                case 'wallet':
                    $updateData['wallet_address'] = null;
                    $updateData['wallet_connected'] = false;
                    break;
                    
                default:
                    throw new Exception('Invalid account type');
            }
            
            $result = $db->users->updateOne(
                ['_id' => new ObjectId($this->user['id'])],
                ['$set' => $updateData]
            );
            
            // If disconnecting Twitter, also clear session data
            if ($accountType === 'twitter') {
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                unset($_SESSION['twitter_access_token']);
                unset($_SESSION['twitter_user_id']);
                unset($_SESSION['twitter_screen_name']);
                unset($_SESSION['twitter_profile_image_url']);
            }
            
            return ['status' => 'success', 'message' => ucfirst($accountType) . ' account disconnected successfully'];
        } catch (Exception $e) {
            throw new Exception("Failed to disconnect account: " . $e->getMessage());
        }
    }
    
    public function connectWallet($data) {
        try {
            $db = Database::getDB();
            
            if (empty($data['wallet_address'])) {
                throw new Exception('Wallet address is required');
            }
            
            // Validate Ethereum address format
            if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $data['wallet_address'])) {
                throw new Exception('Invalid wallet address format');
            }
            
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
                return ['status' => 'success', 'message' => 'Wallet connected successfully'];
            } else {
                return ['status' => 'success', 'message' => 'Wallet already connected'];
            }
        } catch (Exception $e) {
            throw new Exception("Failed to connect wallet: " . $e->getMessage());
        }
    }

    // Get public platform settings for users
    public static function getPlatformSettings() {
        try {
            $db = Database::getDB();
            $settingsCollection = $db->settings;
            
            // Get all settings types
            $generalSettings = $settingsCollection->findOne(['type' => 'general']);
            $campaignSettings = $settingsCollection->findOne(['type' => 'campaign']);
            $userSettings = $settingsCollection->findOne(['type' => 'user']);
            $transactionSettings = $settingsCollection->findOne(['type' => 'transaction']);
            
            // Return only public settings that users need to know
            $publicSettings = [
                'platform' => [
                    'name' => $generalSettings->platform_name ?? 'PARTIVOX',
                    'description' => $generalSettings->platform_description ?? 'A decentralized platform for social media campaigns and task completion.',
                    'maintenance_mode' => $generalSettings->maintenance_mode ?? false
                ],
                'campaigns' => [
                    'min_budget' => $campaignSettings->min_budget ?? 100,
                    'max_budget' => $campaignSettings->max_budget ?? 10000,
                    'auto_approve' => $campaignSettings->auto_approve ?? true
                ],
                'user' => [
                    'registration_enabled' => $userSettings->allow_registration ?? true,
                    'email_verification_required' => $userSettings->email_verification_required ?? true,
                    'default_diamonds' => $userSettings->default_diamonds ?? 50,
                    'referral_bonus' => $userSettings->referral_bonus ?? 25
                ],
                'transactions' => [
                    'min_withdrawal' => $transactionSettings->min_withdrawal ?? 10,
                    'transaction_fee' => $transactionSettings->transaction_fee ?? 2.5
                ]
            ];
            
            return [
                'status' => 'success',
                'data' => $publicSettings
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Failed to fetch platform settings: ' . $e->getMessage()
            ];
        }
    }

    public function uploadAvatar() {
        try {
            error_log('[AVATAR UPLOAD] Starting upload process');
            error_log('[AVATAR UPLOAD] User: ' . json_encode($this->user));
            error_log('[AVATAR UPLOAD] FILES: ' . json_encode($_FILES));
            
            // Check if file was uploaded
            if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
                error_log('[AVATAR UPLOAD] File upload error: ' . ($_FILES['avatar']['error'] ?? 'no file'));
                throw new Exception('No file uploaded or upload error', 400);
            }

            $file = $_FILES['avatar'];
            
            // Validate file type
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mimeType, $allowedTypes)) {
                throw new Exception('Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed', 400);
            }
            
            // Validate file size (max 5MB)
            if ($file['size'] > 5 * 1024 * 1024) {
                throw new Exception('File too large. Maximum size is 5MB', 400);
            }
            
            // Create uploads directory if it doesn't exist
            $uploadDir = __DIR__ . '/../../uploads/avatars/';
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0777, true)) {
                    error_log('[AVATAR UPLOAD] Failed to create directory: ' . $uploadDir);
                    throw new Exception('Failed to create upload directory', 500);
                }
                error_log('[AVATAR UPLOAD] Created directory: ' . $uploadDir);
            }
            
            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'avatar_' . $this->user['id'] . '_' . time() . '.' . $extension;
            $filepath = $uploadDir . $filename;
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                throw new Exception('Failed to save uploaded file', 500);
            }
            
            // Update user's avatar in database
            $db = Database::getDB();
            $avatarUrl = '/uploads/avatars/' . $filename;
            
            $result = $db->users->updateOne(
                ['_id' => new ObjectId($this->user['id'])],
                [
                    '$set' => [
                        'avatar' => $avatarUrl,
                        'updated_at' => new UTCDateTime()
                    ]
                ]
            );
            
            if ($result->getMatchedCount() === 0) {
                throw new Exception('User not found', 404);
            }
            
            // Remove old avatar file if it exists
            $user = $db->users->findOne(['_id' => new ObjectId($this->user['id'])]);
            if ($user && isset($user->avatar) && $user->avatar !== $avatarUrl) {
                $oldFile = __DIR__ . '/../../' . ltrim($user->avatar, '/');
                if (file_exists($oldFile) && strpos($user->avatar, '/uploads/avatars/') === 0) {
                    unlink($oldFile);
                }
            }
            
            return [
                'status' => 'success',
                'message' => 'Avatar uploaded successfully',
                'data' => [
                    'avatar_url' => $avatarUrl
                ]
            ];
            
        } catch (Exception $e) {
            error_log('[AVATAR UPLOAD] Error: ' . $e->getMessage());
            error_log('[AVATAR UPLOAD] User ID: ' . ($this->user['id'] ?? 'unknown'));
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
}
