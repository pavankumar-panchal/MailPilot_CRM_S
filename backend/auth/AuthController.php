<?php
/**
 * AuthController - Professional Authentication Controller
 * Handles login, register, logout, password reset, email verification
 * 
 * Features:
 * - Secure password hashing with PASSWORD_BCRYPT
 * - Session-based authentication with database tokens
 * - Rate limiting for login attempts
 * - Account lockout after failed attempts
 * - CSRF protection
 * - Activity logging
 * - Role-based access control
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/AuthModel.php';
require_once __DIR__ . '/AuthMiddleware.php';
require_once __DIR__ . '/../includes/security_helpers.php';

class AuthController {
    private $authModel;
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
        $this->authModel = new AuthModel($db);
    }
    
    /**
     * User Registration
     */
    public function register($data) {
        try {
            // Validate input
            $name = sanitizeString($data['name'] ?? '', 100);
            $email = validateEmail($data['email'] ?? '');
            $password = $data['password'] ?? '';
            $role = $data['role'] ?? 'user';
            
            // Validation checks
            if (empty($name)) {
                return $this->jsonResponse(false, 'Name is required', 400);
            }
            
            if (strlen($password) < 8) {
                return $this->jsonResponse(false, 'Password must be at least 8 characters', 400);
            }
            
            // Password strength check
            if (!$this->isPasswordStrong($password)) {
                return $this->jsonResponse(false, 'Password must contain uppercase, lowercase, number, and special character', 400);
            }
            
            // Validate role
            $validRoles = ['admin', 'user', 'manager'];
            if (!in_array($role, $validRoles)) {
                $role = 'user';
            }
            
            // Check if email exists
            if ($this->authModel->getUserByEmail($email)) {
                return $this->jsonResponse(false, 'Email already registered', 409);
            }
            
            // Hash password
            $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            
            // Create user
            $userId = $this->authModel->createUser([
                'email' => $email,
                'password_hash' => $passwordHash,
                'name' => $name,
                'role' => $role
            ]);
            
            if ($userId) {
                // Generate email verification token
                $verificationToken = $this->authModel->createEmailVerificationToken($userId);
                
                // TODO: Send verification email
                // $this->sendVerificationEmail($email, $name, $verificationToken);
                
                // Log activity
                $this->authModel->logActivity($userId, 'user_registered', 'New user account created');
                
                return $this->jsonResponse(true, 'Registration successful! Please check your email to verify your account.', 201, [
                    'user_id' => $userId,
                    'verification_required' => true
                ]);
            }
            
            return $this->jsonResponse(false, 'Registration failed', 500);
            
        } catch (Exception $e) {
            error_log('Registration error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Registration failed', 500);
        }
    }
    
    /**
     * User Login
     */
    public function login($data) {
        try {
            $email = validateEmail($data['email'] ?? '');
            $password = $data['password'] ?? '';
            $rememberMe = isset($data['remember_me']) && $data['remember_me'];
            
            // Get user
            $user = $this->authModel->getUserByEmail($email);
            
            if (!$user) {
                // Log failed attempt
                $this->authModel->logActivity(null, 'login_failed', "Failed login attempt for: $email", $_SERVER['REMOTE_ADDR']);
                return $this->jsonResponse(false, 'Invalid credentials', 401);
            }
            
            // Check if account is locked
            if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                $minutesLeft = ceil((strtotime($user['locked_until']) - time()) / 60);
                return $this->jsonResponse(false, "Account locked due to multiple failed attempts. Try again in $minutesLeft minutes.", 403);
            }
            
            // Check if account is active
            if (!$user['is_active']) {
                return $this->jsonResponse(false, 'Account is disabled. Contact administrator.', 403);
            }
            
            // Verify password
            if (!password_verify($password, $user['password_hash'])) {
                // Increment failed attempts
                $this->authModel->incrementFailedAttempts($user['id']);
                
                // Check if should lock account (5 failed attempts)
                if ($user['failed_login_attempts'] + 1 >= 5) {
                    $this->authModel->lockAccount($user['id'], 30); // Lock for 30 minutes
                    $this->authModel->logActivity($user['id'], 'account_locked', 'Account locked due to multiple failed login attempts');
                    return $this->jsonResponse(false, 'Account locked due to multiple failed attempts. Try again in 30 minutes.', 403);
                }
                
                $this->authModel->logActivity($user['id'], 'login_failed', 'Failed login attempt - wrong password');
                return $this->jsonResponse(false, 'Invalid credentials', 401);
            }
            
            // Successful login - reset failed attempts
            $this->authModel->resetFailedAttempts($user['id']);
            
            // Create session
            $sessionDuration = $rememberMe ? (30 * 24 * 60 * 60) : (24 * 60 * 60); // 30 days or 24 hours
            $sessionToken = $this->authModel->createSession(
                $user['id'],
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                $sessionDuration
            );
            
            // Start PHP session and store data
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['session_token'] = $sessionToken;
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            
            // Update last login
            $this->authModel->updateLastLogin($user['id']);
            
            // Log activity
            $this->authModel->logActivity($user['id'], 'user_login', 'User logged in successfully');
            
            // Get user permissions
            $permissions = $this->authModel->getUserPermissions($user['role']);
            
            return $this->jsonResponse(true, 'Login successful', 200, [
                'user' => [
                    'id' => $user['id'],
                    'email' => $user['email'],
                    'name' => $user['name'],
                    'role' => $user['role'],
                    'permissions' => $permissions
                ],
                'session_token' => $sessionToken,
                'expires_at' => date('Y-m-d H:i:s', time() + $sessionDuration),
                'csrf_token' => $_SESSION['csrf_token']
            ]);
            
        } catch (Exception $e) {
            error_log('Login error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Login failed', 500);
        }
    }
    
    /**
     * User Logout
     */
    public function logout() {
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            $userId = $_SESSION['user_id'] ?? null;
            $sessionToken = $_SESSION['session_token'] ?? null;
            
            // Delete session from database
            if ($sessionToken) {
                $this->authModel->deleteSession($sessionToken);
            }
            
            // Log activity
            if ($userId) {
                $this->authModel->logActivity($userId, 'user_logout', 'User logged out');
            }
            
            // Destroy PHP session
            $_SESSION = array();
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            session_destroy();
            
            return $this->jsonResponse(true, 'Logged out successfully');
            
        } catch (Exception $e) {
            error_log('Logout error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Logout failed', 500);
        }
    }
    
    /**
     * Request Password Reset
     */
    public function requestPasswordReset($data) {
        try {
            $email = validateEmail($data['email'] ?? '');
            
            $user = $this->authModel->getUserByEmail($email);
            
            // Always return success (don't reveal if email exists)
            if (!$user) {
                // Still return success for security
                return $this->jsonResponse(true, 'If the email exists, a password reset link has been sent.');
            }
            
            // Create reset token
            $resetToken = $this->authModel->createPasswordResetToken($user['id']);
            
            // TODO: Send password reset email
            // $this->sendPasswordResetEmail($email, $user['name'], $resetToken);
            
            // Log activity
            $this->authModel->logActivity($user['id'], 'password_reset_requested', 'Password reset requested');
            
            return $this->jsonResponse(true, 'If the email exists, a password reset link has been sent.');
            
        } catch (Exception $e) {
            error_log('Password reset request error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Request failed', 500);
        }
    }
    
    /**
     * Reset Password with Token
     */
    public function resetPassword($data) {
        try {
            $token = $data['token'] ?? '';
            $newPassword = $data['password'] ?? '';
            
            if (strlen($newPassword) < 8) {
                return $this->jsonResponse(false, 'Password must be at least 8 characters', 400);
            }
            
            if (!$this->isPasswordStrong($newPassword)) {
                return $this->jsonResponse(false, 'Password must contain uppercase, lowercase, number, and special character', 400);
            }
            
            // Verify token
            $userId = $this->authModel->verifyPasswordResetToken($token);
            
            if (!$userId) {
                return $this->jsonResponse(false, 'Invalid or expired token', 400);
            }
            
            // Hash new password
            $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
            
            // Update password
            if ($this->authModel->updatePassword($userId, $passwordHash)) {
                // Mark token as used
                $this->authModel->markResetTokenUsed($token);
                
                // Invalidate all sessions for this user
                $this->authModel->deleteAllUserSessions($userId);
                
                // Log activity
                $this->authModel->logActivity($userId, 'password_reset', 'Password was reset successfully');
                
                return $this->jsonResponse(true, 'Password reset successfully. Please login with your new password.');
            }
            
            return $this->jsonResponse(false, 'Password reset failed', 500);
            
        } catch (Exception $e) {
            error_log('Password reset error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Password reset failed', 500);
        }
    }
    
    /**
     * Verify Email with Token
     */
    public function verifyEmail($data) {
        try {
            $token = $data['token'] ?? '';
            
            $userId = $this->authModel->verifyEmailToken($token);
            
            if (!$userId) {
                return $this->jsonResponse(false, 'Invalid or expired verification token', 400);
            }
            
            // Mark email as verified
            if ($this->authModel->markEmailVerified($userId)) {
                // Mark token as used
                $this->authModel->markVerificationTokenUsed($token);
                
                // Log activity
                $this->authModel->logActivity($userId, 'email_verified', 'Email address verified');
                
                return $this->jsonResponse(true, 'Email verified successfully! You can now login.');
            }
            
            return $this->jsonResponse(false, 'Email verification failed', 500);
            
        } catch (Exception $e) {
            error_log('Email verification error: ' . $e->getMessage());
            return $this->jsonResponse(false, 'Email verification failed', 500);
        }
    }
    
    /**
     * Check if password is strong enough
     */
    private function isPasswordStrong($password) {
        // At least 8 characters, 1 uppercase, 1 lowercase, 1 number, 1 special char
        return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $password);
    }
    
    /**
     * JSON Response Helper
     */
    private function jsonResponse($success, $message, $code = 200, $data = null) {
        http_response_code($code);
        $response = [
            'success' => $success,
            'message' => $message
        ];
        if ($data) {
            $response = array_merge($response, $data);
        }
        return json_encode($response);
    }
}
?>
