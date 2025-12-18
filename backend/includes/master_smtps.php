<?php

require_once __DIR__ . '/security_helpers.php';
require_once __DIR__ . '/../config/db.php';

// Set security headers
setSecurityHeaders();

// Handle CORS securely
handleCors();

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

function getJsonInput() {
    return getValidatedJsonInput();
}

// GET: List all SMTP servers with their accounts
if ($method === 'GET') {
    $serversRes = $conn->query("SELECT * FROM smtp_servers ORDER BY id DESC");
    $servers = [];

    while ($server = $serversRes->fetch_assoc()) {
        $server['is_active'] = (bool)$server['is_active'];

        // Fetch accounts for each server
        $accountsRes = $conn->query("SELECT * FROM smtp_accounts WHERE smtp_server_id = {$server['id']} ORDER BY id ASC");
        $accounts = [];
        while ($acc = $accountsRes->fetch_assoc()) {
            $acc['is_active'] = (bool)$acc['is_active'];
            $accounts[] = $acc;
        }
        $server['accounts'] = $accounts;
        $servers[] = $server;
    }
    echo json_encode(['data' => $servers]);
    $conn->close();
    exit;
}

// POST: Add new SMTP server + accounts
if ($method === 'POST') {
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

        // Use prepared statement for INSERT
        $stmt = $conn->prepare("INSERT INTO smtp_servers (name, host, port, encryption, received_email, is_active) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssissi", $name, $host, $port, $encryption, $received_email, $is_active);
        
        if (!$stmt->execute()) {
            throw new Exception('Error adding SMTP server: ' . $stmt->error);
        }

        $serverId = $stmt->insert_id;
        $stmt->close();

        // Insert accounts if provided
        if (!empty($data['accounts']) && is_array($data['accounts'])) {
            $stmt_acc = $conn->prepare("INSERT INTO smtp_accounts (smtp_server_id, email, password, daily_limit, hourly_limit, is_active) VALUES (?, ?, ?, ?, ?, ?)");
            
            foreach ($data['accounts'] as $acc) {
                try {
                    $email = validateEmail($acc['email'] ?? '');
                    $password = sanitizeString($acc['password'] ?? '', 500);
                    if ($email === '' || $password === '') continue;

                    $daily_limit = validateInteger($acc['daily_limit'] ?? 500, 0, 10000);
                    $hourly_limit = validateInteger($acc['hourly_limit'] ?? 100, 0, 1000);
                    $acc_active = validateBoolean($acc['is_active'] ?? 1);

                    $stmt_acc->bind_param("issiii", $serverId, $email, $password, $daily_limit, $hourly_limit, $acc_active);
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
    try {
        parse_str($_SERVER['QUERY_STRING'], $query);
        $id = validateInteger($query['id'] ?? 0, 1);

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

                        $stmt = $conn->prepare("UPDATE smtp_accounts SET email = ?, password = ?, daily_limit = ?, hourly_limit = ?, is_active = ? WHERE id = ? AND smtp_server_id = ?");
                        if (!$stmt) {
                            throw new Exception("Prepare account update failed: " . $conn->error);
                        }
                        $stmt->bind_param("ssiiiii", $email, $password, $daily_limit, $hourly_limit, $acc_active, $accId, $id);
                        if (!$stmt->execute()) {
                            throw new Exception("Execute account update failed: " . $stmt->error);
                        }
                        error_log("SMTP UPDATE - Account $email updated successfully");
                        $stmt->close();
                    } else {
                        // Insert new account
                        $email = validateEmail($acc['email'] ?? '');
                        $password = sanitizeString($acc['password'] ?? '', 500);
                        $daily_limit = validateInteger($acc['daily_limit'] ?? 500, 0, 10000);
                        $hourly_limit = validateInteger($acc['hourly_limit'] ?? 100, 0, 1000);
                        $acc_active = validateBoolean($acc['is_active'] ?? 1);

                        $stmt = $conn->prepare("INSERT INTO smtp_accounts (smtp_server_id, email, password, daily_limit, hourly_limit, is_active) VALUES (?, ?, ?, ?, ?, ?)");
                        if (!$stmt) {
                            throw new Exception("Prepare account insert failed: " . $conn->error);
                        }
                        $stmt->bind_param("issiii", $id, $email, $password, $daily_limit, $hourly_limit, $acc_active);
                        if (!$stmt->execute()) {
                            throw new Exception("Execute account insert failed: " . $stmt->error);
                        }
                        error_log("SMTP UPDATE - Account $email inserted successfully");
                        $stmt->close();
                    }
                } catch (Exception $e) {
                    error_log("SMTP UPDATE - Account operation failed: " . $e->getMessage());
                    // Continue to next account instead of failing entire operation
                    logSecurityEvent('Account update failed', ['error' => $e->getMessage()]);
                    continue;
                }
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
    try {
        parse_str($_SERVER['QUERY_STRING'], $query);
        $id = validateInteger($query['id'] ?? 0, 1);

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
