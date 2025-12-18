<?php

require_once __DIR__ . '/security_helpers.php';
require_once __DIR__ . '/../config/db.php';

// Set security headers
setSecurityHeaders();

// Handle CORS securely
handleCors();

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$smtp_server_id = validateInteger($_GET['smtp_server_id'] ?? 0, 1);

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
        $data = getJsonInput();
        if (!$data) {
            throw new InvalidArgumentException('Invalid JSON input');
        }
        
        $email = validateEmail($data['email'] ?? '');
        $password = sanitizeString($data['password'] ?? '', 500);
        if ($email === '' || $password === '') {
            throw new InvalidArgumentException('Email and password required.');
        }
        
        $daily_limit = validateInteger($data['daily_limit'] ?? 500, 0, 10000);
        $hourly_limit = validateInteger($data['hourly_limit'] ?? 50, 0, 1000);
        $is_active = validateBoolean($data['is_active'] ?? 1);

        $stmt = $conn->prepare("INSERT INTO smtp_accounts (smtp_server_id, email, password, daily_limit, hourly_limit, is_active) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issiii", $smtp_server_id, $email, $password, $daily_limit, $hourly_limit, $is_active);
        
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
        $data = getJsonInput();
        if (!$data) {
            throw new InvalidArgumentException('Invalid JSON input');
        }
        
        $account_id = validateInteger($_GET['account_id'] ?? 0, 1);
        $email = validateEmail($data['email'] ?? '');
        $password = sanitizeString($data['password'] ?? '', 500);
        
        if ($email === '') {
            throw new InvalidArgumentException('Email is required.');
        }
        
        $daily_limit = validateInteger($data['daily_limit'] ?? 500, 0, 10000);
        $hourly_limit = validateInteger($data['hourly_limit'] ?? 50, 0, 1000);
        $is_active = validateBoolean($data['is_active'] ?? 1);

        // If password is provided, update it; otherwise keep the existing one
        if (!empty($password)) {
            $stmt = $conn->prepare("UPDATE smtp_accounts SET email = ?, password = ?, daily_limit = ?, hourly_limit = ?, is_active = ? WHERE id = ? AND smtp_server_id = ?");
            $stmt->bind_param("ssiiii", $email, $password, $daily_limit, $hourly_limit, $is_active, $account_id, $smtp_server_id);
        } else {
            $stmt = $conn->prepare("UPDATE smtp_accounts SET email = ?, daily_limit = ?, hourly_limit = ?, is_active = ? WHERE id = ? AND smtp_server_id = ?");
            $stmt->bind_param("siiiii", $email, $daily_limit, $hourly_limit, $is_active, $account_id, $smtp_server_id);
        }
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Account updated successfully.']);
        } else {
            throw new Exception('Error: ' . $stmt->error);
        }
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
        $account_id = validateInteger($_GET['account_id'] ?? 0, 1);
        
        $stmt = $conn->prepare("DELETE FROM smtp_accounts WHERE id = ? AND smtp_server_id = ?");
        $stmt->bind_param("ii", $account_id, $smtp_server_id);
        $stmt->execute();
        $stmt->close();
        
        echo json_encode(['success' => true, 'message' => 'Account deleted.']);
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