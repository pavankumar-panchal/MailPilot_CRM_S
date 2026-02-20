<?php
/**
 * Unified Authentication Helper
 * Supports BOTH session cookies AND token-based authentication
 * This ensures auth works even if browser cookies fail
 * 
 * NOTE: This file requires session_config.php and db.php to be loaded BEFORE including it
 */

// Ensure timezone is set
date_default_timezone_set('Asia/Kolkata');

/**
 * Get current authenticated user from session OR token
 * This is the ONLY function you need to call for authentication
 * 
 * @return array|null User data or null if not authenticated
 */
function getAuthenticatedUser() {
    global $conn;
    
    // Ensure DB connection exists
    if (!isset($conn) || !$conn) {
        // error_log("Auth helper: No database connection available");
        return null;
    }
    
    // Try session first (fastest)
    if (isset($_SESSION['user_id']) && isset($_SESSION['token_expiry'])) {
        // Check if token hasn't expired
        if ($_SESSION['token_expiry'] > time()) {
            // error_log("Auth: Session auth successful for user ID: " . $_SESSION['user_id']);
            return [
                'id' => $_SESSION['user_id'],
                'email' => $_SESSION['user_email'] ?? '',
                'name' => $_SESSION['user_name'] ?? '',
                'role' => $_SESSION['user_role'] ?? 'user'
            ];
        } else {
            // error_log("Auth: Session expired for user ID: " . $_SESSION['user_id']);
        }
    }
    
    // Fallback: Try Authorization header token
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    
    // error_log("Auth: HTTP_AUTHORIZATION from server: " . ($_SERVER['HTTP_AUTHORIZATION'] ?? 'NOT SET'));
    // error_log("Auth: Authorization from headers: " . ($authHeader ? 'Present (' . substr($authHeader, 0, 20) . '...)' : 'Missing'));
    
    if (!$authHeader) {
        // error_log("Auth: No authorization header found");
        return null;
    }
    
    // Extract token from "Bearer <token>" format
    $token = null;
    if (preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
        $token = $matches[1];
    } else {
        $token = $authHeader; // Plain token without "Bearer"
    }
    
    if (empty($token)) {
        return null;
    }
    
    // Validate token against database
    try {
        $stmt = $conn->prepare('
            SELECT u.id, u.email, u.name, u.role, t.expires_at
            FROM user_tokens t
            JOIN users u ON t.user_id = u.id
            WHERE t.token = ? 
            AND t.expires_at > NOW()
            AND u.is_active = 1
            LIMIT 1
        ');
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            // Update last_used_at for the token
            $updateStmt = $conn->prepare('UPDATE user_tokens SET last_used_at = NOW() WHERE token = ?');
            $updateStmt->bind_param('s', $token);
            $updateStmt->execute();
            $updateStmt->close();
            
            // error_log("Token auth successful for user: " . $row['email'] . " (ID: " . $row['id'] . ")");
            
            // Populate session for subsequent requests
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['user_email'] = $row['email'];
            $_SESSION['user_name'] = $row['name'];
            $_SESSION['user_role'] = $row['role'];
            $_SESSION['token'] = $token;
            $_SESSION['token_expiry'] = strtotime($row['expires_at']);
            
            return [
                'id' => $row['id'],
                'email' => $row['email'],
                'name' => $row['name'],
                'role' => $row['role']
            ];
        } else {
            // error_log("Token validation failed: Token not found or expired");
        }
        $stmt->close();
    } catch (Exception $e) {
        // error_log("Token validation error: " . $e->getMessage());
    }
    
    return null;
}

/**
 * Require authentication - send 401 if not authenticated
 * Use this at the top of protected endpoints
 */
function requireAuth() {
    $user = getAuthenticatedUser();
    if (!$user) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Unauthorized. Please log in.']);
        exit;
    }
    return $user;
}

/**
 * Check if current user is admin
 */
function isAuthenticatedAdmin() {
    $user = getAuthenticatedUser();
    return $user && $user['role'] === 'admin';
}
