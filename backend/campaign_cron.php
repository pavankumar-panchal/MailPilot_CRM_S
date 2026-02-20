<?php
/**
 * Campaign Cron Job - Monitor and Auto-Restart
 * 
 * Add to crontab (runs every 2 minutes):
 * 
 * PRODUCTION (payrollsoft.in):
 * star/2 * * * * /opt/plesk/php/8.1/bin/php /var/www/vhosts/payrollsoft.in/httpdocs/emailvalidation/backend/campaign_cron.php >> /var/www/vhosts/payrollsoft.in/httpdocs/emailvalidation/backend/logs/cron_output.log 2>&1
 * (Replace "star" with asterisk symbol *)
 * 
 * LOCAL (LAMPP/XAMPP):
 * star/2 * * * * /opt/lampp/bin/php /opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/backend/campaign_cron.php >> /opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/backend/logs/cron_output.log 2>&1
 * (Replace "star" with asterisk symbol *)
 * 
 * This script:
 * 1. Monitors running campaigns - ensures orchestrator is alive
 * 2. Restarts crashed orchestrators  
 * 3. Auto-restarts campaigns with pending retries (optional)
 * 4. Runs with LOW priority to prevent affecting web server or other apps
 * 
 * VERIFY IT'S WORKING:
 * - Check logs/cron_output.log for cron execution logs
 * - Check logs/campaign_cron.log for detailed monitoring logs
 * - Check logs/orchestrator_campaign_X.log for orchestrator logs
 * - Check logs/worker_campaign_X_server_Y.log for worker logs
 * 
 * TEST MANUALLY:
 *   php test_orchestrator_launch.php <campaign_id>
 * 
 * TROUBLESHOOT:
 * - If no orchestrator logs: Check PHP binary path and permissions
 * - If orchestrator starts but no workers: Check worker script and SMTP accounts
 * - If workers start but no emails sent: Check SMTP configuration and quotas
 */

// Hard lock to prevent concurrent cron executions (mandatory guard)
$__cron_lock_file = sys_get_temp_dir() . '/campaign_cron.lock';
$__cron_lock_fp = @fopen($__cron_lock_file, 'c');
if ($__cron_lock_fp === false) {
    // If we can't open a lock file, safest is to exit to avoid duplicates
    exit(0);
}
// Non-blocking exclusive lock; if already locked, another cron is running
if (!@flock($__cron_lock_fp, LOCK_EX | LOCK_NB)) {
    exit(0);
}
// Keep the lock for the life of the process; release on shutdown
register_shutdown_function(function() use ($__cron_lock_fp) {
    if (is_resource($__cron_lock_fp)) {
        @flock($__cron_lock_fp, LOCK_UN);
        @fclose($__cron_lock_fp);
    }
});

// Ensure lock directory exists
$lock_dir = __DIR__ . '/tmp/cron_locks';
if (!is_dir($lock_dir)) {
    mkdir($lock_dir, 0775, true);
    chmod($lock_dir, 0775);
}

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
// ❌ DISABLED - Log files disabled
// ini_set('error_log', __DIR__ . '/logs/cron_error.log');

// Catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $msg = "[FATAL] {$error['message']} in {$error['file']}:{$error['line']}\n";
        // @file_put_contents(__DIR__ . '/logs/cron_error.log', date('[Y-m-d H:i:s] ') . $msg, FILE_APPEND); // Disabled
        echo $msg;
    }
});

// === RESOURCE MANAGEMENT: Prevent affecting other applications ===
require_once __DIR__ . '/includes/resource_manager.php';
ResourceManager::initCampaignProcess('cron');
// Memory limit (256M) and time limit (120s) set by ResourceManager

// Require ProcessManager FIRST to prevent duplicate executions
require_once __DIR__ . '/includes/ProcessManager.php';

// Acquire lock - exit if already running
$lock = new ProcessManager('campaign_monitor', 300); // 5 minute timeout
if (!$lock->acquire()) {
    // Job already running, exit silently
    exit(0);
}

// Start logging immediately
$log_file = __DIR__ . '/logs/campaign_cron.log';
$log_dir = dirname($log_file);
if (!is_dir($log_dir)) {
    @mkdir($log_dir, 0777, true);
}

function logCron($message) {
    // ❌ DISABLED - Logging disabled
    return;
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log_msg = "[$timestamp] $message\n";
    // Enable log file for campaign monitoring
    @file_put_contents($log_file, $log_msg, FILE_APPEND | LOCK_EX);
    echo $log_msg; // Output to console for cron email/output
}

logCron("=== CRON JOB START ===");
logCron("PHP Version: " . PHP_VERSION);
logCron("Working directory: " . getcwd());
logCron("Script path: " . __FILE__);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/db_campaign.php'; // Campaign database connection

if ($conn->connect_error) {
    logCron("ERROR: Main database connection failed: " . $conn->connect_error);
    die("Database connection failed: " . $conn->connect_error . "\n");
}

if ($conn_heavy->connect_error) {
    logCron("ERROR: Heavy database connection failed: " . $conn_heavy->connect_error);
    die("Heavy database connection failed: " . $conn_heavy->connect_error . "\n");
}

logCron("Both databases connected successfully");

/**
 * Check database connection health and reconnect if necessary
 * Prevents "MySQL server has gone away" errors during long-running operations
 * 
 * @param mysqli $connection Database connection to check
 * @param string $name Connection name for logging ('SERVER 1' or 'SERVER 2')
 * @return bool True if connection is healthy or successfully reconnected
 */
function ensureConnectionAlive(&$connection, $name = 'Database') {
    // Ping the connection to check if it's still alive
    if (!$connection->ping()) {
        logCron("⚠️  $name connection lost, attempting reconnect...");
        
        // Store original connection parameters
        $host = $connection->host_info;
        
        // For SERVER 1 (main database) and SERVER 2 (campaign database), we need to reconnect
        // Note: We cannot easily re-establish the connection here without knowing credentials
        // The best approach is to just log the error and return false
        // The calling code should handle the failure gracefully
        
        logCron("❌ $name reconnection not possible in current context");
        return false;
    }
    return true;
}

function isPidRunning($pid) {
    if ($pid <= 0) return false;
    return file_exists('/proc/' . intval($pid));
}

// Get all running campaigns (exclude paused/stopped/completed) with user_id, csv_list_id, and import_batch_id
logCron("Querying for running campaigns...");
$result = $conn->query("
    SELECT 
        cs.campaign_id, 
        cs.status, 
        cs.total_emails,
        cs.sent_emails,
        cs.failed_emails,
        cs.pending_emails,
        cs.process_pid,
        cm.description,
        cm.user_id,
        cm.csv_list_id,
        cm.import_batch_id,
        u.name as user_name,
        u.email as user_email
    FROM campaign_status cs
    JOIN campaign_master cm ON cs.campaign_id = cm.campaign_id
    LEFT JOIN users u ON cm.user_id = u.id
    WHERE cs.status = 'running'
    ORDER BY cs.campaign_id
");

if (!$result) {
    logCron("ERROR: Query failed: " . $conn->error);
    exit(1);
}

logCron("Query executed. Rows found: " . $result->num_rows);

if ($result->num_rows == 0) {
    logCron("No running campaigns found");
    exit(0);
}

$campaigns = [];
while ($row = $result->fetch_assoc()) {
    $campaigns[] = $row;
}

logCron("Found " . count($campaigns) . " running campaigns");

// Detect PHP binary - MUST use CLI PHP 8.1+ not PHP 5.4
// CRITICAL: Prioritize Plesk PHP 8.1 for production server
$php_bin = null;
$php_candidates = [
    '/opt/plesk/php/8.1/bin/php',   // Plesk PHP 8.1 (PRODUCTION - PRIORITY)
    '/opt/lampp/bin/php',           // LAMPP/XAMPP (LOCAL)
    '/usr/bin/php8.1',              // Standard PHP 8.1
    '/usr/local/bin/php8.1',        // Custom PHP 8.1
    '/usr/bin/php',                 // Standard Linux (may be old PHP 5.4)
    '/usr/local/bin/php',           // Homebrew/custom
    defined('PHP_BINARY') ? PHP_BINARY : null,
    'php'                           // Fallback to PATH
];

foreach ($php_candidates as $candidate) {
    if ($candidate && file_exists($candidate)) {
        $php_bin = $candidate;
        break;
    }
}

if (!$php_bin) {
    $php_bin = defined('PHP_BINARY') ? PHP_BINARY : 'php';
}
logCron("PHP binary: $php_bin");

// CRITICAL: Verify PHP version is 8.0+ (not PHP 5.4)
$version_check = shell_exec(escapeshellarg($php_bin) . ' -r "echo phpversion();" 2>&1');
$version_check = trim($version_check);
logCron("PHP binary version: $version_check");

if (version_compare($version_check, '8.0.0', '<')) {
    logCron("ERROR: PHP binary is version $version_check (requires PHP 8.0+)");
    logCron("Attempting to use Plesk PHP 8.1 explicitly...");
    $php_bin = '/opt/plesk/php/8.1/bin/php';
    if (file_exists($php_bin)) {
        logCron("Switched to: $php_bin");
    } else {
        logCron("CRITICAL: Cannot find PHP 8.1+ binary!");
        exit(1);
    }
}

// Resolve parallel script path - always use relative to current directory
$parallel_script = __DIR__ . '/includes/email_blast_parallel.php';

// Verify the script exists, otherwise halt to prevent errors
if (!file_exists($parallel_script)) {
    logCron("CRITICAL ERROR: email_blast_parallel.php not found at: $parallel_script");
    $conn->close();
    exit(1);
}
logCron("Parallel script: $parallel_script");
logCron("Script exists: " . (file_exists($parallel_script) ? 'YES' : 'NO'));

$pid_dir = __DIR__ . '/tmp';
if (!is_dir($pid_dir)) {
    logCron("Creating PID directory: $pid_dir");
    @mkdir($pid_dir, 0777, true);
}
logCron("PID directory: $pid_dir");

// Launch parallel email blaster for each running campaign (if not already running)
foreach ($campaigns as $campaign) {
    $campaign_id = $campaign['campaign_id'];
    $user_id = isset($campaign['user_id']) ? intval($campaign['user_id']) : 0;
    $user_name = isset($campaign['user_name']) ? $campaign['user_name'] : 'Unknown';
    $total_emails = isset($campaign['total_emails']) ? intval($campaign['total_emails']) : 0;
    $sent_emails = isset($campaign['sent_emails']) ? intval($campaign['sent_emails']) : 0;
    $pending_emails = isset($campaign['pending_emails']) ? intval($campaign['pending_emails']) : 0;
    $process_pid = isset($campaign['process_pid']) ? intval($campaign['process_pid']) : 0;
    $csv_list_id = isset($campaign['csv_list_id']) ? intval($campaign['csv_list_id']) : 0;
    $import_batch_id = isset($campaign['import_batch_id']) ? $campaign['import_batch_id'] : null;
    
    logCron("Processing campaign #{$campaign_id}: {$campaign['description']}");
    logCron("  User: {$user_name} (ID: {$user_id})");
    
    // Log campaign source
    if ($import_batch_id) {
        logCron("  Source: Import Batch ID: {$import_batch_id}");
    } elseif ($csv_list_id > 0) {
        logCron("  Source: CSV List ID: {$csv_list_id}");
    } else {
        logCron("  Source: All Emails");
    }
    
    logCron("  Progress: {$sent_emails}/{$total_emails} emails sent");
    logCron("  Pending: {$pending_emails}, Process PID: {$process_pid}");
    
    // Ensure database connections are alive before querying
    if (!ensureConnectionAlive($conn, 'SERVER 1')) {
        logCron("  ✗ SERVER 1 connection failed, skipping campaign");
        continue;
    }
    if (!ensureConnectionAlive($conn_heavy, 'SERVER 2')) {
        logCron("  ✗ SERVER 2 connection failed, skipping campaign");
        continue;
    }
    
    // SAFETY CHECK: Verify no emails were missed
    // Count actual pending emails in mail_blaster vs campaign_status
    // Apply proper filtering based on campaign source
    if ($import_batch_id) {
        // CRITICAL FIX: Cannot JOIN across servers (mail_blaster on SERVER 2, imported_recipients on SERVER 1)
        // Solution: Query in two steps
        
        // Step 1: Get active emails from imported_recipients (SERVER 1)
        $batch_escaped = $conn->real_escape_string($import_batch_id);
        $emailsResult = $conn->query("
            SELECT Emails 
            FROM imported_recipients 
            WHERE import_batch_id = '$batch_escaped' 
            AND is_active = 1
        ");
        
        if ($emailsResult && $emailsResult->num_rows > 0) {
            // Collect all active emails
            $active_emails = [];
            while ($row = $emailsResult->fetch_assoc()) {
                $active_emails[] = $conn_heavy->real_escape_string($row['Emails']);
            }
            
            // Step 2: Count pending emails in mail_blaster (SERVER 2) that match the active emails
            if (count($active_emails) > 0) {
                $emails_list = "'" . implode("','", $active_emails) . "'";
                $actualPendingCheck = $conn_heavy->query("
                    SELECT COUNT(*) as actual_pending 
                    FROM mail_blaster 
                    WHERE campaign_id = $campaign_id 
                    AND status IN ('pending', 'failed') 
                    AND attempt_count < 5
                    AND to_mail IN ($emails_list)
                ");
            } else {
                $actualPendingCheck = null;
            }
        } else {
            logCron("  No active emails found for import_batch_id: {$import_batch_id}");
            $actualPendingCheck = null;
        }
    } else {
        // For CSV campaigns (or all emails), apply csv_list_id filter
        $csvFilter = ($csv_list_id > 0) ? " AND csv_list_id = $csv_list_id" : "";
        $actualPendingCheck = $conn_heavy->query("
            SELECT COUNT(*) as actual_pending 
            FROM mail_blaster 
            WHERE campaign_id = $campaign_id 
            AND status IN ('pending', 'failed') 
            AND attempt_count < 5
            $csvFilter
        ");
    }
    $actual_pending = ($actualPendingCheck && $actualPendingCheck->num_rows > 0)
        ? intval($actualPendingCheck->fetch_assoc()['actual_pending']) 
        : 0;
    
    if ($actual_pending != $pending_emails && $actual_pending > 0) {
        logCron("  MISMATCH DETECTED: campaign_status shows {$pending_emails} pending, but mail_blaster has {$actual_pending} pending");
        logCron("  Using actual count from mail_blaster: {$actual_pending}");
        $pending_emails = $actual_pending; // Use the accurate count
    }
    
    // CRITICAL: Skip if campaign is completed (no pending emails)
    if ($pending_emails <= 0 && $sent_emails > 0) {
        logCron("  Campaign #{$campaign_id} has NO pending emails - marking as completed");
        try {
            // Set short lock timeout to avoid blocking frontend
            $conn->query("SET SESSION innodb_lock_wait_timeout = 3");
            
            $conn->begin_transaction();
            $conn->query("SELECT campaign_id FROM campaign_status WHERE campaign_id = $campaign_id FOR UPDATE");
            $conn->query("UPDATE campaign_status SET status = 'completed', end_time = NOW(), process_pid = NULL WHERE campaign_id = $campaign_id");
            $conn->commit();
            logCron("  ✓ Campaign #{$campaign_id} marked as completed");
        } catch (Exception $e) {
            $conn->rollback();
            logCron("  ✗ Failed to mark campaign as completed: " . $e->getMessage());
        }
        continue;
    }
    
    // Verify user has active SMTP accounts (Server 2)
    if ($user_id > 0) {
        $smtpCheck = $conn_heavy->query("
            SELECT COUNT(*) as smtp_count 
            FROM smtp_accounts sa
            JOIN smtp_servers ss ON sa.smtp_server_id = ss.id
            WHERE sa.user_id = $user_id 
            AND sa.is_active = 1 
            AND ss.is_active = 1
        ");
        
        if ($smtpCheck && $smtpCheck->num_rows > 0) {
            $smtpData = $smtpCheck->fetch_assoc();
            $smtp_count = $smtpData['smtp_count'];
            
            if ($smtp_count == 0) {
                logCron("  WARNING: User #{$user_id} has NO active SMTP accounts!");
                logCron("  Skipping campaign #{$campaign_id} - cannot send without SMTP");
                continue;
            } else {
                logCron("  User has {$smtp_count} active SMTP account(s) available");
            }
        }
    } else {
        logCron("  WARNING: Campaign has no user_id assigned!");
    }
    
    // Check if emails are in mail_blaster (Server 2)
    logCron("  Checking mail_blaster for emails...");
    $mbCountRes = $conn_heavy->query("SELECT COUNT(*) as cnt FROM mail_blaster WHERE campaign_id = $campaign_id");
    $mbCount = $mbCountRes ? (int)$mbCountRes->fetch_assoc()['cnt'] : 0;
    
    if ($mbCount === 0) {
        logCron("  ✗ No emails found in mail_blaster - campaign may need to be restarted");
        continue;
    }
    
    logCron("  ✓ Found $mbCount emails in mail_blaster ready to send");
    
    // Ensure SERVER 1 connection alive before status check
    ensureConnectionAlive($conn, 'SERVER 1');
    
    // Double-check campaign status with row lock to prevent race conditions with frontend
    try {
        // Set short lock timeout to avoid blocking frontend queries
        $conn->query("SET SESSION innodb_lock_wait_timeout = 3");
        
        $conn->begin_transaction();
        $statusCheck = $conn->query("SELECT status FROM campaign_status WHERE campaign_id = $campaign_id FOR UPDATE");
        if ($statusCheck && $statusCheck->num_rows > 0) {
            $currentStatus = $statusCheck->fetch_assoc()['status'];
            if ($currentStatus !== 'running') {
                $conn->commit();
                logCron("  Status changed to '$currentStatus', skipping orchestrator launch");
                continue;
            }
        }
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        logCron("  Error checking campaign status: " . $e->getMessage());
        continue;
    }
    
    $pid_file = $pid_dir . "/email_blaster_{$campaign_id}.pid";
    
    // Check if parallel blaster is already running for this campaign
    $orchestrator_running = false;
    
    // Check PID file
    if (file_exists($pid_file)) {
        $existing_pid = intval(@file_get_contents($pid_file));
        
        if (isPidRunning($existing_pid)) {
            logCron("  Parallel blaster already running (PID: $existing_pid from file)");
            $orchestrator_running = true;
        } else {
            // Process died - remove stale PID file
            @unlink($pid_file);
            logCron("  Removed stale PID file");
        }
    }
    
    // Also check database process_pid if available
    if (!$orchestrator_running && $process_pid > 0) {
        if (isPidRunning($process_pid)) {
            logCron("  Parallel blaster already running (PID: $process_pid from DB)");
            $orchestrator_running = true;
        } else {
            logCron("  Database PID $process_pid is stale - will restart orchestrator");
        }
    }
    
    // Skip if already running
    if ($orchestrator_running) {
        // SAFETY CHECK: Even if running, verify it's making progress
        // Check if process has been stuck (not updating delivery_time in last 5 minutes)
        // Apply proper filtering based on campaign source
        if ($import_batch_id) {
            // CRITICAL FIX: Cannot JOIN across servers - query in two steps
            
            // Step 1: Get active emails from imported_recipients (SERVER 1)
            $batch_escaped = $conn->real_escape_string($import_batch_id);
            $emailsResult = $conn->query("
                SELECT Emails 
                FROM imported_recipients 
                WHERE import_batch_id = '$batch_escaped' 
                AND is_active = 1
                LIMIT 1000
            ");
            
            if ($emailsResult && $emailsResult->num_rows > 0) {
                $active_emails = [];
                while ($row = $emailsResult->fetch_assoc()) {
                    $active_emails[] = $conn_heavy->real_escape_string($row['Emails']);
                }
                
                // Step 2: Check mail_blaster for recent activity
                if (count($active_emails) > 0) {
                    $emails_list = "'" . implode("','", $active_emails) . "'";
                    $stuckCheck = $conn_heavy->query("
                        SELECT MAX(delivery_time) as last_delivery 
                        FROM mail_blaster 
                        WHERE campaign_id = $campaign_id 
                        AND delivery_time > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                        AND to_mail IN ($emails_list)
                    ");
                } else {
                    $stuckCheck = null;
                }
            } else {
                $stuckCheck = null;
            }
        } else {
            // For CSV campaigns (or all emails)
            $csvFilter = ($csv_list_id > 0) ? " AND csv_list_id = $csv_list_id" : "";
            $stuckCheck = $conn_heavy->query("
                SELECT MAX(delivery_time) as last_delivery 
                FROM mail_blaster 
                WHERE campaign_id = $campaign_id 
                AND delivery_time > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                $csvFilter
            ");
        }
        
        if ($stuckCheck && $stuckCheck->num_rows > 0) {
            $lastDelivery = $stuckCheck->fetch_assoc()['last_delivery'];
            if ($lastDelivery === null && $pending_emails > 0) {
                logCron("  WARNING: Process running but no activity in last 5 minutes. Will restart if this persists.");
                // Don't restart yet, give it more time
            }
        }
        continue;
    }
    
    // Launch parallel email blaster in background with LOW PRIORITY
    // Using 'nice' to ensure it doesn't interfere with web server or other apps
    // Output redirected to /dev/null (logging disabled)
    // Process runs detached with nohup to survive parent termination
    // ❌ DISABLED - Log files disabled
    // $orchestrator_log = __DIR__ . "/logs/orchestrator_campaign_{$campaign_id}.log";
    
    logCron("  === LAUNCHING ORCHESTRATOR ===");
    logCron("  PHP Binary: $php_bin");
    logCron("  Script: $parallel_script");
    logCron("  Campaign ID: $campaign_id");
    // logCron("  Log file: $orchestrator_log"); // ❌ DISABLED - Log files disabled
    
    // Verify PHP binary is executable
    if (!file_exists($php_bin)) {
        logCron("  ✗ ERROR: PHP binary not found: $php_bin");
        continue;
    }
    if (!is_executable($php_bin)) {
        logCron("  ✗ ERROR: PHP binary not executable: $php_bin");
        continue;
    }
    logCron("  ✓ PHP binary verified: $php_bin");
    
    // Verify script exists and is readable
    if (!file_exists($parallel_script)) {
        logCron("  ✗ ERROR: Orchestrator script not found: $parallel_script");
        continue;
    }
    if (!is_readable($parallel_script)) {
        logCron("  ✗ ERROR: Orchestrator script not readable: $parallel_script");
        continue;
    }
    logCron("  ✓ Orchestrator script verified: $parallel_script");
    
    // Construct command
    $cmd = sprintf(
        'nice -n 19 nohup %s %s %d > /dev/null 2>&1 < /dev/null & echo $!',
        escapeshellarg($php_bin),
        escapeshellarg($parallel_script),
        $campaign_id
    );
    logCron("  Command: $cmd");
    
    // Execute command
    logCron("  Executing command...");
    exec($cmd, $output, $ret);
    $new_pid = isset($output[0]) ? intval($output[0]) : 0;
    logCron("  Exec return code: $ret");
    logCron("  Captured PID: $new_pid");
    
    if ($new_pid > 0) {
        // Verify process actually started
        usleep(100000); // Wait 100ms for process to initialize
        
        if (isPidRunning($new_pid)) {
            logCron("  ✓ Process verified running (PID: $new_pid)");
            
            // Save PID to file
            file_put_contents($pid_file, $new_pid);
            logCron("  ✓ PID file created: $pid_file");
            
            // Wait a bit for process to initialize
            usleep(200000); // Wait 200ms
            // ❌ DISABLED - Log files disabled
            // if (file_exists($orchestrator_log)) {
            //     $log_size = filesize($orchestrator_log);
            //     logCron("  ✓ Log file created: $orchestrator_log ({$log_size} bytes)");
            //     
            //     // Show first few lines of log
            //     if ($log_size > 0) {
            //         $log_content = file_get_contents($orchestrator_log);
            //         $first_lines = implode("\n", array_slice(explode("\n", $log_content), 0, 5));
            //         logCron("  Log preview:\n" . $first_lines);
            //     }
            // } else {
            //     logCron("  ⚠ Warning: Log file not created yet (may take a moment)");
            // }
            
            // Ensure SERVER 1 connection alive before updating campaign_status
            ensureConnectionAlive($conn, 'SERVER 1');
            
            // Update campaign_status with process PID - with row-level locking and SHORT timeout
            try {
                // Set short lock timeout to avoid blocking frontend queries
                $conn->query("SET SESSION innodb_lock_wait_timeout = 3");
                
                $conn->begin_transaction();
                $conn->query("SELECT campaign_id FROM campaign_status WHERE campaign_id = $campaign_id FOR UPDATE");
                $conn->query("
                    UPDATE campaign_status 
                    SET process_pid = $new_pid, 
                        start_time = COALESCE(start_time, NOW())
                    WHERE campaign_id = $campaign_id
                ");
                $conn->commit();
                logCron("  ✓ Database updated with PID: $new_pid");
                logCron("  ✓ Campaign will use user #{$user_id}'s SMTP accounts only");
                logCron("  === ORCHESTRATOR LAUNCHED SUCCESSFULLY ===");
            } catch (Exception $e) {
                $conn->rollback();
                logCron("  ✗ Failed to update database: " . $e->getMessage());
            }
        } else {
            logCron("  ✗ ERROR: Process PID $new_pid is NOT running (crashed immediately?)");
            // logCron("  Check orchestrator log for errors: $orchestrator_log"); // ❌ DISABLED - Log files disabled
            
            // Try to read error from log - ❌ DISABLED
            // if (file_exists($orchestrator_log)) {
            //     $error_log = file_get_contents($orchestrator_log);
            //     if (!empty($error_log)) {
            //         logCron("  Error log content:\n" . substr($error_log, 0, 500));
            //     }
            // }
        }
    } else {
        logCron("  ✗ FAILED: Could not capture PID from command execution");
        logCron("  This usually means:");
        logCron("    - Shell command failed to execute");
        logCron("    - PHP binary path is incorrect");
        logCron("    - Permissions issue");
        
        // Try direct execution test
        $test_cmd = sprintf('%s -v 2>&1', escapeshellarg($php_bin));
        exec($test_cmd, $test_output, $test_ret);
        logCron("  PHP version test: " . ($test_ret === 0 ? "PASSED" : "FAILED (code: $test_ret)"));
        if (!empty($test_output)) {
            logCron("  PHP version: " . $test_output[0]);
        }
    }
    
    unset($output);
}

$conn->close();

// Release lock before exiting
$lock->release();

logCron("=== CRON JOB COMPLETED ===\n");
exit(0);
