<?php
/**
 * User-based data filtering migration
 * Adds user_id columns to tables for multi-user support
 * This runs automatically on first API call after update
 */

function ensureUserIdColumns($conn) {
    try {
        $dbNameRes = $conn->query("SELECT DATABASE() as db");
        $dbName = $dbNameRes ? $dbNameRes->fetch_assoc()['db'] : '';
        if (!$dbName) return;

        $tables = ['smtp_servers', 'smtp_accounts', 'campaign_master', 'mail_templates', 'csv_list', 'emails', 'imported_recipients'];
        
        foreach ($tables as $table) {
            // Check if user_id column exists
            $checkSql = "SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS 
                         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = 'user_id'";
            $stmt = $conn->prepare($checkSql);
            $stmt->bind_param('ss', $dbName, $table);
            $stmt->execute();
            $result = $stmt->get_result();
            $hasColumn = (int)$result->fetch_assoc()['cnt'] > 0;
            $stmt->close();
            
            if (!$hasColumn) {
                // Add user_id column
                $alterSql = "ALTER TABLE $table ADD COLUMN user_id INT NULL DEFAULT NULL";
                if ($conn->query($alterSql)) {
                    error_log("Added user_id column to $table");
                    // Add index for better performance
                    $conn->query("ALTER TABLE $table ADD INDEX idx_user_id (user_id)");
                }
            }
        }
    } catch (Exception $e) {
        error_log("User ID migration warning: " . $e->getMessage());
    }
}

/**
 * Get current user from session
 */
function getCurrentUser() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'email' => $_SESSION['user_email'] ?? '',
        'name' => $_SESSION['user_name'] ?? '',
        'role' => $_SESSION['user_role'] ?? 'user'
    ];
}

/**
 * Check if user is admin
 */
function isAdmin() {
    $user = getCurrentUser();
    return $user && $user['role'] === 'admin';
}

/**
 * Get SQL WHERE clause for user filtering
 * Returns empty string for admin, or "WHERE user_id = X" for regular users
 */
function getUserFilterWhere($tableAlias = '') {
    // Use isAuthenticatedAdmin() to support both session and token auth
    $isAdmin = function_exists('isAuthenticatedAdmin') && isAuthenticatedAdmin();
    
    error_log("getUserFilterWhere called - isAdmin: " . ($isAdmin ? 'YES' : 'NO'));
    
    if ($isAdmin) {
        error_log("getUserFilterWhere - Returning EMPTY (admin mode)");
        return ''; // Admin sees everything - no filtering
    }
    
    // Try token-based auth first, fall back to session
    $user = function_exists('getAuthenticatedUser') ? getAuthenticatedUser() : getCurrentUser();
    
    if (!$user) {
        error_log("getUserFilterWhere - No user, blocking");
        return 'WHERE 1=0'; // No access if not logged in
    }
    
    $prefix = $tableAlias ? "$tableAlias." : '';
    $filter = "WHERE {$prefix}user_id = " . intval($user['id']);
    error_log("getUserFilterWhere - Returning: $filter");
    return $filter;
}

/**
 * Get SQL AND clause for user filtering (when WHERE already exists)
 * Returns empty string for admin, or "AND user_id = X" for regular users
 */
function getUserFilterAnd($tableAlias = '') {
    // Use isAuthenticatedAdmin() to support both session and token auth
    $isAdmin = function_exists('isAuthenticatedAdmin') && isAuthenticatedAdmin();
    
    if ($isAdmin) {
        return ''; // Admin sees everything - no filtering
    }
    
    // Try token-based auth first, fall back to session
    $user = function_exists('getAuthenticatedUser') ? getAuthenticatedUser() : getCurrentUser();
    
    if (!$user) {
        return 'AND 1=0'; // No access if not logged in
    }
    
    $prefix = $tableAlias ? "$tableAlias." : '';
    return "AND {$prefix}user_id = " . intval($user['id']);
}

/**
 * Check if user can access a record
 */
function canAccessRecord($recordUserId) {
    if (isAuthenticatedAdmin()) {
        return true;
    }
    
    // Use getAuthenticatedUser() to support both session and token auth
    $user = function_exists('getAuthenticatedUser') ? getAuthenticatedUser() : getCurrentUser();
    if (!$user) {
        return false;
    }
    
    return intval($recordUserId) === intval($user['id']);
}

/**
 * Get current user_id for INSERT operations
 * Returns user_id or NULL if not logged in
 */
function getCurrentUserId() {
    $user = getCurrentUser();
    return $user ? intval($user['id']) : null;
}

/**
 * Get user_id SQL value for INSERT (returns "NULL" or the user_id)
 */
function getUserIdSqlValue() {
    $userId = getCurrentUserId();
    return $userId ? $userId : 'NULL';
}

/**
 * Alias for getUserFilterWhere - used by auth_helper.php
 * Get SQL WHERE clause for user filtering based on authenticated user
 * Returns empty string for admin, or "WHERE user_id = X" for regular users
 */
function getAuthFilterWhere($tableAlias = '') {
    return getUserFilterWhere($tableAlias);
}

/**
 * Alias for getUserFilterAnd - used by auth_helper.php
 * Get SQL AND clause for user filtering (when WHERE already exists)
 * Returns empty string for admin, or "AND user_id = X" for regular users
 */
function getAuthFilterAnd($tableAlias = '') {
    return getUserFilterAnd($tableAlias);
}
