<?php
/**
 * Campaign Cron Job - Monitor and Auto-Restart
 * 
 * Add to crontab (runs every 2 minutes):
 * star-slash-2 * * * * /opt/plesk/php/8.1/bin/php /var/www/vhosts/payrollsoft.in/httpdocs/emailvalidation/backend/campaign_cron.php >> /var/www/vhosts/payrollsoft.in/httpdocs/emailvalidation/backend/logs/cron_output.log 2>&1
 * Replace "star-slash-2" with: asterisk followed by forward-slash followed by 2 (without spaces)
 * 
 * This script:
 * 1. Monitors running campaigns - ensures orchestrator is alive
 * 2. Restarts crashed orchestrators
 * 3. Auto-restarts campaigns with pending retries (optional)
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
set_time_limit(0);

// Require ProcessManager FIRST to prevent duplicate executions
require_once __DIR__ . '/includes/ProcessManager.php';

// Acquire lock - exit if already running
$lock = new ProcessManager('campaign_monitor', 300); // 5 minute timeout
if (!$lock->acquire()) {
    // Job already running, exit silently
    exit(0);
}

// Start logging immediately - DISABLED
// $log_file = __DIR__ . '/logs/campaign_cron.log'; // Commented - log disabled
// $log_dir = dirname($log_file);
// if (!is_dir($log_dir)) {
//     @mkdir($log_dir, 0777, true);
// }

function logCron($message) {
    // Log file disabled - function kept for compatibility
    // global $log_file;
    // $timestamp = date('Y-m-d H:i:s');
    // $log_msg = "[$timestamp] $message\n";
    // file_put_contents($log_file, $log_msg, FILE_APPEND | LOCK_EX);
    // echo $log_msg; // Keep echo for cron output if needed
}

logCron("=== CRON JOB START ===");
logCron("PHP Version: " . PHP_VERSION);
logCron("Working directory: " . getcwd());
logCron("Script path: " . __FILE__);

require_once __DIR__ . '/config/db.php';

if ($conn->connect_error) {
    logCron("ERROR: Database connection failed: " . $conn->connect_error);
    die("Database connection failed: " . $conn->connect_error . "\n");
}

logCron("Database connected successfully");

function isPidRunning($pid) {
    if ($pid <= 0) return false;
    return file_exists('/proc/' . intval($pid));
}

// Get all running campaigns (exclude paused/stopped)
logCron("Querying for running campaigns...");
$result = $conn->query("
    SELECT cs.campaign_id, cs.status, cm.description
    FROM campaign_status cs
    JOIN campaign_master cm ON cs.campaign_id = cm.campaign_id
    WHERE cs.status = 'running'
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

// Detect PHP binary (Plesk CLI or standard) - MUST use CLI not FPM
$php_bin = '/opt/plesk/php/8.1/bin/php';
if (!file_exists($php_bin)) {
    $php_bin = defined('PHP_BINARY') ? PHP_BINARY : 'php';
}
logCron("PHP binary: $php_bin");

// Resolve parallel script path robustly across deployments
function resolveParallelScript() {
    $candidates = [
        __DIR__ . '/includes/email_blast_parallel.php',
        __DIR__ . '/includes/email_blast_parallel.php', // same dir, explicit for clarity
        // Common alternate repo layouts
        '/opt/lampp/htdocs/verify_emails/MailPilot_CRM/backend/includes/email_blast_parallel.php',
        '/opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/backend/includes/email_blast_parallel.php',
    ];
    foreach ($candidates as $p) {
        if (is_string($p) && file_exists($p)) return $p;
    }
    return $candidates[0];
}

$parallel_script = resolveParallelScript();
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
    logCron("Processing campaign #{$campaign_id}: {$campaign['description']}");
    
    // Double-check campaign status (might have been paused/stopped since query)
    $statusCheck = $conn->query("SELECT status FROM campaign_status WHERE campaign_id = $campaign_id");
    if ($statusCheck && $statusCheck->num_rows > 0) {
        $currentStatus = $statusCheck->fetch_assoc()['status'];
        if ($currentStatus !== 'running') {
            logCron("Campaign #{$campaign_id} - Status changed to '$currentStatus', skipping orchestrator launch");
            continue;
        }
    }
    
    $pid_file = $pid_dir . "/email_blaster_{$campaign_id}.pid";
    
    // Check if parallel blaster is already running for this campaign
    if (file_exists($pid_file)) {
        $existing_pid = intval(@file_get_contents($pid_file));
        
        if (isPidRunning($existing_pid)) {
            logCron("Campaign #{$campaign_id} - Parallel blaster already running (PID: $existing_pid)");
            continue; // Already running, skip
        } else {
            // Process died - remove stale PID file
            @unlink($pid_file);
            logCron("Campaign #{$campaign_id} - Removed stale PID file, will restart orchestrator");
        }
    }
    
    // Launch parallel email blaster in background
    $cmd = sprintf(
        'nohup %s %s %d > /dev/null 2>&1 & echo $!',
        escapeshellarg($php_bin),
        escapeshellarg($parallel_script),
        $campaign_id
    );
    logCron("Launching: $cmd");
    
    exec($cmd, $output, $ret);
    $new_pid = isset($output[0]) ? intval($output[0]) : 0;
    
    if ($new_pid > 0) {
        file_put_contents($pid_file, $new_pid);
        logCron("✓ Campaign #{$campaign_id} - Started round-robin orchestrator (PID: $new_pid)");
    } else {
        logCron("✗ Campaign #{$campaign_id} - Failed to start orchestrator");
    }
    
    unset($output);
}

$conn->close();

// Release lock before exiting
$lock->release();

logCron("=== CRON JOB COMPLETED ===\n");
exit(0);
