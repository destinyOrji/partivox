<?php
// Simple email-based login system (alternative to Twitter OAuth)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

try {
    // Start session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    $email = $input['email'] ?? '';
    $password = $input['password'] ?? '';
    $action = $input['action'] ?? 'login'; // 'login' or 'register'
    
    // Basic validation
    if (empty($email) || empty($password)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Email and password are required'
        ]);
        exit;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid email format'
        ]);
        exit;
    }
    
    // Simple file-based user storage
    $usersFile = __DIR__ . '/../../data/users.json';
    $usersDir = dirname($usersFile);
    
    if (!is_dir($usersDir)) {
        mkdir($usersDir, 0755, true);
    }
    
    $users = [];
    if (file_exists($usersFile)) {
        $users = json_decode(file_get_contents($usersFile), true) ?: [];
    }
    
    if ($action === 'register') {
        // Check if user already exists
        foreach ($users as $user) {
            if ($user['email'] === $email) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'User already exists'
                ]);
                exit;
            }
        }
        
        // Create new user
        $newUser = [
            'id' => uniqid(),
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'name' => explode('@', $email)[0], // Use email prefix as name
            'created_at' => date('Y-m-d H:i:s'),
            'auth_provider' => 'email'
        ];
        
        $users[] = $newUser;
        file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT));
        
        // Set session
        $_SESSION['is_authenticated'] = true;
        $_SESSION['auth_provider'] = 'email';
        $_SESSION['user_id'] = $newUser['id'];
        $_SESSION['user_email'] = $email;
        $_SESSION['user_name'] = $newUser['name'];
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Registration successful',
            'user' => [
                'id' => $newUser['id'],
                'email' => $email,
                'name' => $newUser['name'],
                'auth_provider' => 'email'
            ]
        ]);
        
    } else { // login
        $foundUser = null;
        foreach ($users as $user) {
            if ($user['email'] === $email) {
                $foundUser = $user;
                break;
            }
        }
        
        if (!$foundUser || !password_verify($password, $foundUser['password'])) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid email or password'
            ]);
            exit;
        }
        
        // Set session
        $_SESSION['is_authenticated'] = true;
        $_SESSION['auth_provider'] = 'email';
        $_SESSION['user_id'] = $foundUser['id'];
        $_SESSION['user_email'] = $email;
        $_SESSION['user_name'] = $foundUser['name'];
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Login successful',
            'user' => [
                'id' => $foundUser['id'],
                'email' => $email,
                'name' => $foundUser['name'],
                'auth_provider' => 'email'
            ]
        ]);
    }
    
} catch (Exception $e) {
    error_log('[EMAIL_AUTH] Error: ' . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Authentication failed: ' . $e->getMessage()
    ]);
}
?>
