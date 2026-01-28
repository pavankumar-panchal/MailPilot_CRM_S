<?php
/**
 * Centralized Session Configuration
 * Ensures consistent session handling across all backend files
 */

// Set timezone to Asia/Kolkata globally
date_default_timezone_set('Asia/Kolkata');

// Only configure if session not started
if (session_status() === PHP_SESSION_NONE) {
    // Use system temp directory for sessions (works on all servers)
    $sessionPath = sys_get_temp_dir() . '/mailpilot_sessions';
    if (!is_dir($sessionPath)) {
        @mkdir($sessionPath, 0777, true);
        @chmod($sessionPath, 0777);
    }
    if (is_dir($sessionPath) && is_writable($sessionPath)) {
        session_save_path($sessionPath);
    }
    // If custom path fails, PHP will use default system path
    
    // Set session name (consistent across all files)
    session_name('MAILPILOT_SESSION');
    
    // Configure session cookie parameters BEFORE starting session
    // Note: For localhost, do NOT set 'domain' to avoid browsers rejecting the cookie
    // Detect if running on HTTPS
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
                || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
                || (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    
    session_set_cookie_params([
        'lifetime' => 86400,        // 24 hours
        'path' => '/',              // Available across entire site
        // 'domain' omitted for host-only cookie (works for both localhost and production)
        'secure' => $isHttps,       // true for HTTPS, false for HTTP
        'httponly' => false,        // false allows JavaScript access for debugging
        'samesite' => 'Lax'         // Lax allows same-site navigation
    ]);
    
    // Additional session settings
    ini_set('session.gc_maxlifetime', 86400);  // 24 hours garbage collection
    ini_set('session.use_only_cookies', 1);     // Only use cookies, no URL params
    ini_set('session.cookie_lifetime', 86400);  // 24 hours cookie lifetime
    
    // Start the session
    session_start();
    
    // Log session info for debugging
    error_log("session_config.php - Session ID: " . session_id() . " | Has user_id: " . (isset($_SESSION['user_id']) ? 'YES' : 'NO'));
}
