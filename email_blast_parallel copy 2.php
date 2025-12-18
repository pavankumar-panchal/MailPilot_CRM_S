<?php


error_reporting(E_ALL);
ini_set('display_errors', 0); // Production safe
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
set_time_limit(0);
ini_set('memory_limit', '4096M');

date_default_timezone_set('Asia/Kolkata');
// Determine execution mode: CLI daemon or web-trigger that launches the CLI daemon in background
// Support both: when run from CLI the script becomes the daemon; when invoked via web (browser/XHR)
// it will spawn the CLI daemon in background and return immediately.

// Helper to check if a PID is running (works on Linux with /proc)
function isPidRunning($pid) {
    if ($pid <= 0) return false;
    return file_exists('/proc/' . intval($pid));
}

$pid_dir = __DIR__ . '/../tmp';
if (!is_dir($pid_dir)) {
    @mkdir($pid_dir, 0777, true);
}

// Web invocation: spawn background CLI daemon and return JSON
if (php_sapi_name() !== 'cli') {
    // Accept campaign_id via GET/POST (prefer POST)
    $campaign_id = isset($_REQUEST['campaign_id']) ? intval($_REQUEST['campaign_id']) : 0;
    if ($campaign_id <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'campaign_id is required']);
        exit;
    }

    $pid_file = $pid_dir . "/email_blaster_{$campaign_id}.pid";

    // If pid exists and process is running, report already started
    if (file_exists($pid_file)) {
        $existing = intval(@file_get_contents($pid_file));
        if ($existing > 0 && isPidRunning($existing)) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'ok', 'message' => 'Daemon already running', 'pid' => $existing]);
            exit;
        } else {
            // Stale PID file - remove
            @unlink($pid_file);
        }
    }

    // Build command to launch this same file in CLI mode
    $php_bin = defined('PHP_BINARY') ? PHP_BINARY : 'php';
    $script = __FILE__;

    // Try to capture the background PID using shell features
    $cmd = sprintf('%s %s %d > /dev/null 2>&1 & echo $!', escapeshellarg($php_bin), escapeshellarg($script), $campaign_id);
    exec($cmd, $output, $ret);
    $newpid = isset($output[0]) ? intval($output[0]) : 0;

    if ($newpid > 0) {
        // Persist pid file for bookkeeping
        @file_put_contents($pid_file, $newpid);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'ok', 'message' => 'Daemon started', 'pid' => $newpid]);
        exit;
    }

    // Fallback: try without echoing pid (fire-and-forget)
    $cmd2 = sprintf('%s %s %d > /dev/null 2>&1 &', escapeshellarg($php_bin), escapeshellarg($script), $campaign_id);
    exec($cmd2, $out2, $r2);
    // Don't have PID, but assume started
    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok', 'message' => 'Daemon started (pid unknown)']);
    exit;
}

// CLI mode continues below - get campaign_id from argv
$campaign_id = isset($argv[1]) ? intval($argv[1]) : 0;
if ($campaign_id == 0) {
    die("ERROR: Campaign ID required as argument\n");
}

// Create PID file for process tracking (CLI daemon)
$pid_file = $pid_dir . "/email_blaster_{$campaign_id}.pid";
file_put_contents($pid_file, getmypid());

// Register shutdown function to clean up PID file
register_shutdown_function(function () use ($pid_file) {
    if (file_exists($pid_file)) {
        @unlink($pid_file);
    }
});

// Configuration
define('MAX_WORKERS_PER_SERVER', 1); // Exactly one worker per SMTP server
define('EMAILS_PER_WORKER', 100); // Unused in per-server model, kept for compatibility
define('WORKER_SCRIPT', __DIR__ . '/email_blast_worker.php');
define('LOG_FILE', __DIR__ . '/../logs/email_blast_parallel_' . date('Y-m-d') . '.log');
define('RETRY_FAILED_AFTER_CYCLE', true); // Retry failed emails after one complete cycle
define('MAX_RETRY_ATTEMPTS', 3); // Maximum retry attempts per email
define('RETRY_DELAY_SECONDS', 5); // Delay between retry cycles

/**
 * Reset daily counters if it's a new day
 */
function resetDailyCountersIfNeeded($conn) {
    // Check if we need to reset daily counters
    $reset_check = $conn->query("
        SELECT DATE(NOW()) as today,
               MAX(DATE(mb.delivery_time)) as last_send_date
        FROM mail_blaster mb
        WHERE mb.delivery_date = CURDATE()
        LIMIT 1
    ");
    
    if ($reset_check && $reset_check->num_rows > 0) {
        $dates = $reset_check->fetch_assoc();
        
        // If today is different from last send date, or no sends yet today
        if (!$dates['last_send_date'] || $dates['today'] != $dates['last_send_date']) {
            // Check if any accounts have sent_today > 0
            $need_reset = $conn->query("SELECT COUNT(*) as cnt FROM smtp_accounts WHERE sent_today > 0");
            $result = $need_reset->fetch_assoc();
            
            if ($result['cnt'] > 0) {
                // Reset all daily counters
                $conn->query("UPDATE smtp_accounts SET sent_today = 0 WHERE sent_today > 0");
                logMessage("Daily counters reset for new day");
            }
        }
    }
}

/**
 * Check if an account is within its hourly and daily limits
 */
function isAccountWithinLimits($conn, $account_id) {
    $query = $conn->query("SELECT daily_limit, hourly_limit FROM smtp_accounts WHERE id = $account_id");
    
    if ($query && $query->num_rows > 0) {
        $limits = $query->fetch_assoc();
        
        // Check daily limit using smtp_usage (sum all hours for today)
        $today = date('Y-m-d');
        $dailyResult = $conn->query("SELECT COALESCE(SUM(emails_sent), 0) as sent_today FROM smtp_usage WHERE smtp_id = $account_id AND date = '$today'");
        $sent_today = ($dailyResult && $dailyResult->num_rows > 0) ? intval($dailyResult->fetch_assoc()['sent_today']) : 0;
        
        if ($limits['daily_limit'] > 0 && $sent_today >= $limits['daily_limit']) {
            return false;
        }
        
        // Check hourly limit using smtp_usage (current hour only)
        $current_hour = intval(date('G'));
        $hourlyResult = $conn->query("SELECT emails_sent FROM smtp_usage WHERE smtp_id = $account_id AND date = '$today' AND hour = $current_hour");
        $sent_this_hour = ($hourlyResult && $hourlyResult->num_rows > 0) ? intval($hourlyResult->fetch_assoc()['emails_sent']) : 0;
        
        if ($limits['hourly_limit'] > 0 && $sent_this_hour >= $limits['hourly_limit']) {
            return false;
        }
        
        return true;
    }
    
    return false;
}

/**
 * Get remaining quota for an account
 */
function getAccountQuota($conn, $account_id) {
    $query = $conn->query("SELECT daily_limit, hourly_limit FROM smtp_accounts WHERE id = $account_id");
    
    if ($query && $query->num_rows > 0) {
        $data = $query->fetch_assoc();
        
        // Get daily count from smtp_usage
        $today = date('Y-m-d');
        $dailyResult = $conn->query("SELECT COALESCE(SUM(emails_sent), 0) as sent_today FROM smtp_usage WHERE smtp_id = $account_id AND date = '$today'");
        $sent_today = ($dailyResult && $dailyResult->num_rows > 0) ? intval($dailyResult->fetch_assoc()['sent_today']) : 0;
        
        // Get hourly count from smtp_usage
        $current_hour = intval(date('G'));
        $hourlyResult = $conn->query("SELECT emails_sent FROM smtp_usage WHERE smtp_id = $account_id AND date = '$today' AND hour = $current_hour");
        $sent_this_hour = ($hourlyResult && $hourlyResult->num_rows > 0) ? intval($hourlyResult->fetch_assoc()['emails_sent']) : 0;
        
        $daily_remaining = ($data['daily_limit'] > 0) 
            ? max(0, $data['daily_limit'] - $sent_today) 
            : PHP_INT_MAX;
            
        $hourly_remaining = ($data['hourly_limit'] > 0) 
            ? max(0, $data['hourly_limit'] - $sent_this_hour) 
            : PHP_INT_MAX;
        
        return [
            'daily_remaining' => $daily_remaining,
            'hourly_remaining' => $hourly_remaining,
            'can_send' => ($daily_remaining > 0 && $hourly_remaining > 0)
        ];
    }
    
    return ['daily_remaining' => 0, 'hourly_remaining' => 0, 'can_send' => false];
}

/**
 * Main execution function
 */
function runParallelEmailBlast($conn, $campaign_id) {
    logMessage("Starting parallel email blast for campaign #$campaign_id");
    
    // Verify worker script exists
    if (!file_exists(WORKER_SCRIPT)) {
        logMessage("ERROR: Worker script not found at " . WORKER_SCRIPT);
        return ["status" => "error", "message" => "Worker script not found"];
    }
    logMessage("Worker script ready at " . WORKER_SCRIPT);
    
    // Step 1: Get campaign details
    $campaign = getCampaignDetails($conn, $campaign_id);
    if (!$campaign) {
        return ["status" => "error", "message" => "Campaign not found"];
    }
    
    // Step 2: Get all active SMTP servers with their accounts
    $smtp_servers = getSmtpServersWithAccounts($conn);
    if (empty($smtp_servers)) {
        return ["status" => "error", "message" => "No active SMTP servers/accounts found"];
    }
    
    logMessage("Found " . count($smtp_servers) . " active SMTP servers");
    $total_accounts = 0;
    foreach ($smtp_servers as $server) {
        $total_accounts += count($server['accounts']);
        logMessage("Server #{$server['id']} ({$server['name']}): " . count($server['accounts']) . " accounts");
    }
    
    // Step 3: Total emails remaining (informational)
    $emails_remaining = getEmailsRemainingCount($conn, $campaign_id);
    if ($emails_remaining == 0) {
        return ["status" => "success", "message" => "No emails to send"];
    }

    logMessage("Total emails remaining: $emails_remaining");
    logMessage("Total SMTP accounts available: $total_accounts");

    // Step 4: Launch one worker per server. Each worker will:
    // - Load its own accounts
    // - Strict round-robin: one email per account per round
    // - Claim emails atomically to avoid duplicates across servers
    $result = launchPerServerWorkers($conn, $campaign_id, $smtp_servers, $campaign);
    
    // Step 7: Multiple retry cycles for failed emails
    if (RETRY_FAILED_AFTER_CYCLE) {
        $retry_attempt = 1;
        
        while ($retry_attempt <= MAX_RETRY_ATTEMPTS) {
            // Wait a bit before retry
            if ($retry_attempt > 1) {
                logMessage("Waiting " . RETRY_DELAY_SECONDS . " seconds before retry attempt #$retry_attempt");
                sleep(RETRY_DELAY_SECONDS);
            }
            
            // Get fresh list of SMTP servers (exclude failed ones)
            $working_servers = getWorkingSmtpServers($conn);
            if (empty($working_servers)) {
                logMessage("No working SMTP servers available for retry");
                break;
            }
            
            $failed_count = retryFailedEmails($conn, $campaign_id, $working_servers, $campaign, $retry_attempt);
            
            if ($failed_count > 0) {
                logMessage("Retry attempt #$retry_attempt: Retried $failed_count failed emails");
            } else {
                logMessage("No more failed emails to retry");
                break;
            }
            
            $retry_attempt++;
        }
    }
    
    // ALWAYS update final stats and check for completion
    updateFinalCampaignStats($conn, $campaign_id);
    
    return $result;
}

/**
 * Get campaign details
 */
function getCampaignDetails($conn, $campaign_id) {
    $result = $conn->query("
        SELECT * FROM campaign_master 
        WHERE campaign_id = $campaign_id
    ");
    
    if ($result && $result->num_rows > 0) {
        $campaign = $result->fetch_assoc();
        // Normalize mail_body
        if (isset($campaign['mail_body'])) {
            $campaign['mail_body'] = stripcslashes($campaign['mail_body']);
        }
        return $campaign;
    }
    return null;
}

/**
 * Get all active SMTP servers with their accounts (respecting daily/hourly limits)
 */
function getSmtpServersWithAccounts($conn) {
    $servers = [];
    
    // Reset daily counters if it's a new day
    resetDailyCountersIfNeeded($conn);
    
    // Get all active servers
    $server_result = $conn->query("
        SELECT id, name, host, port, encryption, received_email 
        FROM smtp_servers 
        WHERE is_active = 1 
        ORDER BY id ASC
    ");
    
    $today = date('Y-m-d');
    $current_hour = intval(date('G'));
    
    while ($server = $server_result->fetch_assoc()) {
        // Get accounts that are within their limits using smtp_usage
        $account_result = $conn->query("
            SELECT sa.id, sa.email, sa.password, sa.daily_limit, sa.hourly_limit, 
                   sa.total_sent,
                   COALESCE(daily_usage.sent_today, 0) as sent_today,
                   COALESCE(hourly_usage.emails_sent, 0) as sent_this_hour
            FROM smtp_accounts sa
            LEFT JOIN (
                SELECT smtp_id, SUM(emails_sent) as sent_today
                FROM smtp_usage
                WHERE date = '$today'
                GROUP BY smtp_id
            ) daily_usage ON daily_usage.smtp_id = sa.id
            LEFT JOIN smtp_usage hourly_usage ON hourly_usage.smtp_id = sa.id 
                AND hourly_usage.date = '$today' AND hourly_usage.hour = $current_hour
            WHERE sa.smtp_server_id = {$server['id']} 
            AND sa.is_active = 1
            AND (sa.daily_limit = 0 OR COALESCE(daily_usage.sent_today, 0) < sa.daily_limit)
            AND (sa.hourly_limit = 0 OR COALESCE(hourly_usage.emails_sent, 0) < sa.hourly_limit)
            ORDER BY sa.id ASC
        ");
        
        $accounts = [];
        while ($account = $account_result->fetch_assoc()) {
            $accounts[] = $account;
        }
        
        // Only include servers that have available accounts
        if (!empty($accounts)) {
            $server['accounts'] = $accounts;
            $servers[] = $server;
            logMessage("Server {$server['name']}: " . count($accounts) . " accounts available (within limits)");
        } else {
            logMessage("Server {$server['name']}: No accounts available (all at limit)");
        }
    }
    
    return $servers;
}

/**
 * Get next SMTP account for a server using round-robin over accounts.
 * Persists rotation in `smtp_rotation` so we don't repeat until a full cycle completes.
 */
function getNextAccountForServer($conn, $server, $accounts) {
    if (empty($accounts)) return null;

    $server_id = (int)$server['id'];
    $count = count($accounts);

    // Ensure rotation row exists
    $conn->query("INSERT INTO smtp_rotation (id, last_smtp_index, last_smtp_id, total_smtp_count) VALUES ($server_id, 0, NULL, $count) ON DUPLICATE KEY UPDATE total_smtp_count = $count");

    $rotRes = $conn->query("SELECT last_smtp_index FROM smtp_rotation WHERE id = $server_id");
    $idx = 0;
    if ($rotRes && $rotRes->num_rows > 0) {
        $idx = (int)$rotRes->fetch_assoc()['last_smtp_index'];
    }

    // Advance to next index (wrap around)
    $next_idx = ($idx % $count);
    $next_account = $accounts[$next_idx];

    // Update rotation to point to subsequent index for next pick
    $conn->query("UPDATE smtp_rotation SET last_smtp_index = " . (($next_idx + 1) % $count) . ", last_smtp_id = " . (int)$next_account['id'] . ", total_smtp_count = $count WHERE id = $server_id");

    return $next_account;
}

/**
 * Get working SMTP servers (exclude recently failed ones, respect limits)
 */
function getWorkingSmtpServers($conn) {
    $servers = [];
    
    // Reset daily counters if it's a new day
    resetDailyCountersIfNeeded($conn);
    
    // Get servers that have sent successfully in the last 10 minutes
    // OR have no recent failures
    $server_result = $conn->query("
        SELECT DISTINCT ss.id, ss.name, ss.host, ss.port, ss.encryption, ss.received_email
        FROM smtp_servers ss
        WHERE ss.is_active = 1
        AND (
            -- Servers with recent successes
            EXISTS (
                SELECT 1 FROM mail_blaster mb
                JOIN smtp_accounts sa ON sa.id = mb.smtpid
                WHERE sa.smtp_server_id = ss.id
                AND mb.status = 'success'
                AND mb.delivery_date = CURDATE()
                AND mb.delivery_time >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
            )
            OR
            -- Servers with no recent failures
            NOT EXISTS (
                SELECT 1 FROM mail_blaster mb
                JOIN smtp_accounts sa ON sa.id = mb.smtpid
                WHERE sa.smtp_server_id = ss.id
                AND mb.status = 'failed'
                AND mb.delivery_date = CURDATE()
                AND mb.delivery_time >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            )
        )
        ORDER BY ss.id ASC
    ");
    
    $today = date('Y-m-d');
    $current_hour = intval(date('G'));
    
    while ($server = $server_result->fetch_assoc()) {
        // Get accounts that are within their limits using smtp_usage
        $account_result = $conn->query("
            SELECT sa.id, sa.email, sa.password, sa.daily_limit, sa.hourly_limit, 
                   sa.total_sent,
                   COALESCE(daily_usage.sent_today, 0) as sent_today,
                   COALESCE(hourly_usage.emails_sent, 0) as sent_this_hour
            FROM smtp_accounts sa
            LEFT JOIN (
                SELECT smtp_id, SUM(emails_sent) as sent_today
                FROM smtp_usage
                WHERE date = '$today'
                GROUP BY smtp_id
            ) daily_usage ON daily_usage.smtp_id = sa.id
            LEFT JOIN smtp_usage hourly_usage ON hourly_usage.smtp_id = sa.id 
                AND hourly_usage.date = '$today' AND hourly_usage.hour = $current_hour
            WHERE sa.smtp_server_id = {$server['id']} 
            AND sa.is_active = 1
            AND (sa.daily_limit = 0 OR COALESCE(daily_usage.sent_today, 0) < sa.daily_limit)
            AND (sa.hourly_limit = 0 OR COALESCE(hourly_usage.emails_sent, 0) < sa.hourly_limit)
            ORDER BY id ASC
        ");
        
        $accounts = [];
        while ($account = $account_result->fetch_assoc()) {
            $accounts[] = $account;
        }
        
        if (!empty($accounts)) {
            $server['accounts'] = $accounts;
            $servers[] = $server;
        }
    }
    
    // If no working servers found, fall back to all active servers
    if (empty($servers)) {
        return getSmtpServersWithAccounts($conn);
    }
    
    return $servers;
}

/**
 * Get emails that need to be sent
 */
function getEmailsToSend($conn, $campaign_id) {
    // Fetch ALL valid verified emails from emails table
    $result = $conn->query("
        SELECT e.id, e.raw_emailid
        FROM emails e
        WHERE e.domain_status = 1
        AND e.domain_processed = 1
        AND NOT EXISTS (
            SELECT 1 FROM mail_blaster mb 
            WHERE mb.to_mail = e.raw_emailid
            AND mb.campaign_id = $campaign_id
            AND mb.status = 'success'
        )
        ORDER BY e.id ASC
    ");
    
    $emails = [];
    while ($row = $result->fetch_assoc()) {
        $emails[] = $row;
    }
    
    logMessage("Found " . count($emails) . " emails to send for campaign #$campaign_id (all valid verified emails)");
    return $emails;
}

/**
 * Calculate optimal batch configuration
 */
function calculateBatchConfig($total_emails, $total_accounts, $total_servers) {
    // Deprecated in per-server worker model; kept for compatibility
    return [
        'workers_per_server' => 1,
        'emails_per_worker' => EMAILS_PER_WORKER,
        'total_workers' => $total_servers
    ];
}

/**
 * Distribute emails across servers and their accounts
 */
function distributeEmailsAcrossServers($emails, $smtp_servers, $batch_config) {
    // Deprecated in per-server worker model; workers pull from global pool safely
    return [];
}

/**
 * Launch parallel workers for email sending (DEPRECATED - kept for compatibility)
 */
function launchParallelWorkers($conn, $campaign_id, $distribution, $campaign) {
    // Deprecated: use launchPerServerWorkers instead
    return ["status" => "error", "message" => "Use launchPerServerWorkers for per-server worker model"];
}

/**
 * Retry failed emails with working servers
 */
function retryFailedEmails($conn, $campaign_id, $smtp_servers, $campaign, $retry_attempt = 1) {
    logMessage("Retry attempt #$retry_attempt: Checking for failed emails");
    
    // Get failed emails that haven't exceeded max attempts
    $max_attempts = MAX_RETRY_ATTEMPTS + 1; // +1 for initial attempt
    $failed_emails = $conn->query("
        SELECT DISTINCT mb.to_mail, e.id, mb.attempt_count
        FROM mail_blaster mb
        JOIN emails e ON e.raw_emailid = mb.to_mail
        WHERE mb.campaign_id = $campaign_id 
        AND mb.status = 'failed'
        AND mb.attempt_count < $max_attempts
        AND NOT EXISTS (
            SELECT 1 FROM mail_blaster mb2 
            WHERE mb2.to_mail = mb.to_mail 
            AND mb2.campaign_id = $campaign_id
            AND mb2.status = 'success'
        )
        ORDER BY mb.attempt_count ASC, mb.delivery_time DESC
        LIMIT 1000
    ")->fetch_all(MYSQLI_ASSOC);
    
    if (empty($failed_emails)) {
        logMessage("No failed emails to retry (attempt #$retry_attempt)");
        return 0;
    }
    
    logMessage("Found " . count($failed_emails) . " failed emails to retry (attempt #$retry_attempt)");
    
    // Delete old failed records so they can be retried
    $email_list = array_map(function($e) use ($conn) {
        return "'" . $conn->real_escape_string($e['to_mail']) . "'";
    }, $failed_emails);
    $email_list_str = implode(',', $email_list);
    
    $conn->query("
        DELETE FROM mail_blaster 
        WHERE campaign_id = $campaign_id 
        AND to_mail IN ($email_list_str)
        AND status = 'failed'
    ");
    
    logMessage("Cleared " . $conn->affected_rows . " failed records for retry");
    
    // Distribute failed emails across working servers
    $batch_config = calculateBatchConfig(count($failed_emails), 
        array_sum(array_map(function($s) { return count($s['accounts']); }, $smtp_servers)), 
        count($smtp_servers));
    
    $distribution = distributeEmailsAcrossServers($failed_emails, $smtp_servers, $batch_config);
    
    // Launch retry workers
    launchParallelWorkers($conn, $campaign_id, $distribution, $campaign);
    
    // Wait for retry workers to complete
    sleep(3);
    
    return count($failed_emails);
}

/**
 * Update final campaign statistics
 */
function updateFinalCampaignStats($conn, $campaign_id) {
    logMessage("Updating final campaign statistics for campaign #$campaign_id");
    
    // Get accurate counts from mail_blaster table
    // Only count permanently failed (attempt_count >= 5)
    $stats = $conn->query("
        SELECT 
            COUNT(DISTINCT CASE WHEN mb.status = 'success' THEN mb.to_mail END) as sent_count,
            COUNT(DISTINCT CASE WHEN mb.status = 'failed' AND mb.attempt_count >= 5 THEN mb.to_mail END) as failed_count,
            COUNT(DISTINCT CASE WHEN mb.status IN ('pending', 'failed') AND mb.attempt_count < 5 THEN mb.to_mail END) as retry_count
        FROM mail_blaster mb
        WHERE mb.campaign_id = $campaign_id
    ")->fetch_assoc();
    
    $sent_emails = intval($stats['sent_count']);
    $failed_emails = intval($stats['failed_count']); // Only permanently failed (5+ attempts)
    $retry_emails = intval($stats['retry_count']); // Pending retries
    
    // Get total emails for this campaign
    $total_result = $conn->query("
            SELECT COUNT(DISTINCT e.raw_emailid) as total
            FROM emails e
            WHERE e.domain_status = 1 AND e.validation_status = 'valid'
            AND e.raw_emailid IS NOT NULL AND e.raw_emailid <> ''
        ");
    $total_emails = intval($total_result->fetch_assoc()['total']);
    
    // Pending = Total - Success - Permanently Failed
    $pending_emails = max(0, $total_emails - $sent_emails - $failed_emails);
    
    // Determine campaign status
    $campaign_status = 'running';
    if ($pending_emails == 0) {
        $campaign_status = 'completed';
        logMessage("Campaign #$campaign_id COMPLETED - All emails processed (100%)");
    }
    
    // Update campaign_status with accurate numbers
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
    
    logMessage("Final stats: Total=$total_emails, Sent=$sent_emails, Failed=$failed_emails (5+ attempts), Retrying=$retry_emails, Pending=$pending_emails, Status=$campaign_status");
    
    return [
        'total_emails' => $total_emails,
        'sent_emails' => $sent_emails,
        'failed_emails' => $failed_emails,
        'retry_emails' => $retry_emails,
        'pending_emails' => $pending_emails,
        'status' => $campaign_status
    ];
}

/**
 * Log message to file
 */
function logMessage($message) {
    $logDir = dirname(LOG_FILE);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message" . PHP_EOL;
    file_put_contents(LOG_FILE, $logEntry, FILE_APPEND);
}

// Helper: remaining emails count
function getEmailsRemainingCount($conn, $campaign_id) {
    $res = $conn->query("
            SELECT COUNT(*) as remaining FROM emails e
            WHERE e.domain_status = 1
            AND e.validation_status = 'valid'
            AND e.raw_emailid IS NOT NULL AND e.raw_emailid <> ''
            AND NOT EXISTS (
                SELECT 1 FROM mail_blaster mb 
                WHERE mb.to_mail = e.raw_emailid 
                AND mb.campaign_id = $campaign_id
                AND mb.status = 'success'
            )
    ");
    return ($res && $res->num_rows > 0) ? intval($res->fetch_assoc()['remaining']) : 0;
}

// Launch exactly one worker per server; each worker pulls and claims emails itself
function launchPerServerWorkers($conn, $campaign_id, $smtp_servers, $campaign) {
    logMessage("Launching per-server workers: " . count($smtp_servers));

    if (!file_exists(WORKER_SCRIPT)) {
        logMessage("ERROR: Worker script not found at " . WORKER_SCRIPT);
        return ["status" => "error", "message" => "Worker script not created"];
    }

    $php_cli_candidates = [
        '/opt/plesk/php/8.1/bin/php',
        '/usr/bin/php8.1',
        '/usr/local/bin/php',
        '/usr/bin/php'
    ];
    $php_cli = null;
    foreach ($php_cli_candidates as $candidate) {
        if (file_exists($candidate) && is_executable($candidate)) {
            $info = shell_exec(escapeshellarg($candidate) . ' -i 2>&1');
            if ($info && stripos($info, 'Server API => Command Line Interface') !== false) {
                $php_cli = $candidate;
                break;
            }
        }
    }
    if (!$php_cli) {
        $env_php = trim(shell_exec('command -v php 2>/dev/null')) ?: 'php';
        $info = shell_exec(escapeshellarg($env_php) . ' -i 2>&1');
        if ($info && stripos($info, 'Server API => Command Line Interface') !== false) {
            $php_cli = $env_php;
        } else {
            $php_cli = '/opt/plesk/php/8.1/bin/php';
        }
    }
    logMessage("Using PHP CLI binary: $php_cli");

    $processes = [];
    foreach ($smtp_servers as $server) {
        $server_config = json_encode([
            'server_id' => (int)$server['id'],
            'host' => $server['host'],
            'port' => $server['port'],
            'encryption' => $server['encryption'],
            'received_email' => $server['received_email'],
        ]);
        $campaign_json = json_encode($campaign);

        $cmd = sprintf(
            '%s %s %d %s %s %s > /dev/null 2>&1 &',
            escapeshellarg($php_cli),
            escapeshellarg(WORKER_SCRIPT),
            $campaign_id,
            escapeshellarg(''),
            escapeshellarg($server_config),
            escapeshellarg($campaign_json)
        );
        exec($cmd, $out, $ret);
        $processes[] = [
            'server_id' => (int)$server['id'],
            'name' => $server['name']
        ];
        logMessage("Launched server worker for #{$server['id']} ({$server['name']})");
        usleep(50000);
    }

    return [
        'status' => 'success',
        'message' => 'Per-server workers launched',
        'workers_launched' => count($processes)
    ];
}

/**
 * Check network connectivity
 */
function checkNetworkConnectivity() {
    $connected = @fsockopen("8.8.8.8", 53, $errno, $errstr, 5);
    if ($connected) {
        fclose($connected);
        return true;
    }
    return false;
}

// ========================================
// MAIN DAEMON LOOP
// ========================================

// Initialize database connection using production config
require_once __DIR__ . '/../config/db.php';

logMessage("=== Starting Parallel Email Blast Daemon for Campaign #$campaign_id ===");

while (true) {
    try {
        // Reconnect to database for each cycle - Use production config
        require_once __DIR__ . '/../config/db.php';
        if ($conn->connect_error) {
            logMessage("Database connection failed: " . $conn->connect_error);
            sleep(10);
            continue;
        }
        
        // Extra safety: if campaign_master row is deleted, exit daemon and clean PID
        $cm_exists = $conn->query("SELECT 1 FROM campaign_master WHERE campaign_id = $campaign_id LIMIT 1");
        if (!$cm_exists || $cm_exists->num_rows === 0) {
            logMessage("Campaign master row missing (deleted). Exiting daemon.");
            $conn->close();
            break;
        }

        // Check campaign status
        $status_result = $conn->query("
            SELECT status, total_emails, sent_emails, pending_emails, failed_emails
            FROM campaign_status 
            WHERE campaign_id = $campaign_id
        ");

        if ($status_result->num_rows === 0) {
            logMessage("Campaign not found. Exiting daemon.");
            $conn->close();
            break;
        }

        $campaign_data = $status_result->fetch_assoc();
        $status = $campaign_data['status'];

        if ($status !== 'running') {
            logMessage("Campaign status is '$status'. Exiting daemon.");
            $conn->close();
            break;
        }

        // Check network connectivity
        if (!checkNetworkConnectivity()) {
            logMessage("Network connection unavailable. Waiting to retry...");
            $conn->close();
            sleep(60);
            continue;
        }

        // Check if there are emails remaining to send
        $remaining_result = $conn->query("
            SELECT COUNT(*) as remaining FROM emails e
            WHERE e.domain_status = 1
            AND NOT EXISTS (
                SELECT 1 FROM mail_blaster mb 
                WHERE mb.to_mail = e.raw_emailid 
                AND mb.campaign_id = $campaign_id
                AND mb.status = 'success'
            )
        ");
        
        $remaining_count = $remaining_result->fetch_assoc()['remaining'];

        if ($remaining_count == 0) {
            $conn->query("UPDATE campaign_status 
                         SET status = 'completed', pending_emails = 0, end_time = NOW() 
                         WHERE campaign_id = $campaign_id");
            logMessage("All emails processed. Campaign completed. Exiting daemon.");
            $conn->close();
            break;
        }

        logMessage("--- Starting parallel blast cycle for $remaining_count emails ---");
        
        // Execute one cycle of parallel email blast
        $result = runParallelEmailBlast($conn, $campaign_id);
        // Extra diagnostics: summarize current mail_blaster counts
        $diag = $conn->query("SELECT status, COUNT(*) cnt FROM mail_blaster WHERE campaign_id = $campaign_id GROUP BY status");
        if ($diag) {
            $parts = [];
            while ($row = $diag->fetch_assoc()) { $parts[] = $row['status'] . ':' . $row['cnt']; }
            logMessage('Mail blaster status counts: ' . implode(', ', $parts));
        }
        
        logMessage("Cycle completed: " . json_encode($result));
        
        // Check status again after cycle
        $status_check = $conn->query("SELECT status FROM campaign_status WHERE campaign_id = $campaign_id")->fetch_assoc();
        if ($status_check['status'] !== 'running') {
            logMessage("Campaign status changed to '{$status_check['status']}'. Exiting daemon.");
            $conn->close();
            break;
        }
        
        // Small delay before next cycle
        $conn->close();
        sleep(2); // 2 seconds between cycles
        
    } catch (Exception $e) {
        logMessage("Error in daemon loop: " . $e->getMessage());
        if (isset($conn)) {
            $conn->close();
        }
        sleep(10); // Wait before retry on error
    }
}

logMessage("=== Parallel Email Blast Daemon Stopped for Campaign #$campaign_id ===");