    <?php

    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception;

    require __DIR__ . '/../vendor/autoload.php';
    require_once __DIR__ . '/template_merge_helper.php';
    
    // === RESOURCE MANAGEMENT: Prevent affecting other applications ===
    require_once __DIR__ . '/resource_manager.php';
    ResourceManager::initCampaignProcess('worker');

    error_reporting(E_ALL);
    ini_set('display_errors', 0); // Disable display in production
    ini_set('log_errors', 1); // Enable logging to debug issues

    // Memory and time limits are now set by ResourceManager
    // Ensure consistent timezone for hour-based limits
    date_default_timezone_set('Asia/Kolkata');

    // Worker debug logging (enable/disable here)
    if (!defined('WORKER_LOG_ENABLED')) {
        define('WORKER_LOG_ENABLED', false); // ❌ DISABLED - Log files disabled
    }
    if (!defined('WORKER_LOG_FILE')) {
        define('WORKER_LOG_FILE', __DIR__ . '/../logs/email_worker_' . date('Y-m-d') . '.log');
    }
    function workerLog($msg) {
        if (!WORKER_LOG_ENABLED) return;
        $dir = dirname(WORKER_LOG_FILE);
        if (!is_dir($dir)) {@mkdir($dir, 0777, true);}
        $ts = date('Y-m-d H:i:s');
        $pid = getmypid();
        $logMsg = "[$ts][PID:$pid] $msg\n";
        // Echo to stdout FIRST for cron output capture
        echo $logMsg;
        // Then write to file
        @file_put_contents(WORKER_LOG_FILE, $logMsg, FILE_APPEND | LOCK_EX);
    }



    // Catch fatal errors
    register_shutdown_function(function() {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            // Log file creation disabled
            // file_put_contents(__DIR__ . '/../logs/email_worker_fatal.log', '[' . date('Y-m-d H:i:s') . "] FATAL ERROR: {$error['message']} in {$error['file']}:{$error['line']}\n", FILE_APPEND);
            echo "[FATAL] {$error['message']} in {$error['file']}:{$error['line']}\n";
        }
    });

    // If invoked from web/FPM, try to self-relaunch under CLI; if not possible, fall back to inline execution using provided request params
    if (php_sapi_name() !== 'cli') {
        // Respect open_basedir: do NOT probe or access binaries outside allowed paths.
        // Run inline under FPM using provided request parameters.
        $req_campaign_id   = isset($_REQUEST['campaign_id']) ? intval($_REQUEST['campaign_id']) : 0;
        $req_server_config = isset($_REQUEST['server_config']) ? $_REQUEST['server_config'] : '';
        $req_campaign      = isset($_REQUEST['campaign']) ? $_REQUEST['campaign'] : '';

        $server_config_json = $req_server_config ?: (isset($_REQUEST['server']) ? $_REQUEST['server'] : '');
        $campaign_json      = $req_campaign ?: (isset($_REQUEST['campaign_json']) ? $_REQUEST['campaign_json'] : '');
        $argv = [__FILE__, $req_campaign_id, '', $server_config_json, $campaign_json];
    }

    // Log immediately at start - even before validation
    workerLog('=== WORKER PROCESS STARTED ===');
    workerLog('Worker start argv=' . json_encode($argv));
    workerLog('Log file: ' . WORKER_LOG_FILE);
    workerLog('Process ID: ' . getmypid());
    workerLog('PHP Version: ' . PHP_VERSION);
    workerLog('Working directory: ' . getcwd());
    
// Parse command-line arguments
$campaign_id = isset($argv[1]) ? intval($argv[1]) : 0;
$server_config_json = isset($argv[3]) ? $argv[3] : '';
$campaign_json = isset($argv[4]) ? $argv[4] : '';

// Decode JSON arguments
$server_config = !empty($server_config_json) ? json_decode($server_config_json, true) : [];
$campaign = !empty($campaign_json) ? json_decode($campaign_json, true) : [];

// Log parsed values
workerLog("Parsed campaign_id: $campaign_id");
workerLog("Parsed server_config_json length: " . strlen($server_config_json));
workerLog("Parsed campaign_json length: " . strlen($campaign_json));

// Batch configuration - Optimized for high-volume campaigns (100k+ emails)
    // ⚙️ OPTIMIZATION STRATEGY:
    // - BATCH_SIZE 1000: Process large batches for efficiency (users can push unlimited emails)
    // - STATUS_UPDATE_INTERVAL 500: Minimize Server 1 queries (only update every 500 emails)
    // - BATCH_DELAY 2s: Allow system recovery after each 1000-email batch
    // - Workers scaled per SMTP server (1 worker per server) to avoid resource contention
    // - All heavy operations run on Server 2 (mail_blaster, SMTP accounts)
    // - Server 1 only queried minimally for campaign status checks
    // 🔒 MULTI-USER SUPPORT: Designed for 100+ concurrent users
    //    - Strict user_id isolation on SMTP accounts (no account sharing between users)
    //    - Each campaign has unique campaign_id for complete isolation
    //    - Database locking prevents race conditions across concurrent campaigns
    define('BATCH_SIZE', 1000); // Process 1000 emails per batch for maximum efficiency
    define('MAX_ROUNDS', 5); // Maximum 5 rounds of retries (5 attempts total)
    define('ROUND_DELAY', 5); // 5 seconds between rounds
    define('BATCH_DELAY', 2); // 2 second delay between batches to prevent server overload
    define('STATUS_UPDATE_INTERVAL', 500); // Update campaign_status every 500 emails (reduce Server 1 load)

    workerLog("=== WORKER STARTED ===");
    workerLog("Campaign ID: $campaign_id");
    workerLog("Server config: " . (empty($server_config) ? "EMPTY!" : json_encode($server_config)));
    workerLog("Campaign data: " . (empty($campaign) ? "EMPTY - Will fetch from DB" : "Provided"));

    if ($campaign_id == 0 || empty($server_config)) {
        workerLog("ERROR: Missing campaign_id or server_config - EXITING");
        exit(1);
    }

    if (empty($campaign) || empty($campaign['mail_subject']) || empty($campaign['mail_body'])) {
        workerLog("Campaign data incomplete, fetching from database...");
    } else {
        workerLog("Campaign data complete: Subject=" . substr($campaign['mail_subject'], 0, 50) . "...");
    }

    require_once __DIR__ . '/../config/db.php';
    require_once __DIR__ . '/../config/db_campaign.php';
    
    workerLog("=== DATABASE CONNECTIONS ===");
    if ($conn->connect_error) {
        workerLog("ERROR: Server 1 DB connection failed: " . $conn->connect_error);
        exit(1);
    }
    workerLog("✓ Server 1 Connected: " . $conn->host_info);
    workerLog("  └─ Email sources: emails, imported_recipients, campaign_master, campaign_status");
    
    if ($conn_heavy->connect_error) {
        workerLog("ERROR: Server 2 DB connection failed: " . $conn_heavy->connect_error);
        exit(1);
    }
    workerLog("✓ Server 2 Connected: " . $conn_heavy->host_info);
    workerLog("  └─ SMTP & Delivery: smtp_servers, smtp_accounts, smtp_usage, mail_blaster");
    workerLog("============================");
    
    /**
     * Check database connection health and reconnect if necessary
     * Prevents "MySQL server has gone away" errors after delays/sleeps
     * 
     * @param mysqli $connection Database connection to check
     * @param string $name Connection name for logging ('SERVER 1' or 'SERVER 2')
     * @return bool True if connection is healthy or successfully reconnected
     */
    function ensureConnectionAlive($connection, $name = 'Database') {
        // Ping the connection to check if it's still alive
        if (!$connection->ping()) {
            workerLog("⚠️  $name connection lost, attempting reconnect...");
            
            // Attempt to reconnect
            if ($connection->real_connect($connection->host_info)) {
                workerLog("✓ $name connection restored");
                return true;
            } else {
                workerLog("❌ $name reconnection failed: " . $connection->connect_error);
                return false;
            }
        }
        return true;
    }
    


    // If campaign data is incomplete, fetch from database
    if (empty($campaign) || empty($campaign['mail_subject']) || empty($campaign['mail_body'])) {
        // FIX: Use prepared statement to prevent SQL injection
        $stmt = $conn->prepare("SELECT * FROM campaign_master WHERE campaign_id = ?");
        if (!$stmt) {
            workerLog("ERROR: Failed to prepare statement: " . $conn->error);
            $conn->close();
            exit(1);
        }
        $stmt->bind_param("i", $campaign_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $campaign = $result->fetch_assoc();
            $stmt->close();
            
            // VALIDATE: Ensure campaign has required fields
            if (empty($campaign['mail_subject']) || (empty($campaign['mail_body']) && empty($campaign['template_id']))) {
                workerLog("ERROR: Campaign #$campaign_id has missing required data (subject or body/template)");
                $conn->close();
                exit(1);
            }
            workerLog("Campaign #$campaign_id loaded successfully from DB");
        } else {
            workerLog("ERROR: Campaign #$campaign_id not found in database");
            if (isset($stmt)) $stmt->close();
            $conn->close();
            exit(1);
        }
    }
    
    // VALIDATE: Final check before proceeding
    if (empty($campaign['mail_subject'])) {
        workerLog("ERROR: Campaign #$campaign_id missing mail_subject");
        $conn->close();
        exit(1);
    }

    $server_id = isset($server_config['server_id']) ? intval($server_config['server_id']) : 0;
    $csv_list_id = isset($campaign['csv_list_id']) ? intval($campaign['csv_list_id']) : 0;
    $import_batch_id = isset($campaign['import_batch_id']) ? $campaign['import_batch_id'] : null;
    $csv_list_filter = $csv_list_id > 0 ? " AND e.csv_list_id = $csv_list_id" : "";
    
    // Build mail_blaster filter based on campaign source
    // For import campaigns, we'll need to use JOINs in queries
    // For CSV campaigns, we can filter by csv_list_id
    $mailBlasterFilter = "";
    if ($import_batch_id) {
        $batch_escaped = $conn_heavy->real_escape_string($import_batch_id);
        // Will use JOIN in queries below
    } elseif ($csv_list_id > 0) {
        $mailBlasterFilter = " AND csv_list_id = $csv_list_id";
    }
    
    // Store campaign user_id in global for use in recordDelivery and as local variable
    $campaign_user_id = isset($campaign['user_id']) ? intval($campaign['user_id']) : 0;
    $GLOBALS['campaign_user_id'] = $campaign_user_id;
    workerLog("Campaign user_id: " . $campaign_user_id);
    
    if ($import_batch_id) {
        workerLog("Worker for server #$server_id starting with Import Batch ID: $import_batch_id");
    } else {
        workerLog("Worker for server #$server_id starting" . ($csv_list_id > 0 ? " (CSV List ID: $csv_list_id)" : " (All validated emails)"));
    }

    if (!empty($server_config)) {
        $safeServer = $server_config;
        unset($safeServer['password']);
    workerLog('Server config: ' . json_encode($safeServer));
    }

    $accounts = loadActiveAccountsForServer($conn_heavy, $server_id, $campaign_user_id);
    workerLog("Server #$server_id loaded " . count($accounts) . ' accounts for user #' . $campaign_user_id);
    if (empty($accounts)) {
        workerLog("❌ CRITICAL: No SMTP accounts found for server #$server_id and user #$campaign_user_id!");
        workerLog("❌ This means either:");
        workerLog("   1. User #$campaign_user_id has no SMTP accounts configured");
        workerLog("   2. All SMTP accounts for this user are inactive");
        workerLog("   3. All SMTP accounts are at their daily/hourly limits");
        workerLog("   4. User_id filtering is incorrect");
    } else {
        workerLog("✅ Loaded accounts: " . json_encode(array_map(function($a) { return ['id' => $a['id'], 'email' => $a['email']]; }, $accounts)));
    }

    // Log queue state before entering loop - check correct source table
    if ($import_batch_id) {
        // Count from imported_recipients table
        $eligibleRes = $conn->query("SELECT COUNT(*) AS c FROM imported_recipients WHERE import_batch_id = '$batch_escaped' AND is_active = 1 AND Emails IS NOT NULL AND Emails <> ''");
        $eligibleCount = ($eligibleRes && $eligibleRes->num_rows) ? (int)$eligibleRes->fetch_assoc()['c'] : 0;
        workerLog("Server #$server_id: eligible_emails_from_import=$eligibleCount");
        
        // Check pending with import filter - mail_blaster on Server 2
        $pendingRes = $conn_heavy->query("SELECT 1 FROM mail_blaster WHERE campaign_id = $campaign_id AND status IN ('pending', 'failed', 'processing') AND attempt_count < 5 LIMIT 1");
    } else {
        // Count from emails table (CSV)
        $eligibleRes = $conn->query("SELECT COUNT(*) AS c FROM emails e WHERE e.domain_status = 1 AND e.validation_status = 'valid' AND e.raw_emailid IS NOT NULL AND e.raw_emailid <> ''" . $csv_list_filter);
        $eligibleCount = ($eligibleRes && $eligibleRes->num_rows) ? (int)$eligibleRes->fetch_assoc()['c'] : 0;
        workerLog("Server #$server_id: eligible_emails_from_csv=$eligibleCount");
        
        // Check pending with CSV filter - mail_blaster on Server 2
        $pendingRes = $conn_heavy->query("SELECT 1 FROM mail_blaster WHERE campaign_id = $campaign_id AND status IN ('pending', 'failed', 'processing') AND attempt_count < 5" . $mailBlasterFilter . " LIMIT 1");
    }
    $pendingCount = ($pendingRes && $pendingRes->num_rows > 0) ? 1 : 0;
    workerLog("Server #$server_id: eligible_emails=$eligibleCount pending_in_mail_blaster=" . ($pendingCount > 0 ? 'YES' : 'NO'));
    
    if ($eligibleCount == 0) {
        workerLog("❌ NO EMAILS TO SEND: No eligible emails found in source table!");
        workerLog("   Campaign #$campaign_id has no valid emails to process");
    } else {
        workerLog("✅ Found $eligibleCount eligible emails ready to send");
    }

    if (empty($accounts)) { 
        workerLog("Server #$server_id: No accounts found, exiting");
        $conn->close(); 
        exit(0); 
    }

    // CRITICAL: Verify campaign status is 'running' before starting
    // Log to campaign status file for monitoring
    workerLog("📋 Checking campaign_status from SERVER 1 ($conn->host_info)...");
    // $campaign_status_log = __DIR__ . '/../logs/campaign_status_' . $campaign_id . '.log';
    $statusCheckRes = $conn->query("SELECT status FROM campaign_status WHERE campaign_id = $campaign_id LIMIT 1");
    if ($statusCheckRes && $statusCheckRes->num_rows > 0) {
        $currentStatus = $statusCheckRes->fetch_assoc()['status'];
        $statusLogMsg = "[" . date('Y-m-d H:i:s') . "] [Worker-Server#$server_id] Campaign #$campaign_id status check: '$currentStatus' (Expected: 'running')\n";
        // @file_put_contents($campaign_status_log, $statusLogMsg, FILE_APPEND | LOCK_EX);
        
        if ($currentStatus !== 'running') {
            workerLog("❌ ABORT: Campaign #$campaign_id status is '$currentStatus', not 'running'. Worker exiting without sending any emails.");
            $abortMsg = "[" . date('Y-m-d H:i:s') . "] [Worker-Server#$server_id] ❌ ABORTED - Campaign not running\n";
            // @file_put_contents($campaign_status_log, $abortMsg, FILE_APPEND | LOCK_EX);
            $conn->close();
            exit(0);
        }
        workerLog("✅ Campaign #$campaign_id status verified: running");
        $startMsg = "[" . date('Y-m-d H:i:s') . "] [Worker-Server#$server_id] ✅ STARTED - Campaign is running, worker active\n";
        // @file_put_contents($campaign_status_log, $startMsg, FILE_APPEND | LOCK_EX);
    } else {
        workerLog("❌ ABORT: Campaign #$campaign_id not found in campaign_status. Worker exiting.");
        $notFoundMsg = "[" . date('Y-m-d H:i:s') . "] [Worker-Server#$server_id] ❌ NOT FOUND - Campaign missing from database\n";
        // @file_put_contents($campaign_status_log, $notFoundMsg, FILE_APPEND | LOCK_EX);
        $conn->close();
        exit(0);
    }
    
    // Do not alter schema (no index/DDL changes at runtime)
    workerLog("Server #$server_id: Starting send loop for campaign #$campaign_id");

    $rotation_idx = 0;
    $send_count = 0;
    $loop_iter = 0;
    $consecutive_limit_checks = 0;
    $consecutive_empty_claims = 0; // Track consecutive failed claim attempts to handle locked rows
    $consecutive_server_failures = 0; // Track server connection/auth failures for automatic failover
    $max_server_failures = 5; // Switch to another server after 5 consecutive failures
    
    // 🔥 PERFORMANCE: Batch counters for incremental updates (avoid COUNT queries)
    $batch_success_count = 0;
    $batch_failed_count = 0;
    $last_status_update = 0;
    
    // LIGHTWEIGHT: Round-based processing
    $current_round = 1;
    $batch_count = 0;
    $updates_pending = []; // Batch DB updates
    while (true) {
        $loop_iter++;
        
        // === CONNECTION HEALTH CHECK ===
        // Ensure both database connections are alive after potential delays/sleeps
        // This prevents "MySQL server has gone away" errors
        if (!ensureConnectionAlive($conn, 'SERVER 1')) {
            workerLog("❌ SERVER 1 connection failed and cannot be restored. Exiting.");
            exit(1);
        }
        if (!ensureConnectionAlive($conn_heavy, 'SERVER 2')) {
            workerLog("❌ SERVER 2 connection failed and cannot be restored. Exiting.");
            exit(1);
        }
        
        // CRITICAL: Reset variables at start of each loop iteration
        $to = null;
        $email_csv_list_id = null;
        $email_mail_blaster_id = null;
        $csv_id_param = null;
        $mail_blaster_id_param = null;
        
        // 🔥 PERFORMANCE: Increased delay to reduce DB hammering (critical for 100k+ emails)
        // 50ms allows frontend APIs to respond without lock waits
        usleep(50000); // 50ms - prevents DB contention during massive campaigns
        
        // Check campaign existence every 1000 iterations (not every loop) to minimize Server 1 load
        if ($loop_iter % 1000 === 1) {
            $existsRes = $conn->query("SELECT 1 FROM campaign_master WHERE campaign_id = $campaign_id LIMIT 1");
            if (!$existsRes || $existsRes->num_rows === 0) {
                workerLog("Campaign #$campaign_id deleted; worker exiting");
                $conn->close();
                exit(0);
            }
        }
        
        // DB reconnect logic removed: on DB failure the worker should exit; cron/orchestrator will restart.
        
        // OPTIMIZED: Reduce frequency of status checks to lower DB load during large campaigns
        // Check every 500 iterations instead of 200 to minimize Server 1 queries
        if ($loop_iter % 500 === 1) {
            // Check campaign status - ONLY continue if status='running'
            $statusCheck = $conn->query("SELECT status FROM campaign_status WHERE campaign_id = $campaign_id");
            workerLog("📋 Checking campaign_status on SERVER 1...");
            if ($statusCheck && $statusCheck->num_rows > 0) {
                $currentStatus = $statusCheck->fetch_assoc()['status'];
                if ($currentStatus !== 'running') {
                    workerLog("Server #$server_id: Campaign status changed to '$currentStatus' (not 'running'), stopping worker (sent $send_count emails)");
                    // $campaign_status_log = __DIR__ . '/../logs/campaign_status_' . $campaign_id . '.log';
                    $stopMsg = "[" . date('Y-m-d H:i:s') . "] [Worker-Server#$server_id] 🛑 STOPPED - Status changed to '$currentStatus' (Sent: $send_count emails)\n";
                    // @file_put_contents($campaign_status_log, $stopMsg, FILE_APPEND | LOCK_EX);
                    break;
                }
            }
            // If status row missing, treat as deleted and stop
            else {
                workerLog("Campaign #$campaign_id status missing; treating as deleted and stopping");
                $conn->close();
                exit(0);
            }
        }
        
        // RECOVERY: Fix orphaned records less frequently (every 1000 iterations) to reduce UPDATE query load
        if ($loop_iter % 1000 === 1) {
            workerLog("🔧 Fixing orphaned mail_blaster records on SERVER 2...");
            // CRITICAL FIX: Only fix records with status='processing' that timed out (stuck for >2 minutes)
            // DO NOT touch records with status='success' - they are already sent
            $orphanedFix = $conn_heavy->query("
                UPDATE mail_blaster 
                SET status = 'pending', delivery_time = NOW() 
                WHERE campaign_id = $campaign_id 
                AND status = 'processing' 
                AND delivery_time < DATE_SUB(NOW(), INTERVAL 120 SECOND) 
                AND attempt_count < 5
            ");
            if ($orphanedFix && $conn_heavy->affected_rows > 0) {
                workerLog("Server #$server_id: Recovered " . $conn_heavy->affected_rows . " stuck 'processing' records (>2min timeout) for campaign #$campaign_id");
            }
        }
        
        if ($loop_iter % 50 === 1) {
    //         file_put_contents(__DIR__ . '/../logs/email_worker.log', '[' . date('Y-m-d H:i:s') . "] Server #$server_id: Loop iteration $loop_iter (send_count=$send_count)\n", FILE_APPEND);
        }
        
        // Pick next eligible account in strict order first
        $selected = null; $tries = 0; $count = count($accounts);
        while ($tries < $count) {
            $idx = $rotation_idx % $count;
            $candidate = $accounts[$idx];
            if (accountWithinLimits($conn_heavy, intval($candidate['id']))) { 
                $selected = $candidate;
                workerLog("Server #$server_id: Selected account #{$candidate['id']} ({$candidate['email']}) - within limits");
                $rotation_idx = ($idx + 1) % $count; 
                $consecutive_limit_checks = 0; // Reset counter when account found
                break; 
            } else {
                if ($tries === 0) {
                    workerLog("Server #$server_id: Account #{$candidate['id']} ({$candidate['email']}) at limits, checking next...");
                }
            }
            $rotation_idx = ($idx + 1) % $count; 
            $tries++;
        }
        
        if (!$selected) { 
            // All SMTP accounts are at hourly/daily limits temporarily
            // OPTIMIZED: Don't wait 60s - exit and let orchestrator relaunch when needed
            workerLog("Server #$server_id: All accounts at limits. Exiting - orchestrator will restart when limits reset.");
            $conn->close();
            exit(0);
        }

        // First try to pick up an existing pending/failed email (backlog) - prefer cross-server retry
        workerLog("Server #$server_id: 🔍 Checking for pending/retry emails on SERVER 2...");
        try {
            $pending = fetchNextPending($conn_heavy, $campaign_id, $server_id);
        } catch (Exception $e) {
            workerLog("Server #$server_id: ERROR in fetchNextPending: " . $e->getMessage());
            $pending = null;
        }
        
        if ($pending) {
            $to = $pending['to_mail'];
            $email_csv_list_id = isset($pending['csv_list_id']) ? intval($pending['csv_list_id']) : null;
            $email_mail_blaster_id = isset($pending['mail_blaster_id']) ? intval($pending['mail_blaster_id']) : null;
            workerLog("Server #$server_id: ✓ Found pending/retry email from SERVER 2: $to (attempt #{$pending['attempt_count']}, csv_list_id=$email_csv_list_id)");
            // Assign this pending to the selected account to mark ownership (only if campaign still exists)
            $existsRes = $conn->query("SELECT 1 FROM campaign_master WHERE campaign_id = $campaign_id LIMIT 1");
            if (!$existsRes || $existsRes->num_rows === 0) {
                $conn->close();
                exit(0);
            }
            assignPendingToAccount($conn_heavy, $campaign_id, $to, intval($selected['id']));
            workerLog("Server #$server_id: ✓ Assigned pending $to to account #{$selected['id']} ({$selected['email']})");
        } else {
            // No backlog: claim next email atomically only after we have an eligible account
            workerLog("Server #$server_id: No pending emails, attempting to claim new email...");
            try {
                $claimed = claimNextEmail($conn_heavy, $campaign_id, intval($selected['id']));
            } catch (Exception $e) {
                workerLog("Server #$server_id: ERROR in claimNextEmail: " . $e->getMessage());
                $claimed = null;
            }
            
            workerLog("Server #$server_id: claimNextEmail returned: " . ($claimed ? "SUCCESS" : "NULL"));
            if (!$claimed) {
                $consecutive_empty_claims++;
                
                // CRITICAL: Check if there are unclaimed emails not yet in mail_blaster
                // NOTE: Cannot use JOIN across servers - check Server 2's mail_blaster separately
                $unclaimedCheck = null;
                if ($import_batch_id) {
                    // Just check if mail_blaster on Server 2 has any data
                    $unclaimedCheck = $conn_heavy->query("SELECT COUNT(*) as cnt FROM mail_blaster WHERE campaign_id = $campaign_id");
                } else {
                    // Just check if mail_blaster on Server 2 has any data
                    $unclaimedCheck = $conn_heavy->query("SELECT COUNT(*) as cnt FROM mail_blaster WHERE campaign_id = $campaign_id");
                }
                
                $mbCount = 0;
                if ($unclaimedCheck && $unclaimedCheck->num_rows > 0) {
                    $row = $unclaimedCheck->fetch_assoc();
                    $mbCount = (int)$row['cnt'];
                }
                
                if ($mbCount === 0) {
                    // No emails in mail_blaster - this shouldn't happen, campaign was not initialized properly
                    workerLog("Server #$server_id: *** ERROR: No emails found in mail_blaster! Campaign not initialized properly.");
                    // Don't re-initialize - that should only happen on Server 1 when starting campaign
                    break; // Exit worker
                }
                
                // Check if there are emails remaining to process (already in queue)
                // NOTE: Cannot JOIN across servers - mail_blaster is on Server 2, query it directly
                $remainingCheck = $conn_heavy->query("SELECT COUNT(*) as cnt FROM mail_blaster WHERE campaign_id = $campaign_id AND status IN ('pending', 'failed', 'processing') AND attempt_count < 5 LIMIT 1");
                $remaining = ($remainingCheck && $remainingCheck->num_rows > 0) ? 1 : 0;
                
                if ($remaining > 0) {
                    // There ARE emails remaining in queue, but they're locked by other workers
                    // OR they're assigned to other servers via hash distribution
                    // OPTIMIZED: Longer waits to prevent DB hammering when waiting for emails
                    if ($consecutive_empty_claims <= 20) {
                        // Progressive backoff: 200ms → 400ms → 600ms → ... → 2000ms max
                        $wait_ms = min(200 * $consecutive_empty_claims, 2000);
                        workerLog("Server #$server_id: $remaining emails still pending but not for this server yet. Retry attempt #$consecutive_empty_claims/20. Waiting {$wait_ms}ms...");
                        usleep($wait_ms * 1000);
                        // Yield CPU to web processes
                        if (function_exists('gc_collect_cycles')) { @gc_collect_cycles(); }
                        continue; // Retry claiming
                    } else {
                        // After 20 retries, take longer break to reduce system load
                        workerLog("Server #$server_id: $remaining emails pending. Taking 5s break to reduce DB load...");
                        sleep(5); // 5 seconds - longer break for system health
                        $consecutive_empty_claims = 0;
                        continue; // Keep trying, don't exit
                    }
                } else {
                    // No emails remaining - check if ALL workers are done (not just this server)
                    workerLog("Server #$server_id: No more emails for this server. Checking global status...");
                    
                    // Double-check: Are there emails assigned to OTHER servers still pending?
                    // NOTE: Cannot JOIN across servers - mail_blaster is on Server 2, query it directly
                    $globalCheck = $conn_heavy->query("SELECT COUNT(*) as cnt FROM mail_blaster WHERE campaign_id = $campaign_id AND status IN ('pending', 'failed', 'processing') AND attempt_count < 5 LIMIT 1");
                    $globalPending = ($globalCheck && $globalCheck->num_rows > 0) ? 1 : 0;
                    
                    if ($globalPending > 0) {
                        // Other servers are still working - wait longer to reduce DB queries
                        workerLog("Server #$server_id: Other servers still have pending emails. Waiting 5s before rechecking...");
                        sleep(5); // Longer wait to reduce DB load during large campaigns
                        $consecutive_empty_claims = 0;
                        continue; // Keep checking
                    }
                    
                    // Truly no emails left anywhere
                    workerLog("Server #$server_id: No more emails to process globally. Queue truly exhausted (verified: $unclaimed unclaimed, $remaining for this server, $globalPending globally).");
                    
                    // ROUND COMPLETE: Check if all emails are sent or need another round
                    // NOTE: Cannot JOIN across servers - mail_blaster is on Server 2, query it directly
                    $failedForRetry = $conn_heavy->query("SELECT COUNT(*) as cnt FROM mail_blaster WHERE campaign_id = $campaign_id AND status = 'failed' AND attempt_count < 5");
                    $retryCount = ($failedForRetry && $failedForRetry->num_rows > 0) ? intval($failedForRetry->fetch_assoc()['cnt']) : 0;
                    
                    if ($retryCount > 0 && $current_round < MAX_ROUNDS) {
                        workerLog("Server #$server_id: Round $current_round complete. $retryCount emails need retry. Starting round " . ($current_round + 1) . " after " . ROUND_DELAY . "s delay...");
                        sleep(ROUND_DELAY); // Delay between rounds
                        $current_round++;
                        $consecutive_empty_claims = 0;
                        continue; // Start next round
                    } else if ($retryCount > 0 && $current_round >= MAX_ROUNDS) {
                        workerLog("Server #$server_id: Max rounds ($current_round) reached. $retryCount emails still failed - marking as permanent failures.");
                    }
                    
                    // Flush any remaining batch updates before final completion check
                    if ($batch_success_count > 0 || $batch_failed_count > 0) {
                        workerLog("📊 [SERVER 1] Flushing final batch updates: +$batch_success_count sent, +$batch_failed_count failed");
                        updateCampaignStatusIncremental($conn, $campaign_id, $batch_success_count, $batch_failed_count);
                        $batch_success_count = 0;
                        $batch_failed_count = 0;
                    }
                    
                    // CRITICAL: Force synchronize campaign_status with actual mail_blaster counts
                    workerLog("🔄 [SERVER 1] Force-syncing campaign_status with SERVER 2 mail_blaster...");
                    
                    // Check if campaign is completed and update status on SERVER 1
                    workerLog("🔍 Performing final campaign completion check...");
                    checkCampaignCompletion($conn, $campaign_id);
                    
                    break; // Exit worker only when truly nothing left
                }
            }
            
            // Successfully claimed an email - reset counter
            $consecutive_empty_claims = 0;
            $to = $claimed['to_mail'];
            $email_csv_list_id = isset($claimed['csv_list_id']) ? intval($claimed['csv_list_id']) : null;
            $email_mail_blaster_id = isset($claimed['mail_blaster_id']) ? intval($claimed['mail_blaster_id']) : null;
            workerLog("Server #$server_id: Claimed NEW email: $to (csv_list_id=$email_csv_list_id, mail_blaster_id=$email_mail_blaster_id) -> assigned to account #{$selected['id']} ({$selected['email']})");
        }

        try {
            // Prepare parameters BEFORE logging
            $csv_id_param = isset($email_csv_list_id) ? $email_csv_list_id : null;
            $mail_blaster_id_param = isset($email_mail_blaster_id) ? $email_mail_blaster_id : null;
            
            workerLog("─────────────────────────────────────────");
            workerLog("Server #$server_id: 📧 Processing email #$send_count");
            workerLog("   Recipient: $to");
            workerLog("   SMTP Account: #{$selected['id']} ({$selected['email']})");
            workerLog("   SMTP Server: {$server_config['host']}:{$server_config['port']}");
            workerLog("   Campaign ID: $campaign_id");
            workerLog("   CSV List ID: " . ($csv_id_param ?: 'N/A'));
            workerLog("   Mail Blaster ID: " . ($mail_blaster_id_param ?: 'N/A'));
            workerLog("─────────────────────────────────────────");
            
            sendEmail($conn, $conn_heavy, $campaign_id, $to, $server_config, $selected, $campaign, $csv_id_param, $mail_blaster_id_param);
            $send_count++;
            $emails_in_current_batch++;
            
            // CRITICAL: For small campaigns, check completion immediately after each email
            // This ensures frontend shows "completed" status as soon as all emails are sent
            if (isset($eligibleCount) && $eligibleCount > 0 && $eligibleCount <= 20) {
                // Flush pending batch updates first
                if ($batch_success_count > 0 || $batch_failed_count > 0) {
                    updateCampaignStatusIncremental($conn, $campaign_id, $batch_success_count, $batch_failed_count);
                    $batch_success_count = 0;
                    $batch_failed_count = 0;
                }
                
                // Check if any emails still pending on SERVER 2
                $stillPendingCheck = $conn_heavy->query("SELECT COUNT(*) as cnt FROM mail_blaster 
                    WHERE campaign_id = $campaign_id 
                    AND status IN ('pending', 'failed', 'processing') 
                    AND attempt_count < 5");
                if ($stillPendingCheck) {
                    $pendingData = $stillPendingCheck->fetch_assoc();
                    $stillPending = intval($pendingData['cnt']);
                    
                    if ($stillPending === 0) {
                        workerLog("🏁 [SMALL CAMPAIGN] All $eligibleCount emails processed! Marking as completed NOW...");
                        checkCampaignCompletion($conn, $campaign_id);
                    }
                }
            }
            
            // 🔥 PERFORMANCE: Batch status updates every 500 emails to minimize Server 1 load
            if ($send_count - $last_status_update >= STATUS_UPDATE_INTERVAL) {
                updateCampaignStatusIncremental($conn, $campaign_id, $batch_success_count, $batch_failed_count);
                workerLog("📊 [SERVER 1] Batch update: +$batch_success_count success, +$batch_failed_count failed (total sent: $send_count)");
                
                // Every 5 batch updates (2500 emails), check if campaign should be marked completed
                static $batch_update_count = 0;
                $batch_update_count++;
                if ($batch_update_count % 5 === 0) {
                    workerLog("🔍 Periodic completion check (every 2500 emails)...");
                    checkCampaignCompletion($conn, $campaign_id);
                }
                
                $batch_success_count = 0;
                $batch_failed_count = 0;
                $last_status_update = $send_count;
            }
            workerLog("Total sent by this worker: $send_count (batch: $emails_in_current_batch/" . BATCH_SIZE . ")");
            $consecutive_server_failures = 0; // Reset on success
            workerLog("Server #$server_id: ✓ SUCCESS sent to $to via account #{$selected['id']} ({$selected['email']}) [total sent: $send_count]");
            
            // Minimal delay between emails for maximum throughput in large campaigns
            usleep(10000); // 10ms between emails - optimized for high-volume sending
            
            // After each full batch (1000 emails), pause for DB sync and system resource management
            if ($emails_in_current_batch >= BATCH_SIZE) {
                workerLog("Server #$server_id: ==== COMPLETED BATCH OF $emails_in_current_batch EMAILS ====");
                workerLog("Server #$server_id: Total emails sent so far: $send_count");
                workerLog("Server #$server_id: Pausing for " . BATCH_DELAY . "s to reduce CPU load and allow DB sync...");
                
                // Pause between batches to prevent CPU/DB overload and allow system recovery
                sleep(BATCH_DELAY); // 2 second pause between 1000-email batches
                
                // Force garbage collection to free memory
                if (function_exists('gc_collect_cycles')) { 
                    @gc_collect_cycles(); 
                }
                
                // Reset batch counter
                $emails_in_current_batch = 0;
                
                // Check if we should start a new round
                // NOTE: Cannot JOIN across servers - mail_blaster is on Server 2, query it directly
                $pendingCheck = $conn_heavy->query("SELECT COUNT(*) as cnt FROM mail_blaster WHERE campaign_id = $campaign_id AND status IN ('pending', 'failed') AND attempt_count < 5");
                if ($pendingCheck) {
                    $pendingData = $pendingCheck->fetch_assoc();
                    $pending_cnt = intval($pendingData['cnt']);
                    workerLog("Server #$server_id: Pending/retryable emails remaining: $pending_cnt");
                    if ($pending_cnt == 0) {
                        workerLog("Server #$server_id: All emails in this round completed!");
                        sleep(ROUND_DELAY); // Delay before next round
                    }
                }
            }
            
            // Yield CPU to other processes periodically (every 50 emails instead of 5)
            if ($send_count % 50 == 0) {
                if (function_exists('gc_collect_cycles')) { @gc_collect_cycles(); }
                usleep(50000); // 50ms yield every 50 emails to allow frontend/other processes to run
            }
        } catch (Exception $e) {
            // Ensure transaction is rolled back on any exception
            if ($conn->connect_errno === 0) {
                $conn->query("ROLLBACK");
            }
            
            workerLog("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            workerLog("❌ EXCEPTION CAUGHT");
            workerLog("   Email: $to");
            workerLog("   SMTP Account: #{$selected['id']} ({$selected['email']})");
            workerLog("   SMTP Server: {$server_config['host']}:{$server_config['port']}");
            workerLog("   Error: " . $e->getMessage());
            workerLog("   File: " . $e->getFile());
            workerLog("   Line: " . $e->getLine());
            workerLog("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            
            // Check if this was a duplicate prevention (not a real error)
            $isDuplicatePrevention = (strpos($e->getMessage(), 'Duplicate prevented') !== false || 
                                      strpos($e->getMessage(), 'already sent') !== false ||
                                      strpos($e->getMessage(), 'Lock timeout') !== false);
            
            if ($isDuplicatePrevention) {
                workerLog("Server #$server_id: ℹ️  Duplicate/Lock prevented for $to - continuing to next email");
                // Don't record as failed, just continue to next email
                $consecutive_empty_claims = 0; // Reset since we're making progress
                continue;
            }
            
            // Check if failure is server-related (connection/auth issues)
            $error_msg = $e->getMessage();
            $isServerFailure = (stripos($error_msg, 'connect') !== false || 
                              stripos($error_msg, 'authenticate') !== false || 
                              stripos($error_msg, 'login') !== false ||
                              stripos($error_msg, 'timeout') !== false ||
                              stripos($error_msg, 'timed out') !== false ||
                              stripos($error_msg, 'connection refused') !== false);
            
            if ($isServerFailure) {
                $consecutive_server_failures++;
                workerLog("Server #$server_id: SERVER FAILURE ($consecutive_server_failures/$max_server_failures): " . $error_msg);
                
                // Switch to backup server if threshold reached
                if ($consecutive_server_failures >= $max_server_failures) {
                    workerLog("Server #$server_id: Max failures reached! Attempting server failover...");
                    $backup = switchToBackupServer($conn_heavy, $server_id, $campaign_user_id);
                    
                    if ($backup) {
                        // Switch to backup server
                        $server_id = $backup['server_id'];
                        $server_config = $backup;
                        
                        // Reload accounts from new server (with user filter)
                        $accounts = loadActiveAccountsForServer($conn_heavy, $server_id, $campaign_user_id);
                        if (!empty($accounts)) {
                            workerLog("✓ Failover complete! Now using server #$server_id with " . count($accounts) . " accounts for user #$campaign_user_id");
                            $rotation_idx = 0;
                            $consecutive_server_failures = 0;
                            continue; // Skip recording and retry with new server
                        } else {
                            workerLog("✗ Backup server #$server_id has no accounts available for user #$campaign_user_id");
                        }
                    }
                    // If failover fails, continue with normal error handling below
                }
            } else {
                // Not a server failure - reset counter
                $consecutive_server_failures = 0;
            }
            
            // IMPROVED: Determine if error is retryable or permanent
            $error_msg = $e->getMessage();
            $is_permanent_error = false;
            
            // Permanent errors: recipient not found, mailbox full, invalid address, policy rejection
            if (preg_match('/(550|551|553|554)/', $error_msg) || 
                stripos($error_msg, 'user.*not.*found') !== false ||
                stripos($error_msg, 'user.*unknown') !== false ||
                stripos($error_msg, 'does.*not.*exist') !== false ||
                stripos($error_msg, 'no.*such.*user') !== false ||
                stripos($error_msg, 'mailbox.*full') !== false ||
                stripos($error_msg, 'mailbox.*unavailable') !== false ||
                stripos($error_msg, 'recipient.*rejected') !== false ||
                stripos($error_msg, 'address.*rejected') !== false ||
                stripos($error_msg, 'invalid.*recipient') !== false ||
                stripos($error_msg, 'relay.*denied') !== false ||
                stripos($error_msg, 'policy.*rejection') !== false) {
                $is_permanent_error = true;
            }
            
            // Check current attempt count
            $attemptRes = $conn_heavy->query("SELECT attempt_count FROM mail_blaster WHERE campaign_id = $campaign_id AND to_mail = '" . $conn_heavy->real_escape_string($to) . "'");
            $currentAttempts = ($attemptRes && $attemptRes->num_rows > 0) ? intval($attemptRes->fetch_assoc()['attempt_count']) : 0;
            
            if ($is_permanent_error) {
                // Mark as permanently failed immediately (don't retry recipient errors)
                workerLog("Server #$server_id: ✗ PERMANENT RECIPIENT ERROR to $to - Error: " . $e->getMessage());
                workerLog("PERMANENT FAILURE (Recipient Error): $to (Account: {$selected['email']}, Error: " . $e->getMessage() . ")");
                $csv_id_param = isset($email_csv_list_id) ? $email_csv_list_id : null;
                recordDelivery($conn_heavy, $selected['id'], $server_id, $campaign_id, $to, 'permanent_failure', $e->getMessage(), $csv_id_param);
                $batch_failed_count++;
            } elseif ($currentAttempts >= 5) {
                // Mark as permanently failed after 5 attempts for SMTP errors
                workerLog("Server #$server_id: ✗ PERMANENT FAILURE to $to via account #{$selected['id']} ({$selected['email']}) after $currentAttempts attempts - Error: " . $e->getMessage());
                workerLog("PERMANENT FAILURE: $to (Account: {$selected['email']}, Error: " . $e->getMessage() . ")");
                $csv_id_param = isset($email_csv_list_id) ? $email_csv_list_id : null;
                recordDelivery($conn_heavy, $selected['id'], $server_id, $campaign_id, $to, 'failed', "Max retries exceeded: " . $e->getMessage(), $csv_id_param);
                $batch_failed_count++;
            } else {
                // Keep as pending for retry (only for SMTP-related errors: timeouts, connections, etc.)
                workerLog("Server #$server_id: ✗ RETRYABLE SMTP ERROR (attempt #$currentAttempts/5) to $to - Will retry. Error: " . $e->getMessage());
                workerLog("RETRYABLE FAILURE (Attempt $currentAttempts/5): $to (Account: {$selected['email']}, Error: " . $e->getMessage() . ")");
                $csv_id_param = isset($email_csv_list_id) ? $email_csv_list_id : null;
                recordDelivery($conn_heavy, $selected['id'], $server_id, $campaign_id, $to, 'failed_attempt', $e->getMessage(), $csv_id_param);
                $batch_failed_count++;
            }
        }
    }

    // Mark stopped before exiting
    
    // 🔥 PERFORMANCE: Final batch update before exit
    if ($batch_success_count > 0 || $batch_failed_count > 0) {
        updateCampaignStatusIncremental($conn, $campaign_id, $batch_success_count, $batch_failed_count);
        workerLog("📊 Final batch update: +$batch_success_count success, +$batch_failed_count failed");
    }
    
    workerLog("Server #$server_id: ========================================");
    workerLog("Server #$server_id: WORKER SUMMARY");
    workerLog("Server #$server_id: ========================================");
    workerLog("Server #$server_id: Total rounds completed: $current_round");
    workerLog("Server #$server_id: Total emails sent: $send_count");
    
    // LIGHTWEIGHT: Check final state - how many succeeded, failed, permanently failed
    // Apply proper filtering by campaign source
    if ($import_batch_id) {
        $successCheck = $conn_heavy->query("SELECT COUNT(*) as cnt FROM mail_blaster mb INNER JOIN imported_recipients ir ON mb.to_mail COLLATE utf8mb4_unicode_ci = ir.Emails COLLATE utf8mb4_unicode_ci WHERE mb.campaign_id = $campaign_id AND mb.status = 'success' AND ir.import_batch_id = '$batch_escaped' AND ir.is_active = 1");
        $successCount = ($successCheck && $successCheck->num_rows > 0) ? intval($successCheck->fetch_assoc()['cnt']) : 0;
        
        $failedCheck = $conn_heavy->query("SELECT COUNT(*) as cnt FROM mail_blaster mb INNER JOIN imported_recipients ir ON mb.to_mail COLLATE utf8mb4_unicode_ci = ir.Emails COLLATE utf8mb4_unicode_ci WHERE mb.campaign_id = $campaign_id AND mb.status = 'failed' AND mb.attempt_count >= 5 AND ir.import_batch_id = '$batch_escaped' AND ir.is_active = 1");
        $failedCount = ($failedCheck && $failedCheck->num_rows > 0) ? intval($failedCheck->fetch_assoc()['cnt']) : 0;
        
        $pendingCheck = $conn_heavy->query("SELECT COUNT(*) as cnt FROM mail_blaster mb INNER JOIN imported_recipients ir ON mb.to_mail COLLATE utf8mb4_unicode_ci = ir.Emails COLLATE utf8mb4_unicode_ci WHERE mb.campaign_id = $campaign_id AND mb.status IN ('pending', 'failed') AND mb.attempt_count < 5 AND ir.import_batch_id = '$batch_escaped' AND ir.is_active = 1");
        $pendingCount = ($pendingCheck && $pendingCheck->num_rows > 0) ? intval($pendingCheck->fetch_assoc()['cnt']) : 0;
    } else {
        $successCheck = $conn_heavy->query("SELECT COUNT(*) as cnt FROM mail_blaster WHERE campaign_id = $campaign_id AND status = 'success'" . $mailBlasterFilter);
        $successCount = ($successCheck && $successCheck->num_rows > 0) ? intval($successCheck->fetch_assoc()['cnt']) : 0;
        
        $failedCheck = $conn_heavy->query("SELECT COUNT(*) as cnt FROM mail_blaster WHERE campaign_id = $campaign_id AND status = 'failed' AND attempt_count >= 5" . $mailBlasterFilter);
        $failedCount = ($failedCheck && $failedCheck->num_rows > 0) ? intval($failedCheck->fetch_assoc()['cnt']) : 0;
        
        $pendingCheck = $conn_heavy->query("SELECT COUNT(*) as cnt FROM mail_blaster WHERE campaign_id = $campaign_id AND status IN ('pending', 'failed') AND attempt_count < 5" . $mailBlasterFilter);
        $pendingCount = ($pendingCheck && $pendingCheck->num_rows > 0) ? intval($pendingCheck->fetch_assoc()['cnt']) : 0;
    }
    
    workerLog("Server #$server_id: ✓ Successfully sent: $successCount emails");
    workerLog("Server #$server_id: ✗ Permanently failed (5 attempts): $failedCount emails");
    workerLog("Server #$server_id: ⧗ Still pending retry: $pendingCount emails");
    
    if ($pendingCount == 0 && $failedCount == 0) {
        workerLog("Server #$server_id: ✓✓✓ ALL EMAILS SENT SUCCESSFULLY! ✓✓✓");
    } else if ($pendingCount == 0 && $failedCount > 0) {
        workerLog("Server #$server_id: ✓ All processable emails sent. $failedCount permanently failed after 5 attempts.");
    } else {
        workerLog("Server #$server_id: Round $current_round complete. Remaining for retry: $pendingCount");
    }
    workerLog("Server #$server_id: ========================================");
    
    // Final verification: Check if there are still emails to process
    // COMPREHENSIVE CHECK: Ensure no emails were missed
    workerLog("Server #$server_id: ========================================");
    workerLog("Server #$server_id: FINAL VERIFICATION - Checking for missed emails");
    workerLog("Server #$server_id: ========================================");
    
    // Check emails in queue but not yet sent on Server 2
    // NOTE: Cannot use JOIN across servers - mail_blaster is on Server 2 only
    $finalPendingCheck = $conn_heavy->query("SELECT COUNT(*) as cnt FROM mail_blaster WHERE campaign_id = $campaign_id AND status IN ('pending', 'failed', 'processing') AND attempt_count < 5 LIMIT 1");
    $finalUnclaimed = 0; // Not checking unclaimed since all should be in mail_blaster already
    $finalPending = ($finalPendingCheck && $finalPendingCheck->num_rows > 0) ? 1 : 0;
    
    $finalTotal = $finalUnclaimed + $finalPending;
    
    workerLog("Server #$server_id: Final check results:");
    workerLog("Server #$server_id:   - Unclaimed emails (not in mail_blaster): $finalUnclaimed");
    workerLog("Server #$server_id:   - Pending emails (in mail_blaster): $finalPending");
    workerLog("Server #$server_id:   - Total emails missed: $finalTotal");
    
    if ($finalTotal > 0) {
        workerLog("*** WARNING: Server #$server_id exiting but emails still need processing!");
        workerLog("***   Unclaimed (not in mail_blaster): $finalUnclaimed");
        workerLog("***   Pending (in mail_blaster): $finalPending");
        workerLog("***   Total remaining: $finalTotal");
        workerLog("***   Other workers or restart should handle them.");
        workerLog("***   The campaign cron will restart this worker to process remaining emails.");
    } else {
        workerLog("✓ Server #$server_id: Confirmed ALL emails processed (0 unclaimed, 0 pending)");
        workerLog("✓ Server #$server_id: NO EMAILS MISSED - Perfect execution!");
    }
    
    workerLog("Server #$server_id: ========================================");

    $conn->close();
    exit(0);
        
    function sendEmail($conn, $conn_heavy, $campaign_id, $to_email, $server, $account, $campaign, $csv_list_id = null, $mail_blaster_id = null) {
        if (!filter_var($to_email, FILTER_VALIDATE_EMAIL)) { throw new Exception("Invalid email: $to_email"); }
        
        workerLog("🔧 sendEmail() called with mail_blaster_id=" . ($mail_blaster_id === null ? 'NULL' : $mail_blaster_id));
        
        // STRICT DUPLICATE PREVENTION - 4 LAYERS
        // CRITICAL: Start transaction and lock the row IMMEDIATELY to prevent duplicates
        // ⚠️ NOTE: mail_blaster table is on SERVER 2 ($conn_heavy) - ALL queries below use Server 2
        $conn_heavy->query("START TRANSACTION");
        
        $to_escaped = $conn_heavy->real_escape_string($to_email);
        
        // If we have a specific mail_blaster_id (email was already claimed by fetchNextPending),
        // skip the global duplicate checks and work directly with that record
        if ($mail_blaster_id !== null && $mail_blaster_id > 0) {
            workerLog("✓ Working with pre-claimed mail_blaster record ID: $mail_blaster_id");
            
            // Verify this specific record exists and get its current status
            $recordCheck = $conn_heavy->query("SELECT id, status FROM mail_blaster WHERE id = " . intval($mail_blaster_id) . " AND campaign_id = " . intval($campaign_id) . " LIMIT 1 FOR UPDATE");
            
            if (!$recordCheck || $recordCheck->num_rows === 0) {
                @$conn_heavy->query("ROLLBACK");
                workerLog("❌ ERROR: Pre-claimed mail_blaster record ID $mail_blaster_id not found");
                throw new Exception("Mail blaster record not found");
            }
            
            $record = $recordCheck->fetch_assoc();
            
            // If this specific record is already 'success', don't re-send
            if ($record['status'] === 'success') {
                @$conn_heavy->query("ROLLBACK");
                workerLog("✓ SKIP: Mail blaster record $mail_blaster_id already has status='success' - will NOT re-send");
                throw new Exception("Duplicate prevented: Email already sent successfully");
            }
            
            // Record exists and is not 'success' - proceed with sending
            $rowId = $record['id'];
            workerLog("✓ Pre-claimed record verified: ID=$rowId, status={$record['status']}");
            
        } else {
            // LAYER 0: Ultra-fast pre-check for SUCCESS status (99% of duplicates caught here)
            // CRITICAL: NEVER re-send emails with status='success' - this is the primary duplicate prevention
            // This avoids unnecessary locking for already-sent emails
            $ultraFastCheck = $conn_heavy->query("SELECT COUNT(*) as cnt FROM mail_blaster WHERE campaign_id = " . intval($campaign_id) . " AND to_mail = '$to_escaped' AND status = 'success' LIMIT 1");
            if ($ultraFastCheck && $ultraFastCheck->num_rows > 0) {
                @$conn_heavy->query("ROLLBACK"); // Safe rollback in case transaction was started
                workerLog("✓ SKIP: Email $to_email already sent successfully - will NOT re-send");
                throw new Exception("Duplicate prevented: Email already sent successfully");
            }
            
            // LAYER 1: Second check for SUCCESS status (fast abort without lock)
            // Double verification to ensure ZERO chance of re-sending successful emails
            $quickCheck = $conn_heavy->query("SELECT COUNT(*) as cnt FROM mail_blaster WHERE campaign_id = " . intval($campaign_id) . " AND to_mail = '$to_escaped' AND status = 'success' LIMIT 1");
            if ($quickCheck && $quickCheck->num_rows > 0) {
                @$conn_heavy->query("ROLLBACK");
                workerLog("✓ SKIP: Email $to_email already DELIVERED successfully for campaign $campaign_id - will NOT re-send");
                throw new Exception("Duplicate prevented: Email already sent successfully");
            }
        
            // LAYER 2: Lock the row and check detailed status (with timeout handling)
            // 🔒 MULTI-USER OPTIMIZATION: Short lock timeout (2s) for 100+ concurrent users
            // Fail fast instead of waiting - prevents cascading delays across users
            $conn_heavy->query("SET SESSION innodb_lock_wait_timeout = 2"); // 2 second lock timeout for fast failover
            $checkExisting = $conn_heavy->query("SELECT COUNT(*) as cnt FROM mail_blaster WHERE campaign_id = " . intval($campaign_id) . " AND to_mail = '$to_escaped' LIMIT 1 FOR UPDATE");
            
            // Handle lock timeout (error 1205) - common with 100+ concurrent users
            if (!$checkExisting && $conn_heavy->errno == 1205) {
                $conn_heavy->query("ROLLBACK");
                workerLog("✓ DUPLICATE PREVENTED (Layer 2 - Lock Timeout): Email $to_email locked by another worker");
                workerLog("   This is normal with concurrent campaigns - email will be processed by other worker");
                throw new Exception("Lock timeout: Email being processed by another worker");
            }
            
            if ($checkExisting && $checkExisting->num_rows > 0) {
                $existing = $checkExisting->fetch_assoc();
                $rowId = $existing['id'];
                
                // LAYER 3: Already sent successfully - ABORT IMMEDIATELY (with row lock still held)
                // CRITICAL: This is the final check - if status='success', NEVER re-send under any circumstances
                if ($existing['status'] === 'success') {
                    $conn_heavy->query("ROLLBACK");
                    workerLog("✅ PROTECTED: Email $to_email already DELIVERED successfully (row ID: $rowId) - WILL NOT RE-SEND");
                    throw  new Exception("Email already delivered successfully - protected from re-sending");
                }
                
                // LAYER 3: Being processed by another worker RIGHT NOW - ABORT
                if (($existing['status'] === 'pending' || $existing['status'] === 'processing') && $existing['smtpid'] != $account['id']) {
                    // Check if delivery_time is recent (within last 90 seconds) - means actively being sent
                    $deliveryTime = strtotime($existing['delivery_time']);
                    $timeDiff = time() - $deliveryTime;
                    if ($timeDiff < 90) { // Increased from 60 to 90 seconds for safety
                        $conn_heavy->query("ROLLBACK");
                        workerLog("✓ DUPLICATE PREVENTED (Layer 3): Email $to_email being processed by worker #{$existing['smtpid']} (row ID: $rowId, started {$timeDiff}s ago) - STRICT PREVENTION");
                        throw new Exception("Duplicate prevented: Email being processed by another worker");
                    }
                }
                
                // Safe to send - Update to mark THIS worker is now sending it
                workerLog("💾 Updating mail_blaster on SERVER 2 for $to_email...");
                $conn_heavy->query("UPDATE mail_blaster SET smtpid = {$account['id']}, delivery_date = CURDATE(), delivery_time = NOW(), status = 'processing' WHERE id = $rowId AND campaign_id = " . intval($campaign_id));
                workerLog("Claimed email $to_email for sending (row ID: $rowId, status: {$existing['status']} → processing)");
            } else {
                // No existing record - INSERT with INSERT IGNORE (respects UNIQUE constraint)
                // This protects against race condition where two workers try to insert simultaneously
                // CRITICAL: Always set status explicitly ('processing'), NEVER NULL
                workerLog("💾 Inserting into mail_blaster on SERVER 2 for $to_email...");
                $insertResult = $conn_heavy->query("INSERT IGNORE INTO mail_blaster (campaign_id, to_mail, csv_list_id, smtpid, delivery_date, delivery_time, status, attempt_count) VALUES (" . intval($campaign_id) . ", '$to_escaped', " . ($csv_list_id ? intval($csv_list_id) : "NULL") . ", {$account['id']}, CURDATE(), NOW(), 'processing', 0)");
                
                if ($conn_heavy->affected_rows === 0) {
                    // INSERT IGNORE failed = duplicate already exists (created by another worker)
                    $conn_heavy->query("ROLLBACK");
                    workerLog("✓ DUPLICATE PREVENTED (Layer 1 - UNIQUE Constraint): Email $to_email already exists in queue");
                    throw new Exception("Duplicate prevented: Email already in queue");
                }
                workerLog("New email queued: $to_email (INSERT successful)");
            }
        }
        
        // CRITICAL FIX: Commit transaction IMMEDIATELY after claiming row
        // DO NOT hold lock during email send (can take 5-10+ seconds)
        // The row status='processing' prevents other workers from claiming it
        $conn_heavy->query("COMMIT");
        workerLog("✓ Transaction committed, row claimed with status='processing'");
        
        // === DETAILED EMAIL SEND LOGGING ===
        workerLog("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        workerLog("📧 PREPARING TO SEND EMAIL");
        workerLog("   To: $to_email");
        workerLog("   Campaign ID: $campaign_id");
        workerLog("   SMTP Account: {$account['email']} (ID: {$account['id']})");
        workerLog("   SMTP Server: {$server['host']}:{$server['port']} (Encryption: {$server['encryption']})");
        workerLog("   Database: Using SERVER 2 (conn_heavy) for mail_blaster updates");
        workerLog("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        
        workerLog("🔧 Initializing PHPMailer...");
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $server['host'];
        $mail->Port = $server['port'];
        $mail->SMTPAuth = true;
        $mail->Username = $account['email'];
        $mail->Password = str_repeat('*', strlen($account['password'])); // Don't log actual password
        workerLog("   ✓ SMTP Host: {$mail->Host}:{$mail->Port}");
        workerLog("   ✓ SMTP Username: {$mail->Username}");
        workerLog("   ✓ SMTP Auth: Enabled");
        $mail->Password = $account['password']; // Set actual password after logging
        $mail->Timeout = 10; // EXTREME SPEED: Reduced to 10 seconds for faster failure detection
        $mail->SMTPDebug = 0;
        $mail->SMTPKeepAlive = true; // OPTIMIZED: Enable connection reuse for speed
        if ($server['encryption'] === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            workerLog("   ✓ Encryption: SSL/TLS (SMTPS)");
        } elseif ($server['encryption'] === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            workerLog("   ✓ Encryption: STARTTLS");
        }
        $mail->SMTPOptions = ['ssl' => ['verify_peer' => false,'verify_peer_name' => false,'allow_self_signed' => true,'crypto_method' => STREAM_CRYPTO_METHOD_TLS_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT]];
        $mail->SMTPAutoTLS = true;
        workerLog("   ✓ SSL Options: Configured (allow self-signed)");
        $mail->setFrom($account['email']);
        workerLog("   ✓ From: {$account['email']}");
        $mail->addAddress($to_email);
        workerLog("   ✓ To: $to_email");
        
        // Ensure SERVER 1 connection is alive before template merge (accesses mail_templates, imported_recipients)
        ensureConnectionAlive($conn, 'SERVER 1');
        
        // Process campaign body - use template merge if template_id is set, otherwise use regular mail_body
        $body = processCampaignBody($conn, $campaign, $to_email, $csv_list_id);
        
        // VALIDATE: Ensure body is not empty/whitespace-only
        $bodyTrimmed = trim($body);
        if (empty($bodyTrimmed) || strlen($bodyTrimmed) < 10) {
            throw new Exception("Empty or invalid body after template processing (length: " . strlen($bodyTrimmed) . ")");
        }
        
        // VALIDATE: Check for common template merge errors
        if (strpos($body, '[[ERROR') !== false || strpos($body, '{{ERROR') !== false) {
            throw new Exception("Template merge error detected in body");
        }
        
        // Get recipient data for merge fields (used for both subject and body)
        // This fetches data from imported_recipients or emails table and merges extra_data JSON
        $template_id = isset($campaign['template_id']) ? intval($campaign['template_id']) : 0;
        $import_batch_id = isset($campaign['import_batch_id']) ? $campaign['import_batch_id'] : null;
        $email_data = getEmailRowData($conn, $to_email, $csv_list_id, $import_batch_id);
        
        // Process email subject with merge fields support
        // Allows placeholders like [[Name]], [[Amount]], [[Company]] in subject line
        $mail->Subject = mergeTemplateWithData($campaign['mail_subject'], $email_data);
        
        // Rich text editors (like Quill) save HTML as syntax-highlighted code
        // wrapped in <p><span style="color:...">tokens</span></p>
        // We need to: 1) Strip color formatting spans, 2) Decode entities, 3) Extract actual HTML
        
        // Remove Quill/rich text editor's syntax highlighting spans
        // Pattern: <span style="color: rgb(...);"> wrapping each HTML token
        $body = preg_replace('/<span\s+style=["\']color:\s*rgb\([^)]+\);?["\']>([^<]*)<\/span>/i', '$1', $body);
        
        // Also remove any remaining <span> tags without content preservation
        $body = preg_replace('/<\/?span[^>]*>/i', '', $body);
        
        // Remove wrapping <p> tags that Quill adds
        $body = preg_replace('/^<p>(.*)<\/p>$/is', '$1', trim($body));
        $body = str_replace(['<p>', '</p>'], ['', ''], $body);
        
        // Now decode HTML entities (converts &lt; → <, &gt; → >, &quot; → ", etc.)
        $body = html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Auto-detect HTML if text contains tags; allow explicit send_as_html to force
        $detectedHtml = bodyLooksHtml($body);
        $isHtml = (!empty($campaign['send_as_html'])) || $detectedHtml;
        $mail->isHTML($isHtml);

        // Then set charset and encoding
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->XMailer = ' ';
        
        // FORCE Content-Type for HTML emails (belt and suspenders approach)
        if ($isHtml) {
            $mail->ContentType = 'text/html';
        }
        
        // Add custom headers AFTER isHTML
        $mail->addCustomHeader('X-Priority','3');
        $mail->addCustomHeader('Importance','Normal');
        
        if (!empty($campaign['reply_to'])) { $mail->clearReplyTos(); $mail->addReplyTo($campaign['reply_to']); }
        elseif (!empty($server['received_email'])) { $mail->clearReplyTos(); $mail->addReplyTo($server['received_email']); }
        if (!empty($campaign['attachment_path'])) {
            $paths = preg_split('/[,\n\r]+/', (string)$campaign['attachment_path']);
            foreach ($paths as $p) {
                $p = trim($p);
                if ($p === '') continue;
                $resolved = resolve_mail_file_path($p);
                if ($resolved && file_exists($resolved)) { $mail->addAttachment($resolved); }
            }
        }
        // Debug logging
        workerLog("Sending email - send_as_html: {$campaign['send_as_html']}, detectedHtml: " . ($detectedHtml ? 'true' : 'false') . ", isHTML: " . ($isHtml ? 'true' : 'false') . ", ContentType: {$mail->ContentType}");
        
        // Process body for HTML emails
        list($processedBody, $embeddedCount) = embed_local_images($mail, $body);
        
        // If we have embedded images, force HTML mode
        if ($embeddedCount > 0 && !$isHtml) { 
            $mail->isHTML(true); 
            $isHtml = true;
            workerLog("Forced HTML mode due to embedded images");
        }
        
        // Use PHPMailer's msgHTML to set HTML body and AltBody correctly
        if ($isHtml) {
            $mail->msgHTML($processedBody);
            // Ensure plain-text alternative exists
            if (empty($mail->AltBody)) { $mail->AltBody = strip_tags($processedBody); }
            // Force ContentType again after msgHTML (belt and suspenders)
            $mail->ContentType = 'text/html';
        } else {
            // Plain text campaign
            $mail->isHTML(false);
            $mail->Body = strip_tags($processedBody);
        }
        
        workerLog("Final ContentType before send: {$mail->ContentType}");
        workerLog("📤 SENDING EMAIL:");
        workerLog("   To: $to_email");
        workerLog("   From: {$account['email']} (Account ID: {$account['id']})");
        workerLog("   SMTP Server: {$server['host']}:{$server['port']} (Encryption: {$server['encryption']})");
        workerLog("   SMTP Username: {$account['email']}");
        workerLog("   Subject: {$mail->Subject}");
        workerLog("   Campaign ID: $campaign_id");
        
        // Send the email - transaction already committed, no lock held
        workerLog("   🔌 Connecting to SMTP server...");
        if (!$mail->send()) {
            // Send failed - record failure (no rollback needed, transaction already committed)
            workerLog("❌ SEND FAILED to $to_email: {$mail->ErrorInfo}");
            throw new Exception($mail->ErrorInfo);
        }
        
        workerLog("✅ SEND SUCCESS to $to_email");
        $via_info = "via {$account['email']} (Table: smtp_accounts on SERVER 2, SMTP: {$server['host']})";
        workerLog("SUCCESS: Sent to $to_email $via_info");

        // Send successful - record delivery on SERVER 2
        $srvId = isset($server['server_id']) ? intval($server['server_id']) : 0;
        workerLog("💾 Recording delivery on SERVER 2 (mail_blaster table)...");
        workerLog("   Email: $to_email");
        workerLog("   Campaign ID: $campaign_id");
        workerLog("   SMTP Account ID: {$account['id']}");
        workerLog("   SMTP Server ID: $srvId");
        workerLog("   Status: success");
        
        recordDelivery($conn_heavy, $account['id'], $srvId, $campaign_id, $to_email, 'success', null, $csv_list_id);
        
        workerLog("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        workerLog("📊 [SERVER 2] DATABASE UPDATES COMPLETED:");
        workerLog("   ✅ mail_blaster: status='success' for $to_email");
        workerLog("   ✅ smtp_accounts: sent_today+1, total_sent+1");
        workerLog("   ✅ smtp_usage: hourly/daily counters updated");
        workerLog("   ✅ smtp_health: marked healthy");
        workerLog("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        
        // 🔥 PERFORMANCE: Track success for batch update
        global $batch_success_count;
        $batch_success_count++;
        
        // CRITICAL: For small campaigns (<20 emails), update immediately instead of batching
        // This ensures frontend shows correct counts for small test campaigns
        global $conn, $campaign_id, $eligibleCount;
        if (isset($eligibleCount) && $eligibleCount > 0 && $eligibleCount < 20) {
            updateCampaignStatusIncremental($conn, $campaign_id, 1, 0);
            workerLog("✅ [SMALL CAMPAIGN] Immediate campaign_status update on SERVER 1");
        }
    }

    function resolve_mail_file_path($path) {
        $path = trim($path);
        if ($path === '') return null;
        
        // Convert HTTPS/HTTP URLs to local paths
        if (strpos($path, 'https://') === 0 || strpos($path, 'http://') === 0) {
            // Extract path after domain: https://payrollsoft.in/emailvalidationstorage/images/file.jpg -> /emailvalidationstorage/images/file.jpg
            $parsed = parse_url($path);
            if (isset($parsed['path'])) {
                $path = $parsed['path'];
            } else {
                return null;
            }
        }
        
        // Check if it's already an absolute path
        if ($path[0] === '/') { 
            if (file_exists($path)) return $path;
            // Try removing leading /emailvalidationstorage for production
            if (strpos($path, '/emailvalidationstorage/') === 0) {
                $path = substr($path, strlen('/emailvalidationstorage/'));
            }
        }
        
        $candidates = [];
        $rel = ltrim($path, '/');
        $base = __DIR__ . '/../';
        
        // Production server: /var/www/vhosts/payrollsoft.in/httpdocs/emailvalidation/
        $prodBase = '/var/www/vhosts/payrollsoft.in/httpdocs/emailvalidation/';
        
        // Try multiple paths
        $candidates[] = $base . $rel;
        $candidates[] = $base . 'storage/images/' . basename($rel);
        $candidates[] = $base . 'storage/' . $rel;
        $candidates[] = $base . 'public/' . $rel;
        $candidates[] = $prodBase . $rel;
        $candidates[] = $prodBase . 'storage/images/' . basename($rel);
        $candidates[] = $prodBase . 'storage/' . $rel;
        
        foreach ($candidates as $c) { if (file_exists($c)) return $c; }
        return null;
    }

    // Heuristic: returns true if the body appears to contain HTML markup
    function bodyLooksHtml($s) {
        if (!is_string($s)) return false;
        $trimmed = trim($s);
        if ($trimmed === '') return false;
        // Quick check: if removing tags changes the string, it's HTML-like
        if ($trimmed !== strip_tags($trimmed)) return true;
        // Also consider presence of DOCTYPE or common tags
        if (stripos($trimmed, '<!DOCTYPE') !== false) return true;
        if (preg_match('/<\s*(html|head|body|table|div|span|p|a)\b/i', $trimmed)) return true;
        return false;
    }

    function embed_local_images($mail, $html) {
        $count = 0;
        $out = $html;
        if (!is_string($html) || stripos($html, '<img') === false) return [$out, 0];
        $pattern = '/<img\b[^>]*src=["\']?([^"\'>\s]+)["\']?[^>]*>/i';
        if (preg_match_all($pattern, $html, $m)) {
            $seen = [];
            foreach ($m[1] as $src) {
                if (isset($seen[$src])) continue; $seen[$src] = true;
                // Skip data URIs and already embedded images
                if (stripos($src, 'data:') === 0 || stripos($src, 'cid:') === 0) continue;
                
                // NOW we process HTTP/HTTPS URLs by converting to local paths
                $resolved = resolve_mail_file_path($src);
                if ($resolved && file_exists($resolved)) {
                    $cid = 'img' . substr(sha1($resolved . microtime(true)), 0, 12) . '@mail';
                    try { 
                        $mail->addEmbeddedImage($resolved, $cid, basename($resolved)); 
                        $out = str_replace($src, 'cid:' . $cid, $out);
                        $count++;
                        workerLog("Embedded image: $src -> $resolved (cid:$cid)");
                    } catch (\Exception $e) { 
                        workerLog("Failed to embed image $src: " . $e->getMessage());
                        continue; 
                    }
                } else {
                    workerLog("Image not found: $src (resolved: " . ($resolved ?: 'null') . ")");
                }
            }
        }
        return [$out, $count];
    }

    function recordDelivery($conn_heavy, $smtp_account_id, $server_id, $campaign_id, $to_email, $status, $error = null, $csv_list_id = null) {
        // Skip writes if campaign is deleted (check early to avoid unnecessary queries)
        global $conn, $campaign_user_id;
        $existsRes = $conn->query("SELECT 1 FROM campaign_master WHERE campaign_id = " . intval($campaign_id) . " LIMIT 1");
        if (!$existsRes || $existsRes->num_rows === 0) {
            return;
        }
        
        // Fetch the SMTP email address from smtp_account_id
        $smtp_email = '';
        $emailQuery = $conn_heavy->query("SELECT email FROM smtp_accounts WHERE id = " . intval($smtp_account_id) . " LIMIT 1");
        if ($emailQuery && $emailQuery->num_rows > 0) {
            $smtp_email = $emailQuery->fetch_assoc()['email'];
        }
        
        // Check existing record status to avoid overwriting success with failure
        $wasAlreadySuccess = false;
        $existingAttempts = 0;
        $to_escaped = $conn_heavy->real_escape_string($to_email);
        $checkStmt = $conn_heavy->prepare("SELECT status, attempt_count FROM mail_blaster WHERE campaign_id = ? AND to_mail = ? LIMIT 1");
        $checkStmt->bind_param("is", $campaign_id, $to_email);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        if ($checkResult->num_rows > 0) {
            $existing = $checkResult->fetch_assoc();
            $existingAttempts = intval($existing['attempt_count']);
            if ($existing['status'] === 'success') {
                $wasAlreadySuccess = true;
                // Email already sent successfully - skip this duplicate attempt
                workerLog("Skipping duplicate recordDelivery for $to_email - already marked success");
                $checkStmt->close();
                return;
            }
        }
        $checkStmt->close();
        
        // Get campaign user_id for tracking
        $campaign_user_id = isset($GLOBALS['campaign_user_id']) ? intval($GLOBALS['campaign_user_id']) : 0;
        
        // OPTIMIZED: Update existing record instead of inserting duplicates - reduces DB storage
        // ON DUPLICATE KEY UPDATE ensures single record per campaign+email combination
        // REQUIRES: UNIQUE KEY unique_campaign_email (campaign_id, to_mail) on mail_blaster table
        // attempt_count: starts at 1, max 5 attempts with different SMTP accounts
        // CRITICAL: After 5 failed attempts, mark as permanent failure (not counted in pending)
        $newAttemptCount = min($existingAttempts + 1, 5);
        $finalStatus = $status;
        
        // If this is 5th attempt and still failed, mark as permanent failure
        // This ensures the email is NOT counted in pending_emails for campaign_status
        if ($newAttemptCount >= 5 && $status === 'failed') {
            $finalStatus = 'failed'; // Keep as 'failed' but with attempt_count = 5
            workerLog("✗ Email $to_email PERMANENTLY FAILED after 5 attempts - will be excluded from retries");
        } else if ($status === 'failed') {
            workerLog("⧗ Email $to_email FAILED (attempt $newAttemptCount/5) - will retry");
        }
        
        workerLog("💾 Recording delivery result in mail_blaster on SERVER 2: $to_email (status=$finalStatus, attempt=$newAttemptCount)");
        
        // CRITICAL FIX: Use conn_heavy (Server 2) for mail_blaster table, not conn (Server 1)
        $stmt = $conn_heavy->prepare("
            INSERT INTO mail_blaster 
                (campaign_id, smtp_account_id, smtp_email, to_mail, csv_list_id, smtpid, delivery_date, delivery_time, status, error_message, attempt_count, user_id) 
            VALUES 
                (?, ?, ?, ?, ?, ?, CURDATE(), NOW(), ?, ?, 1, ?) 
            ON DUPLICATE KEY UPDATE 
                smtp_account_id = VALUES(smtp_account_id),
                smtp_email = VALUES(smtp_email),
                csv_list_id = VALUES(csv_list_id), 
                smtpid = VALUES(smtpid), 
                delivery_date = VALUES(delivery_date), 
                delivery_time = VALUES(delivery_time), 
                status = VALUES(status),
                error_message = IF(VALUES(status) = 'success', NULL, CONCAT('[Attempt ', mail_blaster.attempt_count + 1, '] ', VALUES(error_message))),
                attempt_count = LEAST(mail_blaster.attempt_count + 1, 5),
                user_id = VALUES(user_id)
        ");
        
        // Bind parameters: campaign_id, smtp_account_id, smtp_email, to_mail, csv_list_id, smtpid, status, error_message, user_id
        $stmt->bind_param("iissiissi", 
            $campaign_id, 
            $smtp_account_id, 
            $smtp_email,
            $to_email, 
            $csv_list_id, 
            $smtp_account_id, 
            $finalStatus, 
            $error, 
            $campaign_user_id
        );
        
        // RETRY LOGIC: Handle lock timeout errors (errno 1205) with exponential backoff
        // 🔒 MULTI-USER OPTIMIZATION: Fast retries for 100+ concurrent users
        $maxRetries = 5;
        $retryAttempt = 0;
        $executeSuccess = false;
        $affectedRows = 0;
        
        while ($retryAttempt < $maxRetries && !$executeSuccess) {
            try {
                // Set a shorter lock timeout for high-concurrency scenarios (SERVER 2)
                // With 100+ users, we want to fail fast and retry instead of long waits
                @$conn_heavy->query("SET SESSION innodb_lock_wait_timeout = 5");
                
                $executeSuccess = $stmt->execute();
                $affectedRows = $stmt->affected_rows;
                break; // Success - exit retry loop
                
            } catch (mysqli_sql_exception $e) {
                $errno = $conn_heavy->errno;
                
                // Retry on lock wait timeout (1205) or deadlock (1213)
                if (($errno === 1205 || $errno === 1213) && $retryAttempt < $maxRetries - 1) {
                    $retryAttempt++;
                    // Faster backoff for multi-user: 50ms, 100ms, 200ms, 400ms, 800ms
                    $backoffMs = min(50 * pow(2, $retryAttempt), 1000);
                    workerLog("Lock timeout for $to_email (errno: $errno), retry $retryAttempt/$maxRetries after {$backoffMs}ms");
                    usleep($backoffMs * 1000); // Convert to microseconds
                    
                    // Yield CPU to other processes
                    if (function_exists('gc_collect_cycles')) {
                        @gc_collect_cycles();
                    }
                    continue;
                } else {
                    // Non-retryable error or max retries reached
                    workerLog("ERROR recording delivery for $to_email after $retryAttempt retries: " . $e->getMessage());
                    $stmt->close();
                    return; // Exit function - don't crash worker
                }
            }
        }
        
        $stmt->close();
        
        // Log update vs insert for debugging
        if ($affectedRows === 1) {
            workerLog("✅ [SERVER 2] Inserted new mail_blaster record: $to_email, status=$status");
        } elseif ($affectedRows === 2) {
            workerLog("✅ [SERVER 2] Updated mail_blaster record: $to_email, attempt=" . ($existingAttempts + 1) . ", status=$status");
        }
        
        // Only increment counters for NEW successful sends (not retries of already-successful emails)
        if ($status === 'success' && !$wasAlreadySuccess) {
            workerLog("📊 [SERVER 2] Updating SMTP counters for successful send...");
            
            // Update SMTP account counters - with retry logic for lock contention
            $counterRetries = 3;
            $counterAttempt = 0;
            $counterSuccess = false;
            
            while ($counterAttempt < $counterRetries && !$counterSuccess) {
                try {
                    // Set shorter timeout for counter updates
                    @$conn_heavy->query("SET SESSION innodb_lock_wait_timeout = 5");
                    
                    $conn_heavy->begin_transaction();
                    
                // UPDATE SERVER 2: smtp_accounts counter
                $updateAccounts = $conn_heavy->query("UPDATE smtp_accounts SET sent_today = sent_today + 1, total_sent = total_sent + 1 WHERE id = $smtp_account_id");
                if ($updateAccounts) {
                    workerLog("   ✅ [SERVER 2] smtp_accounts updated: sent_today+1, total_sent+1 for account #$smtp_account_id ($smtp_email)");
                }
                
                // OPTIMIZED: Update existing hourly record instead of creating duplicates
                // CRITICAL: smtp_usage stored on SERVER 2 ONLY ($conn_heavy)
                $usage_date = date('Y-m-d'); 
                $usage_hour = (int)date('G'); 
                $now = date('Y-m-d H:i:s');
                
                // Lock the row to prevent race conditions across multiple workers/campaigns
                $usageUpdate = $conn_heavy->query("
                    INSERT INTO smtp_usage (smtp_id, date, hour, timestamp, emails_sent, user_id) 
                    VALUES ($smtp_account_id, '$usage_date', $usage_hour, '$now', 1, $campaign_user_id) 
                    ON DUPLICATE KEY UPDATE 
                        emails_sent = emails_sent + 1, 
                        timestamp = VALUES(timestamp)
                ");
                
                if ($usageUpdate) {
                    workerLog("   ✅ [SERVER 2] smtp_usage updated: account #$smtp_account_id, date=$usage_date, hour=$usage_hour, user_id=$campaign_user_id");
                    workerLog("   📈 Hourly/Daily limits now tracked in smtp_usage table for limit enforcement");
                }
                
                $conn_heavy->commit();
                $counterSuccess = true;
                workerLog("✅ [SERVER 2] Transaction committed - All counters updated successfully");
                workerLog("✅ Updated smtp_usage on SERVER 2: smtp_id=$smtp_account_id, hour=$usage_hour, user_id=$campaign_user_id");
                } catch (Exception $e) {
                    if ($conn_heavy->connect_errno === 0) { $conn_heavy->rollback(); }
                    $counterAttempt++;
                    if ($counterAttempt < $counterRetries) {
                        $backoffMs = 50 * $counterAttempt; // 50ms, 100ms, 150ms
                        workerLog("Counter update lock timeout, retry $counterAttempt/$counterRetries after {$backoffMs}ms");
                        usleep($backoffMs * 1000);
                    } else {
                        workerLog("Failed to update counter after $counterRetries attempts: " . $e->getMessage());
                    }
                }
            }
            
            // Even if counter update fails, continue - don't block email sending
            if (!$counterSuccess) {
                workerLog("WARNING: SMTP counters not updated for $to_email, but email was sent successfully");
                workerLog("Failed to update smtp_usage: " . $e->getMessage());
            }
        }
        
        // 🔥 PERFORMANCE: Removed checkCampaignCompletion() - was killing performance at scale
        // Now using incremental batch updates in worker loop (every 50 emails)
        
        // Update SMTP health
        if ($status === 'success') {
            $conn_heavy->query("INSERT INTO smtp_health (smtp_id, health, consecutive_failures, last_success_at, updated_at) 
                VALUES ($smtp_account_id, 'healthy', 0, NOW(), NOW())
                ON DUPLICATE KEY UPDATE 
                    health = IF(suspend_until IS NULL OR suspend_until < NOW(), 'healthy', health),
                    consecutive_failures = 0, 
                    last_success_at = NOW(),
                    suspend_until = NULL,
                    updated_at = NOW()");
        } else {
            // IMPROVED: Track failure and update health status (only for SMTP-related failures)
            $error_type = 'unknown';
            $is_smtp_failure = false; // Only SMTP account issues should affect health
            
            if ($error && (stripos($error, 'authenticate') !== false || stripos($error, 'login') !== false)) {
                $error_type = 'auth_failed';
                $is_smtp_failure = true;
            } elseif ($error && (stripos($error, 'connect') !== false || stripos($error, 'refused') !== false)) {
                $error_type = 'connection_failed';
                $is_smtp_failure = true;
            } elseif ($error && (stripos($error, 'timeout') !== false || stripos($error, 'timed out') !== false)) {
                $error_type = 'timeout';
                $is_smtp_failure = true;
            } elseif ($error && (stripos($error, 'tls') !== false || stripos($error, 'ssl') !== false || stripos($error, 'certificate') !== false)) {
                $error_type = 'tls_failed';
                $is_smtp_failure = true;
            } else {
                // Recipient-related errors (550, 551, 552, 553, 554, mailbox full, user unknown, etc.)
                // These should NOT affect SMTP account health
                $error_type = 'recipient_error';
                $is_smtp_failure = false;
            }
            
            $safe_error = $conn_heavy->real_escape_string(substr($error, 0, 500));
            
            // Only update health if this is an SMTP-related failure
            if ($is_smtp_failure) {
                $conn_heavy->query("INSERT INTO smtp_health (smtp_id, health, consecutive_failures, last_failure_at, last_error_type, last_error_message, updated_at) 
                    VALUES ($smtp_account_id, 'healthy', 1, NOW(), '$error_type', '$safe_error', NOW())
                    ON DUPLICATE KEY UPDATE 
                        consecutive_failures = consecutive_failures + 1,
                        last_failure_at = NOW(),
                        last_error_type = '$error_type',
                        last_error_message = '$safe_error',
                        health = CASE 
                            WHEN consecutive_failures + 1 >= 20 AND '$error_type' != 'timeout' THEN 'suspended'
                            WHEN consecutive_failures + 1 >= 30 AND '$error_type' = 'timeout' THEN 'suspended'
                            WHEN consecutive_failures + 1 >= 10 THEN 'degraded'
                            ELSE 'healthy'
                        END,
                        suspend_until = CASE
                            WHEN consecutive_failures + 1 >= 20 AND '$error_type' != 'timeout' THEN DATE_ADD(NOW(), INTERVAL 30 MINUTE)
                            WHEN consecutive_failures + 1 >= 30 AND '$error_type' = 'timeout' THEN DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                            ELSE suspend_until
                        END,
                        updated_at = NOW()");
            } else {
                // Recipient errors don't affect SMTP health, just log them
                $conn_heavy->query("INSERT INTO smtp_health (smtp_id, last_error_type, last_error_message, updated_at) 
                    VALUES ($smtp_account_id, '$error_type', '$safe_error', NOW())
                    ON DUPLICATE KEY UPDATE 
                        last_error_type = '$error_type',
                        last_error_message = '$safe_error',
                        updated_at = NOW()");
            }
        }
    }

    function accountWithinLimits($conn_heavy, $account_id) {
        // CRITICAL: All SMTP data queries from SERVER 2 ($conn_heavy)
        $res = $conn_heavy->query("SELECT email,daily_limit,hourly_limit FROM smtp_accounts WHERE id = $account_id");
        if (!$res || $res->num_rows === 0) {
            return false;
        }
        $row = $res->fetch_assoc();
        
        // Check daily limit using smtp_usage table (sum all hours for today)
        // CRITICAL: smtp_usage stored on SERVER 2 ONLY
        $today = date('Y-m-d');
        $dailyResult = $conn_heavy->query("SELECT COALESCE(SUM(emails_sent), 0) as sent_today FROM smtp_usage WHERE smtp_id = $account_id AND date = '$today'");
        $sent_today = ($dailyResult && $dailyResult->num_rows > 0) ? intval($dailyResult->fetch_assoc()['sent_today']) : 0;
        
        $daily_limit = intval($row['daily_limit']);
        if ($daily_limit > 0 && $sent_today >= $daily_limit) {
            workerLog("⚠️  [LIMIT REACHED] Account #{$account_id} ({$row['email']}): Daily limit $sent_today/$daily_limit");
            return false;
        }
        
        // Check hourly limit using smtp_usage table (current hour only)
        // CRITICAL: smtp_usage stored on SERVER 2 ONLY
        $current_hour = intval(date('G')); // 0-23
        $hourlyResult = $conn_heavy->query("SELECT emails_sent FROM smtp_usage WHERE smtp_id = $account_id AND date = '$today' AND hour = $current_hour");
        $sent_this_hour = ($hourlyResult && $hourlyResult->num_rows > 0) ? intval($hourlyResult->fetch_assoc()['emails_sent']) : 0;
        
        $hourly_limit = intval($row['hourly_limit']);
        if ($hourly_limit > 0 && $sent_this_hour >= $hourly_limit) {
            workerLog("⚠️  [LIMIT REACHED] Account #{$account_id} ({$row['email']}): Hourly limit $sent_this_hour/$hourly_limit (hour $current_hour)");
            return false;
        }
        
        // Log current usage if within limits
        workerLog("✅ [WITHIN LIMITS] Account #{$account_id} ({$row['email']}): Daily=$sent_today/$daily_limit, Hourly=$sent_this_hour/$hourly_limit");
        return true;
    }

    function switchToBackupServer($conn_heavy, $current_server_id, $campaign_user_id) {
        workerLog("Switching from failed server #$current_server_id to backup server...");
        
        // Get all active servers for this user EXCEPT the failed one - query SERVER 2
        $query = "SELECT ss.id, ss.name, ss.host, ss.port, ss.encryption, ss.received_email 
            FROM smtp_servers ss 
            WHERE ss.is_active = 1 
            AND ss.id != $current_server_id";
        
        if ($campaign_user_id > 0) {
            $query .= " AND EXISTS (
                SELECT 1 FROM smtp_accounts sa 
                WHERE sa.smtp_server_id = ss.id 
                AND sa.user_id = $campaign_user_id 
                AND sa.is_active = 1
            )";
        }
        
        $query .= " ORDER BY ss.id ASC LIMIT 1";
        
        $result = $conn_heavy->query($query);
        if ($result && $result->num_rows > 0) {
            $server = $result->fetch_assoc();
            workerLog("✓ Switched to backup server #{$server['id']} ({$server['name']})");
            return [
                'server_id' => (int)$server['id'],
                'host' => $server['host'],
                'port' => $server['port'],
                'encryption' => $server['encryption'],
                'received_email' => $server['received_email']
            ];
        }
        
        workerLog("✗ No backup servers available");
        return null;
    }

    function loadActiveAccountsForServer($conn_heavy, $server_id, $user_id = 0) {
        // SMTP accounts are on Server 2 (conn_heavy)
        // First try to load healthy accounts only - FILTERED BY USER
        $accounts = [];
        $user_filter = ($user_id > 0) ? " AND sa.user_id = $user_id" : "";
        
        workerLog("🔍 Loading SMTP accounts from SERVER 2 (conn_heavy)...");
        workerLog("   Server ID: $server_id");
        workerLog("   User filter: " . ($user_id > 0 ? "user_id = $user_id" : "ALL USERS"));
        
        $healthyRes = $conn_heavy->query("
            SELECT sa.id, sa.email, sa.password, sa.daily_limit, sa.hourly_limit, sa.sent_today, sa.total_sent, sa.user_id
            FROM smtp_accounts sa
            LEFT JOIN smtp_health sh ON sa.id = sh.smtp_id
            WHERE sa.smtp_server_id = $server_id 
            AND sa.is_active = 1
            $user_filter
            AND (sh.health IS NULL OR sh.health = 'healthy' OR (sh.health = 'suspended' AND sh.suspend_until < NOW()))
            ORDER BY sa.id ASC
        ");
        
        if ($healthyRes) {
            while ($r = $healthyRes->fetch_assoc()) {
                $accounts[] = $r;
                workerLog("   ✓ Loaded account #{$r['id']}: {$r['email']} (user_id: " . ($r['user_id'] ?: 'NULL') . ")");
            }
        } else {
            workerLog("   ❌ Query failed: " . $conn_heavy->error);
        }
        
        // 🔒 MULTI-USER SAFETY: Strict user isolation - NEVER use other users' SMTP accounts
        // This ensures 100+ concurrent users can run campaigns without interfering with each other
        if (empty($accounts) && $user_id > 0) {
            workerLog("❌ CRITICAL: No SMTP accounts found for user #$user_id on server #$server_id");
            workerLog("   MULTI-USER ISOLATION: Will NOT use other users' accounts (strict isolation enforced)");
            workerLog("   Please ensure user #$user_id has SMTP accounts configured in smtp_accounts table");
            workerLog("   Required: smtp_accounts.user_id = $user_id AND smtp_accounts.smtp_server_id = $server_id");
            // Return empty array - do NOT fall back to other users' accounts
        }
        
        // If still no healthy accounts available, load degraded accounts as fallback (user-specific only)
        if (empty($accounts)) {
            workerLog("No healthy accounts found for server #$server_id" . ($user_id > 0 ? " (user #$user_id)" : "") . ", falling back to degraded accounts");
            $degradedRes = $conn_heavy->query("
                SELECT sa.id, sa.email, sa.password, sa.daily_limit, sa.hourly_limit, sa.sent_today, sa.total_sent, sa.user_id
                FROM smtp_accounts sa
                JOIN smtp_health sh ON sa.id = sh.smtp_id
                WHERE sa.smtp_server_id = $server_id 
                AND sa.is_active = 1
                $user_filter
                AND sh.health = 'degraded'
                ORDER BY sa.id ASC
            ");
            
            if ($degradedRes) {
                while ($r = $degradedRes->fetch_assoc()) {
                    $accounts[] = $r;
                    workerLog("   ✓ Degraded account #{$r['id']}: {$r['email']} (user_id: " . ($r['user_id'] ?: 'NULL') . ")");
                }
            }
        }
        
        workerLog("📊 Total accounts loaded: " . count($accounts) . " for server #$server_id" . ($user_id > 0 ? " (user #$user_id)" : ""));
        
        return $accounts;
    }

    function ensureMailBlasterUniqueIndex($conn_heavy) {
        // Check if index already exists
        $result = $conn_heavy->query("SHOW INDEX FROM mail_blaster WHERE Key_name = 'uq_campaign_email'");
        if ($result && $result->num_rows > 0) {
            return; // Index already exists
        }
        // Do NOT alter schema at runtime per ops directive
    }

    /**
     * claimNextEmail - DEPRECATED: Migration now handled by orchestrator bulk migration.
     * Kept as a shell for compatibility with current loop structure.
     */
    function claimNextEmail($conn_heavy, $campaign_id, $smtp_account_id, $depth = 0) {
        // All recipients are now pre-migrated by the orchestrator.
        // fetchNextPending() is the primary high-speed way to get work.
        return null;
    }


    function fetchNextPending($conn_heavy, $campaign_id, $server_id) {
        workerLog("🔍 fetchNextPending: Querying mail_blaster on SERVER 2...");
        workerLog("   Campaign ID: $campaign_id, Server ID: $server_id");
        
        // CRITICAL: This query EXCLUDES status='success' - only processes pending/failed emails
        // ALSO: Includes 'processing' emails that have timed out (> 60s)
        $quickCheck = $conn_heavy->query("SELECT COUNT(*) as cnt FROM mail_blaster WHERE campaign_id = $campaign_id 
            AND status NOT IN ('success')
            AND (
                (status IN ('pending', 'failed') AND attempt_count < 5) 
                OR (status = 'processing' AND delivery_time < DATE_SUB(NOW(), INTERVAL 60 SECOND) AND attempt_count < 5)
            ) LIMIT 1");
        
        if (!$quickCheck) {
            workerLog("   ❌ Quick check query failed: " . $conn_heavy->error);
            return null;
        }
        
        if ($quickCheck->num_rows === 0) {
            workerLog("   ℹ️  No pending/failed/stuck-processing emails in queue");
            return null;
        }
        
        $checkData = $quickCheck->fetch_assoc();
        $pendingCount = (int)$checkData['cnt'];
        workerLog("   ✓ Found $pendingCount pending/failed/stuck emails (excluding already sent)");
        
        // CRITICAL: Use transaction with FOR UPDATE and immediate status change to prevent race conditions
        // Compatible with older MariaDB versions (pre-10.6) that don't support SKIP LOCKED
        $conn_heavy->query("START TRANSACTION");
        
        // Fetch next pending/failed/stuck-processing email that hasn't exceeded 5 retry attempts
        // CRITICAL: This query EXCLUDES status='success' emails - they are NEVER re-processed
        // Fetch next pending/failed/stuck-processing email that hasn't exceeded 5 retry attempts
        // Include 'processing' status if delivery_time is old (crashed worker recovery)
        $query = "SELECT id, to_mail, attempt_count, smtpid, csv_list_id FROM mail_blaster ";
        $query .= "WHERE campaign_id = $campaign_id ";
        $query .= "AND status NOT IN ('success') "; // CRITICAL: Explicitly exclude successfully sent emails
        $query .= "AND (";
        $query .= "  (status IN ('pending', 'failed') AND attempt_count < 5) ";
        $query .= "  OR (status = 'processing' AND delivery_time < DATE_SUB(NOW(), INTERVAL 60 SECOND) AND attempt_count < 5)";
        $query .= ") ";
        $query .= "ORDER BY attempt_count ASC, delivery_date ASC, id ASC LIMIT 1 ";
        $query .= "FOR UPDATE"; // Lock the row
        
        workerLog("   🔒 Attempting to lock next email...");
        $res = $conn_heavy->query($query);
        
        if (!$res) {
            $conn_heavy->query("ROLLBACK");
            workerLog("   ❌ Lock query failed: " . $conn_heavy->error);
            return null;
        }
        
        if ($res->num_rows > 0) {
            $row = $res->fetch_assoc();
            
            // Log the current status before updating
            workerLog("   📋 Email details BEFORE update:");
            workerLog("      Email: {$row['to_mail']}");
            workerLog("      Current status: Will query to show actual status...");
            
            // Quick check: what is the actual current status?
            $statusCheckQuery = "SELECT status FROM mail_blaster WHERE id = {$row['id']}";
            $statusCheck = $conn_heavy->query($statusCheckQuery);
            if ($statusCheck && $statusCheck->num_rows > 0) {
                $currentStatus = $statusCheck->fetch_assoc()['status'];
                workerLog("      ⚠️  CURRENT STATUS IN DB: '$currentStatus'");
                
                // If already success, abort!
                if ($currentStatus === 'success') {
                    $conn_heavy->query("ROLLBACK");
                    workerLog("   ❌ ABORT: Email already has status='success' - will NOT claim!");
                    return null;
                }
            }
            
            // IMMEDIATE UPDATE: Change status to 'processing' to prevent other workers from selecting it
            // Also update delivery_time and smtpid to track which worker/server is handling it
            $email_id = (int)$row['id'];
            $updateQuery = "UPDATE mail_blaster SET status = 'processing', delivery_time = NOW(), smtpid = $server_id ";
            $updateQuery .= "WHERE id = $email_id AND campaign_id = $campaign_id";
            $conn_heavy->query($updateQuery);
            
            $conn_heavy->query("COMMIT");
            workerLog("   ✅ Claimed email {$row['to_mail']} (ID: {$email_id}, attempt #{$row['attempt_count']}, csv_list_id={$row['csv_list_id']})");
            return ['to_mail' => $row['to_mail'], 'attempt_count' => $row['attempt_count'], 'csv_list_id' => $row['csv_list_id'], 'mail_blaster_id' => $email_id];
        }
        
        $conn_heavy->query("COMMIT");
        workerLog("   ℹ️  No pending emails available (all locked by other workers)");
        return null;
    }

    function getActiveServerCount($conn_heavy, $campaign_id) {
        // Count active SMTP servers on Server 2
        $res = $conn_heavy->query("SELECT COUNT(*) AS c FROM smtp_servers WHERE is_active = 1");
        $n = ($res && $res->num_rows > 0) ? intval($res->fetch_assoc()['c']) : 0;
        return max(1, $n);
    }

    function assignPendingToAccount($conn_heavy, $campaign_id, $to_mail, $account_id) {
        global $conn;
        if (!$conn && isset($GLOBALS['conn'])) { $conn = $GLOBALS['conn']; }

        $to = $conn_heavy->real_escape_string($to_mail);
        // Only update if campaign still exists - check Server 1
        $existsRes = $conn->query("SELECT 1 FROM campaign_master WHERE campaign_id = " . intval($campaign_id) . " LIMIT 1");
        if ($existsRes && $existsRes->num_rows > 0) {
            // Update pending/failed emails on Server 2 - ensure we don't reassign if already being processed by another worker
            $conn_heavy->query("UPDATE mail_blaster SET smtpid = $account_id, delivery_date = CURDATE(), delivery_time = CURTIME() WHERE campaign_id = $campaign_id AND to_mail = '$to' AND status IN ('pending', 'failed') AND attempt_count < 5");
        }
    }

    // Utility: table existence check (no schema changes)
    function tableExists($conn, $name) {
        $n = $conn->real_escape_string($name);
        $res = @$conn->query("SHOW TABLES LIKE '" . $n . "'");
        return ($res && $res->num_rows > 0);
    }

    // Check if campaign is completed and update status
    // 🔥 PERFORMANCE: New incremental update function (replaces heavy checkCampaignCompletion)
    // ⚠️ Updates campaign_status on SERVER 1 only
    function updateCampaignStatusIncremental($conn, $campaign_id, $success_delta, $failed_delta) {
        if ($success_delta == 0 && $failed_delta == 0) return;
        
        try {
            // Set short lock timeout to prevent blocking frontend queries
            $conn->query("SET SESSION innodb_lock_wait_timeout = 1");
            
            // Incremental counter update on SERVER 1 - NO COUNT queries!
            workerLog("📊 [SERVER 1] Updating campaign_status (incremental)...");
            $conn->query("UPDATE campaign_status 
                SET sent_emails = sent_emails + $success_delta,
                    failed_emails = failed_emails + $failed_delta,
                    pending_emails = GREATEST(0, pending_emails - $success_delta - $failed_delta)
                WHERE campaign_id = $campaign_id");
            workerLog("✅ [SERVER 1] campaign_status incremental update: campaign_id=$campaign_id, +$success_delta sent, +$failed_delta failed");
        } catch (Exception $e) {
            workerLog("⚠ Failed incremental update on SERVER 1: " . $e->getMessage());
            // If lock timeout, skip this update - next batch will include these counts
        }
    }
    
    // 🔥 OLD FUNCTION - Now only called at campaign end (not after every email)
    function checkCampaignCompletion($conn, $campaign_id) {
        global $conn_heavy;
        if (!$conn_heavy && isset($GLOBALS['conn_heavy'])) { $conn_heavy = $GLOBALS['conn_heavy']; }

        // Get campaign source from Server 1
        $campaignRes = $conn->query("SELECT import_batch_id, csv_list_id FROM campaign_master WHERE campaign_id = $campaign_id");
        if (!$campaignRes || $campaignRes->num_rows === 0) {
            return;
        }
        
        $campaignData = $campaignRes->fetch_assoc();
        $import_batch_id = $campaignData['import_batch_id'];
        $csv_list_id = intval($campaignData['csv_list_id']);
        
        // Count emails from mail_blaster (source of truth - ON SERVER 2)
        $statusQuery = "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success_count,
            SUM(CASE WHEN status IN ('failed', 'permanent_failure', 'failed_attempt') AND attempt_count >= 5 THEN 1 ELSE 0 END) as failed_count,
            SUM(CASE WHEN status IN ('pending', 'failed', 'failed_attempt') AND attempt_count < 5 THEN 1 ELSE 0 END) as retryable_count,
            SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing_count
        FROM mail_blaster 
        WHERE campaign_id = $campaign_id";
        
        $statusRes = $conn_heavy->query($statusQuery);
        if (!$statusRes) {
            workerLog("Campaign $campaign_id: Error querying mail_blaster on Server 2");
            return;
        }
        
        $stats = $statusRes->fetch_assoc();
        $total = intval($stats['total']);
        $successCount = intval($stats['success_count']);
        $failedCount = intval($stats['failed_count']);
        $retryableCount = intval($stats['retryable_count']);
        $processingCount = intval($stats['processing_count']);
        
        workerLog("Campaign $campaign_id Stats from Server 2: Total=$total, Success=$successCount, Failed(5+)=$failedCount, Retryable(<5)=$retryableCount, Processing=$processingCount");
        
        // Update campaign_status table on SERVER 1 with accurate counts
        if ($total > 0) {
            try {
                // Set short lock timeout to prevent blocking frontend queries
                $conn->query("SET SESSION innodb_lock_wait_timeout = 1");
                
                workerLog("📊 [SERVER 1] Updating campaign_status with accurate counts...");
                $updateStatsQuery = "UPDATE campaign_status 
                    SET total_emails = $total,
                        sent_emails = $successCount,
                        failed_emails = $failedCount,
                        pending_emails = $retryableCount
                    WHERE campaign_id = $campaign_id";
                $conn->query($updateStatsQuery); // CRITICAL: Use $conn (Server 1), not $conn_heavy
                workerLog("✅ [SERVER 1] campaign_status updated: total=$total, sent=$successCount, failed=$failedCount, pending=$retryableCount");
            } catch (Exception $e) {
                workerLog("⚠ Failed to update campaign_status stats: " . $e->getMessage());
            }
        }
        
        // Check for unclaimed emails (cannot JOIN across servers, just check if we have any left to claim)
        // We assume if claimNextEmail returns null, there are no unclaimed left.
        // For accurate reporting, we compare total entries on Server 1 vs Server 2
        $unclaimed = 0;
        if ($import_batch_id) {
            $batch_escaped = $conn->real_escape_string($import_batch_id);
            $s1CountRes = $conn->query("SELECT COUNT(*) as cnt FROM imported_recipients WHERE import_batch_id = '$batch_escaped' AND is_active = 1");
            $s1Count = ($s1CountRes) ? intval($s1CountRes->fetch_assoc()['cnt']) : 0;
            $unclaimed = max(0, $s1Count - $total);
        } elseif ($csv_list_id > 0) {
            $s1CountRes = $conn->query("SELECT COUNT(*) as cnt FROM emails WHERE csv_list_id = $csv_list_id AND domain_status = 1 AND validation_status = 'valid'");
            $s1Count = ($s1CountRes) ? intval($s1CountRes->fetch_assoc()['cnt']) : 0;
            $unclaimed = max(0, $s1Count - $total);
        }
        
        // Mark as completed when all retries exhausted and no unclaimed emails
        if ($unclaimed <= 0 && $retryableCount === 0 && $processingCount === 0 && $total > 0) {
            workerLog("🏁 Campaign $campaign_id: All emails processed! Marking as COMPLETED.");
            workerLog("📊 [SERVER 1] Updating campaign_status to 'completed'...");
            try {
                // Set short lock timeout to prevent blocking frontend queries
                $conn->query("SET SESSION innodb_lock_wait_timeout = 2");
                
                $conn->begin_transaction();
                $lockCheck = $conn->query("SELECT status FROM campaign_status WHERE campaign_id = $campaign_id FOR UPDATE");
                if ($lockCheck && $lockCheck->num_rows > 0) {
                    $currentStatus = $lockCheck->fetch_assoc()['status'];
                    if ($currentStatus === 'running' || $currentStatus === 'pending') {
                        $conn->query("UPDATE campaign_status SET status = 'completed', end_time = NOW(), process_pid = NULL, pending_emails = 0 WHERE campaign_id = $campaign_id");
                        workerLog("✅ [SERVER 1] Campaign $campaign_id: Successfully marked as COMPLETED.");
                        workerLog("🎉 Final stats: Total=$total, Sent=$successCount, Failed=$failedCount");
                    }
                }
                $conn->commit();
            } catch (Exception $e) {
                if ($conn->connect_errno === 0) { $conn->rollback(); }
                workerLog("Campaign $campaign_id: Error marking as completed: " . $e->getMessage());
            }
        } else {
            workerLog("⌛ Campaign $campaign_id: Still in progress. Retryable=$retryableCount, Processing=$processingCount, Unclaimed=$unclaimed");
        }
    }

