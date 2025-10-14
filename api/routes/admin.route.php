<?php
require_once __DIR__ . '/../controllers/AdminController.php';

function handleAdminRoutes($method, $action = '', $data = [], $params = []) {
    error_log("[ADMIN ROUTES] Starting handleAdminRoutes - Method: $method, Action: $action");
    error_log("[ADMIN ROUTES] Data: " . json_encode($data));
    error_log("[ADMIN ROUTES] Params: " . json_encode($params));
    
    try {
        error_log("[ADMIN ROUTES] About to create AdminController...");
        $adminController = new AdminController();
        error_log("[ADMIN ROUTES] AdminController created successfully");
    } catch (Exception $e) {
        error_log("[ADMIN ROUTES] Failed to create AdminController: " . $e->getMessage());
        error_log("[ADMIN ROUTES] Stack trace: " . $e->getTraceAsString());
        
        // Return error response instead of throwing exception
        return [
            'status' => 'error',
            'message' => 'Failed to initialize admin controller: ' . $e->getMessage(),
            'debug' => [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]
        ];
    }
    
    try {
        // Check admin authentication for all actions except test/debug
        error_log("[ADMIN] Checking authentication...");
        
        // Enforce admin authentication (set to false to require valid admin token)
        $bypassAuth = false; // Enable proper admin authentication for production
        
        if ($action !== 'test' && $action !== 'debug' && !$bypassAuth && !isAdminAuthenticated()) {
            error_log("[ADMIN] Authentication failed - insufficient privileges");
            throw new Exception('Unauthorized access - Admin privileges required', 401);
        }
        
        if ($bypassAuth) {
            error_log("[ADMIN] Authentication bypassed for initial setup");
        } else {
            error_log("[ADMIN] Authentication successful");
        }
        switch ($action) {
            case 'total-users':
                if ($method !== 'GET') throw new Exception('Method not allowed', 405);
                return $adminController->getTotalUsers();

            case 'dashboard':
                if ($method !== 'GET') throw new Exception('Method not allowed', 405);
                return $adminController->getDashboard();
            // View single user
            case 'users/view':
                if ($method === 'GET') {
                    if (empty($params['id'])) throw new Exception('Missing user ID', 400);
                    return $adminController->getUserById($params['id']);
                } else {
                    throw new Exception('Method not allowed', 405);
                }
                break;

            // Suspend/Activate user
            case 'users/status':
                if ($method === 'PUT') {
                    if (empty($data['user_id']) || !isset($data['status'])) throw new Exception('Missing required parameters', 400);
                    $reason = $data['reason'] ?? null;
                    return $adminController->updateUserStatus($data['user_id'], $data['status'], $reason);
                } else {
                    throw new Exception('Method not allowed', 405);
                }
                break;

            // Grant admin role
            case 'users/grant-admin':
                if ($method === 'PUT') {
                    if (empty($data['user_id'])) throw new Exception('Missing user ID', 400);
                    return $adminController->grantAdminRole($data['user_id']);
                } else {
                    throw new Exception('Method not allowed', 405);
                }
                break;

            // Revoke admin role
            case 'users/revoke-admin':
                if ($method === 'PUT') {
                    if (empty($data['user_id'])) {
                        throw new Exception('Missing user ID', 400);
                    }
                    return $adminController->revokeAdminRole($data['user_id']);
                } else {
                    throw new Exception('Method not allowed', 405);
                }
                break;

            case 'users/activate':
                if ($method === 'PUT') {
                    if (empty($data['user_id'])) {
                        throw new Exception('Missing user ID', 400);
                    }
                    $reason = $data['reason'] ?? null;
                    return $adminController->activateUser($data['user_id'], $reason);
                } else {
                    throw new Exception('Method not allowed', 405);
                }
                break;

            case 'users/suspend':
                if ($method === 'PUT') {
                    if (empty($data['user_id'])) {
                        throw new Exception('Missing user ID', 400);
                    }
                    $reason = $data['reason'] ?? 'User suspended by admin';
                    return $adminController->suspendUser($data['user_id'], $reason);
                } else {
                    throw new Exception('Method not allowed', 405);
                }
                break;

            case 'users/details':
                if ($method === 'GET') {
                    if (empty($_GET['user_id'])) {
                        throw new Exception('Missing user ID', 400);
                    }
                    return $adminController->getUserDetails($_GET['user_id']);
                } else {
                    throw new Exception('Method not allowed', 405);
                }
                break;

            // Users Page
            case 'users':
                if ($method === 'GET') {
                    $filters = [
                        'status' => $params['status'] ?? null,
                        'search' => $params['search'] ?? null
                    ];
                    $page = isset($params['page']) ? (int)$params['page'] : 1;
                    $limit = isset($params['limit']) ? (int)$params['limit'] : 10;
                    return $adminController->getUsers($filters, $page, $limit);
                } else {
                    throw new Exception('Method not allowed', 405);
                }
                break;

            // Recent Users
            case 'recent-users':
                if ($method === 'GET') {
                    $limit = isset($params['limit']) ? (int)$params['limit'] : 10;
                    return $adminController->getRecentUsers($limit);
                } else {
                    throw new Exception('Method not allowed', 405);
                }
                break;

            // Transactions Page
            case 'transactions':
                if ($method === 'GET') {
                    $filters = [
                        'status' => $params['status'] ?? null,
                        'type' => $params['type'] ?? null,
                        'from' => $params['from'] ?? null,
                        'to' => $params['to'] ?? null,
                        'user_id' => $params['user_id'] ?? null,
                        'campaign_id' => $params['campaign_id'] ?? null
                    ];
                    $page = isset($params['page']) ? (int)$params['page'] : 1;
                    $limit = isset($params['limit']) ? (int)$params['limit'] : 10;
                    return $adminController->getTransactions($filters, $page, $limit);
                } else {
                    throw new Exception('Method not allowed', 405);
                }
                break;

            case 'transactions/status':
                if ($method === 'PUT') {
                    if (empty($data['transaction_id']) || empty($data['status'])) {
                        throw new Exception('Missing required parameters', 400);
                    }
                    
                    try {
                        return $adminController->updateTransactionStatus($data['transaction_id'], $data['status']);
                    } catch (Exception $e) {
                        error_log("Admin Route: Transaction status update error: " . $e->getMessage());
                        
                        // For demo transactions, return success
                        if (strpos($e->getMessage(), 'not found') !== false || strpos($e->getMessage(), 'demo') !== false) {
                            return [
                                'status' => 'success',
                                'message' => 'Transaction status updated (demo mode)',
                                'demo' => true
                            ];
                        }
                        
                        throw $e;
                    }
                } else {
                    throw new Exception('Method not allowed', 405);
                }
                break;

            // Reports Page
            case 'reports':
                if ($method === 'GET') {
                    $page = isset($params['page']) ? (int)$params['page'] : 1;
                    $limit = isset($params['limit']) ? (int)$params['limit'] : 10;
                    $status = $params['status'] ?? 'pending';
                    return $adminController->getReports($status, $page, $limit);
                } else {
                    throw new Exception('Method not allowed', 405);
                }
                break;

            // Dashboard
            case 'dashboard':
                if ($method !== 'GET') throw new Exception('Method not allowed', 405);
                return $adminController->getDashboardStats();
                break;


            // Campaigns
            case 'campaigns':
                if ($method === 'GET') {
                    error_log("[ADMIN ROUTE] Campaigns endpoint called");
                    $filters = [
                        'status' => $params['status'] ?? null,
                        'search' => $params['search'] ?? null
                    ];
                    $page = isset($params['page']) ? (int)$params['page'] : 1;
                    $limit = isset($params['limit']) ? (int)$params['limit'] : 10;
                    error_log("[ADMIN ROUTE] Calling getCampaigns with filters: " . json_encode($filters));
                    return $adminController->getCampaigns($filters, $page, $limit);
                } else {
                    throw new Exception('Method not allowed', 405);
                }
                break;

            case 'campaigns/status':
                if ($method === 'PUT') {
                    if (empty($data['campaign_id']) || !isset($data['status'])) {
                        throw new Exception('Missing required parameters', 400);
                    }
                    return $adminController->updateCampaignStatus($data['campaign_id'], $data['status']);
                } else {
                    throw new Exception('Method not allowed', 405);
                }
                break;


            case 'campaigns/suspend':
                if ($method === 'PUT') {
                    if (empty($data['campaign_id'])) {
                        throw new Exception('Missing campaign ID', 400);
                    }
                    $reason = $data['reason'] ?? 'Campaign suspended by admin';
                    return $adminController->suspendCampaign($data['campaign_id'], $reason);
                } else {
                    throw new Exception('Method not allowed', 405);
                }
                break;

            case 'campaigns/activate':
                if ($method === 'PUT') {
                    if (empty($data['campaign_id'])) {
                        throw new Exception('Missing campaign ID', 400);
                    }
                    $reason = $data['reason'] ?? null;
                    $adminNotes = $data['admin_notes'] ?? null;
                    return $adminController->updateCampaignStatus($data['campaign_id'], $data['status'], $reason, $adminNotes);
                } else {
                    throw new Exception('Method not allowed', 405);
                }
                break;

            case 'campaigns/create':
                if ($method === 'POST') {
                    if (empty($data['name']) || empty($data['user']) || empty($data['diamonds'])) {
                        throw new Exception('Missing required parameters: name, user, diamonds', 400);
                    }
                    return $adminController->createCampaign($data);
                } else {
                    throw new Exception('Method not allowed', 405);
                }
                break;

            // View single campaign
            case 'campaigns/view':
                if ($method === 'GET') {
                    if (empty($params['id'])) throw new Exception('Missing campaign ID', 400);
                    return $adminController->getCampaignById($params['id']);
                } else {
                    throw new Exception('Method not allowed', 405);
                }
                break;

            // Update campaign
            case 'campaigns/update':
                if ($method === 'PUT') {
                    if (empty($data['campaign_id'])) throw new Exception('Missing campaign ID', 400);
                    $campaignId = $data['campaign_id'];
                    unset($data['campaign_id']);
                    return $adminController->updateCampaign($campaignId, $data);
                } else {
                    throw new Exception('Method not allowed', 405);
                }
                break;

            // Delete campaign
            case 'campaigns/delete':
                if ($method === 'DELETE') {
                    if (empty($data['campaign_id'])) throw new Exception('Missing campaign ID', 400);
                    return $adminController->deleteCampaign($data['campaign_id']);
                } else {
                    throw new Exception('Method not allowed', 405);
                }
                break;

            // Approve campaign
            case 'campaigns/approve':
                error_log("[ADMIN ROUTES] campaigns/approve route hit with method: $method");
                if ($method === 'PUT') {
                    if (empty($data['campaign_id'])) throw new Exception('Missing campaign ID', 400);
                    $adminNotes = $data['admin_notes'] ?? '';
                    error_log("[ADMIN ROUTES] Calling approveCampaign with ID: " . $data['campaign_id']);
                    return $adminController->approveCampaign($data['campaign_id'], $adminNotes);
                } else {
                    error_log("[ADMIN ROUTES] Method not allowed for campaigns/approve: $method");
                    throw new Exception('Method not allowed', 405);
                }
                break;

            // Reject campaign
            case 'campaigns/reject':
                if ($method === 'PUT') {
                    if (empty($data['campaign_id'])) throw new Exception('Missing campaign ID', 400);
                    $reason = $data['reason'] ?? '';
                    return $adminController->rejectCampaign($data['campaign_id'], $reason);
                } else {
                    throw new Exception('Method not allowed', 405);
                }
                break;

            // Reports
            case 'reports':
                if ($method === 'GET') {
                    $status = $params['status'] ?? 'pending';
                    $page = isset($params['page']) ? (int)$params['page'] : 1;
                    $limit = isset($params['limit']) ? (int)$params['limit'] : 10;
                    return $adminController->getReports($status, $page, $limit);
                } else {
                    throw new Exception('Method not allowed', 405);
                }
                break;

            case 'reports/status':
                if ($method === 'PUT') {
                    if (empty($data['report_id']) || !isset($data['status'])) {
                        throw new Exception('Missing required parameters', 400);
                    }
                    $notes = $data['admin_notes'] ?? '';
                    return $adminController->updateReportStatus($data['report_id'], $data['status'], $notes);
                } else {
                    throw new Exception('Method not allowed', 405);
                }
                break;

            // Settings
            case 'settings':
                error_log("[ADMIN ROUTES] settings route hit with method: $method");
                if ($method === 'GET') {
                    error_log("[ADMIN ROUTES] Calling getSettings");
                    try {
                        $result = $adminController->getSettings();
                        error_log("[ADMIN ROUTES] getSettings returned: " . json_encode($result));
                        return $result;
                    } catch (Exception $e) {
                        error_log("[ADMIN ROUTES] getSettings error: " . $e->getMessage());
                        throw $e;
                    }
                } elseif ($method === 'PUT') {
                    error_log("[ADMIN ROUTES] Calling updateSettings with data: " . json_encode($data));
                    return $adminController->updateSettings($data);
                } else {
                    error_log("[ADMIN ROUTES] Method not allowed for settings: $method");
                    throw new Exception('Method not allowed', 405);
                }
                break;

            // Reset settings
            case 'settings/reset':
                if ($method === 'POST') {
                    $type = $data['type'] ?? 'all';
                    return $adminController->resetSettings($type);
                } else {
                    throw new Exception('Method not allowed', 405);
                }
                break;

            // System status
            case 'system/status':
                if ($method === 'GET') {
                    return $adminController->getSystemStatus();
                } else {
                    throw new Exception('Method not allowed', 405);
                }
                break;

            // System actions
            case 'system/action':
                if ($method === 'POST') {
                    if (empty($data['action'])) throw new Exception('Missing action parameter', 400);
                    return $adminController->performSystemAction($data['action']);
                } else {
                    throw new Exception('Method not allowed', 405);
                }
                break;

            // Debug endpoint
            case 'debug':
                if ($method === 'GET') {
                    try {
                        return [
                            'status' => 'success',
                            'message' => 'Admin API is working',
                            'timestamp' => date('Y-m-d H:i:s'),
                            'php_version' => PHP_VERSION,
                            'request_method' => $method,
                            'action' => $action
                        ];
                    } catch (Exception $e) {
                        return [
                            'status' => 'error',
                            'message' => 'Debug endpoint error: ' . $e->getMessage()
                        ];
                    }
                } else {
                    throw new Exception('Method not allowed', 405);
                }
                break;
                
            // Verify admin token
            case 'verify-token':
                if ($method === 'GET') {
                    return [
                        'status' => 'success',
                        'message' => 'Admin token is valid',
                        'user' => [
                            'id' => $user['id'] ?? null,
                            'email' => $user['email'] ?? null,
                            'role' => $user['role'] ?? null
                        ],
                        'timestamp' => date('Y-m-d H:i:s')
                    ];
                } else {
                    throw new Exception('Method not allowed', 405);
                }
                break;

            // Simple test endpoint
            case 'test':
                if ($method === 'GET') {
                    return [
                        'status' => 'success',
                        'message' => 'Admin routes are working',
                        'timestamp' => date('Y-m-d H:i:s'),
                        'method' => $method,
                        'action' => $action,
                        'data' => $data,
                        'params' => $params
                    ];
                } else {
                    throw new Exception('Method not allowed', 405);
                }
                break;

            // Test database and user count
            case 'test-users':
                if ($method === 'GET') {
                    try {
                        $db = Database::getDB();
                        $userCount = $db->users->countDocuments();
                        $users = $db->users->find([], ['limit' => 5, 'projection' => ['email' => 1, 'name' => 1, 'role' => 1, 'created_at' => 1]])->toArray();
                        
                        return [
                            'status' => 'success',
                            'message' => 'Database connection working',
                            'total_users' => $userCount,
                            'sample_users' => $users,
                            'timestamp' => date('Y-m-d H:i:s')
                        ];
                    } catch (Exception $e) {
                        return [
                            'status' => 'error',
                            'message' => 'Database error: ' . $e->getMessage(),
                            'timestamp' => date('Y-m-d H:i:s')
                        ];
                    }
                } else {
                    throw new Exception('Method not allowed', 405);
                }
                break;

            // === Task Claims Management ===
            case 'task-claims':
                if ($method !== 'GET') throw new Exception('Method not allowed', 405);
                $page = isset($params['page']) ? max(1, (int)$params['page']) : 1;
                $limit = isset($params['limit']) ? min(50, max(1, (int)$params['limit'])) : 20;
                $status = $params['status'] ?? null;
                $search = $params['search'] ?? '';
                return $adminController->getTaskClaims($page, $limit, $status, $search);

            case 'task-claims/approve':
                if ($method !== 'PUT') throw new Exception('Method not allowed', 405);
                $claimId = $data['claim_id'] ?? null;
                $adminNotes = $data['admin_notes'] ?? '';
                if (!$claimId) throw new Exception('Claim ID is required', 400);
                return $adminController->approveTaskClaim($claimId, $adminNotes);

            case 'task-claims/reject':
                if ($method !== 'PUT') throw new Exception('Method not allowed', 405);
                $claimId = $data['claim_id'] ?? null;
                $reason = $data['reason'] ?? '';
                if (!$claimId) throw new Exception('Claim ID is required', 400);
                return $adminController->rejectTaskClaim($claimId, $reason);

            default:
                throw new Exception('Invalid action', 404);
        }

        throw new Exception('Method not allowed', 405);
    } catch (Exception $e) {
        $code = $e->getCode() ?: 500;
        // Ensure HTTP status code reflects the error
        if (!headers_sent()) {
            http_response_code($code);
        }
        error_log("[ADMIN ROUTE] Exception caught: " . $e->getMessage());
        error_log("[ADMIN ROUTE] Stack trace: " . $e->getTraceAsString());
        
        return [
            'status' => 'error',
            'message' => $e->getMessage(),
            'code' => $code,
            'debug' => [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'action' => $action,
                'method' => $method
            ]
        ];
    }
}

// Main function that index.php expects to find
function adminRoutes($method, $action = '', $data = []) {
    error_log("[ADMIN ROUTES] Called with method: $method, action: $action");
    // Pass along $_GET as $params so pagination and IDs keep working
    return handleAdminRoutes($method, $action, $data, $_GET);
}

/**
 * Check if user is authenticated as admin
 */
function isAdminAuthenticated() {
    // Get the authorization header
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    
    // Extract token from "Bearer TOKEN" format
    if (strpos($authHeader, 'Bearer ') === 0) {
        $token = substr($authHeader, 7);
    } else {
        $token = $authHeader;
    }
    
    // Verify admin role for production security
    try {
        if (empty($token)) {
            error_log('[ADMIN AUTH] No token provided');
            return false;
        }
        
        $user = verifyAdminToken($token);
        if (!$user) {
            error_log('[ADMIN AUTH] Invalid token');
            return false;
        }
        
        // Check if user has admin role
        if (!isset($user['role']) || $user['role'] !== 'admin') {
            error_log('[ADMIN AUTH] User does not have admin role: ' . ($user['role'] ?? 'no role'));
            return false;
        }
        
        error_log('[ADMIN AUTH] Admin authentication successful for user: ' . $user['email']);
        return true;
    } catch (Exception $e) {
        error_log('[ADMIN AUTH] Authentication error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Verify JWT token using existing authentication logic
 */
function verifyAdminToken($token) {
    try {
        require_once __DIR__ . '/../config/db.php';
        require_once __DIR__ . '/../../vendor/autoload.php';
        
        // 1) Try sessions-based token (session store)
        $sessions = Database::getCollection('sessions');
        $session = $sessions->findOne([
            'token' => $token,
            'expiresAt' => ['$gt' => new MongoDB\BSON\UTCDateTime()]
        ]);

        if ($session) {
            $users = Database::getCollection('users');
            $user = $users->findOne(['_id' => $session->userId]);
            if ($user) {
                return [
                    'id' => (string)$user->_id,
                    'email' => $user->email,
                    'name' => $user->name ?? '',
                    'role' => $user->role ?? 'user'
                ];
            }
        }

        // 2) Fallback: try JWT token used by frontend (AuthController)
        try {
            $secret = $_ENV['JWT_SECRET'] ?? 'your-secret-key';
            $decoded = \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key($secret, 'HS256'));
            if (!isset($decoded->data)) {
                return null;
            }
            $data = (array)$decoded->data;
            $userId = $data['id'] ?? null;
            $role = $data['role'] ?? 'user';
            if (!$userId) return null;

            // Ensure user exists and fetch latest role from DB
            $users = Database::getCollection('users');
            $user = $users->findOne(['_id' => new MongoDB\BSON\ObjectId($userId)]);
            if (!$user) return null;

            $effectiveRole = $user->role ?? $role ?? 'user';
            return [
                'id' => (string)$user->_id,
                'email' => $user->email,
                'name' => $user->name ?? '',
                'role' => $effectiveRole
            ];
        } catch (\Throwable $jwtErr) {
            // Not a valid JWT; fall through
        }

        return null;
    } catch (Exception $e) {
        error_log('[ADMIN AUTH] Token verification error: ' . $e->getMessage());
        return null;
    }
}
