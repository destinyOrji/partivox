<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../config/google.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use MongoDB\BSON\UTCDateTime;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;

class AuthController {
    /**
     * Handle user login
     */
    public function login($data) {
        try {
            if (empty($data['email']) || empty($data['password'])) {
                error_log('Login error: Missing email or password');
                throw new \Exception('Email and password are required');
            }
            $user = $this->userModel->findByEmail($data['email']);
            if (!$user) {
                error_log('Login error: User not found for email ' . $data['email']);
                throw new \Exception('Invalid email or password');
            }
            if (!$user->is_verified) {
                error_log('Login error: User not verified for email ' . $data['email']);
                throw new \Exception('Account not verified. Please check your email for OTP.');
            }
            if (!password_verify($data['password'], $user->password)) {
                error_log('Login error: Password mismatch for email ' . $data['email']);
                throw new \Exception('Invalid email or password');
            }
            // Store user info in session for dashboard
            session_start();
            $_SESSION['is_authenticated'] = true;
            $_SESSION['auth_provider'] = 'email';
            $_SESSION['user_id'] = (string)$user->_id;
            $_SESSION['user_email'] = $user->email;
            $_SESSION['user_name'] = $user->name ?? explode('@', $user->email)[0];
            
            $token = $this->generateToken([
                'id' => (string)$user->_id,
                'email' => $user->email,
                'name' => $user->name ?? '',
                'role' => $user->role ?? 'user'
            ]);
            return [
                'status' => 'success',
                'message' => 'Login successful.',
                'token' => $token,
                'user' => [
                    'id' => (string)$user->_id,
                    'email' => $user->email,
                    'name' => $user->name ?? explode('@', $user->email)[0],
                    'avatar' => $user->avatar ?? null,
                    'role' => $user->role ?? 'user'
                ]
            ];
        } catch (\Exception $e) {
            error_log('Login exception: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Resend OTP to a user's email
     */
    public function resendOtp($data) {
        try {
            if (empty($data['email'])) {
                throw new \Exception('Email is required');
            }

            $email = $data['email'];
            $user = $this->userModel->findByEmail($email);
            if (!$user) {
                throw new \Exception('User not found');
            }

            // Generate a new OTP and expiry (replicating User::generateOTP behavior)
            $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            $now = class_exists('MongoDB\\BSON\\UTCDateTime') ? new \MongoDB\BSON\UTCDateTime() : date('c');
            $otpExpiry = class_exists('MongoDB\\BSON\\UTCDateTime')
                ? new \MongoDB\BSON\UTCDateTime((time() + 3600) * 1000)
                : date('c', time() + 3600);

            // Persist new OTP
            $this->userModel->update($user->_id, [
                'otp' => $otp,
                'otpExpiry' => $otpExpiry,
                'updated_at' => $now
            ]);

            $emailSent = false;
            if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                try {
                    set_time_limit(10);
                    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                    $mail->isSMTP();
                    $mail->Host       = getenv('MAILTRAP_HOST') ?: 'sandbox.smtp.mailtrap.io';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = getenv('MAILTRAP_USERNAME');
                    $mail->Password   = getenv('MAILTRAP_PASSWORD');
                    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = getenv('MAILTRAP_PORT') ?: 2525;
                    $mail->Timeout    = 5;
                    $mail->SMTPOptions = [
                        'ssl' => [
                            'verify_peer' => false,
                            'verify_peer_name' => false,
                            'allow_self_signed' => true
                        ]
                    ];
                    $mail->setFrom('no-reply@partivox.com', 'Partivox');
                    $mail->addAddress($email);
                    $mail->isHTML(true);
                    $mail->Subject = 'Your Partivox OTP Code (Resent)';
                    $mail->Body    = "Your new OTP code is: <b>{$otp}</b>";
                    $mail->send();
                    $emailSent = true;
                } catch (\PHPMailer\PHPMailer\Exception $e) {
                    // Do not fail if email fails
                    error_log('[AUTH] Resend OTP email failed: ' . $e->getMessage());
                } catch (\Exception $e) {
                    error_log('[AUTH] Resend OTP email error: ' . $e->getMessage());
                } finally {
                    set_time_limit(30);
                }
            }

            return [
                'status' => 'success',
                'message' => $emailSent ? 'OTP resent to your email.' : 'OTP regenerated. Email sending failed; use the code displayed.',
                'data' => [
                    'email' => $email,
                    // Expose OTP for development convenience if email fails
                    'otp' => $otp,
                    'emailSent' => $emailSent
                ]
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
    private $userModel;
    private $googleConfig;

    public function __construct() {
        try {
            error_log('[AUTH] Initializing AuthController...');
            $this->userModel = new User();
            $this->googleConfig = require __DIR__ . '/../config/google.php';
            error_log('[AUTH] AuthController initialized successfully');
        } catch (Exception $e) {
            error_log('[AUTH] Failed to initialize AuthController: ' . $e->getMessage());
            throw new Exception('Failed to initialize authentication system: ' . $e->getMessage());
        }
    }

    /**
     * Handle user registration
     */
    public function register($data) {
        try {
            error_log('[AUTH] Starting registration process for: ' . ($data['email'] ?? 'no email'));
            
            // Validate input
            if (empty($data['email']) || empty($data['password'])) {
                error_log('[AUTH] Missing email or password');
                throw new \Exception('Email and password are required');
            }

            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                error_log('[AUTH] Invalid email format: ' . $data['email']);
                throw new \Exception('Invalid email format');
            }

            if (strlen($data['password']) < 8) {
                error_log('[AUTH] Password too short');
                throw new \Exception('Password must be at least 8 characters long');
            }

            error_log('[AUTH] Validation passed, creating user...');
            
            // Create user
            $user = $this->userModel->create([
                'email' => $data['email'],
                'password' => $data['password'],
                'name' => $data['name'] ?? '',
                'role' => $data['role'] ?? 'user' // Pass role from request data
            ]);
            
            error_log('[AUTH] User created successfully with ID: ' . (string)$user->_id);

            // Send OTP to user's email (with timeout protection)
            $otp = $user->otp ?? null;
            $emailSent = false;
            
            if ($otp && class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                try {
                    // Set a shorter timeout to prevent hanging
                    set_time_limit(10);
                    
                    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                    
                    //Server settings with shorter timeouts
                    $mail->isSMTP();
                    $mail->Host       = getenv('MAILTRAP_HOST') ?: 'sandbox.smtp.mailtrap.io';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = getenv('MAILTRAP_USERNAME');
                    $mail->Password   = getenv('MAILTRAP_PASSWORD');
                    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = getenv('MAILTRAP_PORT') ?: 2525;
                    
                    // Set SMTP timeouts
                    $mail->Timeout = 5;
                    $mail->SMTPOptions = array(
                        'ssl' => array(
                            'verify_peer' => false,
                            'verify_peer_name' => false,
                            'allow_self_signed' => true
                        )
                    );

                    //Recipients
                    $mail->setFrom('no-reply@partivox.com', 'Partivox');
                    $mail->addAddress($user->email);

                    //Content
                    $mail->isHTML(true);
                    $mail->Subject = 'Your Partivox OTP Code';
                    $mail->Body    = "Your OTP code is: <b>{$otp}</b>";

                    $mail->send();
                    $emailSent = true;
                    error_log('[AUTH] OTP email sent successfully to: ' . $user->email);
                    
                } catch (\PHPMailer\PHPMailer\Exception $e) {
                    error_log('[AUTH] Email sending failed: ' . $e->getMessage());
                    // Don't fail registration if email fails
                } catch (Exception $e) {
                    error_log('[AUTH] Email timeout or error: ' . $e->getMessage());
                }
                
                // Reset time limit
                set_time_limit(30);
            }

            // Return success even if email fails (user can still verify with OTP)
            $message = 'Registration successful. Please verify your email.';
            if (!$emailSent) {
                $message = 'Registration successful. Email sending failed, but you can use this OTP: ' . $otp;
            }
            
            return [
                'status' => 'success',
                'message' => $message,
                'data' => [
                    'userId' => (string)$user->_id,
                    'email' => $user->email,
                    'otp' => $user->otp, // Keep for now since email might fail
                    'emailSent' => $emailSent
                ]
            ];

        } catch (\Exception $e) {
            error_log('[AUTH] Registration failed with exception: ' . $e->getMessage());
            error_log('[AUTH] Exception trace: ' . $e->getTraceAsString());
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    public function googleAuth() {
        $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth';
        $params = [
            'client_id' => $this->googleConfig['client_id'],
            'redirect_uri' => $this->googleConfig['redirect_uri'],
            'response_type' => 'code',
            'scope' => implode(' ', $this->googleConfig['scopes']),
            'access_type' => 'offline',
            'prompt' => 'consent'
        ];

        return [
            'status' => 'success',
            'auth_url' => $authUrl . '?' . http_build_query($params)
        ];
    }
        /**
         * Verify OTP and issue JWT
         */
        public function verifyOtp($data) {
            try {
                if (empty($data['email']) || empty($data['otp'])) {
                    throw new \Exception('Email and OTP are required');
                }
                $email = $data['email'];
                $otp = $data['otp'];
                $verified = $this->userModel->verifyOTP($email, $otp);
                if ($verified) {
                    $user = $this->userModel->findByEmail($email);
                    
                    // Store user info in session for dashboard
                    session_start();
                    $_SESSION['is_authenticated'] = true;
                    $_SESSION['auth_provider'] = 'email';
                    $_SESSION['user_id'] = (string)$user->_id;
                    $_SESSION['user_email'] = $user->email;
                    $_SESSION['user_name'] = $user->name ?? explode('@', $user->email)[0];
                    
                    $token = $this->generateToken([
                        'id' => (string)$user->_id,
                        'email' => $user->email,
                        'name' => $user->name ?? '',
                        'role' => $user->role ?? 'user'
                    ]);
                    return [
                        'status' => 'success',
                        'message' => 'OTP verified successfully.',
                        'token' => $token,
                        'user' => [
                            'id' => (string)$user->_id,
                            'email' => $user->email,
                            'name' => $user->name ?? explode('@', $user->email)[0]
                        ]
                    ];
                } else {
                    return [
                        'status' => 'error',
                        'message' => 'Invalid or expired OTP.'
                    ];
                }
            } catch (\Exception $e) {
                return [
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
            }
        }

    public function handleGoogleCallback($code) {
        try {
            // Exchange authorization code for access token
            $tokenResponse = $this->getGoogleAccessToken($code);
            if (isset($tokenResponse['error'])) {
                error_log('Google OAuth error: ' . json_encode($tokenResponse));
                return [
                    'status' => 'error',
                    'message' => $tokenResponse['error_description'] ?? $tokenResponse['error'] ?? 'Failed to get access token from Google',
                    'debug' => $tokenResponse
                ];
            }
            if (!isset($tokenResponse['access_token'])) {
                error_log('Google OAuth error: No access_token in response: ' . json_encode($tokenResponse));
                return [
                    'status' => 'error',
                    'message' => 'No access_token received from Google',
                    'debug' => $tokenResponse
                ];
            }
            $userInfo = $this->getGoogleUserInfo($tokenResponse['access_token']);
            if (!$userInfo || empty($userInfo['email'])) {
                error_log('Google OAuth error: Invalid user info response: ' . json_encode($userInfo));
                return [
                    'status' => 'error',
                    'message' => 'Failed to retrieve user info from Google.',
                    'debug' => $userInfo
                ];
            }
            // Check if user exists by email or Google ID
            $user = $this->userModel->findByEmail($userInfo['email']);
            if (!$user) {
                // Create new user
                $name = $userInfo['name'] ?? explode('@', $userInfo['email'])[0];
                $createdAt = date('c');
                $updatedAt = date('c');
                $user = $this->userModel->create([
                    'email' => $userInfo['email'],
                    'name' => $name,
                    'google_id' => $userInfo['id'],
                    'is_verified' => true,
                    'avatar' => $userInfo['picture'] ?? null,
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt
                ]);
            } else {
                // Update existing user with Google ID if not set
                if (empty($user->google_id)) {
                    $updatedAt = date('c');
                    $this->userModel->update($user->_id, [
                        'google_id' => $userInfo['id'],
                        'updated_at' => $updatedAt
                    ]);
                }
            }
            // Generate JWT token
            $token = $this->generateToken([
                'id' => (string)$user->_id,
                'email' => $user->email,
                'name' => $user->name ?? '',
                'role' => $user->role ?? 'user'
            ]);
            // Return the token and user data
            return [
                'status' => 'success',
                'token' => $token,
                'user' => [
                    'id' => (string)$user->_id,
                    'email' => $user->email,
                    'avatar' => $user->avatar ?? null,
                    'role' => $user->role ?? 'user'
                ]
            ];
        } catch (\Exception $e) {
            error_log('Google OAuth Exception: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'debug' => $e->getTraceAsString()
            ];
        }
    }

    private function getGoogleAccessToken($code) {
        $tokenUrl = 'https://oauth2.googleapis.com/token';
        $params = [
            'code' => $code,
            'client_id' => $this->googleConfig['client_id'],
            'client_secret' => $this->googleConfig['client_secret'],
            'redirect_uri' => $this->googleConfig['redirect_uri'],
            'grant_type' => 'authorization_code'
        ];

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-type: application/x-www-form-urlencoded',
                'content' => http_build_query($params),
                'ignore_errors' => true
            ]
        ]);

        $response = @file_get_contents($tokenUrl, false, $context);
        
        if ($response === false) {
            $error = error_get_last();
            throw new \Exception($error['message'] ?? 'Failed to connect to Google OAuth server');
        }

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid response from Google OAuth server');
        }

        return $data;
    }

    private function getGoogleUserInfo($accessToken) {
        $userInfoUrl = 'https://www.googleapis.com/oauth2/v2/userinfo';
        
        $context = stream_context_create([
            'http' => [
                'header' => "Authorization: Bearer {$accessToken}"
            ]
        ]);
        
        $response = @file_get_contents($userInfoUrl, false, $context);
        
        if ($response === false) {
            throw new \Exception('Failed to fetch user info from Google');
        }

        $userInfo = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid user info response from Google');
        }

        if (empty($userInfo['email'])) {
            throw new \Exception('Email not provided by Google');
        }

        return $userInfo;
    }

    private function generateToken($userData) {
        require_once __DIR__ . '/../../vendor/autoload.php';
        require_once __DIR__ . '/../config/db.php';
        
        $secret = $_ENV['JWT_SECRET'] ?? 'your-secret-key';
        $tokenId = base64_encode(random_bytes(32));
        $issuedAt = time();
        $expire = $issuedAt + 3600; // Token expires in 1 hour
        
        $data = [
            'iat' => $issuedAt,
            'jti' => $tokenId,
            'iss' => 'partivox',
            'nbf' => $issuedAt,
            'exp' => $expire,
            'data' => $userData
        ];
        
        $token = \Firebase\JWT\JWT::encode($data, $secret, 'HS256');
        
        // Store token in sessions collection for middleware validation
        try {
            $sessions = Database::getCollection('sessions');
            $sessions->insertOne([
                'token' => $token,
                'userId' => new MongoDB\BSON\ObjectId($userData['id']),
                'expiresAt' => new MongoDB\BSON\UTCDateTime($expire * 1000),
                'createdAt' => new MongoDB\BSON\UTCDateTime()
            ]);
        } catch (Exception $e) {
            error_log('[AUTH] Failed to store token in sessions: ' . $e->getMessage());
        }
        
        return $token;
    }

    public function getCurrentUser() {
        require_once __DIR__ . '/../middleware/auth.php';
        
        try {
            $user = authenticate();
            if (!$user) {
                throw new \Exception('Not authenticated', 401);
            }

            // Get full user data from database
            $db = \Database::getDB();
            $userData = $db->users->findOne(['_id' => new ObjectId($user['id'])]);
            
            if (!$userData) {
                throw new \Exception('User not found', 404);
            }

            // Determine provider and format response
            $provider = 'email';
            if (isset($userData->twitter_id)) {
                $provider = 'twitter';
            }

            return [
                'status' => 'success',
                'user' => [
                    'id' => (string)$userData->_id,
                    'name' => $userData->name ?? '',
                    'email' => $userData->email ?? '',
                    'twitter_handle' => $userData->twitter_handle ?? '',
                    'twitter_profile_image_url' => $userData->twitter_profile_image_url ?? '',
                    'avatar' => $userData->avatar ?? '',
                    'role' => $userData->role ?? 'user',
                    'created_at' => $userData->created_at ?? null
                ],
                'provider' => $provider
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
}
