<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../config/db.php';
use MongoDB\BSON\UTCDateTime;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;

// ...existing code...

class User {
    /**
     * Get total user count
     */
    public function getTotalUsers() {
        return $this->collection->countDocuments();
    }
    private $collection;
    private $db;

    public function __construct() {
        try {
            error_log('[USER] Initializing User model...');
            $this->db = Database::getDB();
            $this->collection = $this->db->users;
            error_log('[USER] User model initialized successfully');
        } catch (Exception $e) {
            error_log('[USER] Failed to initialize User model: ' . $e->getMessage());
            throw new Exception('Database connection failed: ' . $e->getMessage());
        }
    }

    /**
     * Create a new user
     */
    public function create($data) {
        try {
            // Check if user already exists
            if ($this->findByEmail($data['email'])) {
                throw new Exception('Email already registered');
            }

            $now = class_exists('MongoDB\\BSON\\UTCDateTime') ? new \MongoDB\BSON\UTCDateTime() : date('c');
            $otp = $this->generateOTP();
            $otpExpiry = class_exists('MongoDB\\BSON\\UTCDateTime') ? new \MongoDB\BSON\UTCDateTime((time() + 3600) * 1000) : date('c', time() + 3600); // 1 hour expiry
            // Determine role based on registration type
            $role = $data['role'] ?? 'user'; // Use provided role or default to user
            if (empty($role)) {
                if (!empty($data['password'])) {
                    // Form registration = admin role by default
                    $role = 'admin';
                } elseif (!empty($data['google_id']) || !empty($data['twitter_id'])) {
                    // Social login = user role
                    $role = 'user';
                } else {
                    $role = 'user';
                }
            }

            $userData = [
                'email' => $data['email'],
                'name' => $data['name'] ?? '',
                'avatar' => $data['avatar'] ?? null,
                'role' => $role,
                'is_verified' => false,
                'diamonds' => 50,
                'created_at' => $now,
                'updated_at' => $now,
                'otp' => $otp,
                'otpExpiry' => $otpExpiry
            ];

            if (!empty($data['password'])) {
                $userData['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
            }
            if (!empty($data['google_id'])) {
                $userData['google_id'] = $data['google_id'];
                $userData['is_verified'] = true;
            }

            $result = $this->collection->insertOne($userData);
            $user = $this->findById($result->getInsertedId());
            $user->otp = $otp; // Always return OTP for testing
            return $user;
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Find user by email
     */
    public function findByEmail($email) {
        return $this->collection->findOne(['email' => $email]);
    }

    /**
     * Find user by Google ID
     */
    public function findByGoogleId($googleId) {
        return $this->collection->findOne(['google_id' => $googleId]);
    }

    /**
     * Find user by ID
     */
    public function findById($id) {
        if (is_string($id) && class_exists('MongoDB\\BSON\\ObjectId')) {
            $id = new \MongoDB\BSON\ObjectId($id);
        }
        return $this->collection->findOne(['_id' => $id]);
    }

    /**
     * Update user data
     */
    public function update($id, $data) {
        $now = class_exists('MongoDB\\BSON\\UTCDateTime')
            ? new \MongoDB\BSON\UTCDateTime()
            : date('c');
        $updateData = [
            '$set' => array_merge($data, ['updated_at' => $now])
        ];
        
        if (is_string($id) && class_exists('MongoDB\\BSON\\ObjectId')) {
            $id = new \MongoDB\BSON\ObjectId($id);
        }
        
        $result = $this->collection->updateOne(
            ['_id' => $id],
            $updateData
        );
        
        return $result->getModifiedCount() > 0;
    }

    /**
     * Verify OTP and activate user
     */
    public function verifyOTP($email, $otp) {
        try {
            $now = class_exists('MongoDB\\BSON\\UTCDateTime') ? new \MongoDB\BSON\UTCDateTime() : date('c');
            $user = $this->collection->findOne([
                'email' => $email,
                'otp' => $otp,
                'otpExpiry' => ['$gt' => $now]
            ]);

            if (!$user) {
                error_log('OTP verification failed. Email: ' . $email . ', OTP: ' . $otp);
                $debugUser = $this->collection->findOne(['email' => $email]);
                if ($debugUser) {
                    error_log('User found. OTP in DB: ' . ($debugUser['otp'] ?? 'none') . ', Expiry: ' . ($debugUser['otpExpiry'] ?? 'none'));
                }
                return false;
            }
            // ...existing code...
            // If user is found, activate user
            $this->collection->updateOne(
                ['email' => $email],
                [
                    '$set' => [
                        'is_verified' => true,
                        'status' => 'active',
                        'updated_at' => $now
                    ],
                    '$unset' => ['otp' => '', 'otpExpiry' => '']
                ]
            );
            return true;
        } catch (Exception $e) {
            throw new Exception('Error verifying OTP: ' . $e->getMessage());
        }
    }

    public function updatePassword($email, $newPassword) {
        try {
            $now = class_exists('MongoDB\BSON\UTCDateTime')
                ? new \MongoDB\BSON\UTCDateTime()
                : date('c');
            $this->collection->updateOne(
                ['email' => $email],
                [
                    '$set' => [
                        'password' => password_hash($newPassword, PASSWORD_DEFAULT),
                        'updated_at' => $now
                    ]
                ]
            );
            return true;
        } catch (Exception $e) {
            throw new Exception("Error updating password: " . $e->getMessage());
        }
    }

    /**
     * Find user by Twitter ID
     */
    public function findByTwitterId($twitterId) {
        return $this->collection->findOne(['twitter_id' => $twitterId]);
    }

    /**
     * Find existing user by Twitter ID or create a new one
     */
    public function findOrCreateByTwitter($twitterUser) {
        try {
            if (!$twitterUser || !isset($twitterUser->id_str)) {
                throw new Exception('Invalid Twitter user payload');
            }

            $existing = $this->collection->findOne(['twitter_id' => $twitterUser->id_str]);
            $now = class_exists('MongoDB\\BSON\\UTCDateTime') ? new \MongoDB\BSON\UTCDateTime() : date('c');

            $syntheticEmail = 'tw_' . $twitterUser->id_str . '@twitter.local';
            $userData = [
                'name' => $twitterUser->name ?? '',
                'twitter_handle' => $twitterUser->screen_name ?? '',
                'twitter_id' => $twitterUser->id_str,
                'twitter_profile_image_url' => $twitterUser->profile_image_url_https ?? ($twitterUser->profile_image_url ?? ''),
                'twitter_followers_count' => $twitterUser->followers_count ?? 0,
                'location' => $twitterUser->location ?? '',
                // Ensure unique email constraint is satisfied for users without a real email
                'email' => $syntheticEmail,
                'role' => 'user', // Twitter users always get user role
                'is_verified' => true,
                'status' => 'active',
                'updated_at' => $now
            ];

            if ($existing) {
                $this->collection->updateOne(
                    ['_id' => $existing->_id],
                    ['$set' => $userData]
                );
                return $this->findById($existing->_id);
            }

            $userData['created_at'] = $now;
            $userData['diamonds'] = 50; // Default diamonds for new Twitter users
            $result = $this->collection->insertOne($userData);
            return $this->findById($result->getInsertedId());
        } catch (Exception $e) {
            throw new Exception('Error saving Twitter user: ' . $e->getMessage());
        }
    }

    /**
     * Generate a random OTP
     */
    private function generateOTP() {
        return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Create a new session
     */
    public function createSession($userId) {
        try {
            $sessions = $this->db->sessions;
            $token = bin2hex(random_bytes(32));
            $expiresAt = class_exists('MongoDB\\BSON\\UTCDateTime')
                ? new \MongoDB\BSON\UTCDateTime((time() + 3600) * 1000)
                : date('c', time() + 3600);
        $createdAt = class_exists('MongoDB\\BSON\\UTCDateTime')
            ? new \MongoDB\BSON\UTCDateTime()
            : date('c');
            $sessions->insertOne([
                'userId' => class_exists('MongoDB\\BSON\\ObjectId') ? new \MongoDB\BSON\ObjectId($userId) : $userId,
                'token' => $token,
                'expiresAt' => $expiresAt,
                'createdAt' => $createdAt
            ]);

            return $token;
        } catch (Exception $e) {
            throw new Exception("Error creating session: " . $e->getMessage());
        }
    }

    /**
     * Validate session token
     */
    public function validateSession($token) {
        try {
            $sessions = $this->db->sessions;
            $now = class_exists('MongoDB\\BSON\\UTCDateTime')
                ? new \MongoDB\BSON\UTCDateTime()
                : date('c');
            $session = $sessions->findOne([
                'token' => $token,
                'expiresAt' => ['$gt' => $now]
            ]);

            if ($session) {
                return $this->collection->findOne([
                    '_id' => $session->userId
                ]);
            }
            return null;
        } catch (Exception $e) {
            throw new Exception("Error validating session: " . $e->getMessage());
        }
    }
}
