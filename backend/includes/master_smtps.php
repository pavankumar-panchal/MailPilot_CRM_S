<?php

error_log("=== MASTER_SMTPS.PHP CALLED === Method: " . ($_SERVER['REQUEST_METHOD'] ?? 'unknown') . ", Query: " . ($_SERVER['QUERY_STRING'] ?? 'none'));

// Use centralized session configuration
require_once __DIR__ . '/session_config.php';
require_once __DIR__ . '/security_helpers.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/user_filtering.php';
require_once __DIR__ . '/auth_helper.php';

// Set security headers
setSecurityHeaders();

// Handle CORS securely
handleCors();

// Ensure user_id columns exist
ensureUserIdColumns($conn);

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

function getJsonInput() {
    return getValidatedJsonInput();
}

// GET: List all SMTP servers with their accounts
if ($method === 'GET') {
    // Use unified auth (supports both session cookies and tokens)
    $currentUser = requireAuth();
    $isAdmin = isAuthenticatedAdmin();
    
    error_log("=== SMTP GET REQUEST ===");
    error_log("User: " . json_encode($currentUser));
    error_log("Is Admin: " . ($isAdmin ? 'YES' : 'NO'));
    
    $userFilter = getAuthFilterWhere();
    error_log("User Filter: '$userFilter'");
    
    $query = "SELECT * FROM smtp_servers " . $userFilter . " ORDER BY id DESC";
    error_log("Query: $query");
    
    $serversRes = $conn->query($query);
    $servers = [];

    while ($server = $serversRes->fetch_assoc()) {
        $server['is_active'] = (bool)$server['is_active'];

        // Fetch accounts for each server (also filter by user_id if not admin)
        $accountUserFilter = getAuthFilterAnd();
        $accountsRes = $conn->query("SELECT * FROM smtp_accounts WHERE smtp_server_id = {$server['id']} $accountUserFilter ORDER BY id ASC");
        $accounts = [];
        while ($acc = $accountsRes->fetch_assoc()) {
            $acc['is_active'] = (bool)$acc['is_active'];
            $accounts[] = $acc;
        }
        $server['accounts'] = $accounts;
        $servers[] = $server;
    }
    
    echo json_encode(['success' => true, 'data' => $servers]);
    $conn->close();
    exit;
}

// POST: Add new SMTP server + accounts
if ($method === 'POST') {
    // Require authentication
    $currentUser = requireAuth();
    error_log("master_smtps.php POST - User: " . json_encode($currentUser));
    
    try {
        $data = getJsonInput();
        if (!$data) {
            throw new InvalidArgumentException('Invalid JSON input');
        }

        // Validate and sanitize inputs
        $name = sanitizeString($data['name'] ?? '', 255);
        $host = validateHost($data['host'] ?? '');
        $port = validatePort($data['port'] ?? 465);
        $encryption = validateEncryption($data['encryption'] ?? '');
        $received_email = !empty($data['received_email']) ? validateEmail($data['received_email']) : '';
        $is_active = validateBoolean($data['is_active'] ?? 1);
        
        // Get user ID (already verified above)
        $user_id = $currentUser['id'];

        // Use prepared statement for INSERT
        $stmt = $conn->prepare("INSERT INTO smtp_servers (name, host, port, encryption, received_email, is_active, user_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssissii", $name, $host, $port, $encryption, $received_email, $is_active, $user_id);
        
        if (!$stmt->execute()) {
            throw new Exception('Error adding SMTP server: ' . $stmt->error);
        }

        $serverId = $stmt->insert_id;
        $stmt->close();

        // Insert accounts if provided
        if (!empty($data['accounts']) && is_array($data['accounts'])) {
            $stmt_acc = $conn->prepare("INSERT INTO smtp_accounts (smtp_server_id, email, password, daily_limit, hourly_limit, is_active, user_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
            
            foreach ($data['accounts'] as $acc) {
                try {
                    $email = validateEmail($acc['email'] ?? '');
                    $password = sanitizeString($acc['password'] ?? '', 500);
                    if ($email === '' || $password === '') continue;

                    $daily_limit = validateInteger($acc['daily_limit'] ?? 500, 0, 10000);
                    $hourly_limit = validateInteger($acc['hourly_limit'] ?? 100, 0, 1000);
                    $acc_active = validateBoolean($acc['is_active'] ?? 1);

                    $stmt_acc->bind_param("issiiii", $serverId, $email, $password, $daily_limit, $hourly_limit, $acc_active, $user_id);
                    $stmt_acc->execute();
                } catch (Exception $e) {
                    // Log and continue with next account
                    logSecurityEvent('Account validation failed', ['error' => $e->getMessage()]);
                    continue;
                }
            }
            $stmt_acc->close();
        }

        echo json_encode(['success' => true, 'message' => 'SMTP server and accounts added successfully!']);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        logSecurityEvent('SMTP server creation failed', ['error' => $e->getMessage()]);
    }
    $conn->close();
    exit;
}

// PUT: Update SMTP server or accounts
if ($method === 'PUT') {
    // Require authentication
    $currentUser = requireAuth();
    error_log("master_smtps.php PUT - User: " . json_encode($currentUser));
    
    try {
        parse_str($_SERVER['QUERY_STRING'], $query);
        $id = validateInteger($query['id'] ?? 0, 1);
        
        // Check if user can access this server
        if (!isAdmin()) {
            $checkStmt = $conn->prepare("SELECT user_id FROM smtp_servers WHERE id = ?");
            $checkStmt->bind_param("i", $id);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            $serverData = $checkResult->fetch_assoc();
            $checkStmt->close();
            
            if (!$serverData || !canAccessRecord($serverData['user_id'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access denied']);
                exit;
            }
        }

        $data = getJsonInput();
        if (!$data) {
            throw new InvalidArgumentException('Invalid JSON input');
        }
        
        // Log the incoming data for debugging
        error_log("SMTP UPDATE - ID: $id, Data: " . json_encode($data));

        // Update server
        if (!empty($data['server'])) {
            $srv = $data['server'];
            $name = sanitizeString($srv['name'] ?? '', 255);
            $host = validateHost($srv['host'] ?? '');
            $port = validatePort($srv['port'] ?? 465);
            $encryption = validateEncryption($srv['encryption'] ?? '');
            $received_email = !empty($srv['received_email']) ? validateEmail($srv['received_email']) : '';
            $is_active = validateBoolean($srv['is_active'] ?? 1);

            try {
                $stmt = $conn->prepare("UPDATE smtp_servers SET name = ?, host = ?, port = ?, encryption = ?, received_email = ?, is_active = ? WHERE id = ?");
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                $stmt->bind_param("ssissii", $name, $host, $port, $encryption, $received_email, $is_active, $id);
                if (!$stmt->execute()) {
                    throw new Exception("Execute failed: " . $stmt->error);
                }
                error_log("SMTP UPDATE - Server updated successfully");
                $stmt->close();
            } catch (Exception $e) {
                error_log("SMTP UPDATE - Server update error: " . $e->getMessage());
                throw new Exception("Failed to update server: " . $e->getMessage());
            }
        }

        // Update accounts
        if (!empty($data['accounts']) && is_array($data['accounts'])) {
            $accountErrors = [];
            foreach ($data['accounts'] as $acc) {
                try {
                    if (!empty($acc['id'])) {
                        // Update existing account
                        $accId = validateInteger($acc['id'], 1);
                        $email = validateEmail($acc['email'] ?? '');
                        $password = sanitizeString($acc['password'] ?? '', 500);
                        $daily_limit = validateInteger($acc['daily_limit'] ?? 500, 0, 10000);
                        $hourly_limit = validateInteger($acc['hourly_limit'] ?? 100, 0, 1000);
                        $acc_active = validateBoolean($acc['is_active'] ?? 1);
                        
                        // Build update query dynamically based on what's provided
                        $updateFields = ["email = ?", "daily_limit = ?", "hourly_limit = ?", "is_active = ?"];
                        $types = "siii";
                        $params = [$email, $daily_limit, $hourly_limit, $acc_active];
                        
                        // Only update from_name if provided
                        if (isset($acc['from_name'])) {
                            $from_name = sanitizeString($acc['from_name'], 255);
                            $updateFields[] = "from_name = ?";
                            $types .= "s";
                            $params[] = $from_name;
                        }
                        
                        // Only update password if provided
                        if (!empty($password)) {
                            $updateFields[] = "password = ?";
                            $types .= "s";
                            $params[] = $password;
                        }
                        
                        $updateSQL = "UPDATE smtp_accounts SET " . implode(", ", $updateFields) . " WHERE id = ? AND smtp_server_id = ?";
                        $types .= "ii";
                        $params[] = $accId;
                        $params[] = $id;
                        
                        error_log("SMTP UPDATE - Updating account ID $accId: email=$email, daily=$daily_limit, hourly=$hourly_limit, active=$acc_active, has_password=" . (!empty($password) ? 'yes' : 'no'));

                        $stmt = $conn->prepare($updateSQL);
                        if (!$stmt) {
                            throw new Exception("Prepare account update failed: " . $conn->error);
                        }
                        $stmt->bind_param($types, ...$params);
                        if (!$stmt->execute()) {
                            throw new Exception("Execute account update failed: " . $stmt->error);
                        }
                        
                        if ($stmt->affected_rows === 0) {
                            error_log("SMTP UPDATE - Warning: Account $accId not found or no changes made");
                        } else {
                            error_log("SMTP UPDATE - Account $email (ID: $accId) updated successfully");
                        }
                        $stmt->close();
                    } else {
                        // Insert new account
                        $email = validateEmail($acc['email'] ?? '');
                        $password = sanitizeString($acc['password'] ?? '', 500);
                        $from_name = sanitizeString($acc['from_name'] ?? '', 255);
                        $daily_limit = validateInteger($acc['daily_limit'] ?? 500, 0, 10000);
                        $hourly_limit = validateInteger($acc['hourly_limit'] ?? 100, 0, 1000);
                        $acc_active = validateBoolean($acc['is_active'] ?? 1);
                        
                        // Use current user from requireAuth() above
                        $user_id = $currentUser['id'];

                        $stmt = $conn->prepare("INSERT INTO smtp_accounts (smtp_server_id, email, from_name, password, daily_limit, hourly_limit, is_active, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        if (!$stmt) {
                            throw new Exception("Prepare account insert failed: " . $conn->error);
                        }
                        $stmt->bind_param("isssiiii", $id, $email, $from_name, $password, $daily_limit, $hourly_limit, $acc_active, $user_id);
                        if (!$stmt->execute()) {
                            throw new Exception("Execute account insert failed: " . $stmt->error);
                        }
                        error_log("SMTP UPDATE - Account $email inserted successfully");
                        $stmt->close();
                    }
                } catch (Exception $e) {
                    error_log("SMTP UPDATE - Account operation failed: " . $e->getMessage());
                    $accountErrors[] = $e->getMessage();
                    logSecurityEvent('Account update failed', ['error' => $e->getMessage()]);
                }
            }
            
            // If there were account errors, throw exception with details
            if (!empty($accountErrors)) {
                throw new Exception('Account update errors: ' . implode('; ', $accountErrors));
            }
        }

        echo json_encode(['success' => true, 'message' => 'SMTP server/accounts updated successfully!']);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        logSecurityEvent('SMTP server update failed', ['error' => $e->getMessage()]);
    }
    $conn->close();
    exit;
}

// DELETE: Delete server and its accounts
if ($method === 'DELETE') {
    // Require authentication
    $currentUser = requireAuth();
    error_log("master_smtps.php DELETE - User: " . json_encode($currentUser));
    
    try {
        parse_str($_SERVER['QUERY_STRING'], $query);
        $id = validateInteger($query['id'] ?? 0, 1);
        
        // Check if user can access this server
        if (!isAdmin()) {
            $checkStmt = $conn->prepare("SELECT user_id FROM smtp_servers WHERE id = ?");
            $checkStmt->bind_param("i", $id);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            $serverData = $checkResult->fetch_assoc();
            $checkStmt->close();
            
            if (!$serverData || !canAccessRecord($serverData['user_id'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access denied']);
                exit;
            }
        }

        $stmt = $conn->prepare("DELETE FROM smtp_servers WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => true, 'message' => 'SMTP server and accounts deleted successfully!']);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        logSecurityEvent('SMTP server deletion failed', ['error' => $e->getMessage()]);
    }
    $conn->close();
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
$conn->close();
exit;
