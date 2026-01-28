<?php
/**
 * AuthModel - Database operations for authentication
 * Handles all database queries related to authentication
 */

class AuthModel {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Get user by email
     */
    public function getUserByEmail($email) {
        $stmt = $this->db->prepare("
            SELECT id, email, password_hash, name, role, is_active, is_email_verified,
                   failed_login_attempts, locked_until
            FROM users 
            WHERE email = ? 
            LIMIT 1
        ");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    
    /**
     * Get user by ID
     */
    public function getUserById($userId) {
        $stmt = $this->db->prepare("
            SELECT id, email, name, role, is_active, is_email_verified, created_at, last_login
            FROM users 
            WHERE id = ? 
            LIMIT 1
        ");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    
    /**
     * Create new user
     */
    public function createUser($data) {
        $stmt = $this->db->prepare("
            INSERT INTO users (email, password_hash, name, role, is_active, created_at) 
            VALUES (?, ?, ?, ?, 1, NOW())
        ");
        $stmt->bind_param('ssss', 
            $data['email'], 
            $data['password_hash'], 
            $data['name'], 
            $data['role']
        );
        
        if ($stmt->execute()) {
            return $this->db->insert_id;
        }
        return false;
    }
    
    /**
     * Update user password
     */
    public function updatePassword($userId, $passwordHash) {
        $stmt = $this->db->prepare("
            UPDATE users 
            SET password_hash = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->bind_param('si', $passwordHash, $userId);
        return $stmt->execute();
    }
    
    /**
     * Update last login timestamp
     */
    public function updateLastLogin($userId) {
        $stmt = $this->db->prepare("
            UPDATE users 
            SET last_login = NOW() 
            WHERE id = ?
        ");
        $stmt->bind_param('i', $userId);
        return $stmt->execute();
    }
    
    /**
     * Increment failed login attempts
     */
    public function incrementFailedAttempts($userId) {
        $stmt = $this->db->prepare("
            UPDATE users 
            SET failed_login_attempts = failed_login_attempts + 1 
            WHERE id = ?
        ");
        $stmt->bind_param('i', $userId);
        return $stmt->execute();
    }
    
    /**
     * Reset failed login attempts
     */
    public function resetFailedAttempts($userId) {
        $stmt = $this->db->prepare("
            UPDATE users 
            SET failed_login_attempts = 0, locked_until = NULL 
            WHERE id = ?
        ");
        $stmt->bind_param('i', $userId);
        return $stmt->execute();
    }
    
    /**
     * Lock account temporarily
     */
    public function lockAccount($userId, $minutes = 30) {
        $lockUntil = date('Y-m-d H:i:s', time() + ($minutes * 60));
        $stmt = $this->db->prepare("
            UPDATE users 
            SET locked_until = ? 
            WHERE id = ?
        ");
        $stmt->bind_param('si', $lockUntil, $userId);
        return $stmt->execute();
    }
    
    /**
     * Create session token
     */
    public function createSession($userId, $ipAddress, $userAgent, $duration = 86400) {
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + $duration);
        
        $stmt = $this->db->prepare("
            INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent, expires_at) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('issss', $userId, $token, $ipAddress, $userAgent, $expiresAt);
        
        if ($stmt->execute()) {
            return $token;
        }
        return false;
    }
    
    /**
     * Verify session token
     */
    public function verifySession($token) {
        $stmt = $this->db->prepare("
            SELECT s.user_id, s.expires_at, u.email, u.name, u.role, u.is_active
            FROM user_sessions s
            JOIN users u ON s.user_id = u.id
            WHERE s.session_token = ? AND s.expires_at > NOW() AND u.is_active = 1
            LIMIT 1
        ");
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($session = $result->fetch_assoc()) {
            // Update last activity
            $this->updateSessionActivity($token);
            return $session;
        }
        return false;
    }
    
    /**
     * Update session last activity
     */
    private function updateSessionActivity($token) {
        $stmt = $this->db->prepare("
            UPDATE user_sessions 
            SET last_activity = NOW() 
            WHERE session_token = ?
        ");
        $stmt->bind_param('s', $token);
        return $stmt->execute();
    }
    
    /**
     * Delete session
     */
    public function deleteSession($token) {
        $stmt = $this->db->prepare("
            DELETE FROM user_sessions 
            WHERE session_token = ?
        ");
        $stmt->bind_param('s', $token);
        return $stmt->execute();
    }
    
    /**
     * Delete all sessions for a user
     */
    public function deleteAllUserSessions($userId) {
        $stmt = $this->db->prepare("
            DELETE FROM user_sessions 
            WHERE user_id = ?
        ");
        $stmt->bind_param('i', $userId);
        return $stmt->execute();
    }
    
    /**
     * Create password reset token
     */
    public function createPasswordResetToken($userId) {
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour
        
        $stmt = $this->db->prepare("
            INSERT INTO password_reset_tokens (user_id, token, expires_at) 
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param('iss', $userId, $token, $expiresAt);
        
        if ($stmt->execute()) {
            return $token;
        }
        return false;
    }
    
    /**
     * Verify password reset token
     */
    public function verifyPasswordResetToken($token) {
        $stmt = $this->db->prepare("
            SELECT user_id 
            FROM password_reset_tokens 
            WHERE token = ? AND expires_at > NOW() AND used = 0
            LIMIT 1
        ");
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            return $row['user_id'];
        }
        return false;
    }
    
    /**
     * Mark reset token as used
     */
    public function markResetTokenUsed($token) {
        $stmt = $this->db->prepare("
            UPDATE password_reset_tokens 
            SET used = 1 
            WHERE token = ?
        ");
        $stmt->bind_param('s', $token);
        return $stmt->execute();
    }
    
    /**
     * Create email verification token
     */
    public function createEmailVerificationToken($userId) {
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + 86400); // 24 hours
        
        $stmt = $this->db->prepare("
            INSERT INTO email_verification_tokens (user_id, token, expires_at) 
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param('iss', $userId, $token, $expiresAt);
        
        if ($stmt->execute()) {
            return $token;
        }
        return false;
    }
    
    /**
     * Verify email token
     */
    public function verifyEmailToken($token) {
        $stmt = $this->db->prepare("
            SELECT user_id 
            FROM email_verification_tokens 
            WHERE token = ? AND expires_at > NOW() AND used = 0
            LIMIT 1
        ");
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            return $row['user_id'];
        }
        return false;
    }
    
    /**
     * Mark email as verified
     */
    public function markEmailVerified($userId) {
        $stmt = $this->db->prepare("
            UPDATE users 
            SET is_email_verified = 1 
            WHERE id = ?
        ");
        $stmt->bind_param('i', $userId);
        return $stmt->execute();
    }
    
    /**
     * Mark verification token as used
     */
    public function markVerificationTokenUsed($token) {
        $stmt = $this->db->prepare("
            UPDATE email_verification_tokens 
            SET used = 1 
            WHERE token = ?
        ");
        $stmt->bind_param('s', $token);
        return $stmt->execute();
    }
    
    /**
     * Get user permissions by role
     */
    public function getUserPermissions($role) {
        $stmt = $this->db->prepare("
            SELECT p.name, p.description, p.module
            FROM permissions p
            JOIN role_permissions rp ON p.id = rp.permission_id
            WHERE rp.role = ?
        ");
        $stmt->bind_param('s', $role);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $permissions = [];
        while ($row = $result->fetch_assoc()) {
            $permissions[] = $row['name'];
        }
        return $permissions;
    }
    
    /**
     * Check if user has specific permission
     */
    public function hasPermission($userId, $permissionName) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count
            FROM users u
            JOIN role_permissions rp ON u.role = rp.role
            JOIN permissions p ON rp.permission_id = p.id
            WHERE u.id = ? AND p.name = ?
        ");
        $stmt->bind_param('is', $userId, $permissionName);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['count'] > 0;
    }
    
    /**
     * Log user activity
     */
    public function logActivity($userId, $action, $description = '', $ipAddress = null) {
        $ip = $ipAddress ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $stmt = $this->db->prepare("
            INSERT INTO user_activity_log (user_id, action, description, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('issss', $userId, $action, $description, $ip, $userAgent);
        return $stmt->execute();
    }
    
    /**
     * Get user activity log
     */
    public function getUserActivityLog($userId, $limit = 50) {
        $stmt = $this->db->prepare("
            SELECT action, description, ip_address, created_at
            FROM user_activity_log
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->bind_param('ii', $userId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $logs = [];
        while ($row = $result->fetch_assoc()) {
            $logs[] = $row;
        }
        return $logs;
    }
}
?>
