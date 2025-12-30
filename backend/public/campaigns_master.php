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
        } elseif ($action === 'get_campaign_emails') {
            $campaign_id = (int)($input['campaign_id'] ?? 0);
            $page = (int)($input['page'] ?? 1);
            $limit = (int)($input['limit'] ?? 50);
            $response['success'] = true;
            $response['data'] = getCampaignEmails($conn, $campaign_id, $page, $limit);
        } elseif ($action === 'get_template_preview') {
            $campaign_id = (int)($input['campaign_id'] ?? 0);
            $email_index = (int)($input['email_index'] ?? 0);
            $response['success'] = true;
            $response['data'] = getTemplatePreview($conn, $campaign_id, $email_index);
        } elseif ($action === 'update_campaign_status') {
            $campaign_id = (int)($input['campaign_id'] ?? 0);
            updateCampaignCompletionStatus($conn, $campaign_id);
            $response['success'] = true;
            $response['message'] = "Campaign status updated successfully";
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
    
    // FIRST: Ensure every campaign has a campaign_status row
    // a) from mail_blaster (already sent)
    $conn->query("
        INSERT INTO campaign_status (campaign_id, status, total_emails, sent_emails, failed_emails, pending_emails, start_time)
        SELECT 
            mb.campaign_id,
            'running' as status,
            0 as total_emails,
            COUNT(DISTINCT CASE WHEN mb.status = 'success' THEN mb.to_mail END) as sent_emails,
            COUNT(DISTINCT CASE WHEN mb.status = 'failed' THEN mb.to_mail END) as failed_emails,
            0 as pending_emails,
            MIN(mb.delivery_time) as start_time
        FROM mail_blaster mb
        WHERE mb.campaign_id NOT IN (SELECT campaign_id FROM campaign_status)
        GROUP BY mb.campaign_id
        ON DUPLICATE KEY UPDATE campaign_id = campaign_id
    ");
    // b) from campaign_master (not started yet)
    $conn->query("
        INSERT INTO campaign_status (campaign_id, status, total_emails, pending_emails, sent_emails, failed_emails)
        SELECT cm.campaign_id, 'pending', 0, 0, 0, 0
        FROM campaign_master cm
        WHERE cm.campaign_id NOT IN (SELECT campaign_id FROM campaign_status)
    ");
    
    // SECOND: Update totals for all campaigns based on their source
    $allCampaigns = $conn->query("
        SELECT cs.campaign_id, cm.import_batch_id, cm.csv_list_id
        FROM campaign_status cs
        JOIN campaign_master cm ON cs.campaign_id = cm.campaign_id
    ");
    
    if ($allCampaigns) {
        while ($camp = $allCampaigns->fetch_assoc()) {
            $cid = $camp['campaign_id'];
            $import_batch_id = $camp['import_batch_id'];
            $csv_list_id = intval($camp['csv_list_id']);
            
            $total = 0;
            if ($import_batch_id) {
                $batch_escaped = $conn->real_escape_string($import_batch_id);
                $totalRes = $conn->query("SELECT COUNT(*) as total FROM imported_recipients WHERE import_batch_id = '$batch_escaped' AND is_active = 1 AND Emails IS NOT NULL AND Emails <> ''");
                $total = intval($totalRes->fetch_assoc()['total']);
            } elseif ($csv_list_id > 0) {
                $totalRes = $conn->query("SELECT COUNT(*) as total FROM emails WHERE csv_list_id = $csv_list_id AND domain_status = 1 AND validation_status = 'valid' AND raw_emailid IS NOT NULL AND raw_emailid <> ''");
                $total = intval($totalRes->fetch_assoc()['total']);
            }
            
            // Always update total_emails to the current expected count
            $conn->query("UPDATE campaign_status SET total_emails = $total WHERE campaign_id = $cid");
        }
    }
    
    // THIRD: Auto-update running campaigns to completed based on mail_blaster actual counts
    $runningCampaigns = $conn->query("
        SELECT cs.campaign_id, cm.import_batch_id, cm.csv_list_id,
               cs.total_emails, cs.sent_emails, cs.failed_emails, cs.pending_emails
        FROM campaign_status cs
        JOIN campaign_master cm ON cs.campaign_id = cm.campaign_id
        WHERE cs.status = 'running'
    ");
    
    if ($runningCampaigns) {
        while ($campaign = $runningCampaigns->fetch_assoc()) {
            $campaign_id = $campaign['campaign_id'];
            $import_batch_id = $campaign['import_batch_id'];
            $csv_list_id = intval($campaign['csv_list_id']);
            
            // Get actual counts from mail_blaster
            $blasterStats = $conn->query("
                SELECT 
                    COUNT(DISTINCT to_mail) as total_in_blaster,
                    COUNT(DISTINCT CASE WHEN status = 'success' THEN to_mail END) as actual_sent,
                    COUNT(DISTINCT CASE WHEN status = 'failed' AND attempt_count >= 5 THEN to_mail END) as actual_failed
                FROM mail_blaster
                WHERE campaign_id = $campaign_id
            ");
            
            if ($blasterStats && $blasterStats->num_rows > 0) {
                $stats = $blasterStats->fetch_assoc();
                $actual_sent = intval($stats['actual_sent']);
                $actual_failed = intval($stats['actual_failed']);
                $total_in_blaster = intval($stats['total_in_blaster']);
                
                // CRITICAL: Check for unclaimed emails (not in mail_blaster yet)
                $unclaimed = 0;
                if ($import_batch_id) {
                    $batch_escaped = $conn->real_escape_string($import_batch_id);
                    $unclaimedRes = $conn->query("
                        SELECT COUNT(*) as unclaimed FROM imported_recipients ir
                        WHERE ir.import_batch_id = '$batch_escaped'
                        AND ir.is_active = 1
                        AND ir.Emails IS NOT NULL
                        AND ir.Emails <> ''
                        AND NOT EXISTS (
                            SELECT 1 FROM mail_blaster mb
                            WHERE mb.campaign_id = $campaign_id
                            AND mb.to_mail COLLATE utf8mb4_unicode_ci = ir.Emails
                        )
                    ");
                    if ($unclaimedRes) {
                        $unclaimed = intval($unclaimedRes->fetch_assoc()['unclaimed']);
                    }
                } elseif ($csv_list_id > 0) {
                    $unclaimedRes = $conn->query("
                        SELECT COUNT(*) as unclaimed FROM emails e
                        WHERE e.domain_status = 1
                        AND e.validation_status = 'valid'
                        AND e.raw_emailid IS NOT NULL
                        AND e.raw_emailid <> ''
                        AND e.csv_list_id = $csv_list_id
                        AND NOT EXISTS (
                            SELECT 1 FROM mail_blaster mb
                            WHERE mb.campaign_id = $campaign_id
                            AND mb.to_mail = e.raw_emailid
                        )
                    ");
                    if ($unclaimedRes) {
                        $unclaimed = intval($unclaimedRes->fetch_assoc()['unclaimed']);
                    }
                }
                
                // Get expected total from source
                $expected_total = 0;
                if ($import_batch_id) {
                    $batch_escaped = $conn->real_escape_string($import_batch_id);
                    $totalRes = $conn->query("
                        SELECT COUNT(*) as total 
                        FROM imported_recipients 
                        WHERE import_batch_id = '$batch_escaped' 
                        AND is_active = 1 
                        AND Emails IS NOT NULL 
                        AND Emails <> ''
                    ");
                    $expected_total = intval($totalRes->fetch_assoc()['total']);
                } elseif ($csv_list_id > 0) {
                    $totalRes = $conn->query("
                        SELECT COUNT(*) as total 
                        FROM emails 
                        WHERE csv_list_id = $csv_list_id 
                        AND domain_status = 1 
                        AND validation_status = 'valid'
                        AND raw_emailid IS NOT NULL 
                        AND raw_emailid <> ''
                    ");
                    $expected_total = intval($totalRes->fetch_assoc()['total']);
                } else {
                    $expected_total = intval($campaign['total_emails']);
                }
                
                // Fallback to mail_blaster total if source total is 0
                if ($expected_total === 0 && $total_in_blaster > 0) {
                    $expected_total = $total_in_blaster;
                }

                // Compute pending
                $pending = max(0, $expected_total - $actual_sent - $actual_failed);
                $pending_in_blaster = max(0, $total_in_blaster - $actual_sent - $actual_failed);

                // Determine if campaign should be completed
                // ONLY complete when ALL emails are claimed AND processed
                $should_complete = false;
                if ($unclaimed === 0 && $expected_total > 0 && ($actual_sent + $actual_failed) >= $expected_total) {
                    // No unclaimed emails AND all expected emails are processed
                    $should_complete = true;
                } elseif ($unclaimed === 0 && $total_in_blaster > 0 && $pending_in_blaster === 0 && $expected_total > 0) {
                    // No unclaimed emails AND all in mail_blaster are processed
                    $should_complete = true;
                }
                
                // Update the campaign status
                if ($should_complete) {
                    $conn->query("
                        UPDATE campaign_status 
                        SET status = 'completed',
                            total_emails = $expected_total,
                            sent_emails = $actual_sent,
                            failed_emails = $actual_failed,
                            pending_emails = 0,
                            end_time = CASE WHEN end_time IS NULL THEN NOW() ELSE end_time END
                        WHERE campaign_id = $campaign_id
                    ");
                } else {
                    // Just update counts
                    $conn->query("
                        UPDATE campaign_status 
                        SET total_emails = $expected_total,
                            sent_emails = $actual_sent,
                            failed_emails = $actual_failed,
                            pending_emails = $pending
                        WHERE campaign_id = $campaign_id
                    ");
                }
            }
        }
    }
    
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
                cm.import_batch_id,
                cm.template_id,
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
        
        // Determine email source type
        if ($campaign['import_batch_id']) {
            $campaign['email_source'] = 'imported_recipients';
            $campaign['email_source_label'] = 'Excel Import';
            
            // Get count from imported_recipients
            $batch_escaped = $conn->real_escape_string($campaign['import_batch_id']);
            $importedRes = $conn->query("SELECT COUNT(*) as cnt FROM imported_recipients WHERE import_batch_id = '$batch_escaped' AND is_active = 1 AND Emails IS NOT NULL AND Emails <> ''");
            if ($importedRes) {
                $campaign['csv_list_valid_count'] = (int)$importedRes->fetch_assoc()['cnt'];
            } else {
                $campaign['csv_list_valid_count'] = 0;
            }
        } elseif ($campaign['csv_list_id']) {
            $campaign['email_source'] = 'csv_upload';
            $campaign['email_source_label'] = 'CSV Upload';
            
            // Get actual valid email count for this campaign's CSV list
            $csvListId = (int)$campaign['csv_list_id'];
            $csvValidRes = $conn->query("SELECT COUNT(*) as cnt FROM emails WHERE csv_list_id = $csvListId AND domain_status = 1 AND validation_status = 'valid'");
            if ($csvValidRes) {
                $campaign['csv_list_valid_count'] = (int)$csvValidRes->fetch_assoc()['cnt'];
            } else {
                $campaign['csv_list_valid_count'] = 0;
            }
        } else {
            $campaign['email_source'] = 'all_emails';
            $campaign['email_source_label'] = 'All Valid Emails';
            
            // If no CSV list or import batch selected, show total valid emails
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
    if ($conn && !$conn->connect_error) {
        $conn->query("SET SESSION innodb_lock_wait_timeout = 50");
    }
}

function getEmailCounts($conn, $campaign_id)
{
    // Get csv_list_id and import_batch_id for this campaign
    $campaignResult = $conn->query("SELECT csv_list_id, import_batch_id FROM campaign_master WHERE campaign_id = $campaign_id");
    $csvListId = null;
    $importBatchId = null;
    
    if ($campaignResult && $campaignResult->num_rows > 0) {
        $campaignRow = $campaignResult->fetch_assoc();
        $csvListId = $campaignRow['csv_list_id'];
        $importBatchId = $campaignRow['import_batch_id'];
    }
    
    // Count total valid emails based on source
    if ($importBatchId) {
        // Count from imported_recipients table
        $batch_escaped = $conn->real_escape_string($importBatchId);
        $totalQuery = "SELECT COUNT(*) as total_valid 
                FROM imported_recipients 
                WHERE import_batch_id = '$batch_escaped' 
                AND is_active = 1 
                AND Emails IS NOT NULL 
                AND Emails <> ''";
        $totalResult = $conn->query($totalQuery);
        $totalValid = ($totalResult && $totalResult->num_rows > 0) ? (int)$totalResult->fetch_assoc()['total_valid'] : 0;
        
        // For imported_recipients, we don't use csv_list_id filter in mail_blaster
        $csvListFilter = "";
    } else {
        // Count from emails table (CSV upload)
        $csvListFilter = $csvListId ? "AND mb.csv_list_id = $csvListId" : "";
        
        $totalQuery = "SELECT COUNT(*) as total_valid 
                FROM emails e 
                WHERE e.domain_status = 1 
                AND e.validation_status = 'valid'
                " . ($csvListId ? "AND e.csv_list_id = $csvListId" : "");
        $totalResult = $conn->query($totalQuery);
        $totalValid = ($totalResult && $totalResult->num_rows > 0) ? (int)$totalResult->fetch_assoc()['total_valid'] : 0;
    }
    
    // Build WHERE clause based on csv_list_id
    // Now filter by mail_blaster.csv_list_id since we store it there when sending
    
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
    
    // Don't close the main connection here - let the calling code handle it
    // ProcessManager::closeConnections($conn);
    
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

/**
 * Get list of emails for a specific campaign
 * Shows emails from imported_recipients if campaign uses Excel import
 * Shows emails from emails table if campaign uses CSV upload
 */
function getCampaignEmails($conn, $campaign_id, $page = 1, $limit = 50)
{
    // Get campaign details to determine source
    $campaignQuery = "SELECT csv_list_id, import_batch_id, template_id, description 
                      FROM campaign_master 
                      WHERE campaign_id = $campaign_id";
    $campaignResult = $conn->query($campaignQuery);
    
    if (!$campaignResult || $campaignResult->num_rows === 0) {
        return [
            'error' => 'Campaign not found',
            'campaign_id' => $campaign_id
        ];
    }
    
    $campaign = $campaignResult->fetch_assoc();
    $csvListId = $campaign['csv_list_id'];
    $importBatchId = $campaign['import_batch_id'];
    $templateId = $campaign['template_id'];
    
    $offset = ($page - 1) * $limit;
    $emails = [];
    $total = 0;
    $source = '';
    
    // Determine source and fetch emails
    if ($importBatchId) {
        // Fetch from imported_recipients (Excel import)
        $source = 'imported_recipients';
        $batch_escaped = $conn->real_escape_string($importBatchId);
        
        // Get total count
        $countQuery = "SELECT COUNT(*) as total 
                       FROM imported_recipients 
                       WHERE import_batch_id = '$batch_escaped' 
                       AND is_active = 1 
                       AND Emails IS NOT NULL 
                       AND Emails <> ''";
        $countResult = $conn->query($countQuery);
        $total = ($countResult && $countResult->num_rows > 0) ? (int)$countResult->fetch_assoc()['total'] : 0;
        
        // Get paginated emails with their data
        $emailQuery = "SELECT 
                        id,
                        Emails as email,
                        BilledName as name,
                        Company,
                        Amount,
                        Days,
                        BillNumber,
                        CustomerID,
                        Phone,
                        source_file_type
                       FROM imported_recipients 
                       WHERE import_batch_id = '$batch_escaped' 
                       AND is_active = 1 
                       AND Emails IS NOT NULL 
                       AND Emails <> ''
                       ORDER BY id ASC
                       LIMIT $limit OFFSET $offset";
        
        $emailResult = $conn->query($emailQuery);
        
        if ($emailResult) {
            while ($row = $emailResult->fetch_assoc()) {
                // Get send status from mail_blaster if exists
                $email_escaped = $conn->real_escape_string($row['email']);
                $statusQuery = "SELECT status, attempt_count, delivery_date, delivery_time, error_message 
                                FROM mail_blaster 
                                WHERE campaign_id = $campaign_id 
                                AND to_mail = '$email_escaped' 
                                LIMIT 1";
                $statusResult = $conn->query($statusQuery);
                
                $send_status = 'not_sent';
                $attempt_count = 0;
                $delivery_info = null;
                $error_message = null;
                
                if ($statusResult && $statusResult->num_rows > 0) {
                    $status = $statusResult->fetch_assoc();
                    $send_status = $status['status'] ?? 'pending';
                    $attempt_count = (int)($status['attempt_count'] ?? 0);
                    $delivery_info = $status['delivery_date'] . ' ' . $status['delivery_time'];
                    $error_message = $status['error_message'];
                }
                
                $emails[] = [
                    'id' => $row['id'],
                    'email' => $row['email'],
                    'name' => $row['name'] ?: $row['Company'] ?: 'N/A',
                    'company' => $row['Company'] ?: $row['name'] ?: '',
                    'amount' => $row['Amount'] ?: '',
                    'days' => $row['Days'] ?: '',
                    'bill_number' => $row['BillNumber'] ?: '',
                    'customer_id' => $row['CustomerID'] ?: '',
                    'phone' => $row['Phone'] ?: '',
                    'file_type' => $row['source_file_type'] ?: 'unknown',
                    'send_status' => $send_status,
                    'attempt_count' => $attempt_count,
                    'delivery_info' => $delivery_info,
                    'error_message' => $error_message
                ];
            }
        }
        
    } elseif ($csvListId) {
        // Fetch from emails table (CSV upload)
        $source = 'csv_upload';
        
        // Get total count
        $countQuery = "SELECT COUNT(*) as total 
                       FROM emails 
                       WHERE csv_list_id = $csvListId 
                       AND domain_status = 1 
                       AND validation_status = 'valid'";
        $countResult = $conn->query($countQuery);
        $total = ($countResult && $countResult->num_rows > 0) ? (int)$countResult->fetch_assoc()['total'] : 0;
        
        // Get paginated emails
        $emailQuery = "SELECT 
                        id,
                        raw_emailid as email,
                        name,
                        company,
                        phone
                       FROM emails 
                       WHERE csv_list_id = $csvListId 
                       AND domain_status = 1 
                       AND validation_status = 'valid'
                       ORDER BY id ASC
                       LIMIT $limit OFFSET $offset";
        
        $emailResult = $conn->query($emailQuery);
        
        if ($emailResult) {
            while ($row = $emailResult->fetch_assoc()) {
                // Get send status from mail_blaster if exists
                $email_escaped = $conn->real_escape_string($row['email']);
                $statusQuery = "SELECT status, attempt_count, delivery_date, delivery_time, error_message 
                                FROM mail_blaster 
                                WHERE campaign_id = $campaign_id 
                                AND to_mail = '$email_escaped' 
                                LIMIT 1";
                $statusResult = $conn->query($statusQuery);
                
                $send_status = 'not_sent';
                $attempt_count = 0;
                $delivery_info = null;
                $error_message = null;
                
                if ($statusResult && $statusResult->num_rows > 0) {
                    $status = $statusResult->fetch_assoc();
                    $send_status = $status['status'] ?? 'pending';
                    $attempt_count = (int)($status['attempt_count'] ?? 0);
                    $delivery_info = $status['delivery_date'] . ' ' . $status['delivery_time'];
                    $error_message = $status['error_message'];
                }
                
                $emails[] = [
                    'id' => $row['id'],
                    'email' => $row['email'],
                    'name' => $row['name'] ?: 'N/A',
                    'company' => $row['company'] ?: '',
                    'phone' => $row['phone'] ?: '',
                    'file_type' => 'csv',
                    'send_status' => $send_status,
                    'attempt_count' => $attempt_count,
                    'delivery_info' => $delivery_info,
                    'error_message' => $error_message
                ];
            }
        }
        
    } else {
        // No specific source - use all valid emails
        $source = 'all_valid_emails';
        
        // Get total count
        $countQuery = "SELECT COUNT(*) as total 
                       FROM emails 
                       WHERE domain_status = 1 
                       AND validation_status = 'valid'";
        $countResult = $conn->query($countQuery);
        $total = ($countResult && $countResult->num_rows > 0) ? (int)$countResult->fetch_assoc()['total'] : 0;
        
        // Get paginated emails
        $emailQuery = "SELECT 
                        id,
                        raw_emailid as email,
                        name,
                        company,
                        phone
                       FROM emails 
                       WHERE domain_status = 1 
                       AND validation_status = 'valid'
                       ORDER BY id ASC
                       LIMIT $limit OFFSET $offset";
        
        $emailResult = $conn->query($emailQuery);
        
        if ($emailResult) {
            while ($row = $emailResult->fetch_assoc()) {
                // Get send status from mail_blaster if exists
                $email_escaped = $conn->real_escape_string($row['email']);
                $statusQuery = "SELECT status, attempt_count, delivery_date, delivery_time, error_message 
                                FROM mail_blaster 
                                WHERE campaign_id = $campaign_id 
                                AND to_mail = '$email_escaped' 
                                LIMIT 1";
                $statusResult = $conn->query($statusQuery);
                
                $send_status = 'not_sent';
                $attempt_count = 0;
                $delivery_info = null;
                $error_message = null;
                
                if ($statusResult && $statusResult->num_rows > 0) {
                    $status = $statusResult->fetch_assoc();
                    $send_status = $status['status'] ?? 'pending';
                    $attempt_count = (int)($status['attempt_count'] ?? 0);
                    $delivery_info = $status['delivery_date'] . ' ' . $status['delivery_time'];
                    $error_message = $status['error_message'];
                }
                
                $emails[] = [
                    'id' => $row['id'],
                    'email' => $row['email'],
                    'name' => $row['name'] ?: 'N/A',
                    'company' => $row['company'] ?: '',
                    'phone' => $row['phone'] ?: '',
                    'file_type' => 'system',
                    'send_status' => $send_status,
                    'attempt_count' => $attempt_count,
                    'delivery_info' => $delivery_info,
                    'error_message' => $error_message
                ];
            }
        }
    }
    
    return [
        'campaign_id' => $campaign_id,
        'campaign_description' => $campaign['description'],
        'email_source' => $source,
        'uses_template' => !empty($templateId),
        'template_id' => $templateId,
        'total' => $total,
        'page' => $page,
        'limit' => $limit,
        'total_pages' => ceil($total / $limit),
        'emails' => $emails
    ];
}

function getTemplatePreview($conn, $campaign_id, $email_index = 0) {
    require_once __DIR__ . '/../includes/template_merge_helper.php';
    
    $campaign_id = intval($campaign_id);
    $email_index = intval($email_index);
    
    // Get campaign details
    $query = "SELECT cm.template_id, cm.import_batch_id, cm.description, cm.mail_subject,
                     mt.template_name, mt.template_html, mt.merge_fields
              FROM campaign_master cm
              LEFT JOIN mail_templates mt ON cm.template_id = mt.template_id
              WHERE cm.campaign_id = $campaign_id";
    
    $result = $conn->query($query);
    if (!$result || $result->num_rows === 0) {
        return ['error' => 'Campaign not found'];
    }
    
    $campaign = $result->fetch_assoc();
    
    // Check if campaign uses template
    if (!$campaign['template_id']) {
        return ['error' => 'Campaign does not use a template'];
    }
    
    // Get all sample emails from imported_recipients
    $sampleEmails = [];
    $totalCount = 0;
    $selectedEmail = null;
    
    if ($campaign['import_batch_id']) {
        $batch_escaped = $conn->real_escape_string($campaign['import_batch_id']);
        
        // Get total count
        $countQuery = "SELECT COUNT(*) as total FROM imported_recipients 
                      WHERE import_batch_id = '$batch_escaped' 
                      AND is_active = 1 
                      AND Emails IS NOT NULL 
                      AND Emails <> ''";
        $countResult = $conn->query($countQuery);
        if ($countResult) {
            $totalCount = (int)$countResult->fetch_assoc()['total'];
        }
        
        // Get list of sample emails (first 10 for dropdown)
        $listQuery = "SELECT * FROM imported_recipients 
                     WHERE import_batch_id = '$batch_escaped' 
                     AND is_active = 1 
                     AND Emails IS NOT NULL 
                     AND Emails <> ''
                     LIMIT 10";
        $listResult = $conn->query($listQuery);
        if ($listResult) {
            while ($row = $listResult->fetch_assoc()) {
                $sampleEmails[] = [
                    'email' => $row['Emails'] ?? '',
                    'name' => $row['BilledName'] ?? $row['Name'] ?? '',
                    'preview_label' => ($row['BilledName'] ?? $row['Name'] ?? $row['Emails']) . 
                                      ($row['Amount'] ? ' - ₹' . $row['Amount'] : '') .
                                      ($row['CustomerID'] ? ' (ID: ' . $row['CustomerID'] . ')' : '')
                ];
            }
        }
        
        // Get selected email data
        $offset = max(0, $email_index);
        $selectedQuery = "SELECT * FROM imported_recipients 
                         WHERE import_batch_id = '$batch_escaped' 
                         AND is_active = 1 
                         AND Emails IS NOT NULL 
                         AND Emails <> ''
                         LIMIT 1 OFFSET $offset";
        $selectedResult = $conn->query($selectedQuery);
        if ($selectedResult && $selectedResult->num_rows > 0) {
            $selectedEmail = $selectedResult->fetch_assoc();
        }
    }
    
    // Prepare template HTML
    $templateHtml = $campaign['template_html'] ?? '';
    $mergeFields = json_decode($campaign['merge_fields'] ?? '[]', true);
    
    // If we have sample data, merge it
    if ($selectedEmail) {
        $mergedHtml = mergeTemplateWithData($templateHtml, $selectedEmail);
    } else {
        $mergedHtml = $templateHtml;
    }
    
    // Prepare current email data for display (excluding internal fields)
    $currentEmailData = null;
    if ($selectedEmail) {
        $currentEmailData = $selectedEmail;
        // Remove internal database fields
        unset($currentEmailData['id']);
        unset($currentEmailData['import_batch_id']);
        unset($currentEmailData['is_active']);
        unset($currentEmailData['created_at']);
    }
    
    return [
        'campaign_id' => $campaign_id,
        'campaign_name' => $campaign['description'],
        'template_id' => $campaign['template_id'],
        'template_name' => $campaign['template_name'],
        'template_html' => $mergedHtml,
        'has_sample_data' => $selectedEmail ? true : false,
        'current_index' => $email_index,
        'total_emails' => $totalCount,
        'sample_emails' => $sampleEmails,
        'current_email' => $currentEmailData,
        'merge_fields' => $mergeFields
    ];
}

/**
 * Update campaign completion status based on actual progress
 * Handles both Excel import and CSV list sources
 */
function updateCampaignCompletionStatus($conn, $campaign_id) {
    // Get campaign details to check source
    $campaignResult = $conn->query("SELECT import_batch_id, csv_list_id FROM campaign_master WHERE campaign_id = $campaign_id");
    if (!$campaignResult || $campaignResult->num_rows === 0) {
        return false;
    }
    
    $campaignData = $campaignResult->fetch_assoc();
    $import_batch_id = $campaignData['import_batch_id'];
    $csv_list_id = intval($campaignData['csv_list_id']);
    
    // Get sent and failed counts from mail_blaster
    $stats = $conn->query("
        SELECT 
            COUNT(DISTINCT CASE WHEN mb.status = 'success' THEN mb.to_mail END) as sent_count,
            COUNT(DISTINCT CASE WHEN mb.status = 'failed' AND mb.attempt_count >= 5 THEN mb.to_mail END) as failed_count
        FROM mail_blaster mb
        WHERE mb.campaign_id = $campaign_id
    ")->fetch_assoc();
    
    $sent_emails = intval($stats['sent_count']);
    $failed_emails = intval($stats['failed_count']);
    
    // Get total emails based on campaign source
    if ($import_batch_id) {
        // Excel import source
        $batch_escaped = $conn->real_escape_string($import_batch_id);
        $total_result = $conn->query("
            SELECT COUNT(*) as total
            FROM imported_recipients
            WHERE import_batch_id = '$batch_escaped'
            AND is_active = 1
            AND Emails IS NOT NULL
            AND Emails <> ''
        ");
        $total_emails = intval($total_result->fetch_assoc()['total']);
    } elseif ($csv_list_id > 0) {
        // CSV list source
        $total_result = $conn->query("
            SELECT COUNT(*) as total
            FROM emails
            WHERE csv_list_id = $csv_list_id
            AND domain_status = 1
            AND validation_status = 'valid'
            AND raw_emailid IS NOT NULL
            AND raw_emailid <> ''
        ");
        $total_emails = intval($total_result->fetch_assoc()['total']);
    } else {
        // All valid emails source
        $total_result = $conn->query("
            SELECT COUNT(DISTINCT raw_emailid) as total
            FROM emails
            WHERE domain_status = 1
            AND validation_status = 'valid'
            AND raw_emailid IS NOT NULL
            AND raw_emailid <> ''
        ");
        $total_emails = intval($total_result->fetch_assoc()['total']);
    }
    
    // Calculate pending emails
    $pending_emails = max(0, $total_emails - $sent_emails - $failed_emails);
    
    // Determine campaign status
    $campaign_status = 'running';
    if ($pending_emails == 0 && $total_emails > 0) {
        $campaign_status = 'completed';
    } elseif ($total_emails == 0) {
        $campaign_status = 'completed';
    }
    
    // Update campaign_status table
    $conn->query("
        UPDATE campaign_status 
        SET 
            sent_emails = $sent_emails,
            failed_emails = $failed_emails,
            pending_emails = $pending_emails,
            total_emails = $total_emails,
            status = '$campaign_status',
            end_time = CASE WHEN '$campaign_status' = 'completed' THEN NOW() ELSE end_time END
        WHERE campaign_id = $campaign_id
    ");
    
    return true;
}