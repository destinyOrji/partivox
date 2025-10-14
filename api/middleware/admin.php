<?php

/**
 * Admin Middleware
 * 
 * This middleware checks if the current user is authenticated and has admin privileges.
 * It should be used to protect admin-only routes.
 */

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->load();

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Firebase\JWT\BeforeValidException;

function isAdmin() {
    // Get the authorization header
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    
    // Extract the token
    if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $token = $matches[1];
        
        try {
            // Verify the token and get the user data
            $user = verifyToken($token);
            
            // Check if user is an admin
            if ($user && isset($user->role) && $user->role === 'admin') {
                return true;
            }
        } catch (Exception $e) {
            error_log('Admin auth error: ' . $e->getMessage());
            return false;
        }
    }
    
    return false;
}

/**
 * Verify JWT token
 */
function verifyToken($token) {
    try {
        $secretKey = $_ENV['JWT_SECRET'];
        $algorithm = $_ENV['JWT_ALGORITHM'] ?? 'HS256';
        
        // Decode the token
        $decoded = JWT::decode($token, new Key($secretKey, $algorithm));
        
        // Check if token is expired
        $currentTime = time();
        if (isset($decoded->exp) && $decoded->exp < $currentTime) {
            throw new Exception('Token has expired');
        }
        
        return $decoded->data;
        
    } catch (ExpiredException $e) {
        throw new Exception('Token has expired');
    } catch (SignatureInvalidException $e) {
        throw new Exception('Invalid token signature');
    } catch (BeforeValidException $e) {
        throw new Exception('Token not yet valid');
    } catch (Exception $e) {
        throw new Exception('Invalid token: ' . $e->getMessage());
    }
}

/**
 * Generate JWT token
 */
function generateToken($userData) {
    $secretKey = $_ENV['JWT_SECRET'];
    $algorithm = $_ENV['JWT_ALGORITHM'] ?? 'HS256';
    $expiration = time() + ($_ENV['JWT_EXPIRATION'] ?? 86400);
    
    $payload = [
        'iss' => 'partivox',
        'aud' => 'partivox',
        'iat' => time(),
        'exp' => $expiration,
        'data' => $userData
    ];
    
    return JWT::encode($payload, $secretKey, $algorithm);
}

/**
 * Require admin authentication for a route
 */
function requireAdmin() {
    if (!isAdmin()) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'Unauthorized: Admin access required'
        ]);
        exit();
    }
}

/**
 * Require authentication (any valid user)
 */
function requireAuth() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    
    if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'Authentication required: No token provided'
        ]);
        exit();
    }
    
    try {
        $token = $matches[1];
        return verifyToken($token);
    } catch (Exception $e) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid token: ' . $e->getMessage()
        ]);
        exit();
    }
}
