<?php
// Note: CORS headers and db.php are handled by api.php when routed through it
// Only set headers if accessed directly (not through api.php router)
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/../config/db.php';
    header('Content-Type: application/json');
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    
    // Handle preflight requests
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }
}

require_once __DIR__ . '/../includes/ProcessManager.php';

// Ensure lock directory exists
$lock_dir = __DIR__ . '/../tmp/cron_locks';
if (!is_dir($lock_dir)) {
    mkdir($lock_dir, 0775, true);
    chmod($lock_dir, 0775);
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

ini_set('memory_limit', '2048M');
set_time_limit(0);

$response = [
    'success' => false,
    'message' => '',
    'data' => null
];

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $action = $input['action'] ?? null;

        if ($action === 'start_campaign') {
            $campaign_id = intval($input['campaign_id']);
            startCampaign($conn, $campaign_id);
            $response['success'] = true;
            $response['message'] = "Campaign #$campaign_id started successfully!";
        } elseif ($action === 'pause_campaign') {
            $campaign_id = intval($input['campaign_id']);
            pauseCampaign($conn, $campaign_id);
            $response['success'] = true;
            $response['message'] = "Campaign #$campaign_id paused successfully!";
        } elseif ($action === 'retry_failed') {
            $campaign_id = intval($input['campaign_id']);
            $msg = retryFailedEmails($conn, $campaign_id);
            $response['success'] = true;
            $response['message'] = $msg;
        } elseif ($action === 'list') {
            $response['success'] = true;
            $response['data'] = [
                'campaigns' => getCampaignsWithStats()
            ];
        } elseif ($action === 'email_counts') {
            $campaign_id = (int)($input['campaign_id'] ?? 0);
            $response['success'] = true;
            $response['data'] = getEmailCounts($conn, $campaign_id);
        } else {
            throw new Exception('Invalid action');
        }
    } elseif ($method === 'GET') {
        $response['success'] = true;
        $response['data'] = [
            'campaigns' => getCampaignsWithStats()
        ];
    } else {
        throw new Exception('Invalid request method');
    }
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);

// --- Helper Functions ---

function getCampaignsWithStats()
{
    global $conn;
    // Get total valid emails from emails table (only validation_status = 'valid')
    $valid_emails_total = 0;
    $valid_res = $conn->query("SELECT COUNT(*) as cnt FROM emails WHERE domain_status = 1 AND validation_status = 'valid'");
    if ($valid_res) {
        $valid_emails_total = (int)$valid_res->fetch_assoc()['cnt'];
    }
    
    $query = "SELECT 
                cm.campaign_id, 
                cm.description, 
                cm.mail_subject,
                cm.attachment_path,
                cm.csv_list_id,
                cl.list_name as csv_list_name,
                cs.status as campaign_status,
                COALESCE(cs.total_emails, 0) as total_emails,
                COALESCE(cs.pending_emails, 0) as pending_emails,
                COALESCE(cs.sent_emails, 0) as sent_emails,
                COALESCE(cs.failed_emails, 0) as failed_emails,
                cs.start_time,
                cs.end_time,
                (
                    SELECT COUNT(DISTINCT mb.to_mail) 
                    FROM mail_blaster mb 
                    WHERE mb.campaign_id = cm.campaign_id 
                    AND mb.status = 'failed'
                    AND mb.attempt_count < 5
                ) as retryable_count,
                (
                    SELECT COUNT(DISTINCT mb.to_mail) 
                    FROM mail_blaster mb 
                    WHERE mb.campaign_id = cm.campaign_id 
                    AND mb.status = 'failed'
                    AND mb.attempt_count >= 5
                ) as permanently_failed_count
              FROM campaign_master cm
              LEFT JOIN csv_list cl ON cm.csv_list_id = cl.id
              LEFT JOIN (
                  SELECT cs1.campaign_id, cs1.status, cs1.total_emails, cs1.pending_emails, 
                         cs1.sent_emails, cs1.failed_emails, cs1.start_time, cs1.end_time
                  FROM campaign_status cs1
                  INNER JOIN (
                      SELECT campaign_id, MAX(id) as max_id
                      FROM campaign_status
                      GROUP BY campaign_id
                  ) cs2 ON cs1.campaign_id = cs2.campaign_id AND cs1.id = cs2.max_id
              ) cs ON cm.campaign_id = cs.campaign_id
              ORDER BY cm.campaign_id DESC";
    $result = $conn->query($query);
    $campaigns = $result->fetch_all(MYSQLI_ASSOC);

    foreach ($campaigns as &$campaign) {
        $campaign['valid_emails'] = $valid_emails_total; // Total valid emails in system
        
        // Get actual valid email count for this campaign's CSV list
        if ($campaign['csv_list_id']) {
            $csvListId = (int)$campaign['csv_list_id'];
            $csvValidRes = $conn->query("SELECT COUNT(*) as cnt FROM emails WHERE csv_list_id = $csvListId AND domain_status = 1 AND validation_status = 'valid'");
            if ($csvValidRes) {
                $campaign['csv_list_valid_count'] = (int)$csvValidRes->fetch_assoc()['cnt'];
            } else {
                $campaign['csv_list_valid_count'] = 0;
            }
        } else {
            // If no CSV list selected, show total valid emails
            $campaign['csv_list_valid_count'] = $valid_emails_total;
        }
        
        $campaign['total_emails'] = (int)($campaign['total_emails'] ?? 0);
        $campaign['pending_emails'] = (int)($campaign['pending_emails'] ?? 0);
        $campaign['sent_emails'] = (int)($campaign['sent_emails'] ?? 0);
        
        // Override failed_emails with permanently failed count (5+ attempts) from mail_blaster
        $permanently_failed = (int)($campaign['permanently_failed_count'] ?? 0);
        $campaign['failed_emails'] = $permanently_failed;
        
        // Ensure retryable_count is an integer
        $campaign['retryable_count'] = (int)($campaign['retryable_count'] ?? 0);
        
        $total = max($campaign['total_emails'], 1);
        $sent = min($campaign['sent_emails'], $total);
        $campaign['progress'] = round(($sent / $total) * 100);
    }
    return $campaigns;
}

function startCampaign($conn, $campaign_id)
{
    $max_retries = 5;
    $retry_count = 0;
    $success = false;
    while ($retry_count < $max_retries && !$success) {
        try {
            $conn->query("SET SESSION innodb_lock_wait_timeout = 10");
            $conn->begin_transaction();
            $check = $conn->query("SELECT 1 FROM campaign_master WHERE campaign_id = $campaign_id");
            if ($check->num_rows == 0) {
                $conn->commit();
                throw new Exception("Campaign #$campaign_id does not exist");
            }
            $status_check = $conn->query("SELECT status FROM campaign_status WHERE campaign_id = $campaign_id");
            if ($status_check->num_rows > 0 && $status_check->fetch_assoc()['status'] === 'completed') {
                $conn->commit();
                throw new Exception("Campaign #$campaign_id is already completed");
            }
            $counts = getEmailCounts($conn, $campaign_id);
            // Use INSERT...ON DUPLICATE KEY to prevent duplicates and ensure single row per campaign
            $conn->query("INSERT INTO campaign_status 
                    (campaign_id, total_emails, pending_emails, sent_emails, failed_emails, status, start_time)
                    VALUES ($campaign_id, {$counts['total_valid']}, {$counts['pending']}, {$counts['sent']}, {$counts['failed']}, 'running', NOW())
                    ON DUPLICATE KEY UPDATE
                    status = 'running',
                    total_emails = {$counts['total_valid']},
                    pending_emails = {$counts['pending']},
                    sent_emails = {$counts['sent']},
                    failed_emails = {$counts['failed']},
                    start_time = IFNULL(start_time, NOW()),
                    end_time = NULL");
            $conn->commit();
            $success = true;
            // Start process IMMEDIATELY after commit
            startEmailBlasterProcess($campaign_id);
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            if (strpos($e->getMessage(), 'Lock wait timeout exceeded') !== false) {
                $retry_count++;
                sleep(1);
                if ($retry_count >= $max_retries) {
                    throw new Exception("Failed to start campaign #$campaign_id after $max_retries attempts due to lock timeout");
                }
            } else {
                throw new Exception("Database error starting campaign #$campaign_id: " . $e->getMessage());
            }
        }
    }
    $conn->query("SET SESSION innodb_lock_wait_timeout = 50");
}

function getEmailCounts($conn, $campaign_id)
{
    // Get csv_list_id for this campaign
    $csvListResult = $conn->query("SELECT csv_list_id FROM campaign_master WHERE campaign_id = $campaign_id");
    $csvListId = null;
    if ($csvListResult && $csvListResult->num_rows > 0) {
        $csvListRow = $csvListResult->fetch_assoc();
        $csvListId = $csvListRow['csv_list_id'];
    }
    
    // Build WHERE clause based on csv_list_id
    // Now filter by mail_blaster.csv_list_id since we store it there when sending
    $csvListFilter = $csvListId ? "AND mb.csv_list_id = $csvListId" : "";
    
    // Count total valid emails for this campaign and CSV list from emails table
    $totalQuery = "SELECT COUNT(*) as total_valid 
            FROM emails e 
            WHERE e.domain_status = 1 
            AND e.validation_status = 'valid'
            " . ($csvListId ? "AND e.csv_list_id = $csvListId" : "");
    $totalResult = $conn->query($totalQuery);
    $totalValid = ($totalResult && $totalResult->num_rows > 0) ? (int)$totalResult->fetch_assoc()['total_valid'] : 0;
    
    // Count sent and failed from mail_blaster (filtered by csv_list_id stored in mail_blaster)
    // Only count permanently failed (attempt_count >= 5)
    $query = "SELECT 
                COALESCE(SUM(CASE WHEN mb.status = 'success' THEN 1 ELSE 0 END), 0) as sent,
                COALESCE(SUM(CASE WHEN mb.status = 'failed' AND mb.attempt_count >= 5 THEN 1 ELSE 0 END), 0) as failed
            FROM mail_blaster mb
            WHERE mb.campaign_id = $campaign_id
            $csvListFilter";
    
    $result = $conn->query($query);
    $counts = $result->fetch_assoc();
    
    // Calculate pending as total_valid - sent - failed
    $sent = (int)$counts['sent'];
    $failed = (int)$counts['failed'];
    $pending = max(0, $totalValid - $sent - $failed);
    
    return [
        'total_valid' => $totalValid,
        'pending' => $pending,
        'sent' => $sent,
        'failed' => $failed
    ];
}

function startEmailBlasterProcess($campaign_id)
{
    global $conn;
    
    // Ensure tmp directory exists
    $tmp_dir = __DIR__ . "/../tmp";
    if (!is_dir($tmp_dir)) {
        @mkdir($tmp_dir, 0777, true);
    }
    
    // Use a pid file inside the project's tmp directory
    $pid_file = $tmp_dir . "/email_blaster_{$campaign_id}.pid";

    // If pid file exists, check whether process is still running
    if (file_exists($pid_file)) {
        $pid = trim(file_get_contents($pid_file));
        if (is_numeric($pid)) {
            // posix_kill may not be available on some systems; check /proc as fallback
            $isRunning = false;
            if (function_exists('posix_kill')) {
                $isRunning = @posix_kill((int)$pid, 0);
            } else {
                $isRunning = file_exists("/proc/" . (int)$pid);
            }

            if ($isRunning) {
                // Process is running, do not start another
                return;
            }
        }
        // Stale pid file - remove it
        @unlink($pid_file);
    }

    // Use parallel email blast (7 workers - 1 per server)
    $script_path = __DIR__ . '/../includes/email_blast_parallel.php';
    // $log_file = __DIR__ . '/../logs/campaign_' . $campaign_id . '.log'; // Commented - log file disabled

    // Detect PHP CLI binary (avoid php-fpm!) using php -i Server API check
    $php_candidates = [
        '/opt/plesk/php/8.1/bin/php',
        '/usr/bin/php8.1',
        '/usr/local/bin/php',
        '/usr/bin/php'
    ];
    $php_path = null;
    foreach ($php_candidates as $candidate) {
        if (file_exists($candidate) && is_executable($candidate)) {
            $info = shell_exec(escapeshellarg($candidate) . ' -i 2>&1');
            if ($info && stripos($info, 'Server API => Command Line Interface') !== false) {
                $php_path = $candidate;
                break;
            }
        }
    }
    if (!$php_path) {
        $env_php = trim(shell_exec('command -v php 2>/dev/null')) ?: 'php';
        $info = shell_exec(escapeshellarg($env_php) . ' -i 2>&1');
        $php_path = ($info && stripos($info, 'Server API => Command Line Interface') !== false)
            ? $env_php
            : '/opt/plesk/php/8.1/bin/php';
    }
    
    // Close DB and send response immediately
    ProcessManager::closeConnections($conn);
    
    // Start background process
    $pid = ProcessManager::execute($php_path, $script_path, [$campaign_id], null); // Log file disabled
    
    if ($pid > 0) {
        file_put_contents($pid_file, $pid); // Keep PID file only
        // error_log("[" . date('Y-m-d H:i:s') . "] Started campaign $campaign_id with PID $pid\n", 3, __DIR__ . '/../logs/campaign_starts.log'); // Commented - log disabled
    }
}

function pauseCampaign($conn, $campaign_id)
{
    $max_retries = 3;
    $retry_count = 0;
    $success = false;
    while ($retry_count < $max_retries && !$success) {
        try {
            $conn->query("SET SESSION innodb_lock_wait_timeout = 10");
            $conn->begin_transaction();
            $result = $conn->query("UPDATE campaign_status SET status = 'paused' 
                    WHERE campaign_id = $campaign_id AND status = 'running'");
            if ($conn->affected_rows > 0) {
                stopEmailBlasterProcess($campaign_id);
                $success = true;
            } else {
                $success = true;
            }
            $conn->commit();
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            if (strpos($e->getMessage(), 'Lock wait timeout exceeded') !== false) {
                $retry_count++;
                sleep(1);
                if ($retry_count >= $max_retries) {
                    throw new Exception("Failed to pause campaign #$campaign_id after $max_retries attempts due to lock timeout");
                }
            } else {
                throw new Exception("Database error pausing campaign #$campaign_id: " . $e->getMessage());
            }
        }
    }
    $conn->query("SET SESSION innodb_lock_wait_timeout = 50");
}

function stopEmailBlasterProcess($campaign_id)
{
    // Try graceful stop: read pid file and kill the process, then remove pid file
    $pid_file = __DIR__ . "/../tmp/email_blaster_{$campaign_id}.pid";
    if (file_exists($pid_file)) {
        $pid = (int)trim(file_get_contents($pid_file));
        if ($pid > 0) {
            // Send SIGTERM (15)
            @posix_kill($pid, 15);
            // Wait briefly for process to exit
            usleep(200000);
            // If still running, send SIGKILL (9)
            if (function_exists('posix_kill') && @posix_kill($pid, 0)) {
                @posix_kill($pid, 9);
            }
        }
        @unlink($pid_file);
    } else {
        // Fallback to pkill by pattern if pid file missing
        exec("pkill -f 'email_blaster.php $campaign_id'");
    }
}

function retryFailedEmails($conn, $campaign_id)
{
    // Only retry emails that haven't exceeded 5 attempts
    $result = $conn->query("
            SELECT COUNT(*) as failed_count 
            FROM mail_blaster 
            WHERE campaign_id = $campaign_id 
            AND status = 'failed'
            AND attempt_count < 5
        ");
    $failed_count = $result->fetch_assoc()['failed_count'];
    
    if ($failed_count > 0) {
        // Reset failed emails back to pending for retry (don't increment attempt_count here, worker will do it)
        $conn->query("
                UPDATE mail_blaster 
                SET status = 'pending',     
                    error_message = NULL
                WHERE campaign_id = $campaign_id 
                AND status = 'failed'
                AND attempt_count < 5
            ");
        
        // Update campaign status
        $conn->query("
                UPDATE campaign_status 
                SET status = 'running'
                WHERE campaign_id = $campaign_id
            ");
        
        startEmailBlasterProcess($campaign_id);
        return "Retrying $failed_count failed emails for campaign #$campaign_id (max 5 attempts per email)";
    } else {
        return "No emails available for retry. All failed emails have reached maximum attempts (5).";
    }
}