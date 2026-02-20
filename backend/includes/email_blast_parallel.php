<?php

// === RESOURCE MANAGEMENT: Prevent affecting other applications ===
require_once __DIR__ . '/resource_manager.php';
ResourceManager::initCampaignProcess('orchestrator');

error_reporting(E_ALL);
ini_set('display_errors', 0); // Production safe
ini_set('log_errors', 1);
// Memory and time limits are now set by ResourceManager

date_default_timezone_set('Asia/Kolkata');

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

// Create log directory immediately
$log_dir = __DIR__ . '/../logs';
if (!is_dir($log_dir)) {
    @mkdir($log_dir, 0777, true);
}

if ($campaign_id == 0) {
    $err = "ERROR: Campaign ID required as argument";
    error_log($err);
    echo "[$err]\n";
    die($err . "\n");
}

// Define campaign-specific orchestrator log file (matches cron expectation)
// ❌ DISABLED - Log files disabled
// define('ORCHESTRATOR_LOG', $log_dir . "/orchestrator_campaign_{$campaign_id}.log");

logMessage("=== EMAIL BLAST PARALLEL STARTED ===");
logMessage("Campaign ID: $campaign_id");
logAnalysis($campaign_id, "ORCHESTRATOR STARTED: Processing campaign #$campaign_id");
logMessage("PHP Version: " . PHP_VERSION);
logMessage("Process ID: " . getmypid());
// logMessage("Log file: " . ORCHESTRATOR_LOG); // ❌ DISABLED - Log files disabled

// Check if campaign is already running in database
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/db_campaign.php';

logMessage("=== DATABASE CONNECTIONS ===");
logMessage("Server 1: " . $conn->host_info . " - Email sources & campaigns");
logMessage("Server 2: " . $conn_heavy->host_info . " - SMTP & mail_blaster");
logMessage("============================");

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    logMessage("⚠️ PHP ERROR: [$errno] $errstr in $errfile on line $errline");
    return false;
});

/**
 * Specialized Analysis Log for Campaign Monitoring
 */
function logAnalysis($campaign_id, $msg) {
    // ❌ DISABLED - Logging disabled
    return;
    $log_file = __DIR__ . '/../logs/analysis_campaign_' . $campaign_id . '.log';
    $ts = date('Y-m-d H:i:s');
    $pid = getmypid();
    $logMsg = "[$ts][PID:$pid][ANALYSIS] $msg\n";
    @file_put_contents($log_file, $logMsg, FILE_APPEND | LOCK_EX);
}

logMessage("🔍 DEBUG: About to start Step 1...");

try {
    // Campaign status tracking log
    logMessage("📝 Step 1: Checking if campaign is already running...");
// $campaign_status_log = __DIR__ . '/../logs/campaign_status_' . $campaign_id . '.log';
$startLogMsg = "[" . date('Y-m-d H:i:s') . "] [Orchestrator] Campaign #$campaign_id starting - PID: " . getmypid() . "\n";
// @file_put_contents($campaign_status_log, $startLogMsg, FILE_APPEND | LOCK_EX);

logMessage("Querying campaign_status from SERVER 1 for campaign #$campaign_id...");
$pidCheckQuery = $conn->query("SELECT process_pid, status FROM campaign_status WHERE campaign_id = $campaign_id LIMIT 1");
if ($pidCheckQuery && $pidCheckQuery->num_rows > 0) {
    $pidRow = $pidCheckQuery->fetch_assoc();
    $existingPid = isset($pidRow['process_pid']) ? (int)$pidRow['process_pid'] : 0;
    $statusLogMsg = "[" . date('Y-m-d H:i:s') . "] [Orchestrator] Status: {$pidRow['status']}, Existing PID: $existingPid\n";
    // @file_put_contents($campaign_status_log, $statusLogMsg, FILE_APPEND | LOCK_EX);
    
    logMessage("Campaign status: {$pidRow['status']}, Existing PID: $existingPid, Current PID: " . getmypid());
    
    if ($existingPid > 0 && isPidRunning($existingPid) && $existingPid != getmypid()) {
        $errorMsg = "[" . date('Y-m-d H:i:s') . "] [Orchestrator] ❌ ERROR: Campaign already running with PID $existingPid\n";
        // @file_put_contents($campaign_status_log, $errorMsg, FILE_APPEND | LOCK_EX);
        logMessage("❌ ERROR: Campaign $campaign_id is already running with PID $existingPid - EXITING");
        die("ERROR: Campaign $campaign_id is already running with PID $existingPid\n");
    }
    
    // UPDATE SERVER 1: Mark as running and set PID
    logMessage("📝 Updating status to 'running' on SERVER 1...");
    $conn->query("UPDATE campaign_status SET status = 'running', process_pid = " . getmypid() . ", start_time = NOW() WHERE campaign_id = $campaign_id");
    
    logMessage("✅ No conflicting PID found - proceeding with campaign execution");
} else {
    logMessage("⚠️ WARNING: Campaign #$campaign_id not found in campaign_status table!");
}

// Create PID file for process tracking (CLI daemon)
logMessage("📝 Step 2: Creating PID file...");
$pid_file = $pid_dir . "/email_blaster_{$campaign_id}.pid";
file_put_contents($pid_file, getmypid());
logMessage("✅ PID file created: $pid_file");

// Register shutdown function to clean up PID file
logMessage("🛡️ Step 3: Registering shutdown handler...");
register_shutdown_function(function () use ($pid_file, $campaign_id) {
    if (file_exists($pid_file)) {
        @unlink($pid_file);
    }
    // Clear PID from database on shutdown - with row-level locking and SHORT timeout
    try {
        global $conn;
        require_once __DIR__ . '/../config/db.php';
        // Set short lock timeout to avoid blocking frontend queries
        $conn->query("SET SESSION innodb_lock_wait_timeout = 3");
        
        $conn->begin_transaction();
        $conn->query("SELECT campaign_id FROM campaign_status WHERE campaign_id = $campaign_id FOR UPDATE");
        $conn->query("UPDATE campaign_status SET process_pid = NULL WHERE campaign_id = $campaign_id AND process_pid = " . getmypid());
        $conn->commit();
    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollback();
        }
    }
});
logMessage("✅ Shutdown handler registered");

} catch (Exception $e) {
    logMessage("❌ FATAL ERROR during initialization: " . $e->getMessage());
    logMessage("Stack trace: " . $e->getTraceAsString());
    die("FATAL ERROR: " . $e->getMessage() . "\n");
} catch (Error $e) {
    logMessage("❌ FATAL PHP ERROR during initialization: " . $e->getMessage());
    logMessage("Stack trace: " . $e->getTraceAsString());
    die("FATAL PHP ERROR: " . $e->getMessage() . "\n");
}


logMessage("⚙️ Step 4: Initializing configuration...");
define('MAX_WORKERS_PER_SERVER', 1); // Exactly one worker per SMTP server
define('BATCH_SIZE', 1000); // Process 1000 emails per batch for high-volume campaigns
define('BATCH_UPDATE_SIZE', 500); // Update DB every 500 emails (batch updates to reduce load)
define('WORKER_SCRIPT', __DIR__ . '/email_blast_worker.php');
// LOG_FILE removed - now using ORCHESTRATOR_LOG defined earlier with campaign_id
define('RETRY_FAILED_AFTER_CYCLE', true); // Retry failed emails after one complete cycle
define('MAX_RETRY_ATTEMPTS', 5); // Maximum 5 attempts per email
define('RETRY_DELAY_SECONDS', 5); // WEB-FRIENDLY: 5 seconds between retries
define('ROUND_DELAY_SECONDS', 10); // 10 second delay between rounds to reduce server load

/**
 * Reset daily counters if it's a new day
 */
function resetDailyCountersIfNeeded($conn_heavy) {
    global $conn_heavy_global;
    if (!$conn_heavy && isset($GLOBALS['conn_heavy'])) { $conn_heavy = $GLOBALS['conn_heavy']; }
    // Check if we need to reset daily counters (mail_blaster + smtp on Server 2)
    $reset_check = $conn_heavy->query("
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
            // Check if any accounts have sent_today > 0 (smtp_accounts on Server 2)
            $need_reset = $conn_heavy->query("SELECT COUNT(*) as cnt FROM smtp_accounts WHERE sent_today > 0");
            $result = $need_reset->fetch_assoc();
            
            if ($result['cnt'] > 0) {
                // Reset all daily counters
                $conn_heavy->query("UPDATE smtp_accounts SET sent_today = 0 WHERE sent_today > 0");
                logMessage("Daily counters reset for new day");
            }
        }
    }
}

/**
 * Check if an account is within its hourly and daily limits
 */
function isAccountWithinLimits($conn_heavy, $account_id) {
    global $conn_heavy;
    if (!$conn_heavy && isset($GLOBALS['conn_heavy'])) { $conn_heavy = $GLOBALS['conn_heavy']; }
    $query = $conn_heavy->query("SELECT daily_limit, hourly_limit FROM smtp_accounts WHERE id = $account_id");
    
    if ($query && $query->num_rows > 0) {
        $limits = $query->fetch_assoc();
        
        // Check daily limit using smtp_usage (sum all hours for today)
        $today = date('Y-m-d');
        $dailyResult = $conn_heavy->query("SELECT COALESCE(SUM(emails_sent), 0) as sent_today FROM smtp_usage WHERE smtp_id = $account_id AND date = '$today'");
        $sent_today = ($dailyResult && $dailyResult->num_rows > 0) ? intval($dailyResult->fetch_assoc()['sent_today']) : 0;
        
        if ($limits['daily_limit'] > 0 && $sent_today >= $limits['daily_limit']) {
            return false;
        }
        
        // Check hourly limit using smtp_usage (current hour only)
        $current_hour = intval(date('G'));
        $hourlyResult = $conn_heavy->query("SELECT emails_sent FROM smtp_usage WHERE smtp_id = $account_id AND date = '$today' AND hour = $current_hour");
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
function getAccountQuota($conn_heavy, $account_id) {
    global $conn_heavy;
    if (!$conn_heavy && isset($GLOBALS['conn_heavy'])) { $conn_heavy = $GLOBALS['conn_heavy']; }
    $query = $conn_heavy->query("SELECT daily_limit, hourly_limit FROM smtp_accounts WHERE id = $account_id");
    
    if ($query && $query->num_rows > 0) {
        $data = $query->fetch_assoc();
        
        // Get daily count from smtp_usage
        $today = date('Y-m-d');
        $dailyResult = $conn_heavy->query("SELECT COALESCE(SUM(emails_sent), 0) as sent_today FROM smtp_usage WHERE smtp_id = $account_id AND date = '$today'");
        $sent_today = ($dailyResult && $dailyResult->num_rows > 0) ? intval($dailyResult->fetch_assoc()['sent_today']) : 0;
        
        // Get hourly count from smtp_usage
        $current_hour = intval(date('G'));
        $hourlyResult = $conn_heavy->query("SELECT emails_sent FROM smtp_usage WHERE smtp_id = $account_id AND date = '$today' AND hour = $current_hour");
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
    global $conn_heavy;
    logMessage("Starting parallel email blast for campaign #$campaign_id");
    
    // === RESOURCE CHECK: Throttle if system is under pressure ===
    if (ResourceManager::shouldThrottle()) {
        logMessage("WARNING: System under high load, throttling campaign execution");
        ResourceManager::cpuFriendlySleep(5); // Wait 5 seconds before proceeding
    }
    
    // Verify worker script exists
    if (!file_exists(WORKER_SCRIPT)) {
        logMessage("ERROR: Worker script not found at " . WORKER_SCRIPT);
        return ["status" => "error", "message" => "Worker script not found"];
    }
    logMessage("Worker script ready at " . WORKER_SCRIPT);
    
    // Step 1: Get campaign details
    logMessage("Step 1: Fetching campaign details for campaign #$campaign_id");
    $campaign = getCampaignDetails($conn, $campaign_id);
    if (!$campaign) {
        logMessage("ERROR: Campaign #$campaign_id not found in database!");
        return ["status" => "error", "message" => "Campaign not found"];
    }
    logMessage("Campaign found: " . json_encode($campaign));
    
    // Get campaign owner's user_id for SMTP filtering
    $campaign_user_id = isset($campaign['user_id']) ? (int)$campaign['user_id'] : null;
    $csv_list_id = isset($campaign['csv_list_id']) ? (int)$campaign['csv_list_id'] : 0;
    $import_batch_id = isset($campaign['import_batch_id']) ? $campaign['import_batch_id'] : null;

    logMessage("==========================================");
    logMessage("📋 CAMPAIGN OWNERSHIP INFORMATION");
    $user_id_display = ($campaign_user_id !== null) ? $campaign_user_id : 'NULL (ALL USERS)';
    logMessage("Campaign user_id: " . $user_id_display);
    logAnalysis($campaign_id, "USER ID: Campaign owned by User #$user_id_display");
    
    // Fetch user details from Server 1 for logging
    if ($campaign_user_id) {
        $userQuery = $conn->query("SELECT id, name, email FROM users WHERE id = $campaign_user_id LIMIT 1");
        if ($userQuery && $userQuery->num_rows > 0) {
            $user = $userQuery->fetch_assoc();
            logMessage("👤 Campaign started by user:");
            logMessage("   ├─ ID: " . $user['id']);
            logMessage("   ├─ Name: " . $user['name']);
            logMessage("   └─ Email: " . $user['email']);
            logMessage("🔧 Will use ONLY SMTP servers/accounts belonging to user #" . $user['id'] . " (" . $user['name'] . ")");
        } else {
            logMessage("⚠️ WARNING: User #$campaign_user_id not found in users table!");
            logMessage("   Campaign has user_id=$campaign_user_id but user doesn't exist");
        }
    } else {
        logMessage("⚠️ WARNING: No user_id found in campaign - will use ALL SMTP accounts");
    }
    logMessage("==========================================");
    
    // Step 2: Get all active SMTP servers with their accounts (filtered by user)
    logMessage("Step 2: Fetching SMTP servers for user #$campaign_user_id");
    
    // 🔒 MULTI-USER SAFETY: Check concurrent campaigns for this user
    if ($campaign_user_id) {
        $concurrentCheck = $conn->query("
            SELECT COUNT(*) as concurrent_count 
            FROM campaign_status cs 
            JOIN campaign_master cm ON cs.campaign_id = cm.campaign_id 
            WHERE cm.user_id = $campaign_user_id 
            AND cs.status = 'running' 
            AND cs.campaign_id != $campaign_id
        ");
        if ($concurrentCheck && $concurrentCheck->num_rows > 0) {
            $concurrentData = $concurrentCheck->fetch_assoc();
            $concurrent = intval($concurrentData['concurrent_count']);
            logMessage("📊 MULTI-USER CHECK: User #$campaign_user_id has $concurrent other running campaigns");
            
            // Warning if user has many concurrent campaigns (could indicate resource contention)
            if ($concurrent >= 5) {
                logMessage("⚠️ WARNING: User #$campaign_user_id has $concurrent concurrent campaigns!");
                logMessage("   This may cause SMTP account contention and slower processing");
                logMessage("   Recommendation: Wait for other campaigns to complete or add more SMTP accounts");
            }
        }
    }
    
    try {
        $smtp_servers = getSmtpServersWithAccounts($conn_heavy, $campaign_user_id);
        logMessage("getSmtpServersWithAccounts returned: " . (is_array($smtp_servers) ? count($smtp_servers) . " servers" : "NULL/INVALID"));
    } catch (Exception $e) {
        logMessage("EXCEPTION in getSmtpServersWithAccounts: " . $e->getMessage());
        logMessage("Stack trace: " . $e->getTraceAsString());
        return ["status" => "error", "message" => "Exception fetching SMTP servers: " . $e->getMessage()];
    }
    
    if (empty($smtp_servers)) {
        logMessage("ERROR: No active SMTP servers/accounts found for user #$campaign_user_id!");
        logMessage("This could mean:");
        logMessage("  1. User #$campaign_user_id has no SMTP servers configured");
        logMessage("  2. All SMTP servers for this user are inactive (is_active=0)");
        logMessage("  3. All SMTP accounts are at their daily/hourly limits");
        logMessage("  4. User_id field is missing in smtp_servers table");
        logMessage("  5. Another concurrent campaign is using all available SMTP accounts");
        return ["status" => "error", "message" => "No active SMTP servers/accounts found for user #$campaign_user_id"];
    }
    logMessage("Found " . count($smtp_servers) . " SMTP servers");
    
    logMessage("Found " . count($smtp_servers) . " active SMTP servers");
    $total_accounts = 0;
    foreach ($smtp_servers as $server) {
        $total_accounts += count($server['accounts']);
        logMessage("Server #{$server['id']} ({$server['name']}): " . count($server['accounts']) . " accounts");
    }
    
    // Step 3: Migrate all recipients to Server 2 bulk before starting workers
    // This ensures Server 2 is the source of truth and workers don't need to scan Server 1
    migrateRecipientsToServer2($conn, $conn_heavy, $campaign_id);
    
    $emails_remaining = getEmailsRemainingCount($conn, $campaign_id, $csv_list_id);
    logMessage("Emails remaining count (on Server 2): $emails_remaining");
    
    $email_table = $import_batch_id ? "imported_recipients (Excel/Import)" : "emails (CSV/System)";
    logAnalysis($campaign_id, "SOURCE: Fetching emails from Server 1 table: $email_table");
    logAnalysis($campaign_id, "SMTP SOURCE: Fetching SMTP accounts from Server 2 table: smtp_accounts");
    
    if ($emails_remaining == 0) {
        logMessage("No emails to send - campaign has no pending emails!");
        return ["status" => "success", "message" => "No emails to send"];
    }

    logMessage("Total emails remaining: $emails_remaining" . ($csv_list_id > 0 ? " (CSV List ID: $csv_list_id)" : ""));
    logMessage("Total SMTP accounts available: $total_accounts");

    // Step 3.5: Report SMTP health status
    logMessage("Step 3.5: Checking SMTP account health...");
    $healthStats = $conn_heavy->query("
        SELECT 
            COALESCE(sh.health, 'healthy') as health,
            COUNT(*) as cnt
        FROM smtp_accounts sa
        LEFT JOIN smtp_health sh ON sa.id = sh.smtp_id
        WHERE sa.is_active = 1
        AND (sh.suspend_until IS NULL OR sh.suspend_until < NOW())
        GROUP BY COALESCE(sh.health, 'healthy')
    ");
    
    if ($healthStats && $healthStats->num_rows > 0) {
        $healthReport = [];
        while ($row = $healthStats->fetch_assoc()) {
            $healthReport[] = $row['health'] . '=' . $row['cnt'];
        }
        logMessage("SMTP Health: " . implode(', ', $healthReport));
    }

    // Step 4: Launch one worker per server. Each worker will:
    // - Load its own accounts
    // - Strict round-robin: one email per account per round
    // - Claim emails atomically to avoid duplicates across servers
    logMessage("Step 4: Launching workers for " . count($smtp_servers) . " SMTP servers");
    $result = launchPerServerWorkers($conn, $campaign_id, $smtp_servers, $campaign);
    logMessage("Worker launch result: " . json_encode($result));
    
    // Step 6.5: Monitor workers progress
    logMessage("Monitoring workers progress...");
    $monitoring_start = time();
    $max_monitoring_time = 86400; // 24 hours maximum monitoring (was 60 mins)
    $last_progress_check = 0;
    $consecutive_no_progress = 0;
    
    while (time() - $monitoring_start < $max_monitoring_time) {
        sleep(10); // OPTIMIZED: 10 seconds between monitoring cycles to reduce Server 1 load during large campaigns
        
        // Get current progress from Server 1 (campaign_status table)
        $progress = $conn->query("
            SELECT 
                total_emails,
                sent_emails,
                failed_emails,
                pending_emails,
                status
            FROM campaign_status
            WHERE campaign_id = $campaign_id
        ")->fetch_assoc();
        
        if (!$progress) break;
        
        $total = intval($progress['total_emails']);
        $sent = intval($progress['sent_emails']);
        $failed = intval($progress['failed_emails']);
        $pending = intval($progress['pending_emails']);
        $currentStatus = $progress['status'];
        
        logMessage("Progress: Sent=$sent/$total, Failed=$failed, Pending=$pending, Status=$currentStatus");
        logAnalysis($campaign_id, "MONITOR: Sent=$sent/$total, Failed=$failed, Pending=$pending, Status=$currentStatus");
        
        // Check if campaign was paused or stopped
        if ($currentStatus !== 'running') {
            logMessage("Campaign status changed to '$currentStatus'. Stopping monitoring.");
            break;
        }
        
        // Check if we made progress
        if ($sent > $last_progress_check) {
            $last_progress_check = $sent;
            $consecutive_no_progress = 0;
        } else {
            $consecutive_no_progress++;
        }
        
        // If no progress for 20 checks (100 seconds) and still have pending, something is wrong
        if ($consecutive_no_progress >= 20 && $pending > 0) {
            logMessage("WARNING: No progress detected for 100 seconds with $pending pending emails");
            logMessage("Workers may have crashed. Will try retry logic.");
            break;
        }
        
        // Check if campaign is complete
        if ($pending == 0 && ($sent + $failed) >= $total) {
            logMessage("Campaign completed! All emails processed.");
            break;
        }
        
        // If we've sent most emails and only have a few pending, probably waiting on retries
        if ($pending <= 5 && $pending > 0) {
            logMessage("Only $pending pending emails remaining. Moving to retry phase.");
            break;
        }
    }
    
    $monitoring_duration = time() - $monitoring_start;
    logMessage("Monitoring completed after $monitoring_duration seconds");
    
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
            $working_servers = getWorkingSmtpServers($conn_heavy, $campaign_user_id);
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
        SELECT *, user_id FROM campaign_master 
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
 * Bulk migrate recipients from Server 1 to Server 2 mail_blaster
 */
function migrateRecipientsToServer2($conn, $conn_heavy, $campaign_id) {
    logMessage(">>> STARTING BULK MIGRATION TO SERVER 2 for Campaign #$campaign_id");
    
    // 1. Get campaign source
    $campaignRes = $conn->query("SELECT import_batch_id, csv_list_id, user_id FROM campaign_master WHERE campaign_id = $campaign_id");
    if (!$campaignRes || $campaignRes->num_rows === 0) {
        logMessage("❌ Error: Campaign #$campaign_id not found for migration");
        return 0;
    }
    $campaign = $campaignRes->fetch_assoc();
    $import_batch_id = $campaign['import_batch_id'];
    $csv_list_id = intval($campaign['csv_list_id']);
    $user_id = intval($campaign['user_id']);
    
    // 2. Fetch recipients from Server 1
    if ($import_batch_id) {
        $batch_escaped = $conn->real_escape_string($import_batch_id);
        $srcRes = $conn->query("SELECT ir.Emails as to_mail, '$csv_list_id' as csv_list_id, '$user_id' as user_id 
                               FROM imported_recipients ir 
                               WHERE ir.import_batch_id = '$batch_escaped' 
                               AND ir.is_active = 1 
                               AND ir.Emails IS NOT NULL AND ir.Emails <> ''");
    } else {
        $filter = $csv_list_id > 0 ? " AND e.csv_list_id = $csv_list_id" : "";
        $srcRes = $conn->query("SELECT e.raw_emailid as to_mail, e.csv_list_id, '$user_id' as user_id 
                               FROM emails e
                               WHERE e.domain_status = 1 AND e.validation_status = 'valid' 
                               AND e.raw_emailid IS NOT NULL AND e.raw_emailid <> '' $filter");
    }
    
    if (!$srcRes || $srcRes->num_rows === 0) {
        logMessage("⚠️ No recipients found on Server 1 to migrate");
        return 0;
    }
    
    $total = $srcRes->num_rows;
    logMessage("Found $total recipients on Server 1 to migrate");
    
    // 3. Bulk Insert into Server 2 mail_blaster
    $batchSize = 10000; // Large batches for maximum migration speed (minimizes Server 1 queries)
    $count = 0;
    $inserted = 0;
    $values = [];
    
    while ($row = $srcRes->fetch_assoc()) {
        $mail = $conn_heavy->real_escape_string($row['to_mail']);
        $cid = intval($campaign_id);
        $lid = intval($row['csv_list_id']);
        $uid = intval($row['user_id']);
        
        $values[] = "($cid, '$mail', $lid, $uid, 'pending', 0)";
        $count++;
        
        if (count($values) >= $batchSize) {
            $sql = "INSERT IGNORE INTO mail_blaster (campaign_id, to_mail, csv_list_id, user_id, status, attempt_count) VALUES " . implode(',', $values);
            if ($conn_heavy->query($sql)) {
                $inserted += $conn_heavy->affected_rows;
            } else {
                logMessage("❌ MySQL Error during bulk insert: " . $conn_heavy->error);
            }
            $values = [];
            logMessage("... Migrated $count/$total recipients");
        }
    }
    
    if (!empty($values)) {
        $sql = "INSERT IGNORE INTO mail_blaster (campaign_id, to_mail, csv_list_id, user_id, status, attempt_count) VALUES " . implode(',', $values);
        if ($conn_heavy->query($sql)) {
            $inserted += $conn_heavy->affected_rows;
        } else {
            logMessage("❌ MySQL Error during final bulk insert: " . $conn_heavy->error);
        }
    }
    
    logMessage("✅ BULK MIGRATION COMPLETE: $inserted NEW records seeded on Server 2");
    return $inserted;
}

/**
 * Get all active SMTP servers with their accounts (respecting daily/hourly limits)
 * OPTIMIZED: Adds campaign-specific filtering to prevent SMTP account contention
 * 
 * ⚠️ IMPORTANT: This function fetches data ONLY from Server 2 (CRM database)
 * All SMTP servers, accounts, usage, and health data are stored exclusively on Server 2
 * 
 * @param mysqli $conn_heavy - Server 2 database connection (CRM database)
 * @param int|null $user_id - Filter by specific user ID (optional)
 * @return array - Array of SMTP servers with their available accounts
 */
function getSmtpServersWithAccounts($conn_heavy, $user_id = null) {
    global $conn, $conn_heavy; 
    // Fallback if passed $conn_heavy is null
    if (!$conn_heavy && isset($GLOBALS['conn_heavy'])) { $conn_heavy = $GLOBALS['conn_heavy']; }
    
    logMessage(">>> ENTERING getSmtpServersWithAccounts (user_id=$user_id)");
    logMessage("📊 Database Connection: " . $conn_heavy->host_info . " | Database: CRM (Server 2)");
    logMessage("📌 IMPORTANT: All SMTP servers and accounts are stored ONLY on Server 2");
    $servers = [];
    
    try {
        // Reset daily counters if it's a new day
        resetDailyCountersIfNeeded($conn_heavy);
        
        // 🔍 DIAGNOSTIC: Check if smtp_servers table exists
        $tableCheck = $conn_heavy->query("SHOW TABLES LIKE 'smtp_servers'");
        if (!$tableCheck || $tableCheck->num_rows === 0) {
            logMessage("❌ CRITICAL ERROR: smtp_servers table does NOT exist in Server 2 database!");
            logMessage("   Database: CRM, Host: " . $conn_heavy->host_info);
            return [];
        }
        logMessage("✓ smtp_servers table exists in Server 2");
        
        // 🔍 DIAGNOSTIC: Show total servers in database (active and inactive)
        $totalServersCheck = $conn_heavy->query("SELECT COUNT(*) as total_servers, SUM(is_active) as active_servers FROM smtp_servers");
        if ($totalServersCheck && $totalServersCheck->num_rows > 0) {
            $serverStats = $totalServersCheck->fetch_assoc();
            logMessage("📊 SMTP Server Statistics: Total=" . $serverStats['total_servers'] . ", Active=" . ($serverStats['active_servers'] ?? 0));
            
            if ($serverStats['total_servers'] == 0) {
                logMessage("❌ CRITICAL: No SMTP servers configured in database at all!");
                logMessage("   Please add SMTP servers to smtp_servers table on Server 2");
                return [];
            }
            
            if ($serverStats['active_servers'] == 0) {
                logMessage("⚠️ WARNING: No ACTIVE SMTP servers! All servers have is_active=0");
                logMessage("   Please activate at least one SMTP server");
            }
        }
        
        // User filter for SMTP servers
        $userServerFilter = $user_id ? "AND ss.user_id = $user_id" : "";
        $hasUserIdColumn = false;
        
        logMessage("🔍 Fetching SMTP servers from SERVER 2 (CRM database) with user_id filter: " . ($user_id ? "user_id=$user_id" : "NO FILTER (all users)"));
        
        // First, verify smtp_servers table structure
        if ($user_id) {
            $columns_check = $conn_heavy->query("SHOW COLUMNS FROM smtp_servers LIKE 'user_id'");
            if ($columns_check && $columns_check->num_rows > 0) {
                logMessage("✓ smtp_servers table has 'user_id' column");
                $hasUserIdColumn = true;
                
                // 🔍 DIAGNOSTIC: Show how many servers this user has on Server 2
                $userServerStats = $conn_heavy->query("SELECT COUNT(*) as total, SUM(is_active) as active FROM smtp_servers WHERE user_id = $user_id");
                if ($userServerStats && $userServerStats->num_rows > 0) {
                    $stats = $userServerStats->fetch_assoc();
                    logMessage("📊 User #$user_id SMTP servers on SERVER 2: Total=" . $stats['total'] . ", Active=" . ($stats['active'] ?? 0));
                    
                    if ($stats['total'] == 0) {
                        logMessage("⚠️ User #$user_id has NO SMTP servers on Server 2!");
                    } else if ($stats['active'] == 0) {
                        logMessage("⚠️ User #$user_id has SMTP servers but all are INACTIVE!");
                    }
                }
            } else {
                logMessage("⚠ WARNING: smtp_servers table does NOT have 'user_id' column!");
                logMessage("   Cannot filter by user - will use ALL SMTP servers instead");
                $userServerFilter = ""; // Clear filter if column doesn't exist
            }
        }
        
        // Get all active servers (filtered by user if provided) - All SMTP on Server 2
        $query = "SELECT ss.id, ss.name, ss.host, ss.port, ss.encryption, ss.received_email 
            FROM smtp_servers ss
            WHERE ss.is_active = 1 
            $userServerFilter
            ORDER BY ss.id ASC";
        
        logMessage("SQL Query: $query");
        logMessage("🔧 Executing query on Server 2...");
        $server_result = $conn_heavy->query($query);
        
        if (!$server_result) {
            logMessage("❌ ERROR fetching SMTP servers from Server 2: " . $conn_heavy->error);
            logMessage("SQL Error Code: " . $conn_heavy->errno);
            return [];
        }
        
        logMessage("✅ Query successful! Found " . $server_result->num_rows . " SMTP servers from Server 2 matching filter");
        
        // 🔧 FALLBACK: If no servers found WITH user filter, try WITHOUT filter
        if ($server_result->num_rows === 0 && $user_id && $hasUserIdColumn) {
            logMessage("⚠️ No SMTP servers found on SERVER 2 for user_id=$user_id");
            logMessage("   This means user #$user_id has NO SMTP servers configured on Server 2");
            logMessage("   OR all their SMTP servers are inactive (is_active=0)");
            logMessage("🔄 FALLBACK: Retrying query WITHOUT user_id filter to use ALL servers...");
            
            // Check if user has any servers at all (active or inactive)
            $userServerCheck = $conn_heavy->query("SELECT COUNT(*) as cnt FROM smtp_servers WHERE user_id = $user_id");
            if ($userServerCheck && $userServerCheck->num_rows > 0) {
                $userServerCount = $userServerCheck->fetch_assoc()['cnt'];
                if ($userServerCount > 0) {
                    logMessage("   Found $userServerCount SMTP server(s) for user #$user_id, but all are INACTIVE");
                    logMessage("   Please activate them by setting is_active=1");
                } else {
                    logMessage("   User #$user_id has ZERO SMTP servers configured on Server 2");
                    logMessage("   Please add SMTP servers for this user");
                }
            }
            
            $fallbackQuery = "SELECT ss.id, ss.name, ss.host, ss.port, ss.encryption, ss.received_email 
                FROM smtp_servers ss
                WHERE ss.is_active = 1 
                ORDER BY ss.id ASC";
            
            logMessage("Fallback SQL Query: $fallbackQuery");
            $server_result = $conn_heavy->query($fallbackQuery);
            
            if ($server_result && $server_result->num_rows > 0) {
                logMessage("✅ Fallback successful! Found " . $server_result->num_rows . " SMTP servers (ALL users)");
                logMessage("   Campaign will use SMTP servers from ANY user since user #$user_id has none");
                $userServerFilter = ""; // Clear filter for accounts too
            } else {
                logMessage("❌ Fallback failed - NO SMTP servers found even without user filter!");
                logMessage("   Database has NO active SMTP servers at all!");
            }
        }
    
    $today = date('Y-m-d');
    $current_hour = intval(date('G'));
    
    // User filter for SMTP queries (both servers and accounts)
    $saFilter = ($user_id > 0) ? " AND sa.user_id = $user_id" : "";
    $plainFilter = ($user_id > 0) ? " AND user_id = $user_id" : "";
    
    logMessage("🔍 Will filter SMTP accounts from Server 2 with: " . ($user_id ? "user_id=$user_id" : "NO FILTER"));
    
    // Verify smtp_accounts table structure (only if we're trying to filter)
    if ($user_id && $plainFilter) {
        $acc_columns_check = $conn_heavy->query("SHOW COLUMNS FROM smtp_accounts LIKE 'user_id'");
        if ($acc_columns_check && $acc_columns_check->num_rows > 0) {
            logMessage("✓ smtp_accounts table (Server 2) has 'user_id' column");
        } else {
            logMessage("⚠ WARNING: smtp_accounts table (Server 2) does NOT have 'user_id' column!");
            logMessage("   Cannot filter by user - will return ALL SMTP accounts");
            $saFilter = ""; 
            $plainFilter = "";
        }
    }
    
    logMessage("📋 Processing servers and fetching their SMTP accounts from Server 2...");
    
    while ($server = $server_result->fetch_assoc()) {
        logMessage("🔧 Fetching accounts for server '{$server['name']}' (ID: {$server['id']}) from Server 2...");
        // OPTIMIZED: Get accounts with proper limit checks using COALESCE for better performance
        // Filter out accounts that have exceeded their daily or hourly limits
        $account_result = $conn_heavy->query("
            SELECT sa.id, sa.email, sa.password, sa.daily_limit, sa.hourly_limit, 
                   sa.total_sent,
                   COALESCE(daily_usage.sent_today, 0) as sent_today,
                   COALESCE(hourly_usage.emails_sent, 0) as sent_this_hour,
                   CASE 
                       WHEN sa.daily_limit > 0 THEN sa.daily_limit - COALESCE(daily_usage.sent_today, 0)
                       ELSE 999999
                   END as daily_remaining,
                   CASE 
                       WHEN sa.hourly_limit > 0 THEN sa.hourly_limit - COALESCE(hourly_usage.emails_sent, 0)
                       ELSE 999999
                   END as hourly_remaining
            FROM smtp_accounts sa
            LEFT JOIN (
                SELECT smtp_id, SUM(emails_sent) as sent_today
                FROM smtp_usage
                WHERE date = '$today'
                GROUP BY smtp_id
            ) daily_usage ON daily_usage.smtp_id = sa.id
            LEFT JOIN smtp_usage hourly_usage ON hourly_usage.smtp_id = sa.id 
                AND hourly_usage.date = '$today' AND hourly_usage.hour = $current_hour
            LEFT JOIN smtp_health sh ON sh.smtp_id = sa.id
            WHERE sa.smtp_server_id = {$server['id']} 
            AND sa.is_active = 1
            $saFilter
            AND (sa.daily_limit = 0 OR COALESCE(daily_usage.sent_today, 0) < sa.daily_limit)
            AND (sa.hourly_limit = 0 OR COALESCE(hourly_usage.emails_sent, 0) < sa.hourly_limit)
            AND (sh.health IS NULL OR sh.health != 'suspended' OR sh.suspend_until < NOW())
            ORDER BY 
                COALESCE(hourly_usage.emails_sent, 0) ASC,
                sa.id ASC
        ");
        
        if (!$account_result) {
            logMessage("ERROR fetching accounts for server {$server['id']}: " . $conn_heavy->error);
            continue;
        }
        
        $accounts = [];
        $filtered_count = 0;
        while ($account = $account_result->fetch_assoc()) {
            $accounts[] = $account;
        }
        
        // Also check total accounts before filtering to show how many were excluded
        $total_check = $conn_heavy->query("SELECT COUNT(*) as cnt FROM smtp_accounts WHERE smtp_server_id = {$server['id']} AND is_active = 1 $plainFilter");
        $total_accounts = ($total_check && $total_check->num_rows > 0) ? $total_check->fetch_assoc()['cnt'] : 0;
        
        if ($total_accounts > count($accounts)) {
            $filtered_count = $total_accounts - count($accounts);
            logMessage("Server #{$server['id']}: $filtered_count accounts filtered out (limits/health)");
        }
        
        // Only include servers that have available accounts
        if (!empty($accounts)) {
            $server['accounts'] = $accounts;
            $servers[] = $server;
            logMessage("Server {$server['name']}: " . count($accounts) . " accounts available" . ($user_id ? " (user $user_id)" : "") . " out of $total_accounts total");
        } else {
            logMessage("Server {$server['name']}: No accounts available" . ($user_id ? " for user $user_id" : "") . " (total: $total_accounts, all filtered out)");
        }
    }
    
    if (empty($servers) && $user_id) {
        logMessage("⚠ WARNING: No SMTP servers/accounts found on Server 2 for user $user_id");
        logMessage("Recommendation: Check if smtp_servers table has 'user_id' column and if user #$user_id has servers assigned");
    }
    
    logMessage("✅ <<< EXITING getSmtpServersWithAccounts - Returning " . count($servers) . " servers (ALL from Server 2)");
    return $servers;
    
    } catch (Exception $e) {
        logMessage("❌ EXCEPTION in getSmtpServersWithAccounts: " . $e->getMessage());
        logMessage("Stack trace: " . $e->getTraceAsString());
        return [];
    }
}

/**
 * Get next SMTP account for a server using round-robin over accounts.
 * Persists rotation in `smtp_rotation` so we don't repeat until a full cycle completes.
 */
function getNextAccountForServer($conn_heavy, $server, $accounts) {
    if (empty($accounts)) return null;

    $server_id = (int)$server['id'];
    $count = count($accounts);

    // Ensure rotation row exists (smtp_rotation on Server 2)
    $conn_heavy->query("INSERT INTO smtp_rotation (id, last_smtp_index, last_smtp_id, total_smtp_count) VALUES ($server_id, 0, NULL, $count) ON DUPLICATE KEY UPDATE total_smtp_count = $count");

    $rotRes = $conn_heavy->query("SELECT last_smtp_index FROM smtp_rotation WHERE id = $server_id");
    $idx = 0;
    if ($rotRes && $rotRes->num_rows > 0) {
        $idx = (int)$rotRes->fetch_assoc()['last_smtp_index'];
    }

    // Advance to next index (wrap around)
    $next_idx = ($idx % $count);
    $next_account = $accounts[$next_idx];

    // Update rotation to point to subsequent index for next pick
    $conn_heavy->query("UPDATE smtp_rotation SET last_smtp_index = " . (($next_idx + 1) % $count) . ", last_smtp_id = " . (int)$next_account['id'] . ", total_smtp_count = $count WHERE id = $server_id");

    return $next_account;
}

/**
 * Get working SMTP servers (exclude recently failed ones, respect limits)
 */
function getWorkingSmtpServers($conn_heavy, $user_id = null) {
    global $conn, $conn_heavy;
    if (!$conn_heavy && isset($GLOBALS['conn_heavy'])) { $conn_heavy = $GLOBALS['conn_heavy']; }
    $servers = [];
    
    // Reset daily counters if it's a new day
    resetDailyCountersIfNeeded($conn_heavy);
    
    // Build user filter
    $userFilter = $user_id ? "AND ss.user_id = $user_id" : "";
    
    // Get servers that have sent successfully in the last 10 minutes
    // OR have no recent failures (mail_blaster + smtp_* all on Server 2)
    $server_result = $conn_heavy->query("
        SELECT DISTINCT ss.id, ss.name, ss.host, ss.port, ss.encryption, ss.received_email
        FROM smtp_servers ss
        WHERE ss.is_active = 1
        $userFilter
        ORDER BY ss.id ASC
    ");
    
    $today = date('Y-m-d');
    $current_hour = intval(date('G'));
    
    while ($server = $server_result->fetch_assoc()) {
        // Get accounts that are within their limits using smtp_usage
        $account_result = $conn_heavy->query("
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
            AND sa.is_active = 1" . ($user_id ? " AND sa.user_id = $user_id" : "") . "
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
        return getSmtpServersWithAccounts($conn_heavy, $user_id);
    }
    
    return $servers;
}

/**
 * Get emails that need to be sent
 */
function getEmailsToSend($conn, $campaign_id) {
    global $conn;
    if (!$conn && isset($GLOBALS['conn'])) { $conn = $GLOBALS['conn']; }
    // Check campaign source: import_batch_id or csv_list_id
    $campaignResult = $conn->query("SELECT import_batch_id, csv_list_id, user_id FROM campaign_master WHERE campaign_id = $campaign_id");
    
    if (!$campaignResult || $campaignResult->num_rows === 0) {
        logMessage("ERROR: Campaign #$campaign_id not found");
        return [];
    }
    
    $campaignData = $campaignResult->fetch_assoc();
    $import_batch_id = $campaignData['import_batch_id'];
    $csv_list_id = intval($campaignData['csv_list_id']);
    $campaign_user_id = isset($campaignData['user_id']) ? intval($campaignData['user_id']) : 0;
    
    $emails = [];
    
    if ($import_batch_id) {
        // Fetch from imported_recipients table with user filter
        $batch_escaped = $conn->real_escape_string($import_batch_id);
        $userFilter = $campaign_user_id > 0 ? " AND ir.user_id = $campaign_user_id" : "";
        $result = $conn->query("
            SELECT ir.id, ir.Emails as raw_emailid
            FROM imported_recipients ir
            LEFT JOIN mail_blaster mb ON mb.campaign_id = $campaign_id
                AND mb.to_mail COLLATE utf8mb4_unicode_ci = ir.Emails COLLATE utf8mb4_unicode_ci
            WHERE ir.import_batch_id = '$batch_escaped'
            AND ir.is_active = 1
            AND ir.Emails IS NOT NULL
            AND ir.Emails <> ''
            $userFilter
            AND mb.id IS NULL
            ORDER BY ir.id ASC
        ");
        
        while ($row = $result->fetch_assoc()) {
            $emails[] = $row;
        }
        
        $email_table = "imported_recipients (Excel)";
        logMessage("Found " . count($emails) . " emails to send for campaign #$campaign_id (from $email_table, batch: $import_batch_id)");
        logAnalysis($campaign_id, "SOURCE: Fetching emails from Server 1 table: $email_table");
        logAnalysis($campaign_id, "SMTP SOURCE: Fetching SMTP accounts from Server 2 table: smtp_accounts");
        
    } else {
        // Fetch from emails table (CSV)
        $csvFilter = $csv_list_id > 0 ? "AND e.csv_list_id = $csv_list_id" : "";
        
        $result = $conn->query("
            SELECT e.id, e.raw_emailid
            FROM emails e
            LEFT JOIN mail_blaster mb ON mb.campaign_id = $campaign_id
                AND mb.to_mail = e.raw_emailid
            WHERE e.domain_status = 1
            AND e.validation_status = 'valid'
            $csvFilter
            AND mb.id IS NULL
            ORDER BY e.id ASC
        ");
        
        while ($row = $result->fetch_assoc()) {
            $emails[] = $row;
        }
        
        $source = $csv_list_id > 0 ? "(from CSV list #$csv_list_id)" : "(from all validated emails)";
        logMessage("Found " . count($emails) . " emails to send for campaign #$campaign_id $source");
    }
    
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
    global $conn;
    if (!$conn && isset($GLOBALS['conn'])) { $conn = $GLOBALS['conn']; }
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
    
    // Wait for retry workers to complete - EXTREME SPEED
    usleep(2000000); // WEB-FRIENDLY: 2 seconds between retry checks to not block web
    
    return count($failed_emails);
}

/**
 * Update final campaign statistics
 */
function updateFinalCampaignStats($conn, $campaign_id) {
    global $conn, $conn_heavy;
    if (!$conn && isset($GLOBALS['conn'])) { $conn = $GLOBALS['conn']; }
    if (!$conn_heavy && isset($GLOBALS['conn_heavy'])) { $conn_heavy = $GLOBALS['conn_heavy']; }
    logMessage("Updating final campaign statistics for campaign #$campaign_id");
    
    // Get campaign details to check source
    $campaignResult = $conn->query("SELECT import_batch_id, csv_list_id FROM campaign_master WHERE campaign_id = $campaign_id");
    if (!$campaignResult || $campaignResult->num_rows === 0) {
        logMessage("Campaign #$campaign_id not found");
        return null;
    }
    $campaignData = $campaignResult->fetch_assoc();
    $import_batch_id = $campaignData['import_batch_id'];
    $csv_list_id = intval($campaignData['csv_list_id']);
    
    // Get accurate counts from mail_blaster table (SERVER 2)
    // Only count permanently failed (attempt_count >= 5)
    $stats = $conn_heavy->query("
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
    
    // 🔥 PERFORMANCE: Cache total_emails - only calculate ONCE per campaign
    // Check if we already have total_emails in campaign_status
    $existingTotal = $conn->query("SELECT total_emails FROM campaign_status WHERE campaign_id = $campaign_id LIMIT 1");
    $total_emails = 0;
    
    if ($existingTotal && $existingTotal->num_rows > 0) {
        $total_emails = intval($existingTotal->fetch_assoc()['total_emails']);
    }
    
    // Only calculate total if not already set (first run)
    if ($total_emails == 0) {
        logMessage("First stats update - calculating total emails for campaign #$campaign_id");
        
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
            logMessage("Campaign #$campaign_id uses Excel import (batch: $import_batch_id), Total emails: $total_emails");
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
            logMessage("Campaign #$campaign_id uses CSV list (ID: $csv_list_id), Total valid emails: $total_emails");
        } else {
            // All valid emails source
            $total_result = $conn->query("
                SELECT COUNT(DISTINCT e.raw_emailid) as total
                FROM emails e
                WHERE e.domain_status = 1 AND e.validation_status = 'valid'
                AND e.raw_emailid IS NOT NULL AND e.raw_emailid <> ''
            ");
            $total_emails = intval($total_result->fetch_assoc()['total']);
            logMessage("Campaign #$campaign_id uses all valid emails, Total: $total_emails");
        }
    } else {
        logMessage("Using cached total_emails: $total_emails (no recalculation needed)");
    }
    
    // Pending = Total - Success - Permanently Failed
    $pending_emails = max(0, $total_emails - $sent_emails - $failed_emails);
    
    // Determine campaign status - mark as completed when all emails are processed
    $campaign_status = 'running';
    if ($pending_emails == 0 && $total_emails > 0) {
        $campaign_status = 'completed';
        logMessage("Campaign #$campaign_id COMPLETED - All $total_emails emails processed (Sent: $sent_emails, Failed: $failed_emails)");
    } elseif ($total_emails == 0) {
        $campaign_status = 'completed';
        logMessage("Campaign #$campaign_id COMPLETED - No emails to send");
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
 * Log message - ❌ DISABLED for production performance (2 lakh email capacity)
 * Only outputs to stdout for cron capture if needed
 */

function logMessage($message) {
    // ❌ DISABLED - Logging disabled
    return;
    // ✅ ENABLED - Write to campaign-specific orchestrator log
    $timestamp = date('Y-m-d H:i:s');
    $pid = getmypid();
    $logMsg = "[$timestamp][PID:$pid] $message\n";
    
    // Write to orchestrator log file
    // ❌ DISABLED - Log files disabled
    // if (defined('ORCHESTRATOR_LOG')) {
    //     @file_put_contents(ORCHESTRATOR_LOG, $logMsg, FILE_APPEND | LOCK_EX);
    // }
    
    // Don't echo to stdout - cron is already redirecting stdout to the same file (would cause duplicates)
}

// Helper: remaining emails count - OPTIMIZED for speed
function getEmailsRemainingCount($conn, $campaign_id, $csv_list_id = 0) {
    global $conn_heavy;
    if (!$conn_heavy && isset($GLOBALS['conn_heavy'])) { $conn_heavy = $GLOBALS['conn_heavy']; }
    
    // Real count on Server 2 (mail_blaster) - this is now the source of truth
    // CRITICAL: Include 'processing' so orchestrator waits for active workers
    $res = $conn_heavy->query("SELECT COUNT(*) as cnt FROM mail_blaster WHERE campaign_id = " . intval($campaign_id) . " AND status IN ('pending', 'failed', 'processing') AND attempt_count < 5");
    if ($res && $row = $res->fetch_assoc()) {
        $count = intval($row['cnt']);
        // Update campaign_status on Server 1 with this fresh count
        $conn->query("UPDATE campaign_status SET pending_emails = $count WHERE campaign_id = " . intval($campaign_id));
        return $count;
    }
    
    return 0;
}

// Launch exactly one worker per server; each worker pulls and claims emails itself
function launchPerServerWorkers($conn, $campaign_id, $smtp_servers, $campaign) {
    global $conn;
    if (!$conn && isset($GLOBALS['conn'])) { $conn = $GLOBALS['conn']; }
    logMessage("Launching per-server workers: " . count($smtp_servers));
    logMessage("Worker script path: " . WORKER_SCRIPT);

    if (!file_exists(WORKER_SCRIPT)) {
        logMessage("ERROR: Worker script not found at " . WORKER_SCRIPT);
        return ["status" => "error", "message" => "Worker script not created"];
    }
    logMessage("Worker script verified: EXISTS");

    // List of PHP binary candidates in order of preference
    $php_cli_candidates = [
        '/opt/plesk/php/8.1/bin/php',   // Plesk PHP 8.1 (Production Preferred)
        '/usr/bin/php8.1',              // Standard PHP 8.1
        '/usr/local/bin/php',
        '/usr/bin/php',
        '/opt/lampp/bin/php'            // XAMPP/LAMPP
    ];

    $php_cli = null;
    
    // 1. Try to find a valid PHP binary from candidates
    foreach ($php_cli_candidates as $candidate) {
        if (file_exists($candidate) && is_executable($candidate)) {
            $php_cli = $candidate;
            break;
        }
    }
    
    // 2. Fallback to system PATH or PHP_BINARY constant
    if (!$php_cli) {
        if (defined('PHP_BINARY') && PHP_BINARY && file_exists(PHP_BINARY) && is_executable(PHP_BINARY)) {
            $php_cli = PHP_BINARY;
        } else {
            $env_php = trim(shell_exec('command -v php 2>/dev/null'));
            if ($env_php && file_exists($env_php) && is_executable($env_php)) {
                $php_cli = $env_php;
            } else {
                // Last ditch effort - assume it's in path
                $php_cli = 'php';
            }
        }
    }
    
    logMessage("Using PHP CLI binary: $php_cli");
    
    // CRITICAL: Verify PHP version is 8.0+ for workers
    // capture stderr too to filter out warnings
    $version_output = shell_exec(escapeshellarg($php_cli) . ' -r "echo phpversion();" 2>&1');
    logMessage("Raw PHP version output: $version_output");
    
    // Extract version number using regex (ignore warnings/errors in output)
    $worker_php_version = '0.0.0';
    if (preg_match('/(\d+\.\d+\.\d+)/', $version_output ?? '', $matches)) {
        $worker_php_version = $matches[1];
    }
    
    logMessage("Detected Worker PHP version: $worker_php_version");
    
    if (version_compare($worker_php_version, '8.0.0', '<')) {
        logMessage("ERROR: Worker PHP is version $worker_php_version (requires PHP 8.0+)");
        
        // Try to force Plesk PHP 8.1 if we aren't already using it
        $plesk_php = '/opt/plesk/php/8.1/bin/php';
        if ($php_cli !== $plesk_php && file_exists($plesk_php) && is_executable($plesk_php)) {
            logMessage("Attempting to switch to Plesk PHP 8.1...");
            $php_cli = $plesk_php;
             // Re-verify
             $version_output = shell_exec(escapeshellarg($php_cli) . ' -r "echo phpversion();" 2>&1');
             if (preg_match('/(\d+\.\d+\.\d+)/', $version_output ?? '', $matches)) {
                $worker_php_version = $matches[1];
                logMessage("New PHP version: $worker_php_version");
             }
        }
        
        if (version_compare($worker_php_version, '8.0.0', '<')) {
             logMessage("CRITICAL: Cannot find PHP 8.1+ for workers!");
             return ["status" => "error", "message" => "PHP 8.1+ required (Found $worker_php_version)"];
        }
    }
    
    // Final verify
    if ($php_cli !== 'php' && (!file_exists($php_cli) || !is_executable($php_cli))) {
         // Fallback to just 'php' if the explicit path failed validation somehow
         $php_cli = 'php';
    }

    $processes = [];
    $workers_launched = 0;
    $workers_failed = 0;
    
    foreach ($smtp_servers as $server) {
        $server_config = json_encode([
            'server_id' => (int)$server['id'],
            'host' => $server['host'],
            'port' => $server['port'],
            'encryption' => $server['encryption'],
            'received_email' => $server['received_email'],
        ]);
        $campaign_json = json_encode($campaign);

        // Redirect worker output to campaign-specific log file for debugging
        // ❌ DISABLED - Log files disabled
        // $worker_log = __DIR__ . '/../logs/worker_campaign_' . $campaign_id . '_server_' . $server['id'] . '.log';
        // logMessage("Worker log file: $worker_log");
        
        $cmd = sprintf(
            '%s %s %d %s %s %s > /dev/null 2>&1 &',
            escapeshellarg($php_cli),
            escapeshellarg(WORKER_SCRIPT),
            $campaign_id,
            escapeshellarg(''),
            escapeshellarg($server_config),
            escapeshellarg($campaign_json)
        );
        
        logMessage("=== LAUNCHING WORKER #{$server['id']} ===");
        logMessage("Server: {$server['name']} (Host: {$server['host']}:{$server['port']})");
        logMessage("Accounts for this server: " . count($server['accounts']));
        logMessage("Command: $cmd");
        
        exec($cmd, $out, $ret);
        logMessage("Exec return code: $ret");
        
        // Give worker moment to start and verify log creation
        usleep(200000); // 200ms
        
        // Worker launched - check if log file created
        $workers_launched++;
        
        // Verify worker log file was created (should exist immediately with WORKER_LOG_ENABLED=true)
        // ❌ DISABLED - Log files disabled
        // if (file_exists($worker_log)) {
        //     $log_size = filesize($worker_log);
        //     logMessage("✓ Worker log created: $worker_log ($log_size bytes)");
        // } else {
        //     logMessage("⚠ Worker log not created yet - worker may have failed to start");
        // }
        
        $processes[] = [
            'server_id' => (int)$server['id'],
            'name' => $server['name']
        ];
        logMessage("✓ Worker launch attempt completed for #{$server['id']} ({$server['name']})");
        usleep(100000); // HIGH-SPEED: 100ms delay between workers
    }
    
    logMessage("=== WORKER LAUNCH SUMMARY ===");

    logMessage("Total servers: " . count($smtp_servers));
    logMessage("Workers launched successfully: $workers_launched");
    logMessage("Workers failed: $workers_failed");
    
    if ($workers_launched == 0) {
        logMessage("⚠ CRITICAL: No workers started successfully!");
        return [
            'status' => 'error',
            'message' => 'No workers started',
            'workers_launched' => 0,
            'workers_failed' => $workers_failed
        ];
    }

    return [
        'status' => 'success',
        'message' => 'Per-server workers launched',
        'workers_launched' => $workers_launched,
        'workers_failed' => $workers_failed
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
logMessage("🔄 Step 5: Re-initializing database connections for main loop...");
require_once __DIR__ . '/../config/db.php';
logMessage("✅ Database connections ready for daemon loop");

logMessage("=== Starting Parallel Email Blast Daemon for Campaign #$campaign_id ===");

logMessage("🔄 Entering main daemon loop...");
$loop_iteration = 0;

while (true) {
    $loop_iteration++;
    logMessage("📍 Loop iteration #$loop_iteration starting...");
    
    try {
        // Reconnect to database for each cycle - Use production config
        logMessage("  └─ Reconnecting to database...");
        require_once __DIR__ . '/../config/db.php';
        if ($conn->connect_error) {
            logMessage("❌ Database connection failed: " . $conn->connect_error);
            sleep(5); // WEB-FRIENDLY: 5 seconds before reconnect to reduce connection churn
            continue;
        }
        logMessage("  └─ ✅ Database reconnected");
        
        // Extra safety: if campaign_master row is deleted, exit daemon and clean PID
        logMessage("  └─ Checking if campaign still exists in campaign_master...");
        $cm_res = $conn->query("SELECT csv_list_id, import_batch_id FROM campaign_master WHERE campaign_id = $campaign_id LIMIT 1");
        if (!$cm_res || $cm_res->num_rows === 0) {
            logMessage("❌ Campaign master row missing (deleted). Exiting daemon.");
            $conn->close();
            break;
        }
        $cm_row = $cm_res->fetch_assoc();
        $csv_list_id = intval($cm_row['csv_list_id']);
        $import_batch_id = $cm_row['import_batch_id'];
        logMessage("  └─ ✅ Campaign found in campaign_master (CSV List: $csv_list_id, Batch: $import_batch_id)");

        // Check campaign status
        logMessage("  └─ Checking campaign status from campaign_status table...");
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

        // Auto-resume pending campaigns to running after checking SMTP health
        if ($status === 'pending') {
            logMessage("Campaign is pending. Checking SMTP health before starting...");
            
            // Auto-restore suspended accounts if suspend time has passed (smtp_health on Server 2)
            $conn_heavy->query("UPDATE smtp_health SET health = 'healthy', consecutive_failures = 0, suspend_until = NULL 
                WHERE health = 'suspended' AND suspend_until IS NOT NULL AND suspend_until < NOW()");
            
            // Count healthy SMTP accounts available (smtp_accounts + smtp_health on Server 2)
            $healthyCount = $conn_heavy->query("
                SELECT COUNT(*) as cnt FROM smtp_accounts sa
                LEFT JOIN smtp_health sh ON sa.id = sh.smtp_id
                WHERE sa.is_active = 1
                AND (sh.health IS NULL OR sh.health = 'healthy' OR (sh.health = 'suspended' AND sh.suspend_until < NOW()))
            ");
            $healthy = ($healthyCount && $healthyCount->num_rows > 0) ? intval($healthyCount->fetch_assoc()['cnt']) : 0;
            
            if ($healthy > 0) {
                logMessage("Found $healthy healthy SMTP accounts. Starting campaign...");
                try {
                    $conn->begin_transaction();
                    $conn->query("SELECT campaign_id FROM campaign_status WHERE campaign_id = $campaign_id FOR UPDATE");
                    $conn->query("UPDATE campaign_status SET status = 'running', start_time = NOW() WHERE campaign_id = $campaign_id");
                    $conn->commit();
                    $status = 'running';
                } catch (Exception $e) {
                    $conn->rollback();
                    logMessage("Failed to start campaign: " . $e->getMessage());
                }
            } else {
                logMessage("No healthy SMTP accounts available. Checking degraded accounts...");
                $degradedCount = $conn_heavy->query("
                    SELECT COUNT(*) as cnt FROM smtp_accounts sa
                    JOIN smtp_health sh ON sa.id = sh.smtp_id
                    WHERE sa.is_active = 1 AND sh.health = 'degraded'
                ");
                $degraded = ($degradedCount && $degradedCount->num_rows > 0) ? intval($degradedCount->fetch_assoc()['cnt']) : 0;
                
                if ($degraded > 0) {
                    logMessage("Found $degraded degraded SMTP accounts. Starting with caution...");
                    try {
                        $conn->begin_transaction();
                        $conn->query("SELECT campaign_id FROM campaign_status WHERE campaign_id = $campaign_id FOR UPDATE");
                        $conn->query("UPDATE campaign_status SET status = 'running', start_time = NOW() WHERE campaign_id = $campaign_id");
                        $conn->commit();
                        $status = 'running';
                    } catch (Exception $e) {
                        $conn->rollback();
                        logMessage("Failed to start campaign with degraded SMTPs: " . $e->getMessage());
                    }
                } else {
                    logMessage("No SMTP accounts available. Keeping campaign pending.");
                    $conn->close();
                    sleep(10); // EXTREME SPEED: Reduced to 10 seconds for faster retry
                    continue;
                }
            }
        }

        if ($status !== 'running') {
            logMessage("Campaign status is '$status'. Exiting daemon.");
            $conn->close();
            break;
        }

        // Check network connectivity
        if (!checkNetworkConnectivity()) {
            logMessage("Network connection unavailable. Waiting to retry...");
            $conn->close();
            sleep(10); // EXTREME SPEED: Reduced to 10 seconds for faster network retry
            continue;
        }

        // Check if there are emails remaining to send (on Server 2 only)
        // CRITICAL: Include 'processing' so daemon doesn't exit while workers are busy
        $remaining_res = $conn_heavy->query("SELECT 1 FROM mail_blaster WHERE campaign_id = $campaign_id AND status IN ('pending', 'failed', 'processing') AND attempt_count < 5 LIMIT 1");
        $remaining_count = ($remaining_res && $remaining_res->num_rows > 0) ? 1 : 0;

        // CRITICAL: On the first iteration, we MUST proceed to runParallelEmailBlast() 
        // to trigger the bulk migration, even if mail_blaster is currently empty.
        if ($remaining_count == 0 && $loop_iteration > 1) {
            $conn->query("UPDATE campaign_status 
                         SET status = 'completed', pending_emails = 0, end_time = NOW() 
                         WHERE campaign_id = $campaign_id");
            logMessage("All emails processed for campaign #$campaign_id" . ($csv_list_id > 0 ? " (CSV List ID: $csv_list_id)" : "") . ". Campaign completed. Exiting daemon.");
            $conn->close();
            break;
        }

        logMessage("--- Starting parallel blast cycle for $remaining_count emails" . ($csv_list_id > 0 ? " (CSV List ID: $csv_list_id)" : "") . " ---");
        
        // Execute one cycle of parallel email blast
        $result = runParallelEmailBlast($conn, $campaign_id);
        // Extra diagnostics: summarize current mail_blaster counts
        $diag = $conn_heavy->query("SELECT status, COUNT(*) cnt FROM mail_blaster WHERE campaign_id = $campaign_id GROUP BY status");
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
        
        // Small delay before next cycle - EXTREME SPEED for 200+ emails/min
        $conn->close();
        usleep(2000000); // WEB-FRIENDLY: 2 seconds between cycles to not overwhelm server
        
    } catch (Exception $e) {
        logMessage("Error in daemon loop: " . $e->getMessage());
        if (isset($conn)) {
            $conn->close();
        }
        sleep(10); // WEB-FRIENDLY: 10 seconds before error recovery retry
    }
}

logMessage("=== Parallel Email Blast Daemon Stopped for Campaign #$campaign_id ===");