<?php
/**
 * Authentication API Endpoints
 * RESTful API for authentication operations
 * 
 * Endpoints:
 * POST /auth/register - Register new user
 * POST /auth/login - Login
 * POST /auth/logout - Logout
 * POST /auth/forgot-password - Request password reset
 * POST /auth/reset-password - Reset password with token
 * POST /auth/verify-email - Verify email with token
 * GET /auth/me - Get current user info
 * GET /auth/check - Check if authenticated
 */

// CORS Headers
header('Access-Control-Allow-Origin: http://localhost:5174');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, Authorization');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/AuthController.php';
require_once __DIR__ . '/../auth/AuthMiddleware.php';

// Initialize controller
$authController = new AuthController($conn);

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI'];
$action = basename(parse_url($requestUri, PHP_URL_PATH));

// Parse input
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// Router
try {
    switch ($action) {
        
        // Register new user
        case 'register':
            if ($method === 'POST') {
                echo $authController->register($input);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;
        
        // User login
        case 'login':
            if ($method === 'POST') {
                // Rate limiting
                AuthMiddleware::rateLimit('login_' . ($input['email'] ?? 'unknown'), 5, 5);
                echo $authController->login($input);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;
        
        // User logout
        case 'logout':
            if ($method === 'POST') {
                echo $authController->logout();
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;
        
        // Request password reset
        case 'forgot-password':
            if ($method === 'POST') {
                // Rate limiting
                AuthMiddleware::rateLimit('forgot_' . ($input['email'] ?? 'unknown'), 3, 15);
                echo $authController->requestPasswordReset($input);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;
        
        // Reset password with token
        case 'reset-password':
            if ($method === 'POST') {
                echo $authController->resetPassword($input);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;
        
        // Verify email
        case 'verify-email':
            if ($method === 'POST') {
                echo $authController->verifyEmail($input);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;
        
        // Get current user (requires auth)
        case 'me':
            if ($method === 'GET') {
                $user = AuthMiddleware::requireAuth($conn);
                echo json_encode([
                    'success' => true,
                    'user' => $user
                ]);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;
        
        // Check if authenticated
        case 'check':
            if ($method === 'GET') {
                $isAuthenticated = AuthMiddleware::check();
                $user = $isAuthenticated ? AuthMiddleware::user() : null;
                echo json_encode([
                    'success' => true,
                    'authenticated' => $isAuthenticated,
                    'user' => $user
                ]);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;
        
        default:
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Endpoint not found'
            ]);
            break;
    }
    
} catch (Exception $e) {
    error_log('Auth API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred. Please try again.'
    ]);
}
?>
