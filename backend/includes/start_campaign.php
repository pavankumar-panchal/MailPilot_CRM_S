<?php
/**
 * Start (or resume) a campaign by spawning the parallel email blast daemon.
 * Expected POST param: campaign_id
 */
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit();
}

require_once __DIR__ . '/BackgroundProcess.php';
require_once __DIR__ . '/session_config.php';
require_once __DIR__ . '/auth_helper.php';

// DIRECT DATABASE CONNECTIONS - Don't use config files
// SERVER 1: For campaign_master and campaign_status
$conn_server1 = new mysqli('127.0.0.1', 'email_id', '55y60jgW*', 'email_id');
if ($conn_server1->connect_error) {
    error_log("Server 1 DB Connection failed: " . $conn_server1->connect_error);
    http_response_code(500);
    echo json_encode(['error' => 'Server 1 database connection failed']);
    exit();
}
$conn_server1->set_charset("utf8mb4");
error_log("✓ Connected to Server 1 - Database: email_id");

// SERVER 2: For mail_blaster ONLY
$conn_server2 = new mysqli('207.244.80.245', 'CRM', '55y60jgW*', 'CRM');
if ($conn_server2->connect_error) {
    error_log("Server 2 DB Connection failed: " . $conn_server2->connect_error);
    http_response_code(500);
    echo json_encode(['error' => 'Server 2 database connection failed - cannot store emails']);
    exit();
}
$conn_server2->set_charset("utf8mb4");
error_log("✓ Connected to Server 2 - Database: CRM");

// Verify we're on the right database
$verify = $conn_server2->query("SELECT DATABASE() as db");
$current_db = $verify->fetch_assoc()['db'];
error_log("✓ Server 2 verified - Using database: $current_db");

// Use these aliases for clarity
$conn = $conn_server1;  // Server 1 for campaign data
$conn_heavy = $conn_server2;  // Server 2 for mail_blaster

// Get current user for user_id tracking
$currentUser = getAuthenticatedUser();
$user_id = $currentUser ? $currentUser['id'] : null;

$campaign_id = isset($_POST['campaign_id']) ? (int)$_POST['campaign_id'] : 0;
if ($campaign_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'campaign_id is required']);
    exit();
}

try {
    // Get current user for filtering
    require_once __DIR__ . '/auth_helper.php';
    $currentUser = getAuthenticatedUser();
    $isAdmin = isAuthenticatedAdmin();
    $userId = $currentUser ? $currentUser['id'] : 0;
    
    // Validate campaign exists on SERVER 1 and get csv_list_id AND import_batch_id with user filtering
    $userFilter = $isAdmin ? '' : ' AND user_id = ' . intval($userId);
    $stmt = $conn->prepare('SELECT campaign_id, mail_subject, mail_body, csv_list_id, import_batch_id, user_id FROM email_id.campaign_master WHERE campaign_id = ?' . $userFilter);
    $stmt->bind_param('i', $campaign_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $campaign = $result->fetch_assoc();
    if (!$campaign) {
        http_response_code(404);
        echo json_encode(['error' => 'Campaign not found or access denied']);
        exit();
    }
    if (empty(trim($campaign['mail_subject'])) || empty(trim($campaign['mail_body']))) {
        http_response_code(400);
        echo json_encode(['error' => 'Campaign missing subject or body']);
        exit();
    }

    $csv_list_id = isset($campaign['csv_list_id']) ? intval($campaign['csv_list_id']) : 0;
    $import_batch_id = isset($campaign['import_batch_id']) ? $campaign['import_batch_id'] : null;
    
    error_log("[CAMPAIGN $campaign_id] ========== CAMPAIGN START REQUEST (Server 1) ==========");
    error_log("[CAMPAIGN $campaign_id] Campaign source - import_batch_id: " . ($import_batch_id ?: 'NULL') . ", csv_list_id: " . ($csv_list_id ?: 'NULL'));
    
    // SERVER 1: Fetch emails from Server 1 DB and store directly into Server 2's mail_blaster
    error_log("[CAMPAIGN $campaign_id] Populating mail_blaster on Server 2...");
    
    $totalEmails = 0;
    $inserted = 0;
    
    if ($import_batch_id) {
        // Fetch from imported_recipients on Server 1 and insert into Server 2's mail_blaster
        $batch_escaped = $conn->real_escape_string($import_batch_id);
        $batch_escaped_heavy = $conn_heavy->real_escape_string($import_batch_id);
        
        // Get emails from Server 1
        $emailsRes = $conn->query("
            SELECT DISTINCT Emails as email, Name as name
            FROM imported_recipients 
            WHERE import_batch_id = '$batch_escaped' 
            AND is_active = 1 
            AND Emails IS NOT NULL 
            AND Emails <> ''
        ");
        
        if (!$emailsRes) {
            http_response_code(400);
            echo json_encode(['error' => "Failed to fetch recipients from imported batch."]);
            exit();
        }
        
        $totalEmails = $emailsRes->num_rows;
        
        if ($totalEmails === 0) {
            http_response_code(400);
            echo json_encode(['error' => "No recipients found in the imported Excel batch (ID: $import_batch_id)."]);
            exit();
        }
        
        // Insert into Server 2's CRM.mail_blaster in batches
        $batch = [];
        while ($row = $emailsRes->fetch_assoc()) {
            $email = $conn_server2->real_escape_string($row['email']);
            $name = $conn_server2->real_escape_string($row['name'] ?? '');
            $batch[] = "($campaign_id, '$email', '$name', 'pending', NOW())";
            
            if (count($batch) >= 500) {
                $insertSql = "INSERT IGNORE INTO CRM.mail_blaster (campaign_id, to_mail, to_name, status, delivery_time) VALUES " . implode(', ', $batch);
                if ($conn_server2->query($insertSql)) {
                    $inserted += $conn_server2->affected_rows;
                    error_log("[CAMPAIGN $campaign_id] Inserted batch of {$conn_server2->affected_rows} emails into Server 2 CRM.mail_blaster");
                }
                $batch = [];
            }
        }
        
        // Insert remaining
        if (count($batch) > 0) {
            $insertSql = "INSERT IGNORE INTO CRM.mail_blaster (campaign_id, to_mail, to_name, status, delivery_time) VALUES " . implode(', ', $batch);
            if ($conn_server2->query($insertSql)) {
                $inserted += $conn_server2->affected_rows;
                error_log("[CAMPAIGN $campaign_id] Inserted final batch of {$conn_server2->affected_rows} emails into Server 2 CRM.mail_blaster");
            }
        }
        
    } elseif ($csv_list_id > 0) {
        // Fetch from emails table on Server 1 and insert into Server 2's CRM.mail_blaster
        $emailsRes = $conn->query("
            SELECT raw_emailid as email
            FROM emails 
            WHERE domain_status = 1 
            AND raw_emailid IS NOT NULL 
            AND raw_emailid <> ''
            AND csv_list_id = " . (int)$csv_list_id
        );
        
        if (!$emailsRes) {
            http_response_code(400);
            echo json_encode(['error' => "Failed to fetch recipients from CSV list."]);
            exit();
        }
        
        $totalEmails = $emailsRes->num_rows;
        
        if ($totalEmails === 0) {
            http_response_code(400);
            echo json_encode(['error' => "No recipients found for this campaign in the selected CSV list (ID: $csv_list_id)."]);
            exit();
        }
        
        // Insert into Server 2's CRM.mail_blaster in batches
        $batch = [];
        while ($row = $emailsRes->fetch_assoc()) {
            $email = $conn_server2->real_escape_string($row['email']);
            $batch[] = "($campaign_id, '$email', '', 'pending', NOW())";
            
            if (count($batch) >= 500) {
                $insertSql = "INSERT IGNORE INTO CRM.mail_blaster (campaign_id, to_mail, to_name, status, delivery_time) VALUES " . implode(', ', $batch);
                if ($conn_server2->query($insertSql)) {
                    $inserted += $conn_server2->affected_rows;
                    error_log("[CAMPAIGN $campaign_id] CSV: Inserted batch of {$conn_server2->affected_rows} emails into Server 2 CRM.mail_blaster");
                }
                $batch = [];
            }
        }
        
        // Insert remaining
        if (count($batch) > 0) {
            $insertSql = "INSERT IGNORE INTO CRM.mail_blaster (campaign_id, to_mail, to_name, status, delivery_time) VALUES " . implode(', ', $batch);
            if ($conn_server2->query($insertSql)) {
                $inserted += $conn_server2->affected_rows;
                error_log("[CAMPAIGN $campaign_id] CSV: Inserted final batch of {$conn_server2->affected_rows} emails into Server 2 CRM.mail_blaster");
            }
        }
        
    } else {
        // Fetch all valid emails from emails table on Server 1
        $emailsRes = $conn->query("
            SELECT raw_emailid as email
            FROM emails 
            WHERE domain_status = 1 
            AND raw_emailid IS NOT NULL 
            AND raw_emailid <> ''
        ");
        
        if (!$emailsRes) {
            http_response_code(400);
            echo json_encode(['error' => "Failed to fetch recipients."]);
            exit();
        }
        
        $totalEmails = $emailsRes->num_rows;
        
        if ($totalEmails === 0) {
            http_response_code(400);
            echo json_encode(['error' => 'No recipients found for this campaign.']);
            exit();
        }
        
        // Insert into Server 2's CRM.mail_blaster in batches
        $batch = [];
        while ($row = $emailsRes->fetch_assoc()) {
            $email = $conn_server2->real_escape_string($row['email']);
            $batch[] = "($campaign_id, '$email', '', 'pending', NOW())";
            
            if (count($batch) >= 500) {
                $insertSql = "INSERT IGNORE INTO CRM.mail_blaster (campaign_id, to_mail, to_name, status, delivery_time) VALUES " . implode(', ', $batch);
                if ($conn_server2->query($insertSql)) {
                    $inserted += $conn_server2->affected_rows;
                    error_log("[CAMPAIGN $campaign_id] ALL: Inserted batch of {$conn_server2->affected_rows} emails into Server 2 CRM.mail_blaster");
                }
                $batch = [];
            }
        }
        
        // Insert remaining
        if (count($batch) > 0) {
            $insertSql = "INSERT IGNORE INTO CRM.mail_blaster (campaign_id, to_mail, to_name, status, delivery_time) VALUES " . implode(', ', $batch);
            if ($conn_server2->query($insertSql)) {
                $inserted += $conn_server2->affected_rows;
                error_log("[CAMPAIGN $campaign_id] ALL: Inserted final batch of {$conn_server2->affected_rows} emails into Server 2 CRM.mail_blaster");
            }
        }
    }
    
    error_log("[CAMPAIGN $campaign_id] ✓ STORED $inserted emails into Server 2 CRM.mail_blaster (total found: $totalEmails)");

    // Check existing status row on SERVER 1
    $statusRes = $conn->query("SELECT status, sent_emails, failed_emails FROM email_id.campaign_status WHERE campaign_id = $campaign_id");
    $statusRow = $statusRes ? $statusRes->fetch_assoc() : null;

    // Initialize stats with actual inserted count
    $sent = $statusRow ? (int)($statusRow['sent_emails'] ?? 0) : 0;
    $failed = $statusRow ? (int)($statusRow['failed_emails'] ?? 0) : 0;
    $pending = $inserted - ($sent + $failed);

    // Detect if already running - with row-level locking to prevent race conditions
    try {
        $conn->begin_transaction();
        
        // Lock the row for this campaign on SERVER 1
        $lockResult = $conn->query("SELECT status, sent_emails, failed_emails FROM email_id.campaign_status WHERE campaign_id = $campaign_id FOR UPDATE");
        $statusRow = $lockResult ? $lockResult->fetch_assoc() : null;
        
        // CRITICAL: Check if already running WHILE holding lock
        if ($statusRow && $statusRow['status'] === 'running') {
            $conn->commit();
            echo json_encode([
                'status' => 'already_running',
                'message' => 'Campaign already running - cannot start multiple instances of the same campaign',
                'campaign_id' => $campaign_id,
            ]);
            exit();
        }
        
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Failed to check campaign #$campaign_id status: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to verify campaign status']);
        exit();
    }

    // Minimal info_schema check for optional columns (start_time)
    $dbNameRes = $conn->query('SELECT DATABASE() as db');
    $dbName = $dbNameRes ? ($dbNameRes->fetch_assoc()['db'] ?? '') : '';
    $hasStartTime = false;
    if ($dbName) {
        $colCheck = $conn->query("SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '" . $conn->real_escape_string($dbName) . "' AND TABLE_NAME = 'campaign_status' AND COLUMN_NAME = 'start_time'");
        if ($colCheck) {
            $hasStartTime = (int)$colCheck->fetch_assoc()['cnt'] > 0;
        }
    }

    // Check if PID column exists
    $hasPid = false;
    if ($dbName) {
        $pidCheck = $conn->query("SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '" . $conn->real_escape_string($dbName) . "' AND TABLE_NAME = 'campaign_status' AND COLUMN_NAME = 'process_pid'");
        if ($pidCheck) {
            $hasPid = (int)$pidCheck->fetch_assoc()['cnt'] > 0;
        }
    }

    // If PID column exists, check if campaign is already running with an active process
    if ($hasPid && $statusRow && $statusRow['status'] === 'running') {
        $existingPid = isset($statusRow['process_pid']) ? (int)$statusRow['process_pid'] : 0;
        if ($existingPid > 0) {
            // Check if process is still running
            $pidExists = file_exists("/proc/$existingPid");
            if ($pidExists) {
                echo json_encode([
                    'status' => 'already_running',
                    'message' => 'Campaign is already running with PID ' . $existingPid,
                    'campaign_id' => $campaign_id,
                    'pid' => $existingPid
                ]);
                exit();
            }
        }
    }

    if (!$statusRow) {
        // Insert new status row on SERVER 1 with user_id
        try {
            $conn->begin_transaction();
            if ($hasStartTime) {
                $insertSql = "INSERT INTO email_id.campaign_status (campaign_id, status, total_emails, pending_emails, sent_emails, failed_emails, start_time, user_id) VALUES ($campaign_id, 'running', $inserted, $pending, $sent, $failed, NOW(), " . ($user_id ? $user_id : "NULL") . ")";
                $conn->query($insertSql);
                error_log("[CAMPAIGN $campaign_id] ✓ Inserted campaign_status into Server 1 email_id.campaign_status");
            } else {
                $insertSql = "INSERT INTO email_id.campaign_status (campaign_id, status, total_emails, pending_emails, sent_emails, failed_emails, user_id) VALUES ($campaign_id, 'running', $inserted, $pending, $sent, $failed, " . ($user_id ? $user_id : "NULL") . ")";
                $conn->query($insertSql);
                error_log("[CAMPAIGN $campaign_id] ✓ Inserted campaign_status into Server 1 email_id.campaign_status");
            }
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Failed to insert campaign #$campaign_id status: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to start campaign - could not create status record']);
            exit();
        }
    } else {
        // Update existing row to running on SERVER 1
        try {
            $conn->begin_transaction();
            $conn->query("SELECT campaign_id FROM email_id.campaign_status WHERE campaign_id = $campaign_id FOR UPDATE");
            
            // Double-check status hasn't changed to running by another process
            $recheckResult = $conn->query("SELECT status FROM email_id.campaign_status WHERE campaign_id = $campaign_id");
            $recheckRow = $recheckResult ? $recheckResult->fetch_assoc() : null;
            
            if ($recheckRow && $recheckRow['status'] === 'running') {
                $conn->commit();
                echo json_encode([
                    'status' => 'already_running',
                    'message' => 'Campaign started by another process',
                    'campaign_id' => $campaign_id,
                ]);
                exit();
            }
            
            if ($hasStartTime) {
                $conn->query("UPDATE email_id.campaign_status SET status = 'running', total_emails = $inserted, pending_emails = $pending, start_time = IF(start_time IS NULL, NOW(), start_time) WHERE campaign_id = $campaign_id");
                error_log("[CAMPAIGN $campaign_id] ✓ Updated campaign_status to 'running' in Server 1 email_id.campaign_status");
            } else {
                $conn->query("UPDATE email_id.campaign_status SET status = 'running', total_emails = $inserted, pending_emails = $pending WHERE campaign_id = $campaign_id");
                error_log("[CAMPAIGN $campaign_id] ✓ Updated campaign_status to 'running' in Server 1 email_id.campaign_status");
            }
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Failed to update campaign #$campaign_id status: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to start campaign - could not update status']);
            exit();
        }
    }

    // SUCCESS: Campaign prepared and emails stored in Server 2's mail_blaster
    // Server 2 cron will detect and start sending
    
    // Log the campaign start with source information
    $logsDir = realpath(__DIR__ . '/../logs') ?: (__DIR__ . '/../logs');
    if (!is_dir($logsDir)) {
        @mkdir($logsDir, 0775, true);
    }
    
    $sourceInfo = '';
    if ($import_batch_id) {
        $sourceInfo = " from Import Batch: $import_batch_id";
    } elseif ($csv_list_id > 0) {
        $sourceInfo = " from CSV List ID: $csv_list_id";
    }
    
    error_log("[" . date('Y-m-d H:i:s') . "] Campaign $campaign_id set to RUNNING - $inserted emails stored in Server 2 CRM.mail_blaster$sourceInfo\n", 3, $logsDir . '/campaign_starts.log');

    error_log("[CAMPAIGN $campaign_id] ========== SUMMARY ==========");
    error_log("[CAMPAIGN $campaign_id] Server 1 (email_id): campaign_status = 'running'");
    error_log("[CAMPAIGN $campaign_id] Server 2 (CRM): mail_blaster = $inserted emails");
    error_log("[CAMPAIGN $campaign_id] ================================");

    // Build response message with source information
    $responseMessage = 'Campaign started - Status updated on Server 1, Emails stored on Server 2';
    $responseData = [
        'status' => 'started',
        'message' => $responseMessage,
        'campaign_id' => $campaign_id,
        'total_emails' => $inserted,
        'pending_emails' => $pending,
        'note' => 'Server 2 will start sending within 1-2 minutes',
        'debug' => [
            'server1_db' => 'email_id (campaign_status updated)',
            'server2_db' => 'CRM (mail_blaster populated)',
            'emails_stored' => $inserted
        ]
    ];
    
    // Add source information to response
    if ($import_batch_id) {
        $responseData['source'] = 'import_batch';
        $responseData['import_batch_id'] = $import_batch_id;
    } elseif ($csv_list_id > 0) {
        $responseData['source'] = 'csv_list';
        $responseData['csv_list_id'] = $csv_list_id;
    } else {
        $responseData['source'] = 'all_emails';
    }
    
    // Return immediately to frontend
    echo json_encode($responseData);
    
    // Close connections
    $conn_server1->close();
    $conn_server2->close();
    
    // Ensure output is sent immediately
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    
    // Close connections on error
    if (isset($conn_server1)) $conn_server1->close();
    if (isset($conn_server2)) $conn_server2->close();
}
