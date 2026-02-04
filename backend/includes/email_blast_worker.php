    <?php

    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception;

    require __DIR__ . '/../vendor/autoload.php';
    require_once __DIR__ . '/template_merge_helper.php';

    error_reporting(E_ALL);
    ini_set('display_errors', 0); // Disable display in production
    ini_set('log_errors', 0); // Disable logging in production
    set_time_limit(0);

    // Ensure consistent timezone for hour-based limits
    date_default_timezone_set('Asia/Kolkata');

    // Worker debug logging (enable/disable here)
    if (!defined('WORKER_LOG_ENABLED')) {
        define('WORKER_LOG_ENABLED', false); // DISABLED
    }
    if (!defined('WORKER_LOG_FILE')) {
        define('WORKER_LOG_FILE', __DIR__ . '/../logs/email_worker_' . date('Y-m-d') . '.log');
    }
    function workerLog($msg) {
        if (!WORKER_LOG_ENABLED) return;
        $dir = dirname(WORKER_LOG_FILE);
        if (!is_dir($dir)) {@mkdir($dir, 0777, true);}    
        $ts = date('Y-m-d H:i:s');
        @file_put_contents(WORKER_LOG_FILE, "[$ts] $msg\n", FILE_APPEND);
        echo "[$ts] $msg\n"; // Also echo to console
    }

    // Catch fatal errors
    register_shutdown_function(function() {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            file_put_contents(__DIR__ . '/../logs/email_worker_fatal.log', '[' . date('Y-m-d H:i:s') . "] FATAL ERROR: {$error['message']} in {$error['file']}:{$error['line']}\n", FILE_APPEND);
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

    workerLog('Worker start argv=' . json_encode($argv));

    $campaign_id = isset($argv[1]) ? intval($argv[1]) : 0;
    $server_config = isset($argv[3]) ? json_decode($argv[3], true) : [];
    $campaign = isset($argv[4]) ? json_decode($argv[4], true) : [];

    if ($campaign_id == 0 || empty($server_config)) {
    //     file_put_contents(__DIR__ . '/../logs/email_worker.log', '[' . date('Y-m-d H:i:s') . "] ERROR: Missing campaign_id or server_config\n", FILE_APPEND);
        exit(1);
    }

    if (empty($campaign) || empty($campaign['mail_subject']) || empty($campaign['mail_body'])) {
    //     file_put_contents(__DIR__ . '/../logs/email_worker.log', '[' . date('Y-m-d H:i:s') . "] ERROR: Campaign data incomplete, will fetch from DB\n", FILE_APPEND);
        // Campaign data missing or incomplete - this is the actual problem!
    }

    require_once __DIR__ . '/../config/db.php';
    if ($conn->connect_error) {
    //     file_put_contents(__DIR__ . '/../logs/email_worker.log', '[' . date('Y-m-d H:i:s') . "] ERROR: DB connection failed\n", FILE_APPEND);
        exit(1);
    }

    // If campaign data is incomplete, fetch from database
    if (empty($campaign) || empty($campaign['mail_subject']) || empty($campaign['mail_body'])) {
        $result = $conn->query("SELECT * FROM campaign_master WHERE campaign_id = $campaign_id");
        if ($result && $result->num_rows > 0) {
            $campaign = $result->fetch_assoc();
            // Don't use stripcslashes - it corrupts HTML
            // $campaign['mail_body'] = stripcslashes($campaign['mail_body']);
    //         file_put_contents(__DIR__ . '/../logs/email_worker.log', '[' . date('Y-m-d H:i:s') . "] Campaign #$campaign_id loaded from DB\n", FILE_APPEND);
        } else {
    //         file_put_contents(__DIR__ . '/../logs/email_worker.log', '[' . date('Y-m-d H:i:s') . "] ERROR: Campaign #$campaign_id not found in DB\n", FILE_APPEND);
            $conn->close();
            exit(1);
        }
    }

    $server_id = isset($server_config['server_id']) ? intval($server_config['server_id']) : 0;
    $csv_list_id = isset($campaign['csv_list_id']) ? intval($campaign['csv_list_id']) : 0;
    $import_batch_id = isset($campaign['import_batch_id']) ? $campaign['import_batch_id'] : null;
    $csv_list_filter = $csv_list_id > 0 ? " AND e.csv_list_id = $csv_list_id" : "";
    
    // Store campaign user_id in global for use in recordDelivery
    $GLOBALS['campaign_user_id'] = isset($campaign['user_id']) ? intval($campaign['user_id']) : 0;
    workerLog("Campaign user_id: " . $GLOBALS['campaign_user_id']);
    
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

    $accounts = loadActiveAccountsForServer($conn, $server_id, $campaign_user_id);
    workerLog("Server #$server_id loaded " . count($accounts) . ' accounts for user #' . $campaign_user_id);

    // Log queue state before entering loop - check correct source table
    if ($import_batch_id) {
        // Count from imported_recipients table
        $batch_escaped = $conn->real_escape_string($import_batch_id);
        $eligibleRes = $conn->query("SELECT COUNT(*) AS c FROM imported_recipients WHERE import_batch_id = '$batch_escaped' AND is_active = 1 AND Emails IS NOT NULL AND Emails <> ''");
        $eligibleCount = ($eligibleRes && $eligibleRes->num_rows) ? (int)$eligibleRes->fetch_assoc()['c'] : 0;
        workerLog("Server #$server_id: eligible_emails_from_import=$eligibleCount");
    } else {
        // Count from emails table (CSV)
        $eligibleRes = $conn->query("SELECT COUNT(*) AS c FROM emails e WHERE e.domain_status = 1 AND e.validation_status = 'valid' AND e.raw_emailid IS NOT NULL AND e.raw_emailid <> ''" . $csv_list_filter);
        $eligibleCount = ($eligibleRes && $eligibleRes->num_rows) ? (int)$eligibleRes->fetch_assoc()['c'] : 0;
        workerLog("Server #$server_id: eligible_emails_from_csv=$eligibleCount");
    }
    $pendingRes = $conn->query("SELECT COUNT(*) AS c FROM mail_blaster WHERE campaign_id = $campaign_id AND status = 'pending'");
    $pendingCount = ($pendingRes && $pendingRes->num_rows) ? (int)$pendingRes->fetch_assoc()['c'] : 0;
    workerLog("Server #$server_id: eligible_emails=$eligibleCount pending_in_mail_blaster=$pendingCount");

    if (empty($accounts)) { 
        workerLog("Server #$server_id: No accounts found, exiting");
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
    while (true) {
        $loop_iter++;
        // Abort immediately if campaign no longer exists (deleted)
        $existsRes = $conn->query("SELECT 1 FROM campaign_master WHERE campaign_id = $campaign_id LIMIT 1");
        if (!$existsRes || $existsRes->num_rows === 0) {
            workerLog("Campaign #$campaign_id deleted; worker exiting");
            $conn->close();
            exit(0);
        }
        
        // DB reconnect logic removed: on DB failure the worker should exit; cron/orchestrator will restart.
        
        // Check campaign status every 10 iterations (pause/stop support)
        if ($loop_iter % 10 === 1) {
            $statusCheck = $conn->query("SELECT status FROM campaign_status WHERE campaign_id = $campaign_id");
            if ($statusCheck && $statusCheck->num_rows > 0) {
                $currentStatus = $statusCheck->fetch_assoc()['status'];
                if ($currentStatus === 'paused' || $currentStatus === 'stopped') {
                    workerLog("Server #$server_id: Campaign status is '$currentStatus', stopping worker (sent $send_count emails)");
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
        
        if ($loop_iter % 50 === 1) {
    //         file_put_contents(__DIR__ . '/../logs/email_worker.log', '[' . date('Y-m-d H:i:s') . "] Server #$server_id: Loop iteration $loop_iter (send_count=$send_count)\n", FILE_APPEND);
        }
        
        // Pick next eligible account in strict order first
        $selected = null; $tries = 0; $count = count($accounts);
        while ($tries < $count) {
            $idx = $rotation_idx % $count;
            $candidate = $accounts[$idx];
            if (accountWithinLimits($conn, intval($candidate['id']))) { 
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
            // Wait for limits to reset instead of exiting immediately
            workerLog("Server #$server_id: All accounts at limits temporarily. Waiting 60s for limits to reset...");
            sleep(60); // Wait 1 minute for hourly limit reset
            
            // Reload accounts and check again
            $accounts = loadActiveAccountsForServer($conn, $server_id, $campaign_user_id);
            if (empty($accounts)) {
                workerLog("Server #$server_id: Still no accounts available after waiting. Exiting.");
                $conn->close();
                exit(0);
            }
            
            // Reset rotation and continue trying
            $rotation_idx = 0;
            $consecutive_limit_checks = 0;
            continue; // Retry account selection
        }

        // First try to pick up an existing pending/failed email (backlog) - prefer cross-server retry
        workerLog("Server #$server_id: Checking for pending/retry emails...");
        try {
            $pending = fetchNextPending($conn, $campaign_id, $server_id);
        } catch (Exception $e) {
            workerLog("Server #$server_id: ERROR in fetchNextPending: " . $e->getMessage());
            $pending = null;
        }
        
        if ($pending) {
            $to = $pending['to_mail'];
            $email_csv_list_id = isset($pending['csv_list_id']) ? intval($pending['csv_list_id']) : null;
            workerLog("Server #$server_id: Found pending/retry email: $to (attempt #{$pending['attempt_count']}, csv_list_id=$email_csv_list_id)");
            // Assign this pending to the selected account to mark ownership (only if campaign still exists)
            $existsRes = $conn->query("SELECT 1 FROM campaign_master WHERE campaign_id = $campaign_id LIMIT 1");
            if (!$existsRes || $existsRes->num_rows === 0) {
                $conn->close();
                exit(0);
            }
            assignPendingToAccount($conn, $campaign_id, $to, intval($selected['id']));
            workerLog("Server #$server_id: Assigned pending $to to account #{$selected['id']} ({$selected['email']})");
        } else {
            // No backlog: claim next email atomically only after we have an eligible account
            workerLog("Server #$server_id: No pending emails, attempting to claim new email...");
            try {
                $claimed = claimNextEmail($conn, $campaign_id, intval($selected['id']));
            } catch (Exception $e) {
                workerLog("Server #$server_id: ERROR in claimNextEmail: " . $e->getMessage());
                $claimed = null;
            }
            
            workerLog("Server #$server_id: claimNextEmail returned: " . ($claimed ? "SUCCESS" : "NULL"));
            if (!$claimed) {
                $consecutive_empty_claims++;
                
                // CRITICAL: Check if there are unclaimed emails not yet in mail_blaster
                $unclaimedCheck = null;
                if ($import_batch_id) {
                    $batch_escaped = $conn->real_escape_string($import_batch_id);
                    $unclaimedCheck = $conn->query("SELECT COUNT(*) as cnt FROM imported_recipients ir WHERE ir.import_batch_id = '$batch_escaped' AND ir.is_active = 1 AND ir.Emails IS NOT NULL AND ir.Emails <> '' AND NOT EXISTS (SELECT 1 FROM mail_blaster mb WHERE mb.campaign_id = $campaign_id AND mb.to_mail COLLATE utf8mb4_unicode_ci = ir.Emails)");
                } else {
                    $unclaimedCheck = $conn->query("SELECT COUNT(*) as cnt FROM emails e WHERE e.domain_status = 1 AND e.validation_status = 'valid' AND e.raw_emailid IS NOT NULL AND e.raw_emailid <> '' $csv_list_filter AND NOT EXISTS (SELECT 1 FROM mail_blaster mb WHERE mb.campaign_id = $campaign_id AND mb.to_mail = e.raw_emailid)");
                }
                
                $unclaimed = ($unclaimedCheck && $unclaimedCheck->num_rows > 0) ? intval($unclaimedCheck->fetch_assoc()['cnt']) : 0;
                
                if ($unclaimed > 0) {
                    // CRITICAL FIX: Re-initialize queue to ensure ALL emails are tracked
                    workerLog("Server #$server_id: *** FOUND $unclaimed UNCLAIMED EMAILS! Re-initializing queue to prevent missing emails...");
                    require_once __DIR__ . '/campaign_email_verification.php';
                    $queueStats = initializeEmailQueue($conn, $campaign_id);
                    workerLog("Server #$server_id: Queue re-initialized: {$queueStats['queued']} new emails added");
                    $consecutive_empty_claims = 0; // Reset counter after re-init
                    continue; // Retry claiming
                }
                
                // Check if there are emails remaining to process (already in queue)
                $remainingCheck = $conn->query("SELECT COUNT(*) as cnt FROM mail_blaster WHERE campaign_id = $campaign_id AND (status IN ('pending', 'failed') AND attempt_count < 5)");
                $remaining = ($remainingCheck && $remainingCheck->num_rows > 0) ? intval($remainingCheck->fetch_assoc()['cnt']) : 0;
                
                if ($remaining > 0) {
                    // There ARE emails remaining in queue, but they're locked by other workers
                    // OR they're assigned to other servers via hash distribution
                    // Keep retrying with exponential backoff
                    if ($consecutive_empty_claims <= 30) { // Increased from 10 to 30
                        $wait_ms = min(100 * $consecutive_empty_claims, 2000); // 100ms to 2000ms
                        workerLog("Server #$server_id: $remaining emails still pending but not for this server yet. Retry attempt #$consecutive_empty_claims/30. Waiting {$wait_ms}ms...");
                        usleep($wait_ms * 1000);
                        continue; // Retry claiming
                    } else {
                        // After 30 retries, take a longer break and reset counter (keep trying!)
                        workerLog("Server #$server_id: $remaining emails pending. Taking 5s break then continuing...");
                        sleep(5);
                        $consecutive_empty_claims = 0;
                        continue; // Keep trying, don't exit
                    }
                } else {
                    // No emails remaining - check if ALL workers are done (not just this server)
                    workerLog("Server #$server_id: No more emails for this server. Checking global status...");
                    
                    // Double-check: Are there emails assigned to OTHER servers still pending?
                    $globalCheck = $conn->query("SELECT COUNT(*) as cnt FROM mail_blaster WHERE campaign_id = $campaign_id AND status IN ('pending', 'failed', 'processing') AND attempt_count < 5");
                    $globalPending = ($globalCheck && $globalCheck->num_rows > 0) ? intval($globalCheck->fetch_assoc()['cnt']) : 0;
                    
                    if ($globalPending > 0) {
                        // Other servers are still working - wait and see if we get reassignments
                        workerLog("Server #$server_id: $globalPending emails being processed by other servers. Waiting 10s before rechecking...");
                        sleep(10);
                        $consecutive_empty_claims = 0;
                        continue; // Keep checking
                    }
                    
                    // Truly no emails left anywhere
                    workerLog("Server #$server_id: No more emails to process globally. Queue truly exhausted (verified: $unclaimed unclaimed, $remaining for this server, $globalPending globally).");
                    
                    // Check if campaign is completed and update status
                    checkCampaignCompletion($conn, $campaign_id);
                    
                    break; // Exit worker only when truly nothing left
                }
            }
            
            // Successfully claimed an email - reset counter
            $consecutive_empty_claims = 0;
            $to = $claimed['to_mail'];
            $email_csv_list_id = isset($claimed['csv_list_id']) ? intval($claimed['csv_list_id']) : null;
            workerLog("Server #$server_id: Claimed NEW email: $to (csv_list_id=$email_csv_list_id) -> assigned to account #{$selected['id']} ({$selected['email']})");
        }

        try {
            workerLog("Server #$server_id: Sending to $to via account #{$selected['id']} ({$selected['email']}) on {$server_config['host']}:{$server_config['port']}");
            sendEmail($conn, $campaign_id, $to, $server_config, $selected, $campaign, $email_csv_list_id ?? null);
            $send_count++;
            $consecutive_server_failures = 0; // Reset on success
            workerLog("Server #$server_id: ✓ SUCCESS sent to $to via account #{$selected['id']} ({$selected['email']}) [total sent: $send_count]");
            if ($send_count % 10 == 0) { workerLog("Server #$server_id: === Progress: $send_count emails sent ==="); }
            usleep(50000); // OPTIMIZED: Reduced from 100ms to 50ms for faster throughput
        } catch (Exception $e) {
            // Ensure transaction is rolled back on any exception
            if ($conn->connect_errno === 0) {
                $conn->query("ROLLBACK");
            }
            
            // Check if this was a duplicate prevention (not a real error)
            $isDuplicatePrevention = (strpos($e->getMessage(), 'Duplicate prevented') !== false || 
                                      strpos($e->getMessage(), 'already sent') !== false ||
                                      strpos($e->getMessage(), 'Lock timeout') !== false);
            
            if ($isDuplicatePrevention) {
                workerLog("Server #$server_id: Duplicate/Lock prevented for $to - continuing to next email");
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
                    $backup = switchToBackupServer($conn, $server_id, $campaign_user_id);
                    
                    if ($backup) {
                        // Switch to backup server
                        $server_id = $backup['server_id'];
                        $server_config = $backup;
                        
                        // Reload accounts from new server (with user filter)
                        $accounts = loadActiveAccountsForServer($conn, $server_id, $campaign_user_id);
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
            
            // Check current attempt count
            $attemptRes = $conn->query("SELECT attempt_count FROM mail_blaster WHERE campaign_id = $campaign_id AND to_mail = '" . $conn->real_escape_string($to) . "'");
            $currentAttempts = ($attemptRes && $attemptRes->num_rows > 0) ? intval($attemptRes->fetch_assoc()['attempt_count']) : 0;
            
            if ($currentAttempts >= 5) {
                // Mark as permanently failed after 5 attempts
                workerLog("Server #$server_id: ✗ PERMANENT FAILURE to $to via account #{$selected['id']} ({$selected['email']}) after $currentAttempts attempts - Error: " . $e->getMessage());
                recordDelivery($conn, $selected['id'], $server_id, $campaign_id, $to, 'failed', "Max retries exceeded: " . $e->getMessage(), $email_csv_list_id ?? null);
            } else {
                // Keep as pending for retry
                workerLog("Server #$server_id: ✗ FAILED (attempt #$currentAttempts/5) to $to via account #{$selected['id']} ({$selected['email']}) - Will retry. Error: " . $e->getMessage());
                recordDelivery($conn, $selected['id'], $server_id, $campaign_id, $to, 'failed', $e->getMessage(), $email_csv_list_id ?? null);
            }
        }
    }

    // Mark stopped before exiting
    workerLog("Server #$server_id: Worker stopping after sending $send_count emails");
    
    // Final verification: Check if there are still emails to process
    $finalUnclaimedCheck = null;
    $finalPendingCheck = null;
    
    if ($import_batch_id) {
        $batch_escaped = $conn->real_escape_string($import_batch_id);
        $finalUnclaimedCheck = $conn->query("SELECT COUNT(*) as cnt FROM imported_recipients ir WHERE ir.import_batch_id = '$batch_escaped' AND ir.is_active = 1 AND ir.Emails IS NOT NULL AND ir.Emails <> '' AND NOT EXISTS (SELECT 1 FROM mail_blaster mb WHERE mb.campaign_id = $campaign_id AND mb.to_mail COLLATE utf8mb4_unicode_ci = ir.Emails)");
    } else {
        $finalUnclaimedCheck = $conn->query("SELECT COUNT(*) as cnt FROM emails e WHERE e.domain_status = 1 AND e.validation_status = 'valid' AND e.raw_emailid IS NOT NULL AND e.raw_emailid <> '' $csv_list_filter AND NOT EXISTS (SELECT 1 FROM mail_blaster mb WHERE mb.campaign_id = $campaign_id AND mb.to_mail = e.raw_emailid)");
    }
    $finalUnclaimed = ($finalUnclaimedCheck && $finalUnclaimedCheck->num_rows > 0) ? intval($finalUnclaimedCheck->fetch_assoc()['cnt']) : 0;
    
    // Check emails in queue but not yet sent
    $finalPendingCheck = $conn->query("SELECT COUNT(*) as cnt FROM mail_blaster WHERE campaign_id = $campaign_id AND (status IN ('pending', 'failed') AND attempt_count < 5)");
    $finalPending = ($finalPendingCheck && $finalPendingCheck->num_rows > 0) ? intval($finalPendingCheck->fetch_assoc()['cnt']) : 0;
    
    $finalTotal = $finalUnclaimed + $finalPending;
    
    if ($finalTotal > 0) {
        workerLog("*** WARNING: Server #$server_id exiting but emails still need processing!");
        workerLog("***   Unclaimed (not in mail_blaster): $finalUnclaimed");
        workerLog("***   Pending (in mail_blaster): $finalPending");
        workerLog("***   Total remaining: $finalTotal");
        workerLog("***   Other workers or restart should handle them.");
    } else {
        workerLog("✓ Server #$server_id: Confirmed ALL emails processed (0 unclaimed, 0 pending)");
    }

    $conn->close();
    exit(0);
        
    function sendEmail($conn, $campaign_id, $to_email, $server, $account, $campaign, $csv_list_id = null) {
        if (!filter_var($to_email, FILTER_VALIDATE_EMAIL)) { throw new Exception("Invalid email: $to_email"); }
        
        // ============================================================================
        // MULTI-LAYER DUPLICATE PREVENTION SYSTEM
        // ============================================================================
        // Layer 1: Database UNIQUE constraint (campaign_id, to_mail) - PRIMARY PROTECTION
        // Layer 2: Transaction with FOR UPDATE lock - RACE CONDITION PROTECTION  
        // Layer 3: Status checking before send - LOGICAL PROTECTION
        // Layer 4: Double-check after send - FINAL VERIFICATION
        // ============================================================================
        
        // CRITICAL: Start transaction and lock the row IMMEDIATELY to prevent duplicates
        $conn->query("START TRANSACTION");
        
        $to_escaped = $conn->real_escape_string($to_email);
        
        // LAYER 1: Quick check if email already sent successfully (fast abort without lock)
        $quickCheck = $conn->query("SELECT status FROM mail_blaster WHERE campaign_id = " . intval($campaign_id) . " AND to_mail = '$to_escaped' AND status = 'success' LIMIT 1");
        if ($quickCheck && $quickCheck->num_rows > 0) {
            $conn->query("ROLLBACK");
            workerLog("✓ DUPLICATE PREVENTED (Layer 1): Email $to_email already sent successfully for campaign $campaign_id");
            throw new Exception("Duplicate prevented: Email already sent successfully");
        }
        
        // LAYER 2: Lock the row and check detailed status (with timeout handling)
        $conn->query("SET SESSION innodb_lock_wait_timeout = 5"); // 5 second lock timeout
        $checkExisting = $conn->query("SELECT status, smtpid, delivery_time, id FROM mail_blaster WHERE campaign_id = " . intval($campaign_id) . " AND to_mail = '$to_escaped' LIMIT 1 FOR UPDATE");
        
        // Handle lock timeout (error 1205)
        if (!$checkExisting && $conn->errno == 1205) {
            $conn->query("ROLLBACK");
            workerLog("✓ DUPLICATE PREVENTED (Layer 2 - Lock Timeout): Email $to_email locked by another worker");
            throw new Exception("Lock timeout: Email being processed by another worker");
        }
        
        if ($checkExisting && $checkExisting->num_rows > 0) {
            $existing = $checkExisting->fetch_assoc();
            $rowId = $existing['id'];
            
            // LAYER 3: Already sent successfully - ABORT (with row lock still held)
            if ($existing['status'] === 'success') {
                $conn->query("ROLLBACK");
                workerLog("✓ DUPLICATE PREVENTED (Layer 3): Email $to_email already sent successfully (row ID: $rowId)");
                throw new Exception("Duplicate prevented: Email already sent successfully");
            }
            
            // LAYER 3: Being processed by another worker RIGHT NOW - ABORT
            if (($existing['status'] === 'pending' || $existing['status'] === 'processing') && $existing['smtpid'] != $account['id']) {
                // Check if delivery_time is recent (within last 60 seconds) - means actively being sent
                $deliveryTime = strtotime($existing['delivery_time']);
                $timeDiff = time() - $deliveryTime;
                if ($timeDiff < 60) {
                    $conn->query("ROLLBACK");
                    workerLog("✓ DUPLICATE PREVENTED (Layer 3): Email $to_email being processed by worker #{$existing['smtpid']} (row ID: $rowId, started {$timeDiff}s ago)");
                    throw new Exception("Duplicate prevented: Email being processed by another worker");
                }
            }
            
            // Safe to send - Update to mark THIS worker is now sending it
            $conn->query("UPDATE mail_blaster SET smtpid = {$account['id']}, delivery_date = CURDATE(), delivery_time = NOW(), status = 'processing' WHERE id = $rowId AND campaign_id = " . intval($campaign_id));
            workerLog("Claimed email $to_email for sending (row ID: $rowId, status: {$existing['status']} → processing)");
        } else {
            // No existing record - INSERT with INSERT IGNORE (respects UNIQUE constraint)
            // This protects against race condition where two workers try to insert simultaneously
            $insertResult = $conn->query("INSERT IGNORE INTO mail_blaster (campaign_id, to_mail, csv_list_id, smtpid, delivery_date, delivery_time, status, attempt_count) VALUES (" . intval($campaign_id) . ", '$to_escaped', " . ($csv_list_id ? intval($csv_list_id) : "NULL") . ", {$account['id']}, CURDATE(), NOW(), 'processing', 0)");
            
            if ($conn->affected_rows === 0) {
                // INSERT IGNORE failed = duplicate already exists (created by another worker)
                $conn->query("ROLLBACK");
                workerLog("✓ DUPLICATE PREVENTED (Layer 1 - UNIQUE Constraint): Email $to_email already exists in queue");
                throw new Exception("Duplicate prevented: Email already in queue");
            }
            workerLog("New email queued: $to_email (INSERT successful)");
        }
        
        // DO NOT COMMIT YET - Keep transaction open until after send!
        // $conn->query("COMMIT"); // REMOVED - commit happens after send
        
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $server['host'];
        $mail->Port = $server['port'];
        $mail->SMTPAuth = true;
        $mail->Username = $account['email'];
        $mail->Password = $account['password'];
        $mail->Timeout = 15; // OPTIMIZED: Reduced from 30 to 15 seconds for faster failure detection
        $mail->SMTPDebug = 0;
        $mail->SMTPKeepAlive = true; // OPTIMIZED: Enable connection reuse for speed
        if ($server['encryption'] === 'ssl') $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        elseif ($server['encryption'] === 'tls') $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->SMTPOptions = ['ssl' => ['verify_peer' => false,'verify_peer_name' => false,'allow_self_signed' => true,'crypto_method' => STREAM_CRYPTO_METHOD_TLS_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT]];
        $mail->SMTPAutoTLS = true;
        $mail->setFrom($account['email']);
        $mail->addAddress($to_email);
        $mail->Subject = $campaign['mail_subject'];

        // Process campaign body - use template merge if template_id is set, otherwise use regular mail_body
        $body = processCampaignBody($conn, $campaign, $to_email, $csv_list_id);
        if (empty(trim($body))) throw new Exception("Empty body after template processing");
        
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
        
        // Send the email while transaction is still open (row locked)
        if (!$mail->send()) {
            // Send failed - rollback transaction
            $conn->query("ROLLBACK");
            throw new Exception($mail->ErrorInfo);
        }
        
        // Send successful - now commit the transaction to release lock
        $conn->query("COMMIT");
        
        $srvId = intval($server['server_id'] ?? 0);
        recordDelivery($conn, $account['id'], $srvId, $campaign_id, $to_email, 'success', null, $csv_list_id);
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

    function recordDelivery($conn, $smtp_account_id, $server_id, $campaign_id, $to_email, $status, $error = null, $csv_list_id = null) {
        // Skip writes if campaign is deleted (check early to avoid unnecessary queries)
        $existsRes = $conn->query("SELECT 1 FROM campaign_master WHERE campaign_id = " . intval($campaign_id) . " LIMIT 1");
        if (!$existsRes || $existsRes->num_rows === 0) {
            return;
        }
        
        // Fetch the SMTP email address from smtp_account_id
        $smtp_email = '';
        $emailQuery = $conn->query("SELECT email FROM smtp_accounts WHERE id = " . intval($smtp_account_id) . " LIMIT 1");
        if ($emailQuery && $emailQuery->num_rows > 0) {
            $smtp_email = $emailQuery->fetch_assoc()['email'];
        }
        
        // Check existing record status to avoid overwriting success with failure
        $wasAlreadySuccess = false;
        $existingAttempts = 0;
        $to_escaped = $conn->real_escape_string($to_email);
        $checkStmt = $conn->prepare("SELECT status, attempt_count FROM mail_blaster WHERE campaign_id = ? AND to_mail = ? LIMIT 1");
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
        $stmt = $conn->prepare("
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
            $status, 
            $error, 
            $campaign_user_id
        );
        $stmt->execute();
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        // Log update vs insert for debugging
        if ($affectedRows === 1) {
            workerLog("Inserted new record for $to_email with status=$status");
        } elseif ($affectedRows === 2) {
            workerLog("Updated existing record for $to_email: attempts=" . ($existingAttempts + 1) . ", status=$status");
        }
        
        // Only increment counters for NEW successful sends (not retries of already-successful emails)
        if ($status === 'success' && !$wasAlreadySuccess) {
            // Update SMTP account counters
            $conn->query("UPDATE smtp_accounts SET sent_today = sent_today + 1, total_sent = total_sent + 1 WHERE id = $smtp_account_id");
            
            // OPTIMIZED: Update existing hourly record instead of creating duplicates - reduces DB storage
            // Uses ON DUPLICATE KEY UPDATE to increment counter in single row per (smtp_id, date, hour, user_id)
            $usage_date = date('Y-m-d'); 
            $usage_hour = (int)date('G'); 
            $now = date('Y-m-d H:i:s');
            
            // Ensure smtp_usage has unique key on (smtp_id, date, hour, user_id) for proper updates
            $conn->query("
                INSERT INTO smtp_usage (smtp_id, date, hour, timestamp, emails_sent, user_id) 
                VALUES ($smtp_account_id, '$usage_date', $usage_hour, '$now', 1, $campaign_user_id) 
                ON DUPLICATE KEY UPDATE 
                    emails_sent = emails_sent + 1, 
                    timestamp = VALUES(timestamp)
            ");
            
            // Safety cap: Prevent counter from exceeding hourly_limit (handles race conditions)
            // Only applies when hourly_limit > 0 (not unlimited)
            $conn->query("
                UPDATE smtp_usage su 
                JOIN smtp_accounts sa ON sa.id = $smtp_account_id 
                SET su.emails_sent = LEAST(su.emails_sent, sa.hourly_limit)
                WHERE su.smtp_id = $smtp_account_id 
                    AND su.date = '$usage_date' 
                    AND su.hour = $usage_hour
                    AND su.user_id = $campaign_user_id
                    AND sa.hourly_limit > 0
            ");
            
            workerLog("Updated smtp_usage: smtp_id=$smtp_account_id, hour=$usage_hour, user_id=$campaign_user_id");
        }
        
        // CRITICAL: Check campaign completion after EVERY delivery to update pending count in real-time
        // This ensures the UI shows accurate counts and campaign completes when all retries exhausted
        checkCampaignCompletion($conn, $campaign_id);
        
        // Update SMTP health
        if ($status === 'success') {
            $conn->query("INSERT INTO smtp_health (smtp_id, health, consecutive_failures, last_success_at, updated_at) 
                VALUES ($smtp_account_id, 'healthy', 0, NOW(), NOW())
                ON DUPLICATE KEY UPDATE 
                    health = IF(suspend_until IS NULL OR suspend_until < NOW(), 'healthy', health),
                    consecutive_failures = 0, 
                    last_success_at = NOW(),
                    suspend_until = NULL,
                    updated_at = NOW()");
        } else {
            // Track failure and update health status
            $error_type = 'unknown';
            if ($error && (stripos($error, 'authenticate') !== false || stripos($error, 'login') !== false)) {
                $error_type = 'auth_failed';
            } elseif ($error && stripos($error, 'connect') !== false) {
                $error_type = 'connection_failed';
            } elseif ($error && (stripos($error, 'timeout') !== false || stripos($error, 'timed out') !== false)) {
                $error_type = 'timeout';
            }
            
            $safe_error = $conn->real_escape_string(substr($error, 0, 500));
            $conn->query("INSERT INTO smtp_health (smtp_id, health, consecutive_failures, last_failure_at, last_error_type, last_error_message, updated_at) 
                VALUES ($smtp_account_id, 'healthy', 1, NOW(), '$error_type', '$safe_error', NOW())
                ON DUPLICATE KEY UPDATE 
                    consecutive_failures = consecutive_failures + 1,
                    last_failure_at = NOW(),
                    last_error_type = '$error_type',
                    last_error_message = '$safe_error',
                    health = CASE 
                        WHEN consecutive_failures + 1 >= 15 THEN 'suspended'
                        WHEN consecutive_failures + 1 >= 8 THEN 'degraded'
                        ELSE 'healthy'
                    END,
                    suspend_until = CASE
                        WHEN consecutive_failures + 1 >= 15 THEN DATE_ADD(NOW(), INTERVAL 30 MINUTE)
                        ELSE suspend_until
                    END,
                    updated_at = NOW()"); // OPTIMIZED: Increased thresholds (15/8 vs 10/5) and reduced suspension (30min vs 1hr)
        }
    }

    function accountWithinLimits($conn, $account_id) {
        $res = $conn->query("SELECT email,daily_limit,hourly_limit FROM smtp_accounts WHERE id = $account_id");
        if (!$res || $res->num_rows === 0) {
            return false;
        }
        $row = $res->fetch_assoc();
        
        // Check daily limit using smtp_usage table (sum all hours for today)
        $today = date('Y-m-d');
        $dailyResult = $conn->query("SELECT COALESCE(SUM(emails_sent), 0) as sent_today FROM smtp_usage WHERE smtp_id = $account_id AND date = '$today'");
        $sent_today = ($dailyResult && $dailyResult->num_rows > 0) ? intval($dailyResult->fetch_assoc()['sent_today']) : 0;
        
        if (intval($row['daily_limit']) > 0 && $sent_today >= intval($row['daily_limit'])) {
            return false;
        }
        
        // Check hourly limit using smtp_usage table (current hour only)
        $current_hour = intval(date('G')); // 0-23
        $hourlyResult = $conn->query("SELECT emails_sent FROM smtp_usage WHERE smtp_id = $account_id AND date = '$today' AND hour = $current_hour");
        $sent_this_hour = ($hourlyResult && $hourlyResult->num_rows > 0) ? intval($hourlyResult->fetch_assoc()['emails_sent']) : 0;
        
        if (intval($row['hourly_limit']) > 0 && $sent_this_hour >= intval($row['hourly_limit'])) {
            return false;
        }   
        return true;
    }

    function switchToBackupServer($conn, $current_server_id, $campaign_user_id) {
        workerLog("Switching from failed server #$current_server_id to backup server...");
        
        // Get all active servers for this user EXCEPT the failed one
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
        
        $result = $conn->query($query);
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

    function loadActiveAccountsForServer($conn, $server_id, $user_id = 0) {
        // First try to load healthy accounts only - FILTERED BY USER
        $accounts = [];
        $user_filter = ($user_id > 0) ? " AND sa.user_id = $user_id" : "";
        
        $healthyRes = $conn->query("
            SELECT sa.id, sa.email, sa.password, sa.daily_limit, sa.hourly_limit, sa.sent_today, sa.total_sent 
            FROM smtp_accounts sa
            LEFT JOIN smtp_health sh ON sa.id = sh.smtp_id
            WHERE sa.smtp_server_id = $server_id 
            AND sa.is_active = 1
            $user_filter
            AND (sh.health IS NULL OR sh.health = 'healthy' OR (sh.health = 'suspended' AND sh.suspend_until < NOW()))
            ORDER BY sa.id ASC
        ");
        
        if ($healthyRes) {
            while ($r = $healthyRes->fetch_assoc()) $accounts[] = $r;
        }
        
        // If no healthy accounts available, load degraded accounts as fallback
        if (empty($accounts)) {
            workerLog("No healthy accounts found for server #$server_id" . ($user_id > 0 ? " (user #$user_id)" : "") . ", falling back to degraded accounts");
            $degradedRes = $conn->query("
                SELECT sa.id, sa.email, sa.password, sa.daily_limit, sa.hourly_limit, sa.sent_today, sa.total_sent 
                FROM smtp_accounts sa
                JOIN smtp_health sh ON sa.id = sh.smtp_id
                WHERE sa.smtp_server_id = $server_id 
                AND sa.is_active = 1
                $user_filter
                AND sh.health = 'degraded'
                ORDER BY sa.id ASC
            ");
            
            if ($degradedRes) {
                while ($r = $degradedRes->fetch_assoc()) $accounts[] = $r;
            }
        }
        
        if ($user_id > 0) {
            workerLog("Loaded " . count($accounts) . " accounts for server #$server_id filtered by user #$user_id");
        }
        
        return $accounts;
    }

    function ensureMailBlasterUniqueIndex($conn) {
        // Check if index already exists
        $result = $conn->query("SHOW INDEX FROM mail_blaster WHERE Key_name = 'uq_campaign_email'");
        if ($result && $result->num_rows > 0) {
            return; // Index already exists
        }
        // Do NOT alter schema at runtime per ops directive
    }

    function claimNextEmail($conn, $campaign_id, $smtp_account_id, $depth = 0) {
        global $server_id, $csv_list_filter;
        
        // Safety: confirm campaign still exists before attempting to claim/insert
        $existsRes = $conn->query("SELECT import_batch_id, csv_list_id FROM campaign_master WHERE campaign_id = " . intval($campaign_id) . " LIMIT 1");
        if (!$existsRes || $existsRes->num_rows === 0) {
            return null;
        }
        $campaign_row = $existsRes->fetch_assoc();
        $import_batch_id = $campaign_row['import_batch_id'];
        $campaign_csv_list_id = $campaign_row['csv_list_id'];
        
        // Start transaction for atomic claim
        $conn->query("START TRANSACTION");
        
        // PRIORITY 1: Try to claim FAILED emails that need retry (attempt_count < 5)
        workerLog("claimNextEmail: First checking for failed emails that need retry...");
        $retryRes = $conn->query("SELECT to_mail, csv_list_id, attempt_count, error_message 
            FROM mail_blaster 
            WHERE campaign_id = $campaign_id 
            AND status = 'failed' 
            AND attempt_count < 5
            ORDER BY attempt_count ASC, id ASC
            LIMIT 1 FOR UPDATE");
        
        if ($retryRes && $retryRes->num_rows > 0) {
            $retryRow = $retryRes->fetch_assoc();
            $to_mail = $retryRow['to_mail'];
            $csv_list_id = $retryRow['csv_list_id'];
            $prev_attempt = intval($retryRow['attempt_count']);
            workerLog("claimNextEmail: Found failed email for retry: $to_mail (previous attempts: $prev_attempt)");
            
            // Update to pending status so it will be retried
            $to_escaped = $conn->real_escape_string($to_mail);
            $conn->query("UPDATE mail_blaster 
                SET status = 'pending', 
                    smtpid = $smtp_account_id
                WHERE campaign_id = $campaign_id 
                AND to_mail = '$to_escaped'");
            
            $conn->query("COMMIT");
            
            return [
                'to_mail' => $to_mail,
                'csv_list_id' => $csv_list_id,
                'is_retry' => true,
                'attempt_count' => $prev_attempt
            ];
        }
        
        // PRIORITY 2: Pick next NEW email that's NOT in mail_blaster yet
        // Use import_batch_id to determine source table
        $offset = mt_rand(0, 999);
        
        if ($import_batch_id) {
            // Fetch from imported_recipients table
            $batch_escaped = $conn->real_escape_string($import_batch_id);
            workerLog("claimNextEmail: Executing SELECT from imported_recipients (offset=$offset)");
            $res = $conn->query("SELECT ir.id, ir.Emails AS to_mail, '$import_batch_id' AS import_batch_id 
                FROM imported_recipients ir
                WHERE ir.Emails IS NOT NULL 
                AND ir.Emails <> '' 
                AND ir.import_batch_id = '$batch_escaped'
                AND ir.is_active = 1
                AND NOT EXISTS (
                    SELECT 1 FROM mail_blaster mb 
                    WHERE mb.campaign_id = $campaign_id 
                    AND mb.to_mail COLLATE utf8mb4_unicode_ci = ir.Emails
                )
                ORDER BY ir.id ASC 
                LIMIT 1 OFFSET $offset LOCK IN SHARE MODE");
            workerLog("claimNextEmail: Query result: " . ($res ? "SUCCESS" : "FAILED") . " rows=" . ($res ? $res->num_rows : 0) . " error=" . $conn->error);
        } else {
            // Fetch from emails table (original CSV logic)
            $res = $conn->query("SELECT e.id, e.raw_emailid AS to_mail, e.csv_list_id 
                FROM emails e 
                WHERE e.domain_status = 1 
                AND e.validation_status = 'valid' 
                AND e.raw_emailid IS NOT NULL 
                AND e.raw_emailid <> ''
                $csv_list_filter
                AND NOT EXISTS (
                    SELECT 1 FROM mail_blaster mb 
                    WHERE mb.campaign_id = $campaign_id 
                    AND mb.to_mail = e.raw_emailid
                )
                ORDER BY e.id ASC 
                LIMIT 1 OFFSET $offset
                LOCK IN SHARE MODE");
        }
            
        if (!$res || $res->num_rows === 0) {
            workerLog("claimNextEmail: First attempt returned no rows, checking if we should retry without offset (offset=$offset)");
            // If our offset range is exhausted, try from beginning without offset
            if ($offset > 0) {
                workerLog("claimNextEmail: Retrying from beginning (offset=0)");
                if ($import_batch_id) {
                    $batch_escaped = $conn->real_escape_string($import_batch_id);
                    workerLog("claimNextEmail: Retry SELECT from imported_recipients (offset=0)");
                    $res = $conn->query("SELECT ir.id, ir.Emails AS to_mail, '$import_batch_id' AS import_batch_id 
                        FROM imported_recipients ir
                        WHERE ir.Emails IS NOT NULL 
                        AND ir.Emails <> '' 
                        AND ir.import_batch_id = '$batch_escaped'
                        AND ir.is_active = 1
                        AND NOT EXISTS (
                            SELECT 1 FROM mail_blaster mb 
                            WHERE mb.campaign_id = $campaign_id 
                            AND mb.to_mail COLLATE utf8mb4_unicode_ci = ir.Emails
                        )
                        ORDER BY ir.id ASC 
                        LIMIT 1 LOCK IN SHARE MODE");
                    workerLog("claimNextEmail: Retry result: " . ($res ? "SUCCESS" : "FAILED") . " rows=" . ($res ? $res->num_rows : 0) . " error=" . $conn->error);
                } else {
                    $res = $conn->query("SELECT e.id, e.raw_emailid AS to_mail, e.csv_list_id 
                        FROM emails e 
                        WHERE e.domain_status = 1 
                        AND e.validation_status = 'valid' 
                        AND e.raw_emailid IS NOT NULL 
                        AND e.raw_emailid <> ''
                        $csv_list_filter
                        AND NOT EXISTS (
                            SELECT 1 FROM mail_blaster mb 
                            WHERE mb.campaign_id = $campaign_id 
                            AND mb.to_mail = e.raw_emailid
                        )
                        ORDER BY e.id ASC 
                        LIMIT 1
                        LOCK IN SHARE MODE");
                }
            }
            
            if (!$res || $res->num_rows === 0) {
                $conn->query("COMMIT");
                if ($depth === 0) { workerLog("claimNextEmail: no unclaimed eligible email found after retry"); }
                return null;
            }
        }
        
        workerLog("claimNextEmail: Found row, fetching data...");
        $row = $res->fetch_assoc();
        $to = $conn->real_escape_string($row['to_mail']);
        $email_csv_list_id = isset($row['csv_list_id']) ? intval($row['csv_list_id']) : 'NULL';
        $email_import_batch = isset($row['import_batch_id']) ? "'" . $conn->real_escape_string($row['import_batch_id']) . "'" : 'NULL';
        workerLog("claimNextEmail: attempting to claim $to (email.id={$row['id']}, csv_list_id=$email_csv_list_id, import_batch_id=$email_import_batch, depth=$depth)");
        
        // Double-check: does this email already exist in mail_blaster for this campaign?
        workerLog("claimNextEmail: Double-checking if $to already exists in mail_blaster...");
        $doubleCheck = $conn->query("SELECT id, status, attempt_count FROM mail_blaster WHERE campaign_id = $campaign_id AND to_mail = '$to' LIMIT 1 FOR UPDATE");
        workerLog("claimNextEmail: Double-check result: " . ($doubleCheck ? $doubleCheck->num_rows : 0) . " rows");
        if ($doubleCheck && $doubleCheck->num_rows > 0) {
            // Already claimed by another worker - rollback and try next
            $existing = $doubleCheck->fetch_assoc();
            $conn->query("ROLLBACK");
            workerLog("claimNextEmail: $to already claimed (status={$existing['status']}) - seeking next (depth=$depth)");
            if ($depth > 50) { 
                workerLog("claimNextEmail: depth limit reached (depth=$depth), giving up"); 
                return null; 
            }
            return claimNextEmail($conn, $campaign_id, $smtp_account_id, $depth + 1);
        }
        
        // Insert to claim this email (csv_list_id will be NULL for imported_recipients)
        workerLog("claimNextEmail: Inserting into mail_blaster to claim $to...");
        workerLog("claimNextEmail: Connection status: " . ($conn->ping() ? "ALIVE" : "DEAD"));
        
        try {
            workerLog("claimNextEmail: Preparing INSERT statement...");
            // Start attempt_count at 1 (not 0) to properly track retry attempts
            $stmt = $conn->prepare("INSERT IGNORE INTO mail_blaster (campaign_id,to_mail,csv_list_id,smtpid,delivery_date,delivery_time,status,error_message,attempt_count) 
                VALUES (?, ?, ?, ?, CURDATE(), CURTIME(), 'pending', NULL, 1)");
            
            if (!$stmt) {
                workerLog("claimNextEmail: Prepare failed: " . $conn->error);
                $conn->query("ROLLBACK");
                return null;
            }
            
            workerLog("claimNextEmail: Binding parameters...");
            $csv_list_for_bind = ($email_csv_list_id === 'NULL') ? null : $email_csv_list_id;
            $stmt->bind_param("isii", $campaign_id, $to, $csv_list_for_bind, $smtp_account_id);
            
            workerLog("claimNextEmail: Executing statement...");
            $insertResult = $stmt->execute();
            $affectedRows = $stmt->affected_rows;
            $stmt->close();
            workerLog("claimNextEmail: Statement executed! affected_rows=$affectedRows");
        } catch (Exception $e) {
            workerLog("claimNextEmail: EXCEPTION during INSERT: " . $e->getMessage());
            $conn->query("ROLLBACK");
            return null;
        } catch (Error $e) {
            workerLog("claimNextEmail: PHP ERROR during INSERT: " . $e->getMessage());
            $conn->query("ROLLBACK");
            return null;
        }
        
        workerLog("claimNextEmail: Insert result: " . ($insertResult ? "SUCCESS" : "FAILED") . " affected_rows=$affectedRows error=" . $conn->error . " errno=" . $conn->errno);
                
        // With INSERT IGNORE, if affected_rows = 0, it means duplicate was ignored
        if ($affectedRows === 0) {
            $conn->query("ROLLBACK");
            workerLog("claimNextEmail: $to already claimed by another worker (INSERT IGNORE returned 0 rows) - retrying with next email (depth=$depth)");
            if ($depth > 50) { 
                workerLog("claimNextEmail: depth limit reached, giving up"); 
                return null; 
            }
            return claimNextEmail($conn, $campaign_id, $smtp_account_id, $depth + 1);
        }
        
        if (!$insertResult || $conn->errno) {
            // Check if it's a duplicate key error (errno 1062) - shouldn't happen with INSERT IGNORE but keep as safety
            if ($conn->errno == 1062) {
                $conn->query("ROLLBACK");
                workerLog("claimNextEmail: $to already claimed by another worker (duplicate key) - retrying with next email (depth=$depth)");
                if ($depth > 50) { 
                    workerLog("claimNextEmail: depth limit reached, giving up"); 
                    return null; 
                }
                return claimNextEmail($conn, $campaign_id, $smtp_account_id, $depth + 1);
            }
            // Other error - fail
            $conn->query("ROLLBACK");
            workerLog("claimNextEmail: DB error while claiming $to: " . $conn->error); 
            return null; 
        }
        
        // Commit the transaction - we successfully claimed this email
        workerLog("claimNextEmail: Committing transaction...");
        $conn->query("COMMIT");
        workerLog("claimNextEmail: ✓ successfully claimed $to");
        return [
            'id' => $row['id'], 
            'to_mail' => $row['to_mail'], 
            'csv_list_id' => isset($row['csv_list_id']) ? $row['csv_list_id'] : null,
            'import_batch_id' => isset($row['import_batch_id']) ? $row['import_batch_id'] : null
        ];
    }

    function fetchNextPending($conn, $campaign_id, $server_id) {
        // Quick check: if no pending emails exist at all, return immediately
        $quickCheck = $conn->query("SELECT 1 FROM mail_blaster WHERE campaign_id = $campaign_id AND status IN ('pending', 'failed') AND attempt_count < 5 LIMIT 1");
        if (!$quickCheck || $quickCheck->num_rows === 0) {
            workerLog("fetchNextPending: No pending/failed emails in queue");
            return null;
        }
        
        // CRITICAL: Use transaction with FOR UPDATE and immediate status change to prevent race conditions
        // Compatible with older MariaDB versions (pre-10.6) that don't support SKIP LOCKED
        $conn->query("START TRANSACTION");
        
        // Fetch next pending/failed/stuck-processing email that hasn't exceeded 5 retry attempts
        // Include 'processing' status if delivery_time is old (crashed worker recovery)
        $query = "SELECT id, to_mail, attempt_count, smtpid, csv_list_id FROM mail_blaster ";
        $query .= "WHERE campaign_id = $campaign_id ";
        $query .= "AND (";
        $query .= "  (status IN ('pending', 'failed') AND attempt_count < 5) ";
        $query .= "  OR (status = 'processing' AND delivery_time < DATE_SUB(NOW(), INTERVAL 60 SECOND) AND attempt_count < 5)";
        $query .= ") ";
        $query .= "ORDER BY attempt_count ASC, delivery_date ASC, id ASC LIMIT 1 ";
        $query .= "FOR UPDATE"; // Lock the row
        
        $res = $conn->query($query);
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            
            // IMMEDIATE UPDATE: Change status to 'processing' to prevent other workers from selecting it
            // Also update delivery_time and smtpid to track which worker/server is handling it
            $email_id = (int)$row['id'];
            $updateQuery = "UPDATE mail_blaster SET status = 'processing', delivery_time = NOW(), smtpid = $server_id ";
            $updateQuery .= "WHERE id = $email_id AND campaign_id = $campaign_id";
            $conn->query($updateQuery);
            
            $conn->query("COMMIT");
            workerLog("fetchNextPending: Claimed email {$row['to_mail']} (ID: {$email_id}, attempt #{$row['attempt_count']}, csv_list_id={$row['csv_list_id']})");
            return ['to_mail' => $row['to_mail'], 'attempt_count' => $row['attempt_count'], 'csv_list_id' => $row['csv_list_id']];
        }
        
        $conn->query("COMMIT");
        workerLog("fetchNextPending: No pending emails available");
        return null;
    }

    function getActiveServerCount($conn, $campaign_id) {
        // Count active SMTP servers
        $res = $conn->query("SELECT COUNT(*) AS c FROM smtp_servers WHERE is_active = 1");
        $n = ($res && $res->num_rows > 0) ? intval($res->fetch_assoc()['c']) : 0;
        return max(1, $n);
    }

    function assignPendingToAccount($conn, $campaign_id, $to_mail, $account_id) {
        $to = $conn->real_escape_string($to_mail);
        // Only update if campaign still exists
        $existsRes = $conn->query("SELECT 1 FROM campaign_master WHERE campaign_id = " . intval($campaign_id) . " LIMIT 1");
        if ($existsRes && $existsRes->num_rows > 0) {
            // Update pending/failed emails - ensure we don't reassign if already being processed by another worker
            $conn->query("UPDATE mail_blaster SET smtpid = $account_id, delivery_date = CURDATE(), delivery_time = CURTIME() WHERE campaign_id = $campaign_id AND to_mail = '$to' AND status IN ('pending', 'failed') AND attempt_count < 5");
        }
    }

    // Utility: table existence check (no schema changes)
    function tableExists($conn, $name) {
        $n = $conn->real_escape_string($name);
        $res = @$conn->query("SHOW TABLES LIKE '" . $n . "'");
        return ($res && $res->num_rows > 0);
    }

    // Check if campaign is completed and update status
    function checkCampaignCompletion($conn, $campaign_id) {
        // Get campaign source
        $campaignRes = $conn->query("SELECT import_batch_id, csv_list_id FROM campaign_master WHERE campaign_id = $campaign_id");
        if (!$campaignRes || $campaignRes->num_rows === 0) {
            return;
        }
        
        $campaignData = $campaignRes->fetch_assoc();
        $import_batch_id = $campaignData['import_batch_id'];
        $csv_list_id = intval($campaignData['csv_list_id']);
        
        // Count emails from mail_blaster (source of truth)
        $statusQuery = "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success_count,
            SUM(CASE WHEN status = 'failed' AND attempt_count >= 5 THEN 1 ELSE 0 END) as failed_count,
            SUM(CASE WHEN status IN ('pending', 'failed') AND attempt_count < 5 THEN 1 ELSE 0 END) as retryable_count
        FROM mail_blaster 
        WHERE campaign_id = $campaign_id";
        
        $statusRes = $conn->query($statusQuery);
        if (!$statusRes) {
            workerLog("Campaign $campaign_id: Error querying mail_blaster");
            return;
        }
        
        $stats = $statusRes->fetch_assoc();
        $total = intval($stats['total']);
        $successCount = intval($stats['success_count']);
        $failedCount = intval($stats['failed_count']);
        $retryableCount = intval($stats['retryable_count']);
        
        workerLog("Campaign $campaign_id: Total=$total, Success=$successCount, Failed(5+)=$failedCount, Retryable(<5)=$retryableCount");
        
        // Update campaign_status table with accurate counts
        if ($total > 0) {
            $updateStatsQuery = "UPDATE campaign_status 
                SET total_emails = $total,
                    sent_emails = $successCount,
                    failed_emails = $failedCount,
                    pending_emails = $retryableCount
                WHERE campaign_id = $campaign_id";
            $conn->query($updateStatsQuery);
        }
        
        // Check for unclaimed emails
        $unclaimed = 0;
        if ($import_batch_id) {
            $batch_escaped = $conn->real_escape_string($import_batch_id);
            $unclaimedQuery = "SELECT COUNT(*) as unclaimed FROM imported_recipients ir
                WHERE ir.import_batch_id = '$batch_escaped'
                AND ir.is_active = 1
                AND ir.Emails IS NOT NULL
                AND ir.Emails <> ''
                AND NOT EXISTS (
                    SELECT 1 FROM mail_blaster mb
                    WHERE mb.campaign_id = $campaign_id
                    AND mb.to_mail COLLATE utf8mb4_unicode_ci = ir.Emails
                )";
            $unclaimedRes = $conn->query($unclaimedQuery);
            if ($unclaimedRes) {
                $unclaimedRow = $unclaimedRes->fetch_assoc();
                $unclaimed = intval($unclaimedRow['unclaimed']);
            }
        } elseif ($csv_list_id > 0) {
            $unclaimedQuery = "SELECT COUNT(*) as unclaimed FROM emails e
                WHERE e.domain_status = 1
                AND e.validation_status = 'valid'
                AND e.raw_emailid IS NOT NULL
                AND e.raw_emailid <> ''
                AND e.csv_list_id = $csv_list_id
                AND NOT EXISTS (
                    SELECT 1 FROM mail_blaster mb
                    WHERE mb.campaign_id = $campaign_id
                    AND mb.to_mail = e.raw_emailid
                )";
            $unclaimedRes = $conn->query($unclaimedQuery);
            if ($unclaimedRes) {
                $unclaimedRow = $unclaimedRes->fetch_assoc();
                $unclaimed = intval($unclaimedRow['unclaimed']);
            }
        }
        
        // Mark as completed when all retries exhausted and no unclaimed emails
        if ($unclaimed === 0 && $retryableCount === 0 && $total > 0) {
            workerLog("Campaign $campaign_id: All retry attempts exhausted! Marking as completed.");
            $conn->query("UPDATE campaign_status SET status = 'completed', end_time = NOW(), process_pid = NULL, pending_emails = 0 WHERE campaign_id = $campaign_id AND status != 'completed'");
        } else {
            workerLog("Campaign $campaign_id: Not completed. Retryable=$retryableCount, Unclaimed=$unclaimed");
        }
    }

