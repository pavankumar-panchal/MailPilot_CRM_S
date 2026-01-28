<?php
/**
 * PARALLEL CRON JOB: Process All Pending Email Validations
 * Run every minute: * * * * * /usr/bin/php /path/to/cron_validate_emails_parallel.php
 */
date_default_timezone_set('Asia/Kolkata');

if (php_sapi_name() !== 'cli') {
    die("CLI only\n");
}

require_once __DIR__ . '/backend/config/db.php';

// Worker configuration - Dynamic parallel processing
if (!defined('MAX_WORKERS')) define('MAX_WORKERS', 30); 
if (!defined('MIN_EMAILS_PER_WORKER')) define('MIN_EMAILS_PER_WORKER', 3); 
if (!defined('OPTIMAL_EMAILS_PER_WORKER')) define('OPTIMAL_EMAILS_PER_WORKER', 10);
if (!defined('PHP_BINARY')) define('PHP_BINARY', '/usr/bin/php');
if (!defined('WORKER_SCRIPT')) define('WORKER_SCRIPT', __DIR__ . '/backend/worker/smtp_worker_parallel.php');

// SMTP tuning constants (matching single_email.php)
if (!defined('SIP_SMTP_SOCKET_TIMEOUT')) define('SIP_SMTP_SOCKET_TIMEOUT', 8);
if (!defined('SIP_SMTP_MAX_MX')) define('SIP_SMTP_MAX_MX', 4);
if (!defined('SIP_SMTP_MAX_IPS_PER_MX')) define('SIP_SMTP_MAX_IPS_PER_MX', 3);
if (!defined('SIP_SMTP_CATCHALL_PROBES')) define('SIP_SMTP_CATCHALL_PROBES', 3);
if (!defined('SIP_SMTP_BACKOFF_CONNECT_MS')) define('SIP_SMTP_BACKOFF_CONNECT_MS', 120);
// Enhanced retryable codes for 100% accuracy - includes protocol errors, rate limits, and temporary failures
if (!defined('SIP_SMTP_RETRYABLE_CODES')) define('SIP_SMTP_RETRYABLE_CODES', serialize(['421','450','451','452','447','449','550','554'])); 
if (!defined('SIP_SMTP_DEFERRAL_DELAY_MIN')) define('SIP_SMTP_DEFERRAL_DELAY_MIN', 8);
if (!defined('SIP_MAX_TOTAL_SMTP_TIME')) define('SIP_MAX_TOTAL_SMTP_TIME', 28);
if (!defined('SIP_DISABLE_CATCHALL_DETECTION')) define('SIP_DISABLE_CATCHALL_DETECTION', false);

function log_msg($msg) {
    echo "[" . date('Y-m-d H:i:s') . "] $msg\n";
}

// Worker script already exists and has been updated with parallel validation logic

// Lock to prevent concurrent runs
$lockFile = __DIR__ . '/backend/storage/cron.lock';
$lock = fopen($lockFile, 'c');
if (!flock($lock, LOCK_EX | LOCK_NB)) {
    log_msg("Already running. Exit.");
    exit(0);
}

log_msg("=== CRON START (SIMPLE MODE - PROCESS ALL UNVERIFIED EMAILS) ===");
log_msg("Database connected successfully");

// Check for ANY batch processing results from previous runs
$batchDirs = glob('/tmp/bulk_workers_*/');
if (!empty($batchDirs)) {
    log_msg("Found " . count($batchDirs) . " batch directories to aggregate");
    
    foreach ($batchDirs as $workerDir) {
        $batchId = basename($workerDir);
        log_msg("Processing batch: $batchId");
        
        $resultFiles = glob($workerDir . 'worker_*.json');
        $expectedFile = $workerDir . 'worker_count.txt';
        $expectedWorkers = file_exists($expectedFile) ? intval(file_get_contents($expectedFile)) : 1;
        
        if (count($resultFiles) >= $expectedWorkers && count($resultFiles) > 0) {
            log_msg("All workers completed for batch $batchId! Aggregating results (enterprise-grade)...");
            
            $validEmails = [];
            $invalidEmails = [];
            $retryableEmails = [];
            $emailDetails = [];
            
            foreach ($resultFiles as $file) {
                $data = json_decode(file_get_contents($file), true);
                if ($data) {
                    $validEmails = array_merge($validEmails, $data['valid'] ?? []);
                    $invalidEmails = array_merge($invalidEmails, $data['invalid'] ?? []);
                    $retryableEmails = array_merge($retryableEmails, $data['retryable'] ?? []);
                    if (isset($data['details'])) {
                        $emailDetails = array_merge($emailDetails, $data['details']);
                    }
                }
            }
            
            $validCount = count($validEmails);
            $invalidCount = count($invalidEmails);
            $retryableCount = count($retryableEmails);
            log_msg("Aggregated: $validCount valid, $invalidCount invalid, $retryableCount retryable");
            
            // Note: Workers already updated DB immediately with all fields (catch_all, smtp_meta, etc.)
            // This aggregation is just for verification and csv_list updates
            log_msg("Skipping email table updates (already done by workers with all metadata)");
            
            // Update csv_list counts (workers already updated emails table)
            $csvListStats = [];
            foreach (array_merge($validEmails, $invalidEmails, $retryableEmails) as $email) {
                $stmt = $conn->prepare("SELECT csv_list_id FROM emails WHERE raw_emailid = ? LIMIT 1");
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                $stmt->close();
                
                if ($row) {
                    $listId = $row['csv_list_id'];
                    if (!isset($csvListStats[$listId])) {
                        $csvListStats[$listId] = ['valid' => 0, 'invalid' => 0];
                    }
                    if (in_array($email, $validEmails)) {
                        $csvListStats[$listId]['valid']++;
                    } else {
                        $csvListStats[$listId]['invalid']++;
                    }
                }
            }
            
            // Update csv_list counts by RECALCULATING from emails table (prevents double-counting)
            foreach ($csvListStats as $listId => $stats) {
                // Get actual counts from emails table
                $stmt = $conn->prepare("
                    SELECT 
                        COUNT(*) as processed,
                        SUM(CASE WHEN domain_status = 1 AND domain_processed = 1 THEN 1 ELSE 0 END) as valid,
                        SUM(CASE WHEN domain_status = 0 AND domain_processed = 1 THEN 1 ELSE 0 END) as invalid
                    FROM emails 
                    WHERE csv_list_id = ? AND domain_processed = 1
                ");
                $stmt->bind_param('i', $listId);
                $stmt->execute();
                $result = $stmt->get_result();
                $counts = $result->fetch_assoc();
                $stmt->close();
                
                // Update with ACTUAL counts (not incremental)
                $conn->query("
                    UPDATE csv_list 
                    SET valid_count = {$counts['valid']}, 
                        invalid_count = {$counts['invalid']},
                        processed_count = {$counts['processed']},
                        updated_at = NOW() 
                    WHERE id = $listId
                ");
                log_msg("Updated csv_list_id $listId: {$counts['valid']} valid, {$counts['invalid']} invalid, {$counts['processed']} processed (recalculated)");
                
                // Check if all emails for this list are processed and mark as completed
                $stmt = $conn->prepare("
                    SELECT total_emails, processed_count 
                    FROM csv_list 
                    WHERE id = ?
                ");
                $stmt->bind_param('i', $listId);
                $stmt->execute();
                $result = $stmt->get_result();
                $listData = $result->fetch_assoc();
                $stmt->close();
                
                if ($listData && $listData['processed_count'] >= $listData['total_emails']) {
                    $conn->query("
                        UPDATE csv_list 
                        SET status = 'completed', 
                            updated_at = NOW() 
                        WHERE id = $listId
                    ");
                    log_msg("Marked csv_list_id $listId as COMPLETED (all {$listData['total_emails']} emails processed)");
                }
            }
            
            // Cleanup
            foreach (glob($workerDir . '*') as $file) {
                unlink($file);
            }
            @rmdir($workerDir);
            log_msg("Batch $batchId aggregated and cleaned up");
        }
    }
}

// Fetch emails: unprocessed (domain_processed = 0) OR retryable past their retry time
// Note: domain_processed = 1 is kept for ALL processed emails to ensure downloads work correctly
log_msg("Fetching unprocessed emails and retryable emails past retry time...");
$stmt = $conn->query("
    SELECT raw_emailid 
    FROM emails 
    WHERE domain_processed = 0 
       OR (validation_status = 'retryable' AND next_retry_at IS NOT NULL AND next_retry_at <= NOW())
    ORDER BY id ASC
");

if (!$stmt) {
    log_msg("ERROR: Failed to fetch emails: " . $conn->error);
    log_msg("=== CRON END ===");
    flock($lock, LOCK_UN);
    exit(1);
}

$emails = [];
while ($row = $stmt->fetch_assoc()) {
    $emails[] = $row['raw_emailid'];
}
$stmt->close();

if (empty($emails)) {
    log_msg("No emails to process - all emails verified or waiting for retry!");
    log_msg("=== CRON END ===");
    flock($lock, LOCK_UN);
    exit(0);
}

log_msg("Found " . count($emails) . " emails to verify (includes retries)");

// Mark all related csv_lists as 'processing' if they have any emails being processed now
$affectedLists = $conn->query("
    SELECT DISTINCT csv_list_id 
    FROM emails 
    WHERE raw_emailid IN ('" . implode("','", array_map(function($e) use ($conn) { return $conn->real_escape_string($e); }, $emails)) . "')
");
$listIds = [];
if ($affectedLists) {
    while ($row = $affectedLists->fetch_assoc()) {
        if ($row['csv_list_id']) {
            $listIds[] = intval($row['csv_list_id']);
        }
    }
}
if (!empty($listIds)) {
    $conn->query("
        UPDATE csv_list 
        SET status = 'processing', updated_at = NOW() 
        WHERE id IN (" . implode(',', $listIds) . ") AND status != 'completed'
    ");
    log_msg("Marked " . count($listIds) . " csv_list(s) as 'processing'");
} else {
    log_msg("No csv_lists to update status");
}

// Generate a unique batch ID
$batchProcessId = md5(uniqid('batch_', true));
log_msg("Batch ID: $batchProcessId");

// Create worker directory
$workerDir = '/tmp/bulk_workers_' . $batchProcessId . '/';
if (!is_dir($workerDir)) {
    mkdir($workerDir, 0755, true);
    log_msg("Created worker directory: $workerDir");
}

// Save emails to file
$emailsText = implode("\n", $emails);
$bytesWritten = file_put_contents($workerDir . 'emails.txt', $emailsText);
log_msg("Saved emails.txt: $bytesWritten bytes written");


// Calculate optimal number of workers based on email count
$totalEmails = count($emails);



if ($totalEmails <= 5) {
    // Very small batch: 1-2 workers
    $numWorkers = max(1, min(2, $totalEmails));
} elseif ($totalEmails <= 20) {
    // Small batch: 2-4 workers, ~5 emails per worker
    $numWorkers = max(2, min(4, ceil($totalEmails / 5)));
} elseif ($totalEmails <= 50) {
    // Medium batch: 4-8 workers, ~8 emails per worker
    $numWorkers = max(4, min(8, ceil($totalEmails / 8)));
} elseif ($totalEmails <= 200) {
    // Large batch: 8-15 workers, ~12 emails per worker
    $numWorkers = max(8, min(15, ceil($totalEmails / 12)));
} else {
    // Very large batch: 15-20 workers, ~15-20 emails per worker
    $numWorkers = max(15, min(MAX_WORKERS, ceil($totalEmails / 15)));
}

// Ensure we don't have more workers than emails
$numWorkers = min($numWorkers, $totalEmails);

// Ensure minimum emails per worker (avoid workers with 1 email)
if ($totalEmails / $numWorkers < MIN_EMAILS_PER_WORKER && $numWorkers > 1) {
    $numWorkers = max(1, ceil($totalEmails / MIN_EMAILS_PER_WORKER));
}

$emailsPerWorker = ceil($totalEmails / $numWorkers);

log_msg("DYNAMIC ALLOCATION: $totalEmails emails → $numWorkers workers (~$emailsPerWorker emails/worker)");
log_msg("Spawning $numWorkers workers (parallel execution)");

$activeProcesses = [];

for ($i = 1; $i <= $numWorkers; $i++) {
    $startIndex = ($i - 1) * $emailsPerWorker;
    $endIndex = min($i * $emailsPerWorker, count($emails));
    
    $cmd = sprintf(
        '%s %s %d %s %d %d',
        PHP_BINARY,
        WORKER_SCRIPT,
        $i,
        $batchProcessId,
        $startIndex,
        $endIndex
    );
    
    log_msg("Worker $i command: $cmd");
    
    // Spawn worker in background
    $descriptors = [
        0 => ['pipe', 'r'],  // stdin
        1 => ['pipe', 'w'],  // stdout
        2 => ['pipe', 'w']   // stderr
    ];
    
    $process = proc_open($cmd, $descriptors, $pipes);
    
    if (is_resource($process)) {
        fclose($pipes[0]);  // Close stdin immediately
        
        // Store process info for monitoring
        $activeProcesses[] = [
            'process' => $process,
            'pipes' => $pipes,
            'worker_id' => $i,
            'start_index' => $startIndex,
            'end_index' => $endIndex
        ];
        
        log_msg("Worker $i spawned successfully (processing emails $startIndex to $endIndex)");
    } else {
        log_msg("ERROR: Failed to spawn worker $i");
    }
}

// Save worker count
file_put_contents($workerDir . 'worker_count.txt', $numWorkers);
log_msg("Worker count saved: $numWorkers");

// Wait for all workers to complete (with timeout)
$maxWaitTime = 600; // 10 minutes
$startTime = time();
$allCompleted = false;

log_msg("Waiting for workers to complete (max wait: {$maxWaitTime}s)");

while ((time() - $startTime) < $maxWaitTime) {
    $completed = 0;
    
    foreach ($activeProcesses as $worker) {
        $status = proc_get_status($worker['process']);
        if (!$status['running']) {
            $completed++;
        }
    }
    
    if ($completed >= count($activeProcesses)) {
        $allCompleted = true;
        log_msg("All $numWorkers workers completed in " . (time() - $startTime) . " seconds");
        break;
    }
    
    sleep(1); // Check every second
}

if (!$allCompleted) {
    log_msg("WARNING: Timeout reached, some workers may still be running");
}

// Close all processes and collect output
foreach ($activeProcesses as $worker) {
    $stdout = stream_get_contents($worker['pipes'][1]);
    $stderr = stream_get_contents($worker['pipes'][2]);
    
    if ($stdout) {
        log_msg("Worker {$worker['worker_id']} STDOUT: " . trim($stdout));
    }
    if ($stderr) {
        log_msg("Worker {$worker['worker_id']} STDERR: " . trim($stderr));
    }
    
    fclose($worker['pipes'][1]);
    fclose($worker['pipes'][2]);
    proc_close($worker['process']);
}

log_msg("All workers terminated. Results will be aggregated in next cron run.");
log_msg("=== CRON END ===");

flock($lock, LOCK_UN);
exit(0);                                        