<?php

require_once __DIR__ . '/session_config.php';
require_once __DIR__ . '/security_helpers.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/user_filtering.php';
require_once __DIR__ . '/auth_helper.php';

// Set security headers
setSecurityHeaders();

// Handle CORS securely
handleCors();

// Require authentication
$currentUser = requireAuth();

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

// Don't validate smtp_server_id yet - it will be validated in each method handler
$smtp_server_id = isset($_GET['smtp_server_id']) ? (int)$_GET['smtp_server_id'] : 0;

function getJsonInput() {
    return getValidatedJsonInput();
}

// Handle preflight OPTIONS request
if ($method === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Add new account to a server
if ($method === 'POST') {
    try {
        // Validate smtp_server_id
        $smtp_server_id = validateInteger($smtp_server_id, 1);
        
        $data = getJsonInput();
        if (!$data) {
            throw new InvalidArgumentException('Invalid JSON input');
        }
        
        $email = validateEmail($data['email'] ?? '');
        $password = sanitizeString($data['password'] ?? '', 500);
        if ($email === '' || $password === '') {
            throw new InvalidArgumentException('Email and password required.');
        }
        
        $from_name = sanitizeString($data['from_name'] ?? '', 255);
        $daily_limit = validateInteger($data['daily_limit'] ?? 500, 0, 10000);
        $hourly_limit = validateInteger($data['hourly_limit'] ?? 50, 0, 1000);
        $is_active = validateBoolean($data['is_active'] ?? 1);
        
        // Get current user ID
        $user_id = $currentUser['id'];

        $stmt = $conn->prepare("INSERT INTO smtp_accounts (smtp_server_id, email, from_name, password, daily_limit, hourly_limit, is_active, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssiiii", $smtp_server_id, $email, $from_name, $password, $daily_limit, $hourly_limit, $is_active, $user_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Account added.']);
        } else {
            throw new Exception('Error: ' . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        logSecurityEvent('SMTP account creation failed', ['error' => $e->getMessage()]);
    }
    exit;
}

// Update an existing account
if ($method === 'PUT') {
    try {
        // Validate smtp_server_id
        $smtp_server_id = validateInteger($smtp_server_id, 1);
        
        $data = getJsonInput();
        if (!$data) {
            throw new InvalidArgumentException('Invalid JSON input');
        }
        
        // Log the incoming data for debugging
        error_log("PUT request data: " . json_encode($data));
        error_log("smtp_server_id: $smtp_server_id, account_id from GET: " . ($_GET['account_id'] ?? 'NOT SET'));
        
        $account_id = validateInteger($_GET['account_id'] ?? 0, 1);
        $email = validateEmail($data['email'] ?? '');
        $password = sanitizeString($data['password'] ?? '', 500);
        
        if ($email === '') {
            throw new InvalidArgumentException('Email is required.');
        }
        
        $from_name = sanitizeString($data['from_name'] ?? '', 255);
        $daily_limit = validateInteger($data['daily_limit'] ?? 500, 0, 10000);
        $hourly_limit = validateInteger($data['hourly_limit'] ?? 50, 0, 1000);
        $is_active = validateBoolean($data['is_active'] ?? 1);
        
        // Check if user has permission to update this account
        $isAdmin = isAuthenticatedAdmin();
        $user_id = $currentUser['id'];
        
        error_log("Update account - isAdmin: " . ($isAdmin ? 'YES' : 'NO') . ", user_id: $user_id, account_id: $account_id, smtp_server_id: $smtp_server_id");

        // If password is provided, update it; otherwise keep the existing one
        if (!empty($password)) {
            if ($isAdmin) {
                $stmt = $conn->prepare("UPDATE smtp_accounts SET email = ?, from_name = ?, password = ?, daily_limit = ?, hourly_limit = ?, is_active = ? WHERE id = ? AND smtp_server_id = ?");
                $stmt->bind_param("sssiiiii", $email, $from_name, $password, $daily_limit, $hourly_limit, $is_active, $account_id, $smtp_server_id);
            } else {
                $stmt = $conn->prepare("UPDATE smtp_accounts SET email = ?, from_name = ?, password = ?, daily_limit = ?, hourly_limit = ?, is_active = ? WHERE id = ? AND smtp_server_id = ? AND user_id = ?");
                $stmt->bind_param("sssiiiiii", $email, $from_name, $password, $daily_limit, $hourly_limit, $is_active, $account_id, $smtp_server_id, $user_id);
            }
        } else {
            if ($isAdmin) {
                $stmt = $conn->prepare("UPDATE smtp_accounts SET email = ?, from_name = ?, daily_limit = ?, hourly_limit = ?, is_active = ? WHERE id = ? AND smtp_server_id = ?");
                $stmt->bind_param("ssiiiii", $email, $from_name, $daily_limit, $hourly_limit, $is_active, $account_id, $smtp_server_id);
            } else {
                $stmt = $conn->prepare("UPDATE smtp_accounts SET email = ?, from_name = ?, daily_limit = ?, hourly_limit = ?, is_active = ? WHERE id = ? AND smtp_server_id = ? AND user_id = ?");
                $stmt->bind_param("ssiiiiii", $email, $from_name, $daily_limit, $hourly_limit, $is_active, $account_id, $smtp_server_id, $user_id);
            }
        }
        
        if (!$stmt->execute()) {
            throw new Exception('Update failed: ' . $stmt->error);
        }
        
        // Success even if no rows were changed (already up to date)
        echo json_encode(['success' => true, 'message' => 'Account updated successfully.']);
        $stmt->close();
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        logSecurityEvent('SMTP account update failed', ['error' => $e->getMessage()]);
    }
    exit;
}

// Delete an account from a server
if ($method === 'DELETE') {
    try {
        // Validate smtp_server_id
        $smtp_server_id = validateInteger($smtp_server_id, 1);
        
        $account_id = validateInteger($_GET['account_id'] ?? 0, 1);
        
        // Check if user has permission to delete this account
        $isAdmin = isAuthenticatedAdmin();
        $user_id = $currentUser['id'];
        
        if ($isAdmin) {
            $stmt = $conn->prepare("DELETE FROM smtp_accounts WHERE id = ? AND smtp_server_id = ?");
            $stmt->bind_param("ii", $account_id, $smtp_server_id);
        } else {
            $stmt = $conn->prepare("DELETE FROM smtp_accounts WHERE id = ? AND smtp_server_id = ? AND user_id = ?");
            $stmt->bind_param("iii", $account_id, $smtp_server_id, $user_id);
        }
        
        if (!$stmt->execute()) {
            throw new Exception('Delete failed: ' . $stmt->error);
        }
        
        if ($stmt->affected_rows === 0) {
            throw new Exception('Account not found or already deleted.');
        }
        
        echo json_encode(['success' => true, 'message' => 'Account deleted.']);
        $stmt->close();
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        logSecurityEvent('SMTP account deletion failed', ['error' => $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
exit;