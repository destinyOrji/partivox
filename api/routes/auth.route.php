<?php
require_once __DIR__ . '/../controllers/AuthController.php';

function authRoutes($method, $action = '', $data = []) {
    $authController = new AuthController();
    
    try {
        switch ($action) {
            case 'register':
                if ($method !== 'POST') throw new Exception('Method not allowed');
                return $authController->register($data);
                
            case 'login':
                if ($method !== 'POST') throw new Exception('Method not allowed');
                return $authController->login($data);
                
            case 'verify-otp':
                if ($method !== 'POST') throw new Exception('Method not allowed');
                return $authController->verifyOtp($data);
            
            case 'resend-otp':
                if ($method !== 'POST') throw new Exception('Method not allowed');
                return $authController->resendOtp($data);
                
            case 'google':
                if ($method !== 'GET') throw new Exception('Method not allowed');
                return $authController->googleAuth();
                
            case 'google/url':
                if ($method === 'GET') {
                    // Return the Google OAuth URL
                    $config = require __DIR__ . '/../config/google.php';
                    $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth' . '?' . http_build_query([
                        'client_id' => $config['client_id'],
                        'redirect_uri' => $config['redirect_uri'],
                        'response_type' => 'code',
                        'scope' => implode(' ', $config['scopes']),
                        'access_type' => 'offline',
                        'prompt' => 'consent',
                    ]);
                    
                    header('Content-Type: application/json');
                    echo json_encode([
                        'status' => 'success',
                        'auth_url' => $authUrl
                    ]);
                    return;
                }
                break;
                
            case 'google/callback':
                if ($method !== 'GET') throw new Exception('Method not allowed');
                if (empty($_GET['code'])) throw new Exception('Authorization code is required');
                return $authController->handleGoogleCallback($_GET['code']);

            case 'me':
                if ($method !== 'GET') throw new Exception('Method not allowed');
                return $authController->getCurrentUser();
                
            default:
                throw new Exception('Endpoint not found', 404);
        }
        
    } catch (Exception $e) {
        http_response_code($e->getCode() ?: 500);
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
}