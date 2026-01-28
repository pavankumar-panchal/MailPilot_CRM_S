-- ============================================
-- Relyon CRM - AUTHENTICATION SYSTEM
-- Database Schema for Secure Authentication
-- ============================================

-- 1. Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'user', 'manager') DEFAULT 'user',
    is_active TINYINT(1) DEFAULT 1,
    is_email_verified TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    failed_login_attempts INT DEFAULT 0,
    locked_until TIMESTAMP NULL,
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Sessions Table (for secure session management)
CREATE TABLE IF NOT EXISTS user_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    session_token VARCHAR(64) NOT NULL UNIQUE,
    ip_address VARCHAR(45),
    user_agent TEXT,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (session_token),
    INDEX idx_user_id (user_id),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Password Reset Tokens Table
CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Email Verification Tokens Table
CREATE TABLE IF NOT EXISTS email_verification_tokens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Permissions Table (for fine-grained RBAC)
CREATE TABLE IF NOT EXISTS permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    module VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_module (module)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Role Permissions Junction Table
CREATE TABLE IF NOT EXISTS role_permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    role ENUM('admin', 'user', 'manager') NOT NULL,
    permission_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_role_permission (role, permission_id),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. User Activity Log (for audit trail)
CREATE TABLE IF NOT EXISTS user_activity_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- INITIAL DATA SETUP
-- ============================================

-- Insert default permissions
INSERT INTO permissions (name, description, module) VALUES
('view_dashboard', 'Can view dashboard', 'dashboard'),
('manage_users', 'Can create, edit, delete users', 'users'),
('view_users', 'Can view users list', 'users'),
('manage_campaigns', 'Can create, edit, delete campaigns', 'campaigns'),
('view_campaigns', 'Can view campaigns', 'campaigns'),
('manage_smtp', 'Can manage SMTP accounts', 'smtp'),
('view_smtp', 'Can view SMTP accounts', 'smtp'),
('manage_templates', 'Can manage email templates', 'templates'),
('view_templates', 'Can view email templates', 'templates'),
('view_reports', 'Can view reports', 'reports'),
('manage_settings', 'Can manage system settings', 'settings');

-- Assign permissions to admin role
INSERT INTO role_permissions (role, permission_id)
SELECT 'admin', id FROM permissions;

-- Assign basic permissions to user role
INSERT INTO role_permissions (role, permission_id)
SELECT 'user', id FROM permissions WHERE name IN (
    'view_dashboard',
    'view_campaigns',
    'view_smtp',
    'view_templates',
    'view_reports'
);

-- Assign manager permissions (between admin and user)
INSERT INTO role_permissions (role, permission_id)
SELECT 'manager', id FROM permissions WHERE name IN (
    'view_dashboard',
    'view_users',
    'manage_campaigns',
    'view_campaigns',
    'view_smtp',
    'manage_templates',
    'view_templates',
    'view_reports'
);

-- Create default admin user
-- Password: Admin@123 (CHANGE THIS IMMEDIATELY IN PRODUCTION!)
INSERT INTO users (email, password_hash, name, role, is_active, is_email_verified) VALUES
('admin@mailpilot.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin', 1, 1);

-- Create default user
-- Password: User@123 (CHANGE THIS IN PRODUCTION!)
INSERT INTO users (email, password_hash, name, role, is_active, is_email_verified) VALUES
('user@mailpilot.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Test User', 'user', 1, 1);

-- ============================================
-- CLEANUP PROCEDURES (Run periodically via cron)
-- ============================================

-- Procedure to clean expired sessions
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS cleanup_expired_sessions()
BEGIN
    DELETE FROM user_sessions WHERE expires_at < NOW();
END //
DELIMITER ;

-- Procedure to clean expired password reset tokens
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS cleanup_expired_reset_tokens()
BEGIN
    DELETE FROM password_reset_tokens WHERE expires_at < NOW();
END //
DELIMITER ;

-- Procedure to clean expired email verification tokens
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS cleanup_expired_verification_tokens()
BEGIN
    DELETE FROM email_verification_tokens WHERE expires_at < NOW();
END //
DELIMITER ;

-- ============================================
-- USAGE NOTES
-- ============================================
-- 1. Run this schema on your MySQL database
-- 2. Change default passwords immediately
-- 3. Set up cron jobs to run cleanup procedures daily
-- 4. Regular backups of users and activity_log tables
-- 5. Consider adding 2FA in future enhancements
