<?php
/**
 * SMTP Servers Management & Status API
 * 
 * =====================================================
 * SERVER 2 CONNECTION - CRITICAL REQUIREMENT
 * =====================================================
 * When uploading to Server 1, this file MUST connect to Server 2 database
 * 
 * Tables on Server 2 (CRM database):
 * - smtp_servers (SMTP server configurations)
 * - smtp_accounts (SMTP account credentials)  
 * - smtp_usage (hourly/daily usage tracking)
 * - mail_blaster (email sending queue and status)
 * 
 * Tables on Server 1 (email_id database):
 * - campaign_master (campaign metadata - accessed via email_id.campaign_master)
 * 
 * Connection: $conn_heavy (via db_campaign.php)
 * - When running on Server 1 → connects to 207.244.80.245 (Server 2)
 * - When running on Server 2 → connects to 127.0.0.1 (local)
 * - When running on localhost → connects to 127.0.0.1 (local CRM DB)
 * =====================================================
 */

error_log("=== MASTER_SMTPS.PHP CALLED === Method: " . ($_SERVER['REQUEST_METHOD'] ?? 'unknown') . ", Query: " . ($_SERVER['QUERY_STRING'] ?? 'none'));

// Use centralized session configuration
try {
    require_once __DIR__ . '/session_config.php';
    require_once __DIR__ . '/security_helpers.php';
    
    // CRITICAL: Load BOTH database connections
    // Server 1 (email_id): campaign_master, users, etc.
    require_once __DIR__ . '/../config/db.php';
    // Server 2 (CRM): smtp_servers, smtp_accounts, smtp_usage, mail_blaster
    require_once __DIR__ . '/../config/db_campaign.php';
    error_log("MASTER_SMTPS: Both Server 1 & Server 2 DB loaded successfully");
    
    require_once __DIR__ . '/user_filtering.php';
    require_once __DIR__ . '/auth_helper.php';
} catch (Exception $e) {
    error_log("FATAL: MASTER_SMTPS dependencies failed: " . $e->getMessage());
    if (!defined('ROUTER_HANDLED')) {
        header('Content-Type: application/json');
        http_response_code(500);
    }
    echo json_encode([
        'success' => false,
        'error' => 'Failed to initialize SMTP system',
        'message' => 'Database connection error. Please try again.'
    ]);
    exit(1);
}

// Verify Server 2 connection is available
if (!isset($conn_heavy) || !$conn_heavy) {
    error_log("CRITICAL: Missing Server 2 connection (\$conn_heavy) in master_smtps.php");
    if (!defined('ROUTER_HANDLED')) {
        header('Content-Type: application/json');
        http_response_code(500);
    }
    echo json_encode([
        'success' => false,
        'error' => 'Campaign database not available',
        'message' => 'SMTP system temporarily unavailable'
    ]);
    exit(1);
}

// Use Server 2 (Campaign DB) for SMTP operations
// But keep Server 1 connection ($conn) available for campaign_master access
$conn_smtp = $conn_heavy;
error_log("MASTER_SMTPS: Using Server 2 connection for SMTP operations");

// Set security headers
setSecurityHeaders();

// Handle CORS securely
handleCors();

// Ensure user_id columns exist on Server 2 (CRM)
ensureUserIdColumns($conn_smtp);

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

function getJsonInput() {
    return getValidatedJsonInput();
}

// GET: List all SMTP servers with their accounts and real-time status
if ($method === 'GET') {
    // Use unified auth (supports both session cookies and tokens)
    $currentUser = requireAuth();
    $isAdmin = isAuthenticatedAdmin();
    
    error_log("=== SMTP GET REQUEST ===");
    error_log("User: " . json_encode($currentUser));
    error_log("Is Admin: " . ($isAdmin ? 'YES' : 'NO'));
    
    $userFilter = getAuthFilterWhere();
    error_log("User Filter: '$userFilter'");
    
    // Get current date and hour for usage tracking
    $today = date('Y-m-d');
    $current_hour = intval(date('G'));
    
    // Initialize summary stats
    $summary = [
        'total' => 0,
        'available' => 0,
        'at_daily_limit' => 0,
        'at_hourly_limit' => 0,
        'inactive' => 0,
        'working_now' => 0
    ];
    
    // Query all SMTP servers with status from Server 2 (mail_blaster, smtp_usage)
    $query = "SELECT * FROM smtp_servers " . $userFilter . " ORDER BY id DESC";
    error_log("Query: $query");
    
    $serversRes = $conn_smtp->query($query);
    $servers = [];

    while ($server = $serversRes->fetch_assoc()) {
        $server['is_active'] = (bool)$server['is_active'];

        // Fetch accounts for each server with real-time status from Server 2
        $accountUserFilter = getAuthFilterAnd('sa');
        $serverId = intval($server['id']);
        
        // Get accounts with usage data from Server 2 (smtp_usage, mail_blaster)
        $accountQuery = "
            SELECT 
                sa.id,
                sa.email,
                sa.password,
                sa.from_name,
                sa.smtp_server_id,
                sa.is_active,
                sa.daily_limit,
                sa.hourly_limit,
                sa.user_id,
                COALESCE(daily_usage.sent_today, 0) as sent_today,
                COALESCE(hourly_usage.emails_sent, 0) as sent_this_hour,
                CASE 
                    WHEN sa.is_active = 0 THEN 'inactive'
                    WHEN sa.daily_limit > 0 AND COALESCE(daily_usage.sent_today, 0) >= sa.daily_limit THEN 'daily_limit'
                    WHEN sa.hourly_limit > 0 AND COALESCE(hourly_usage.emails_sent, 0) >= sa.hourly_limit THEN 'hourly_limit'
                    ELSE 'available'
                END as status,
                (SELECT COUNT(*) 
                 FROM mail_blaster mb 
                 WHERE mb.smtpid = sa.id 
                 AND mb.delivery_time >= DATE_SUB(NOW(), INTERVAL 60 SECOND)
                 AND mb.status IN ('processing', 'success')
                ) as recent_activity,
                (SELECT COUNT(*) 
                 FROM mail_blaster mb 
                 WHERE mb.smtpid = sa.id 
                 AND mb.delivery_date = CURDATE()
                 AND mb.status = 'success'
                ) as sent_today_count,
                (SELECT COUNT(*) 
                 FROM mail_blaster mb 
                 WHERE mb.smtpid = sa.id 
                 AND mb.delivery_date = CURDATE()
                 AND mb.status = 'failed'
                ) as failed_today_count
            FROM smtp_accounts sa
            LEFT JOIN (
                SELECT smtp_id, SUM(emails_sent) as sent_today
                FROM smtp_usage
                WHERE date = '$today'
                GROUP BY smtp_id
            ) daily_usage ON daily_usage.smtp_id = sa.id
            LEFT JOIN smtp_usage hourly_usage ON hourly_usage.smtp_id = sa.id 
                AND hourly_usage.date = '$today' AND hourly_usage.hour = $current_hour
            WHERE sa.smtp_server_id = $serverId 
            $accountUserFilter
            ORDER BY sa.id ASC
        ";
        
        $accountsRes = $conn_smtp->query($accountQuery);
        $accounts = [];
        
        while ($acc = $accountsRes->fetch_assoc()) {
            $summary['total']++;
            
            // Update summary stats
            switch ($acc['status']) {
                case 'available':
                    $summary['available']++;
                    break;
                case 'daily_limit':
                    $summary['at_daily_limit']++;
                    break;
                case 'hourly_limit':
                    $summary['at_hourly_limit']++;
                    break;
                case 'inactive':
                    $summary['inactive']++;
                    break;
            }
            
            if (intval($acc['recent_activity']) > 0) {
                $summary['working_now']++;
            }
            
            // Calculate remaining limits
            $daily_remaining = $acc['daily_limit'] > 0 
                ? max(0, $acc['daily_limit'] - $acc['sent_today']) 
                : 999999;
            
            $hourly_remaining = $acc['hourly_limit'] > 0 
                ? max(0, $acc['hourly_limit'] - $acc['sent_this_hour']) 
                : 999999;
            
            // Format account data with status
            $acc['is_active'] = (bool)$acc['is_active'];
            $acc['limits'] = [
                'daily_limit' => intval($acc['daily_limit']),
                'hourly_limit' => intval($acc['hourly_limit']),
                'daily_remaining' => $daily_remaining,
                'hourly_remaining' => $hourly_remaining
            ];
            $acc['usage'] = [
                'sent_today' => intval($acc['sent_today_count']),
                'failed_today' => intval($acc['failed_today_count']),
                'recent_activity' => intval($acc['recent_activity']) > 0
            ];
            
            // Remove redundant fields
            unset($acc['sent_today']);
            unset($acc['sent_this_hour']);
            unset($acc['recent_activity']);
            unset($acc['sent_today_count']);
            unset($acc['failed_today_count']);
            
            $accounts[] = $acc;
        }
        
        $server['accounts'] = $accounts;
        $servers[] = $server;
    }
    
    // Get campaign usage from Server 2 (mail_blaster) and Server 1 (campaign_master)
    $campaign_usage_raw = $conn_heavy->query("
        SELECT 
            mb.smtpid,
            mb.campaign_id,
            COUNT(*) as emails_processing
        FROM mail_blaster mb
        WHERE mb.status IN ('processing', 'pending')
        AND mb.delivery_time >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        GROUP BY mb.smtpid, mb.campaign_id
    ")->fetch_all(MYSQLI_ASSOC);
    
    // Get campaign details from Server 1 if we have campaign IDs
    $campaign_usage = [];
    if (!empty($campaign_usage_raw)) {
        $campaign_ids = array_unique(array_column($campaign_usage_raw, 'campaign_id'));
        if (!empty($campaign_ids)) {
            $ids_list = implode(',', array_map('intval', $campaign_ids));
            $campaigns_result = $conn->query("
                SELECT campaign_id, mail_subject 
                FROM campaign_master 
                WHERE campaign_id IN ($ids_list)
            ");
            
            $campaigns = [];
            if ($campaigns_result) {
                while ($row = $campaigns_result->fetch_assoc()) {
                    $campaigns[$row['campaign_id']] = $row['mail_subject'];
                }
            }
            
            // Combine the data
            foreach ($campaign_usage_raw as $usage) {
                $campaign_usage[] = [
                    'smtpid' => $usage['smtpid'],
                    'campaign_id' => $usage['campaign_id'],
                    'mail_subject' => $campaigns[$usage['campaign_id']] ?? 'Unknown Campaign',
                    'emails_processing' => $usage['emails_processing']
                ];
            }
        }
    }
    
    echo json_encode([
        'success' => true, 
        'data' => $servers,
        'summary' => $summary,
        'campaign_usage' => $campaign_usage,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
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

        // Use prepared statement for INSERT on Server 2
        $stmt = $conn_smtp->prepare("INSERT INTO smtp_servers (name, host, port, encryption, received_email, is_active, user_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssissii", $name, $host, $port, $encryption, $received_email, $is_active, $user_id);
        
        if (!$stmt->execute()) {
            throw new Exception('Error adding SMTP server: ' . $stmt->error);
        }

        $serverId = $stmt->insert_id;
        $stmt->close();

        // Insert accounts if provided
        if (!empty($data['accounts']) && is_array($data['accounts'])) {
            $stmt_acc = $conn_smtp->prepare("INSERT INTO smtp_accounts (smtp_server_id, email, password, daily_limit, hourly_limit, is_active, user_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
            
            foreach ($data['accounts'] as $acc) {
                try {
                    $email = validateEmail($acc['email'] ?? '');
                    $password = sanitizeString($acc['password'] ?? '', 500);
                    if ($email === '' || $password === '') continue;

                    $daily_limit = validateInteger($acc['daily_limit'] ?? 500, 0, 1000000);
                    $hourly_limit = validateInteger($acc['hourly_limit'] ?? 100, 0, 1000000);
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
            $checkStmt = $conn_smtp->prepare("SELECT user_id FROM smtp_servers WHERE id = ?");
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
                $stmt = $conn_smtp->prepare("UPDATE smtp_servers SET name = ?, host = ?, port = ?, encryption = ?, received_email = ?, is_active = ? WHERE id = ?");
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn_smtp->error);
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
                        $daily_limit = validateInteger($acc['daily_limit'] ?? 500, 0, 1000000);
                        $hourly_limit = validateInteger($acc['hourly_limit'] ?? 100, 0, 1000000);
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

                        $stmt = $conn_smtp->prepare($updateSQL);
                        if (!$stmt) {
                            throw new Exception("Prepare account update failed: " . $conn_smtp->error);
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
                        $daily_limit = validateInteger($acc['daily_limit'] ?? 500, 0, 1000000);
                        $hourly_limit = validateInteger($acc['hourly_limit'] ?? 100, 0, 1000000);
                        $acc_active = validateBoolean($acc['is_active'] ?? 1);
                        
                        // Use current user from requireAuth() above
                        $user_id = $currentUser['id'];

                        $stmt = $conn_smtp->prepare("INSERT INTO smtp_accounts (smtp_server_id, email, from_name, password, daily_limit, hourly_limit, is_active, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        if (!$stmt) {
                            throw new Exception("Prepare account insert failed: " . $conn_smtp->error);
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
            $checkStmt = $conn_smtp->prepare("SELECT user_id FROM smtp_servers WHERE id = ?");
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

        $stmt = $conn_smtp->prepare("DELETE FROM smtp_servers WHERE id = ?");
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
