<?php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
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
    $query = "SELECT 
                cm.campaign_id, 
                cm.description, 
                cm.mail_subject,
                cm.attachment_path,
                COALESCE((SELECT COUNT(*) FROM emails WHERE domain_status = 1 AND domain_processed = 1), 0) AS valid_emails,
                cs.status as campaign_status,
                COALESCE(cs.total_emails, 0) as total_emails,
                COALESCE(cs.pending_emails, 0) as pending_emails,
                COALESCE(cs.sent_emails, 0) as sent_emails,
                COALESCE(cs.failed_emails, 0) as failed_emails,
                cs.start_time,
                cs.end_time,
                (
                    SELECT COUNT(*) 
                    FROM mail_blaster mb 
                    WHERE mb.campaign_id = cm.campaign_id 
                    AND mb.status = 'pending'
                    AND mb.attempt_count < 3
                ) as retryable_count
              FROM campaign_master cm
              LEFT JOIN campaign_status cs ON cm.campaign_id = cs.campaign_id
              ORDER BY cm.campaign_id DESC";
    $result = $conn->query($query);
    $campaigns = $result->fetch_all(MYSQLI_ASSOC);

    foreach ($campaigns as &$campaign) {
        $campaign['valid_emails'] = (int)($campaign['valid_emails'] ?? 0);
        $campaign['total_emails'] = (int)($campaign['total_emails'] ?? 0);
        $campaign['pending_emails'] = (int)($campaign['pending_emails'] ?? 0);
        $campaign['sent_emails'] = (int)($campaign['sent_emails'] ?? 0);
        $campaign['failed_emails'] = (int)($campaign['failed_emails'] ?? 0);
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
            if ($status_check->num_rows > 0) {
                $conn->query("UPDATE campaign_status SET 
                        status = 'running',
                        total_emails = {$counts['total_valid']},
                        pending_emails = {$counts['pending']},
                        sent_emails = {$counts['sent']},
                        failed_emails = {$counts['failed']},
                        start_time = IFNULL(start_time, NOW()),
                        end_time = NULL
                        WHERE campaign_id = $campaign_id");
            } else {
                $conn->query("INSERT INTO campaign_status 
                        (campaign_id, total_emails, pending_emails, sent_emails, failed_emails, status, start_time)
                        VALUES ($campaign_id, {$counts['total_valid']}, {$counts['pending']}, {$counts['sent']}, {$counts['failed']}, 'running', NOW())");
            }
            $conn->commit();
            $success = true;
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
    // Compute counts based on emails and mail_blaster for the given campaign
    $result = $conn->query("SELECT 
                COUNT(*) as total_valid,
                SUM(CASE WHEN (mb.status IS NULL OR (mb.status = 'failed' AND mb.attempt_count < 3)) THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN mb.status = 'success' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN mb.status = 'failed' AND mb.attempt_count >= 3 THEN 1 ELSE 0 END) as failed
            FROM emails e
            LEFT JOIN mail_blaster mb ON mb.to_mail = e.raw_emailid AND mb.campaign_id = $campaign_id
            WHERE e.domain_status = 1");

    return $result->fetch_assoc();
}

function startEmailBlasterProcess($campaign_id)
{
    // Use a pid file inside the project's tmp directory so it's easy to find and consistent
    $pid_file = __DIR__ . "/../tmp/email_blaster_{$campaign_id}.pid";

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

    // Prefer the current PHP binary; fallback to 'php' in PATH
    $php_path = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : 'php';
    // Script path relative to this file
    $script_path = __DIR__ . '/email_blaster.php';

    // Start the email blaster in background and capture pid
    $command = "nohup " . escapeshellcmd($php_path) . " " . escapeshellarg($script_path) . " " . intval($campaign_id) . " > /dev/null 2>&1 & echo $!";
    $pid = shell_exec($command);
    if ($pid) {
        file_put_contents($pid_file, trim($pid));
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
    $result = $conn->query("
            SELECT COUNT(*) as failed_count 
            FROM mail_blaster 
            WHERE campaign_id = $campaign_id 
            AND status = 'failed'
            AND attempt_count < 5
        ");
    $failed_count = $result->fetch_assoc()['failed_count'];
    if ($failed_count > 0) {
        $conn->query("
                UPDATE mail_blaster 
                SET status = 'pending',     
                    error_message = NULL,
                    attempt_count = attempt_count + 1
                WHERE campaign_id = $campaign_id 
                AND status = 'failed'
            ");
        $conn->query("
                UPDATE campaign_status 
                SET pending_emails = pending_emails + $failed_count,
                    failed_emails = GREATEST(0, failed_emails - $failed_count),
                    status = 'running'
                WHERE campaign_id = $campaign_id
            ");
        startEmailBlasterProcess($campaign_id);
        return "Retrying $failed_count failed emails for campaign #$campaign_id";
    } else {
        return "Retry campaign limit reached for campaign #$campaign_id";
    }
}