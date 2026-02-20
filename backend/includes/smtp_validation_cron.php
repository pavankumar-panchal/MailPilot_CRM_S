<?php
/**
 * High-Performance SMTP Validation Cron Script
 * Optimized for processing crores (tens of millions) of emails
 * Features: Chunked processing, connection pooling, error recovery, progress tracking
 */

date_default_timezone_set('Asia/Kolkata');

if (php_sapi_name() !== 'cli') {
    die("CLI only\n");
}

// Set memory and execution limits for large-scale processing
ini_set('memory_limit', '512M');
ini_set('max_execution_time', '0');
set_time_limit(0);

// Enable garbage collection for memory efficiency
gc_enable();

require_once __DIR__ . '/../config/db.php';

// ============================================================
// WORKER ID CONFIGURATION - EDIT THIS VALUE FOR EACH SERVER
// ============================================================
$CONFIGURED_WORKER_ID = 1; // ← EDIT THIS VALUE FOR EACH SERVER
// ============================================================

// Worker ID Configuration
if (!defined('WORKER_ID')) {
    if (isset($CONFIGURED_WORKER_ID) && is_numeric($CONFIGURED_WORKER_ID)) {
        define('WORKER_ID', intval($CONFIGURED_WORKER_ID));
    }
    elseif (isset($argv[1]) && is_numeric($argv[1])) {
        define('WORKER_ID', intval($argv[1]));
    } 
    elseif (getenv('WORKER_ID') !== false) {
        define('WORKER_ID', intval(getenv('WORKER_ID')));
    } 
    else {
        define('WORKER_ID', 1);
    }
}

// ============================================================
// ENHANCED CONFIGURATION FOR HIGH-SCALE PROCESSING
// ============================================================
if (!defined('MAX_CONCURRENT_USERS')) define('MAX_CONCURRENT_USERS', 3);
if (!defined('TOTAL_WORKERS_POOL')) define('TOTAL_WORKERS_POOL', 25);
if (!defined('MIN_WORKERS_PER_USER')) define('MIN_WORKERS_PER_USER', 5);

// Chunked processing configuration
if (!defined('CHUNK_SIZE')) define('CHUNK_SIZE', 10000); // Process 10K emails at a time
if (!defined('MAX_MEMORY_THRESHOLD')) define('MAX_MEMORY_THRESHOLD', 400 * 1024 * 1024); // 400MB

// Logging configuration
if (!defined('LOG_DIR')) define('LOG_DIR', __DIR__ . '/../logs/');
if (!defined('ENABLE_DETAILED_LOGGING')) define('ENABLE_DETAILED_LOGGING', false);
if (!defined('PROGRESS_LOG_INTERVAL')) define('PROGRESS_LOG_INTERVAL', 1000); // Log every 1000 emails

// Worker configuration - optimized for large datasets
if (!defined('MIN_EMAILS_PER_WORKER')) define('MIN_EMAILS_PER_WORKER', 100); 
if (!defined('OPTIMAL_EMAILS_PER_WORKER')) define('OPTIMAL_EMAILS_PER_WORKER', 1000);
if (!defined('MAX_EMAILS_PER_WORKER')) define('MAX_EMAILS_PER_WORKER', 5000);
if (!defined('PHP_BINARY')) define('PHP_BINARY', '/usr/bin/php');
if (!defined('WORKER_SCRIPT')) define('WORKER_SCRIPT', __DIR__ . '/../worker/smtp_worker_parallel.php');

// Database configuration for high concurrency
if (!defined('DB_QUERY_TIMEOUT')) define('DB_QUERY_TIMEOUT', 30);
if (!defined('DB_MAX_RETRIES')) define('DB_MAX_RETRIES', 3);
if (!defined('DB_RETRY_DELAY_MS')) define('DB_RETRY_DELAY_MS', 100);

// SMTP tuning constants
if (!defined('SIP_SMTP_SOCKET_TIMEOUT')) define('SIP_SMTP_SOCKET_TIMEOUT', 8);
if (!defined('SIP_SMTP_MAX_MX')) define('SIP_SMTP_MAX_MX', 4);
if (!defined('SIP_SMTP_MAX_IPS_PER_MX')) define('SIP_SMTP_MAX_IPS_PER_MX', 3);
if (!defined('SIP_SMTP_CATCHALL_PROBES')) define('SIP_SMTP_CATCHALL_PROBES', 3);
if (!defined('SIP_SMTP_BACKOFF_CONNECT_MS')) define('SIP_SMTP_BACKOFF_CONNECT_MS', 120);
if (!defined('SIP_SMTP_RETRYABLE_CODES')) define('SIP_SMTP_RETRYABLE_CODES', serialize(['421','450','451','452','447','449','550','554'])); 
if (!defined('SIP_SMTP_DEFERRAL_DELAY_MIN')) define('SIP_SMTP_DEFERRAL_DELAY_MIN', 8);
if (!defined('SIP_MAX_TOTAL_SMTP_TIME')) define('SIP_MAX_TOTAL_SMTP_TIME', 28);
if (!defined('SIP_DISABLE_CATCHALL_DETECTION')) define('SIP_DISABLE_CATCHALL_DETECTION', false);

// ============================================================
// ENHANCED LOGGING SYSTEM - Buffered writes for performance
// ============================================================
$GLOBAL_LOG_FILE = null;
$USER_LOG_FILES = [];
$LOG_BUFFER = [];
$LOG_BUFFER_SIZE = 100;

function init_logging() {
    global $GLOBAL_LOG_FILE;
    
    if (!is_dir(LOG_DIR)) {
        mkdir(LOG_DIR, 0755, true);
    }
    
    $logFileName = 'smtp_validation_cron_' . date('Y-m-d') . '.log';
    $GLOBAL_LOG_FILE = LOG_DIR . $logFileName;
}

function flush_log_buffer() {
    global $LOG_BUFFER, $GLOBAL_LOG_FILE;
    
    if (!empty($LOG_BUFFER) && $GLOBAL_LOG_FILE && ENABLE_DETAILED_LOGGING) {
        file_put_contents($GLOBAL_LOG_FILE, implode('', $LOG_BUFFER), FILE_APPEND | LOCK_EX);
        $LOG_BUFFER = [];
    }
}

function log_msg($msg, $userId = null) {
    global $GLOBAL_LOG_FILE, $USER_LOG_FILES, $LOG_BUFFER, $LOG_BUFFER_SIZE;
    
    $timestamp = date('Y-m-d H:i:s');
    $memUsage = round(memory_get_usage() / 1024 / 1024, 2);
    $formattedMsg = "[$timestamp][MEM:{$memUsage}MB] $msg\n";
    
    echo $formattedMsg;
    
    if (ENABLE_DETAILED_LOGGING) {
        $LOG_BUFFER[] = $formattedMsg;
        
        if (count($LOG_BUFFER) >= $LOG_BUFFER_SIZE) {
            flush_log_buffer();
        }
    }
    
    if ($userId !== null && ENABLE_DETAILED_LOGGING) {
        if (!isset($USER_LOG_FILES[$userId])) {
            $userLogFile = LOG_DIR . 'user_' . $userId . '_' . date('Y-m-d') . '.log';
            $USER_LOG_FILES[$userId] = $userLogFile;
        }
        file_put_contents($USER_LOG_FILES[$userId], $formattedMsg, FILE_APPEND | LOCK_EX);
    }
}

function log_user($userId, $msg) {
    log_msg("[User $userId] $msg", $userId);
}

function log_worker_spawn($userId, $workerId, $msg) {
    log_msg("[User $userId | Worker $workerId] $msg", $userId);
}

function log_progress($current, $total, $label = "Progress") {
    $percentage = $total > 0 ? round(($current / $total) * 100, 2) : 0;
    log_msg("$label: $current/$total ($percentage%)");
}

// ============================================================
// DATABASE HELPER FUNCTIONS - Enhanced with retry logic
// ============================================================
function db_query_with_retry($conn, $query, $maxRetries = DB_MAX_RETRIES) {
    $attempt = 0;
    while ($attempt < $maxRetries) {
        $result = $conn->query($query);
        
        if ($result !== false) {
            return $result;
        }
        
        // Check for recoverable errors
        $errno = $conn->errno;
        if ($errno == 1213 || $errno == 1205 || $errno == 2006 || $errno == 2013) {
            $attempt++;
            log_msg("DB query failed (attempt $attempt/$maxRetries): " . $conn->error);
            
            if ($attempt < $maxRetries) {
                usleep(DB_RETRY_DELAY_MS * 1000 * $attempt); // Exponential backoff
                
                // Reconnect if connection lost
                if ($errno == 2006 || $errno == 2013) {
                    @$conn->close();
                    global $servername, $username, $password, $dbname;
                    $conn = new mysqli($servername, $username, $password, $dbname);
                    if ($conn->connect_error) {
                        log_msg("DB reconnection failed: " . $conn->connect_error);
                        return false;
                    }
                }
            }
        } else {
            log_msg("DB query failed (non-recoverable): " . $conn->error);
            return false;
        }
    }
    
    return false;
}

function db_execute_stmt($stmt, $maxRetries = DB_MAX_RETRIES) {
    $attempt = 0;
    while ($attempt < $maxRetries) {
        if ($stmt->execute()) {
            return true;
        }
        
        $errno = $stmt->errno;
        if ($errno == 1213 || $errno == 1205) {
            $attempt++;
            usleep(DB_RETRY_DELAY_MS * 1000 * $attempt);
        } else {
            return false;
        }
    }
    return false;
}

// Memory management helper
function check_memory_usage() {
    $memUsage = memory_get_usage();
    if ($memUsage > MAX_MEMORY_THRESHOLD) {
        gc_collect_cycles();
        log_msg("Memory threshold reached. Garbage collection executed.");
    }
    return $memUsage;
}

// Signal handling for graceful shutdown
$SHUTDOWN_REQUESTED = false;
pcntl_async_signals(true);
pcntl_signal(SIGTERM, function() use (&$SHUTDOWN_REQUESTED) {
    global $SHUTDOWN_REQUESTED;
    $SHUTDOWN_REQUESTED = true;
    log_msg("SIGTERM received. Graceful shutdown initiated...");
});
pcntl_signal(SIGINT, function() use (&$SHUTDOWN_REQUESTED) {
    global $SHUTDOWN_REQUESTED;
    $SHUTDOWN_REQUESTED = true;
    log_msg("SIGINT received. Graceful shutdown initiated...");
});

// Initialize logging system
init_logging();

// Lock to prevent concurrent runs
$lockFile = __DIR__ . '/../storage/cron.lock';
if (!is_dir(dirname($lockFile))) {
    mkdir(dirname($lockFile), 0755, true);
}
$lock = fopen($lockFile, 'c');
if (!flock($lock, LOCK_EX | LOCK_NB)) {
    log_msg("Already running. Exit.");
    exit(0);
}

// Enhanced shutdown function with cleanup
register_shutdown_function(function() use (&$conn, $lock) {
    flush_log_buffer();
    
    if (isset($conn) && $conn instanceof mysqli) {
        @$conn->close();
    }
    if (is_resource($lock)) {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
    
    // Final memory report
    $peakMemory = round(memory_get_peak_usage() / 1024 / 1024, 2);
    log_msg("Peak memory usage: {$peakMemory}MB");
});

log_msg("=== HIGH-PERFORMANCE SMTP VALIDATION CRON START ===");
log_msg("Server WORKER_ID: " . WORKER_ID);
log_msg("Database connected | Max concurrent users: " . MAX_CONCURRENT_USERS);
log_msg("Total workers pool: " . TOTAL_WORKERS_POOL . " | Chunk size: " . CHUNK_SIZE);

// ============================================================
// STEP 1: DETECT ACTIVE USERS (Optimized query)
// ============================================================
log_msg("Detecting active users with pending emails...");

$activeUsersQuery = db_query_with_retry($conn, "
    SELECT 
        COALESCE(e.user_id, cl.user_id) as user_id,
        COUNT(*) as pending_count,
        MIN(e.id) as first_email_id
    FROM emails e
    LEFT JOIN csv_list cl ON e.csv_list_id = cl.id
    WHERE e.domain_processed = 0
      AND e.worker_id = " . WORKER_ID . "
    GROUP BY COALESCE(e.user_id, cl.user_id)
    HAVING user_id IS NOT NULL
    ORDER BY first_email_id ASC
    LIMIT " . MAX_CONCURRENT_USERS . "
");

if (!$activeUsersQuery) {
    log_msg("ERROR: Failed to detect active users: " . $conn->error);
    $conn->close();
    flock($lock, LOCK_UN);
    exit(1);
}

$activeUsers = [];
while ($row = $activeUsersQuery->fetch_assoc()) {
    $activeUsers[] = [
        'user_id' => intval($row['user_id']),
        'pending_count' => intval($row['pending_count'])
    ];
}
$activeUsersQuery->close();

$activeUserCount = count($activeUsers);

if ($activeUserCount === 0) {
    log_msg("No active users with pending emails. Exiting.");
    $conn->close();
    flock($lock, LOCK_UN);
    exit(0);
}

log_msg("Found $activeUserCount active user(s) with pending emails:");
foreach ($activeUsers as $user) {
    log_msg("  - User ID {$user['user_id']}: {$user['pending_count']} pending emails");
}

// ============================================================
// STEP 2: ALLOCATE WORKERS FAIRLY - Improved algorithm
// ============================================================
log_msg("Allocating workers among $activeUserCount active user(s)...");

$workersPerUser = [];
if ($activeUserCount === 1) {
    $workersPerUser[$activeUsers[0]['user_id']] = TOTAL_WORKERS_POOL;
    log_msg("Single user: allocating ALL " . TOTAL_WORKERS_POOL . " workers to User {$activeUsers[0]['user_id']}");
} else {
    // Weighted allocation based on pending count
    $totalPending = array_sum(array_column($activeUsers, 'pending_count'));
    $remainingWorkers = TOTAL_WORKERS_POOL;
    
    foreach ($activeUsers as $index => $user) {
        if ($index === count($activeUsers) - 1) {
            $workersPerUser[$user['user_id']] = max(MIN_WORKERS_PER_USER, $remainingWorkers);
        } else {
            $weight = $user['pending_count'] / $totalPending;
            $proportionalWorkers = max(
                MIN_WORKERS_PER_USER, 
                min($remainingWorkers - MIN_WORKERS_PER_USER, floor(TOTAL_WORKERS_POOL * $weight))
            );
            $workersPerUser[$user['user_id']] = $proportionalWorkers;
            $remainingWorkers -= $proportionalWorkers;
        }
        
        log_msg("User {$user['user_id']}: {$workersPerUser[$user['user_id']]} workers | {$user['pending_count']} emails");
    }
}

// ============================================================
// STEP 2.5: EFFICIENT BATCH RESULT AGGREGATION
// ============================================================
log_msg("Checking for previous batch results...");
$batchDirs = glob('/tmp/bulk_workers_*/');

if (!empty($batchDirs)) {
    log_msg("Found " . count($batchDirs) . " batch directories to process");
    
    foreach ($batchDirs as $workerDir) {
        if ($SHUTDOWN_REQUESTED) break;
        
        $batchId = basename($workerDir);
        $userIdFile = $workerDir . 'user_id.txt';
        $workerIdFile = $workerDir . 'worker_id.txt';
        
        $batchUserId = file_exists($userIdFile) ? intval(file_get_contents($userIdFile)) : 0;
        $batchWorkerId = file_exists($workerIdFile) ? intval(file_get_contents($workerIdFile)) : 0;
        
        // Clean up invalid batches
        if ($batchUserId === 0 && $batchWorkerId === 0) {
            cleanup_batch_dir($workerDir, $batchId, "invalid tracking data");
            continue;
        }
        
        // Skip batches from other workers
        if ($batchWorkerId > 0 && $batchWorkerId !== WORKER_ID) {
            continue;
        }
        
        log_msg("Processing batch: $batchId (User: $batchUserId)");
        
        $resultFiles = glob($workerDir . 'worker_*.json');
        $expectedFile = $workerDir . 'worker_count.txt';
        $expectedWorkers = file_exists($expectedFile) ? intval(file_get_contents($expectedFile)) : 1;
        
        if (count($resultFiles) >= $expectedWorkers && count($resultFiles) > 0) {
            log_msg("Aggregating $batchId: " . count($resultFiles) . " worker results");
            
            $validEmails = [];
            $invalidEmails = [];
            $retryableEmails = [];
            
            // Process results in chunks to manage memory
            foreach ($resultFiles as $file) {
                $data = json_decode(file_get_contents($file), true);
                if ($data) {
                    $validEmails = array_merge($validEmails, $data['valid'] ?? []);
                    $invalidEmails = array_merge($invalidEmails, $data['invalid'] ?? []);
                    $retryableEmails = array_merge($retryableEmails, $data['retryable'] ?? []);
                }
                
                // Free memory periodically
                if (count($validEmails) % 10000 == 0) {
                    check_memory_usage();
                }
            }
            
            log_msg("Totals: " . count($validEmails) . " valid | " . 
                    count($invalidEmails) . " invalid | " . 
                    count($retryableEmails) . " retryable");
            
            // Batch update csv_list counts efficiently
            update_csv_list_counts($conn, array_merge($validEmails, $invalidEmails, $retryableEmails));
            
            // Cleanup batch directory
            cleanup_batch_dir($workerDir, $batchId);
        } else {
            log_msg("Batch $batchId incomplete: " . count($resultFiles) . "/$expectedWorkers workers");
        }
    }
}

function cleanup_batch_dir($workerDir, $batchId, $reason = "processed") {
    foreach (glob($workerDir . '*') as $file) {
        @unlink($file);
    }
    @rmdir($workerDir);
    log_msg("Cleaned up batch $batchId ($reason)");
}

function update_csv_list_counts($conn, $allEmails) {
    if (empty($allEmails)) return;
    
    log_msg("Updating csv_list counts for " . count($allEmails) . " emails...");
    
    // Group emails by csv_list_id in chunks
    $chunkSize = 1000;
    $chunks = array_chunk($allEmails, $chunkSize);
    $csvListIds = [];
    
    foreach ($chunks as $chunk) {
        $emailsPlaceholder = implode(',', array_fill(0, count($chunk), '?'));
        $stmt = $conn->prepare("
            SELECT DISTINCT csv_list_id 
            FROM emails 
            WHERE raw_emailid IN ($emailsPlaceholder)
            AND csv_list_id IS NOT NULL
        ");
        
        if ($stmt) {
            $types = str_repeat('s', count($chunk));
            $stmt->bind_param($types, ...$chunk);
            
            if (db_execute_stmt($stmt)) {
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $csvListIds[] = intval($row['csv_list_id']);
                }
            }
            $stmt->close();
        }
    }
    
    $csvListIds = array_unique($csvListIds);
    
    // Recalculate counts for affected lists
    foreach ($csvListIds as $listId) {
        $stmt = $conn->prepare("
            SELECT 
                COUNT(*) as total_in_list,
                SUM(CASE WHEN domain_processed = 1 THEN 1 ELSE 0 END) as processed,
                SUM(CASE WHEN domain_status = 1 AND domain_processed = 1 THEN 1 ELSE 0 END) as valid,
                SUM(CASE WHEN domain_status = 0 AND domain_processed = 1 THEN 1 ELSE 0 END) as invalid
            FROM emails 
            WHERE csv_list_id = ?
        ");
        
        $stmt->bind_param('i', $listId);
        
        if (db_execute_stmt($stmt)) {
            $result = $stmt->get_result();
            $counts = $result->fetch_assoc();
            $stmt->close();
            
            // Update with calculated values
            $updateStmt = $conn->prepare("
                UPDATE csv_list 
                SET valid_count = ?, 
                    invalid_count = (total_emails - ?) + ?,
                    status = CASE WHEN ? >= ? AND ? > 0 THEN 'completed' ELSE status END
                WHERE id = ?
            ");
            
            $updateStmt->bind_param('iiiiiii', 
                $counts['valid'],
                $counts['total_in_list'],
                $counts['invalid'],
                $counts['processed'],
                $counts['total_in_list'],
                $counts['total_in_list'],
                $listId
            );
            
            db_execute_stmt($updateStmt);
            $updateStmt->close();
        }
    }
    
    log_msg("Updated " . count($csvListIds) . " csv_list records");
}

// ============================================================
// STEP 3: CHUNKED PROCESSING FOR EACH USER - Memory-efficient
// ============================================================
$allUserProcesses = [];

foreach ($activeUsers as $user) {
    if ($SHUTDOWN_REQUESTED) {
        log_msg("Shutdown requested. Stopping user processing.");
        break;
    }
    
    $userId = $user['user_id'];
    $allocatedWorkers = $workersPerUser[$userId];
    
    log_user($userId, "======= PROCESSING USER $userId (WORKER_ID=" . WORKER_ID . ") =======");
    log_user($userId, "Allocated workers: $allocatedWorkers | Pending emails: {$user['pending_count']}");
    
    // Get total count first (no data loading)
    $countQuery = db_query_with_retry($conn, "
        SELECT COUNT(*) as total
        FROM emails e
        LEFT JOIN csv_list cl ON e.csv_list_id = cl.id
        WHERE e.domain_processed = 0 
          AND e.worker_id = " . WORKER_ID . "
          AND COALESCE(e.user_id, cl.user_id) = $userId
    ");
    
    if (!$countQuery) {
        log_user($userId, "ERROR: Failed to count emails: " . $conn->error);
        continue;
    }
    
    $totalRow = $countQuery->fetch_assoc();
    $totalEmails = intval($totalRow['total']);
    $countQuery->close();
    
    if ($totalEmails === 0) {
        log_user($userId, "No emails to process. Skipping.");
        continue;
    }
    
    log_user($userId, "Total emails: $totalEmails (will process in chunks of " . CHUNK_SIZE . ")");
    
    // Mark csv_lists as 'running'
    $listIdsQuery = db_query_with_retry($conn, "
        SELECT DISTINCT e.csv_list_id 
        FROM emails e
        LEFT JOIN csv_list cl ON e.csv_list_id = cl.id
        WHERE e.worker_id = " . WORKER_ID . "
          AND e.domain_processed = 0 
          AND COALESCE(e.user_id, cl.user_id) = $userId
          AND e.csv_list_id IS NOT NULL
    ");
    
    $listIds = [];
    if ($listIdsQuery) {
        while ($row = $listIdsQuery->fetch_assoc()) {
            if ($row['csv_list_id']) {
                $listIds[] = intval($row['csv_list_id']);
            }
        }
        $listIdsQuery->close();
    }
    
    if (!empty($listIds)) {
        $listIdsStr = implode(',', $listIds);
        db_query_with_retry($conn, "
            UPDATE csv_list 
            SET status = 'running'
            WHERE id IN ($listIdsStr) AND status != 'completed'
        ");
        log_user($userId, "Marked " . count($listIds) . " csv_list(s) as 'running'");
    }
    
    // Generate unique batch ID
    $batchProcessId = md5(uniqid("user_{$userId}_batch_", true));
    $workerDir = '/tmp/bulk_workers_' . $batchProcessId . '/';
    
    if (!is_dir($workerDir)) {
        mkdir($workerDir, 0755, true);
    }
    
    file_put_contents($workerDir . 'user_id.txt', $userId);
    file_put_contents($workerDir . 'worker_id.txt', WORKER_ID);
    
    // CHUNKED PROCESSING: Stream emails in chunks instead of loading all at once
    $emailFile = $workerDir . 'emails.txt';
    $emailFileHandle = fopen($emailFile, 'w');
    
    $offset = 0;
    $processedCount = 0;
    $chunkNumber = 1;
    
    log_user($userId, "Starting chunked email retrieval...");
    
    while ($offset < $totalEmails) {
        if ($SHUTDOWN_REQUESTED) {
            log_msg("Shutdown requested during chunk processing.");
            break;
        }
        
        $chunkSize = min(CHUNK_SIZE, $totalEmails - $offset);
        
        // Fetch chunk with LIMIT/OFFSET
        $stmt = $conn->prepare("
            SELECT e.raw_emailid 
            FROM emails e
            LEFT JOIN csv_list cl ON e.csv_list_id = cl.id
            WHERE e.domain_processed = 0 
              AND e.worker_id = ?
              AND COALESCE(e.user_id, cl.user_id) = ?
            ORDER BY e.id ASC
            LIMIT ? OFFSET ?
        ");
        
        $workerId = WORKER_ID;
        $stmt->bind_param('iiii', $workerId, $userId, $chunkSize, $offset);
        
        if (!db_execute_stmt($stmt)) {
            log_user($userId, "ERROR: Failed to fetch chunk at offset $offset");
            $stmt->close();
            break;
        }
        
        $result = $stmt->get_result();
        $chunkCount = 0;
        
        while ($row = $result->fetch_assoc()) {
            fwrite($emailFileHandle, $row['raw_emailid'] . "\n");
            $chunkCount++;
        }
        
        $stmt->close();
        $processedCount += $chunkCount;
        
        if ($chunkCount % PROGRESS_LOG_INTERVAL == 0 || $offset + $chunkSize >= $totalEmails) {
            log_progress($processedCount, $totalEmails, "User $userId email retrieval");
            check_memory_usage();
        }
        
        $offset += $chunkSize;
        $chunkNumber++;
        
        // Force garbage collection periodically
        if ($chunkNumber % 10 == 0) {
            gc_collect_cycles();
        }
    }
    
    fclose($emailFileHandle);
    
    if ($SHUTDOWN_REQUESTED) {
        continue;
    }
    
    log_user($userId, "Retrieved all $processedCount emails using chunked approach");
    
    // Calculate optimal workers for this user's workload
    $numWorkers = calculate_optimal_workers($totalEmails, $allocatedWorkers);
    $emailsPerWorker = ceil($totalEmails / $numWorkers);
    
    log_user($userId, "Spawning $numWorkers workers (~$emailsPerWorker emails/worker)");
    
    $userProcesses = spawn_workers($userId, $numWorkers, $batchProcessId, $totalEmails);
    
    file_put_contents($workerDir . 'worker_count.txt', $numWorkers);
    
    $allUserProcesses[$userId] = [
        'processes' => $userProcesses,
        'batch_id' => $batchProcessId,
        'worker_dir' => $workerDir,
        'num_workers' => $numWorkers,
        'email_count' => $totalEmails
    ];
    
    log_user($userId, "All $numWorkers workers spawned");
}

// ============================================================
// HELPER FUNCTIONS FOR WORKER MANAGEMENT
// ============================================================
function calculate_optimal_workers($totalEmails, $maxWorkers) {
    if ($totalEmails <= 100) {
        return min(2, $maxWorkers, $totalEmails);
    } elseif ($totalEmails <= 1000) {
        return min(5, $maxWorkers, ceil($totalEmails / 200));
    } elseif ($totalEmails <= 10000) {
        return min(10, $maxWorkers, ceil($totalEmails / 1000));
    } elseif ($totalEmails <= 100000) {
        return min(15, $maxWorkers, ceil($totalEmails / 5000));
    } else {
        // For crores of emails
        return min($maxWorkers, max(20, ceil($totalEmails / MAX_EMAILS_PER_WORKER)));
    }
}

function spawn_workers($userId, $numWorkers, $batchProcessId, $totalEmails) {
    $userProcesses = [];
    $emailsPerWorker = ceil($totalEmails / $numWorkers);
    
    for ($i = 1; $i <= $numWorkers; $i++) {
        $startIndex = ($i - 1) * $emailsPerWorker;
        $endIndex = min($i * $emailsPerWorker, $totalEmails);
        
        $cmd = sprintf(
            '%s %s %d %s %d %d %d %d > /dev/null 2>&1 &',
            PHP_BINARY,
            WORKER_SCRIPT,
            $i,
            escapeshellarg($batchProcessId),
            $startIndex,
            $endIndex,
            $userId,
            WORKER_ID
        );
        
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ];
        
        $process = proc_open($cmd, $descriptors, $pipes);
        
        if (is_resource($process)) {
            fclose($pipes[0]);
            
            $userProcesses[] = [
                'process' => $process,
                'pipes' => $pipes,
                'worker_id' => $i,
                'start_index' => $startIndex,
                'end_index' => $endIndex,
                'user_id' => $userId,
                'start_time' => time()
            ];
            
            log_worker_spawn($userId, $i, "Spawned (emails $startIndex-$endIndex)");
        } else {
            log_worker_spawn($userId, $i, "ERROR: Failed to spawn");
        }
    }
    
    return $userProcesses;
}

// ============================================================
// STEP 4: ADVANCED WORKER MONITORING WITH HEALTH CHECKS
// ============================================================
log_msg("Monitoring all user workers with health checks...");
$maxWaitTime = 1800; // 30 minutes for large datasets
$startTime = time();
$allCompleted = false;
$lastProgressLog = time();
$progressLogInterval = 30; // Log progress every 30 seconds

while ((time() - $startTime) < $maxWaitTime) {
    if ($SHUTDOWN_REQUESTED) {
        log_msg("Shutdown requested. Terminating workers...");
        terminate_all_workers($allUserProcesses);
        break;
    }
    
    $totalCompleted = 0;
    $totalWorkers = 0;
    $statusSummary = [];
    
    foreach ($allUserProcesses as $userId => $userInfo) {
        $completed = 0;
        $running = 0;
        $failed = 0;
        
        foreach ($userInfo['processes'] as &$worker) {
            $status = proc_get_status($worker['process']);
            
            if (!$status['running']) {
                $exitCode = $status['exitcode'];
                
                if ($exitCode === 0 || $exitCode === -1) {
                    $completed++;
                } else {
                    $failed++;
                    log_worker_spawn($userId, $worker['worker_id'], "FAILED with exit code $exitCode");
                }
            } else {
                $running++;
                
                // Check for stuck workers (running > 15 minutes)
                $runTime = time() - $worker['start_time'];
                if ($runTime > 900) { // 15 minutes
                    log_worker_spawn($userId, $worker['worker_id'], "WARNING: Running for {$runTime}s (may be stuck)");
                }
            }
            $totalWorkers++;
        }
        
        $statusSummary[$userId] = [
            'completed' => $completed,
            'running' => $running,
            'failed' => $failed,
            'total' => count($userInfo['processes'])
        ];
        
        if ($completed + $failed >= count($userInfo['processes'])) {
            $totalCompleted++;
        }
    }
    
    // Log progress periodically
    if (time() - $lastProgressLog >= $progressLogInterval) {
        log_msg("Worker status: $totalCompleted/" . count($allUserProcesses) . " users completed");
        foreach ($statusSummary as $uid => $stats) {
            if ($stats['running'] > 0) {
                log_msg("  User $uid: {$stats['completed']} done, {$stats['running']} running, {$stats['failed']} failed");
            }
        }
        $lastProgressLog = time();
    }
    
    if ($totalCompleted >= count($allUserProcesses)) {
        $allCompleted = true;
        $elapsed = time() - $startTime;
        log_msg("All workers completed in {$elapsed}s");
        break;
    }
    
    sleep(2); // Check every 2 seconds
}

if (!$allCompleted) {
    log_msg("WARNING: Timeout after " . (time() - $startTime) . "s. Some workers may still be running.");
    terminate_all_workers($allUserProcesses);
}

function terminate_all_workers(&$allUserProcesses) {
    log_msg("Terminating all running workers...");
    
    foreach ($allUserProcesses as $userId => $userInfo) {
        foreach ($userInfo['processes'] as $worker) {
            $status = proc_get_status($worker['process']);
            if ($status['running']) {
                proc_terminate($worker['process'], SIGTERM);
                log_worker_spawn($userId, $worker['worker_id'], "Terminated");
            }
        }
    }
}

// ============================================================
// STEP 5: EFFICIENT OUTPUT COLLECTION AND CLEANUP
// ============================================================
log_msg("Collecting worker output...");

foreach ($allUserProcesses as $userId => $userInfo) {
    log_user($userId, "--- Finalizing results for User $userId ---");
    
    $outputSummary = ['success' => 0, 'errors' => 0];
    
    foreach ($userInfo['processes'] as $worker) {
        // Read output with timeout protection
        stream_set_blocking($worker['pipes'][1], false);
        stream_set_blocking($worker['pipes'][2], false);
        
        $stdout = stream_get_contents($worker['pipes'][1]);
        $stderr = stream_get_contents($worker['pipes'][2]);
        
        if ($stdout && ENABLE_DETAILED_LOGGING) {
            log_user($userId, "Worker {$worker['worker_id']} output: " . substr($stdout, 0, 200));
            $outputSummary['success']++;
        }
        
        if ($stderr) {
            log_user($userId, "Worker {$worker['worker_id']} error: " . substr($stderr, 0, 200));
            $outputSummary['errors']++;
        }
        
        @fclose($worker['pipes'][1]);
        @fclose($worker['pipes'][2]);
        @proc_close($worker['process']);
    }
    
    log_user($userId, "Workers completed: {$outputSummary['success']} success, {$outputSummary['errors']} with errors");
}

// ============================================================
// STEP 6: OPTIMIZED CSV_LIST COMPLETION CHECK WITH BATCH UPDATES
// ============================================================
log_msg("Updating csv_list status and counts...");

// Single optimized query to update all completed lists
$updateQuery = "
    UPDATE csv_list cl
    INNER JOIN (
        SELECT 
            csv_list_id,
            COUNT(*) as total_db,
            SUM(CASE WHEN domain_status = 1 AND domain_processed = 1 THEN 1 ELSE 0 END) as valid_db,
            SUM(CASE WHEN domain_status = 0 AND domain_processed = 1 THEN 1 ELSE 0 END) as invalid_db,
            SUM(CASE WHEN domain_processed = 1 THEN 1 ELSE 0 END) as processed_db
        FROM emails
        WHERE csv_list_id IS NOT NULL
        GROUP BY csv_list_id
    ) e ON cl.id = e.csv_list_id
    SET 
        cl.status = CASE 
            WHEN e.processed_db >= e.total_db AND e.total_db > 0 THEN 'completed'
            ELSE cl.status
        END,
        cl.valid_count = e.valid_db,
        cl.invalid_count = (cl.total_emails - e.total_db) + e.invalid_db,
        cl.updated_at = NOW()
    WHERE cl.status IN ('running', 'pending')
";

$result = db_query_with_retry($conn, $updateQuery);

if ($result) {
    $affectedRows = $conn->affected_rows;
    if ($affectedRows > 0) {
        log_msg("✓ Updated $affectedRows csv_list record(s)");
    }
    
    // Count newly completed lists
    $completedQuery = db_query_with_retry($conn, "
        SELECT COUNT(*) as completed_count
        FROM csv_list
        WHERE status = 'completed'
        AND updated_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)
    ");
    
    if ($completedQuery) {
        $row = $completedQuery->fetch_assoc();
        $newlyCompleted = $row['completed_count'];
        if ($newlyCompleted > 0) {
            log_msg("✓ $newlyCompleted csv_list(s) marked as COMPLETED");
        }
        $completedQuery->close();
    }
} else {
    log_msg("ERROR: Failed to update csv_list: " . $conn->error);
}

// Final statistics
log_msg("=== PROCESSING COMPLETE ===");
log_msg("Processed " . count($allUserProcesses) . " user(s) in this run:");

$totalEmailsProcessed = 0;
$totalWorkersUsed = 0;

foreach ($allUserProcesses as $userId => $userInfo) {
    $totalEmailsProcessed += $userInfo['email_count'];
    $totalWorkersUsed += $userInfo['num_workers'];
    log_msg("  ✓ User $userId: {$userInfo['email_count']} emails with {$userInfo['num_workers']} workers");
}

$totalElapsed = time() - $startTime;
$emailsPerSecond = $totalElapsed > 0 ? round($totalEmailsProcessed / $totalElapsed, 2) : 0;

log_msg("Total emails: $totalEmailsProcessed | Workers: $totalWorkersUsed | Time: {$totalElapsed}s");
log_msg("Throughput: $emailsPerSecond emails/second");

if (ENABLE_DETAILED_LOGGING) {
    log_msg("Detailed logs: " . LOG_DIR);
}

// Flush any remaining log buffer
flush_log_buffer();

// Close database connection
$conn->close();
flock($lock, LOCK_UN);

log_msg("=== HIGH-PERFORMANCE SMTP VALIDATION CRON END ===");
exit(0);                                        