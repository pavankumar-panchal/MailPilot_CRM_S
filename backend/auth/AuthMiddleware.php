<?php
/**
 * AuthMiddleware - Authentication & Authorization Middleware
 * Protects routes and checks permissions
 * 
 * Usage:
 * - AuthMiddleware::requireAuth() - Requires user to be logged in
 * - AuthMiddleware::requireRole('admin') - Requires specific role
 * - AuthMiddleware::requirePermission('manage_users') - Requires specific permission
 * - AuthMiddleware::verifyCsrfToken() - Verifies CSRF token for POST/PUT/DELETE
 */

require_once __DIR__ . '/AuthModel.php';

class AuthMiddleware {
    private static $authModel;
    
    /**
     * Initialize middleware
     */
    private static function init($db) {
        if (!self::$authModel) {
            self::$authModel = new AuthModel($db);
        }
    }
    
    /**
     * Require user to be authenticated
     */
    public static function requireAuth($db) {
        self::init($db);
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check session
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_token'])) {
            self::unauthorized('Authentication required');
        }
        
        // Verify session token in database
        $session = self::$authModel->verifySession($_SESSION['session_token']);
        
        if (!$session) {
            // Session expired or invalid
            session_destroy();
            self::unauthorized('Session expired. Please login again.');
        }
        
        // Update session data
        $_SESSION['user_id'] = $session['user_id'];
        $_SESSION['user_email'] = $session['email'];
        $_SESSION['user_name'] = $session['name'];
        $_SESSION['user_role'] = $session['role'];
        
        return [
            'user_id' => $session['user_id'],
            'email' => $session['email'],
            'name' => $session['name'],
            'role' => $session['role']
        ];
    }
    
    /**
     * Require specific role
     */
    public static function requireRole($db, $requiredRole) {
        $user = self::requireAuth($db);
        
        $roleHierarchy = [
            'admin' => 3,
            'manager' => 2,
            'user' => 1
        ];
        
        $userLevel = $roleHierarchy[$user['role']] ?? 0;
        $requiredLevel = $roleHierarchy[$requiredRole] ?? 0;
        
        if ($userLevel < $requiredLevel) {
            self::forbidden('Insufficient permissions. ' . ucfirst($requiredRole) . ' role required.');
        }
        
        return $user;
    }
    
    /**
     * Require specific permission
     */
    public static function requirePermission($db, $permission) {
        $user = self::requireAuth($db);
        self::init($db);
        
        $hasPermission = self::$authModel->hasPermission($user['user_id'], $permission);
        
        if (!$hasPermission) {
            self::forbidden("You don't have permission to perform this action.");
        }
        
        return $user;
    }
    
    /**
     * Verify CSRF token for state-changing operations
     */
    public static function verifyCsrfToken() {
        $method = $_SERVER['REQUEST_METHOD'];
        
        // Only check for POST, PUT, DELETE, PATCH
        if (!in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            return true;
        }
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $sessionToken = $_SESSION['csrf_token'] ?? null;
        
        // Get token from header or POST data
        $requestToken = null;
        if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $requestToken = $_SERVER['HTTP_X_CSRF_TOKEN'];
        } elseif (isset($_POST['csrf_token'])) {
            $requestToken = $_POST['csrf_token'];
        } else {
            $input = json_decode(file_get_contents('php://input'), true);
            $requestToken = $input['csrf_token'] ?? null;
        }
        
        if (!$sessionToken || !$requestToken || !hash_equals($sessionToken, $requestToken)) {
            self::forbidden('Invalid CSRF token');
        }
        
        return true;
    }
    
    /**
     * Check if user is admin
     */
    public static function isAdmin($db) {
        $user = self::requireAuth($db);
        return $user['role'] === 'admin';
    }
    
    /**
     * Get current user
     */
    public static function getCurrentUser($db) {
        return self::requireAuth($db);
    }
    
    /**
     * Check if user is logged in (without throwing error)
     */
    public static function check() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['user_id']) && isset($_SESSION['session_token']);
    }
    
    /**
     * Get user from session (without verification)
     */
    public static function user() {
        if (!self::check()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'] ?? null,
            'email' => $_SESSION['user_email'] ?? null,
            'name' => $_SESSION['user_name'] ?? null,
            'role' => $_SESSION['user_role'] ?? null
        ];
    }
    
    /**
     * Return unauthorized response
     */
    private static function unauthorized($message = 'Unauthorized') {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $message,
            'error_code' => 'UNAUTHORIZED'
        ]);
        exit;
    }
    
    /**
     * Return forbidden response
     */
    private static function forbidden($message = 'Forbidden') {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $message,
            'error_code' => 'FORBIDDEN'
        ]);
        exit;
    }
    
    /**
     * Rate limiting helper
     */
    public static function rateLimit($identifier, $maxAttempts = 5, $decayMinutes = 1) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $key = 'rate_limit_' . $identifier;
        $now = time();
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['attempts' => 0, 'reset_at' => $now + ($decayMinutes * 60)];
        }
        
        $data = $_SESSION[$key];
        
        // Reset if decay time passed
        if ($now > $data['reset_at']) {
            $_SESSION[$key] = ['attempts' => 0, 'reset_at' => $now + ($decayMinutes * 60)];
            $data = $_SESSION[$key];
        }
        
        // Check if exceeded
        if ($data['attempts'] >= $maxAttempts) {
            $resetIn = ceil(($data['reset_at'] - $now) / 60);
            http_response_code(429);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => "Too many attempts. Please try again in $resetIn minute(s).",
                'error_code' => 'RATE_LIMIT_EXCEEDED'
            ]);
            exit;
        }
        
        // Increment attempts
        $_SESSION[$key]['attempts']++;
    }
}
?>
