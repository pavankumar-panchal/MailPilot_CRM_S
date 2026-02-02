    <?php

    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception;

    require __DIR__ . '/../vendor/autoload.php';

    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    set_time_limit(0);

    // Ensure consistent timezone for hour-based limits
    date_default_timezone_set('Asia/Kolkata');

    // Worker debug logging
    function workerLog($msg) {
        $ts = date('Y-m-d H:i:s');
        echo "[$ts] $msg\n"; // Console only
    }

    // Catch fatal errors
    register_shutdown_function(function() {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
    //         file_put_contents(__DIR__ . '/../logs/email_worker.log', '[' . date('Y-m-d H:i:s') . "] FATAL ERROR: {$error['message']} in {$error['file']}:{$error['line']}\n", FILE_APPEND);
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
            $campaign['mail_body'] = stripcslashes($campaign['mail_body']);
    //         file_put_contents(__DIR__ . '/../logs/email_worker.log', '[' . date('Y-m-d H:i:s') . "] Campaign #$campaign_id loaded from DB\n", FILE_APPEND);
        } else {
    //         file_put_contents(__DIR__ . '/../logs/email_worker.log', '[' . date('Y-m-d H:i:s') . "] ERROR: Campaign #$campaign_id not found in DB\n", FILE_APPEND);
            $conn->close();
            exit(1);
        }
    }

    $server_id = isset($server_config['server_id']) ? intval($server_config['server_id']) : 0;
    workerLog("Worker for server #$server_id starting");

    if (!empty($server_config)) {
        $safeServer = $server_config;
        unset($safeServer['password']);
    workerLog('Server config: ' . json_encode($safeServer));
    }

    $accounts = loadActiveAccountsForServer($conn, $server_id);
    workerLog("Server #$server_id loaded " . count($accounts) . ' accounts');

    // Log queue state before entering loop
    $eligibleRes = $conn->query("SELECT COUNT(*) AS c FROM emails WHERE domain_status = 1 AND validation_status = 'valid' AND raw_emailid IS NOT NULL AND raw_emailid <> ''");
    $eligibleCount = ($eligibleRes && $eligibleRes->num_rows) ? (int)$eligibleRes->fetch_assoc()['c'] : 0;
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
            // All SMTP accounts are at hourly/daily limits. Stop immediately to free DB connections.
            workerLog("Server #$server_id: All accounts at limits; exiting");
            $conn->close();
            exit(0);
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
            workerLog("Server #$server_id: Found pending/retry email: $to (attempt #{$pending['attempt_count']})");
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
                $claimed = claimNextEmail($conn, $campaign_id);
            } catch (Exception $e) {
                workerLog("Server #$server_id: ERROR in claimNextEmail: " . $e->getMessage());
                $claimed = null;
            }
            
            if (!$claimed) {
                workerLog("Server #$server_id: No more emails to claim. Queue exhausted.");
                break; // no more emails
            }
            $to = $claimed['to_mail'];
            workerLog("Server #$server_id: Claimed NEW email: $to -> assigned to account #{$selected['id']} ({$selected['email']})");
        }

        try {
            workerLog("Server #$server_id: Sending to $to via account #{$selected['id']} ({$selected['email']}) on {$server_config['host']}:{$server_config['port']}");
            sendEmail($conn, $campaign_id, $to, $server_config, $selected, $campaign);
            $send_count++;
            workerLog("Server #$server_id: ✓ SUCCESS sent to $to via account #{$selected['id']} ({$selected['email']}) [total sent: $send_count]");
            if ($send_count % 10 == 0) { workerLog("Server #$server_id: === Progress: $send_count emails sent ==="); }
            usleep(100000);
        } catch (Exception $e) {
            // Check current attempt count
            $attemptRes = $conn->query("SELECT attempt_count FROM mail_blaster WHERE campaign_id = $campaign_id AND to_mail = '" . $conn->real_escape_string($to) . "'");
            $currentAttempts = ($attemptRes && $attemptRes->num_rows > 0) ? intval($attemptRes->fetch_assoc()['attempt_count']) : 0;
            
            if ($currentAttempts >= 5) {
                // Mark as permanently failed after 5 attempts
                workerLog("Server #$server_id: ✗ PERMANENT FAILURE to $to via account #{$selected['id']} ({$selected['email']}) after $currentAttempts attempts - Error: " . $e->getMessage());
                recordDelivery($conn, $selected['id'], $server_id, $campaign_id, $to, 'failed', "Max retries exceeded: " . $e->getMessage());
            } else {
                // Keep as pending for retry
                workerLog("Server #$server_id: ✗ FAILED (attempt #$currentAttempts/5) to $to via account #{$selected['id']} ({$selected['email']}) - Will retry. Error: " . $e->getMessage());
                recordDelivery($conn, $selected['id'], $server_id, $campaign_id, $to, 'failed', $e->getMessage());
            }
        }
    }

    // Mark stopped before exiting
    workerLog("Server #$server_id: Worker stopping after sending $send_count emails");

    $conn->close();
    exit(0);
        
    function sendEmail($conn, $campaign_id, $to_email, $server, $account, $campaign) {
        if (!filter_var($to_email, FILTER_VALIDATE_EMAIL)) { throw new Exception("Invalid email: $to_email"); }
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $server['host'];
        $mail->Port = $server['port'];
        $mail->SMTPAuth = true;
        $mail->Username = $account['email'];
        $mail->Password = $account['password'];
        $mail->Timeout = 30;
        $mail->SMTPDebug = 0;
        $mail->SMTPKeepAlive = false;
        if ($server['encryption'] === 'ssl') $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        elseif ($server['encryption'] === 'tls') $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->SMTPOptions = ['ssl' => ['verify_peer' => false,'verify_peer_name' => false,'allow_self_signed' => true,'crypto_method' => STREAM_CRYPTO_METHOD_TLS_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT]];
        $mail->SMTPAutoTLS = true;
        $mail->setFrom($account['email']);
        $mail->addAddress($to_email);
        $mail->Subject = $campaign['mail_subject'];
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->XMailer = ' ';
        $mail->addCustomHeader('MIME-Version','1.0');
        $mail->addCustomHeader('X-Priority','3');
        $mail->addCustomHeader('Importance','Normal');
        $isHtml = !empty($campaign['send_as_html']);
        $mail->isHTML($isHtml);
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
        $body = $campaign['mail_body']; if (empty(trim($body))) throw new Exception("Empty body");
        list($processedBody, $embeddedCount) = embed_local_images($mail, $body);
        if ($embeddedCount > 0 && !$isHtml) { $mail->isHTML(true); }
        $mail->Body = $processedBody; if ($isHtml || $embeddedCount > 0) $mail->AltBody = strip_tags($processedBody);
        if (!$mail->send()) throw new Exception($mail->ErrorInfo);
        $srvId = intval($server['server_id'] ?? 0);
        recordDelivery($conn, $account['id'], $srvId, $campaign_id, $to_email, 'success');
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

    function recordDelivery($conn, $smtp_account_id, $server_id, $campaign_id, $to_email, $status, $error = null) {
        $stmt = $conn->prepare("INSERT INTO mail_blaster (campaign_id,to_mail,smtpid,delivery_date,delivery_time,status,error_message,attempt_count) VALUES (?,?,?,CURDATE(),CURTIME(),?,?,1) ON DUPLICATE KEY UPDATE smtpid=VALUES(smtpid), delivery_date=VALUES(delivery_date), delivery_time=VALUES(delivery_time), status=IF(mail_blaster.status='success','success',VALUES(status)), error_message=IF(VALUES(status)='failed',VALUES(error_message),NULL), attempt_count=IF(mail_blaster.status='success',mail_blaster.attempt_count,LEAST(mail_blaster.attempt_count+1,5))");
        // Skip writes if campaign is deleted
        $existsRes = $conn->query("SELECT 1 FROM campaign_master WHERE campaign_id = " . intval($campaign_id) . " LIMIT 1");
        if (!$existsRes || $existsRes->num_rows === 0) {
            return;
        }
        $stmt->bind_param("isiss", $campaign_id, $to_email, $smtp_account_id, $status, $error);
        $stmt->execute();
        $stmt->close();
        
        if ($status === 'success') {
            $conn->query("UPDATE smtp_accounts SET sent_today = sent_today + 1, total_sent = total_sent + 1 WHERE id = $smtp_account_id");
            $usage_date = date('Y-m-d'); $usage_hour = (int)date('G'); $now = date('Y-m-d H:i:s');
            // Increment hourly usage, then hard-cap to hourly_limit to avoid > limit due to race conditions
            $conn->query("INSERT INTO smtp_usage (smtp_id,date,hour,timestamp,emails_sent) VALUES ($smtp_account_id,'$usage_date',$usage_hour,'$now',1) ON DUPLICATE KEY UPDATE emails_sent = emails_sent + 1, timestamp = VALUES(timestamp)");
                    // Only clamp when an explicit hourly_limit is configured (>0)
                    $conn->query("UPDATE smtp_usage su JOIN smtp_accounts sa ON sa.id = $smtp_account_id 
                                                SET su.emails_sent = LEAST(su.emails_sent, sa.hourly_limit)
                                                WHERE su.smtp_id = $smtp_account_id 
                                                    AND su.date = '$usage_date' AND su.hour = $usage_hour
                                                    AND sa.hourly_limit > 0");
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

    function loadActiveAccountsForServer($conn, $server_id) {
        $accounts = []; $res = $conn->query("SELECT id,email,password,daily_limit,hourly_limit,sent_today,total_sent FROM smtp_accounts WHERE smtp_server_id = $server_id AND is_active = 1 ORDER BY id ASC");
        if ($res) { while ($r = $res->fetch_assoc()) $accounts[] = $r; }
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

    function claimNextEmail($conn, $campaign_id, $depth = 0) {
        global $server_id;
        
        // Safety: confirm campaign still exists before attempting to claim/insert
        $existsRes = $conn->query("SELECT 1 FROM campaign_master WHERE campaign_id = " . intval($campaign_id) . " LIMIT 1");
        if (!$existsRes || $existsRes->num_rows === 0) {
            return null;
        }
        
        // Pick next eligible email that's NOT already in mail_blaster
        // Use server_id as offset to spread workers across different ranges of the email table
        // This prevents all workers from competing for the same email
        $offset = ($server_id - 14) * 50; // Each server gets a different starting position
        $res = $conn->query("SELECT e.id, e.raw_emailid AS to_mail 
            FROM emails e 
            WHERE e.domain_status = 1 
            AND e.validation_status = 'valid' 
            AND e.raw_emailid IS NOT NULL 
            AND e.raw_emailid <> ''
            AND NOT EXISTS (
                SELECT 1 FROM mail_blaster mb 
                WHERE mb.campaign_id = $campaign_id 
                AND mb.to_mail = e.raw_emailid
            )
            ORDER BY e.id ASC 
            LIMIT 1 OFFSET $offset");
            
        if (!$res || $res->num_rows === 0) {
            // If our offset range is exhausted, try from beginning without offset
            if ($offset > 0) {
                $res = $conn->query("SELECT e.id, e.raw_emailid AS to_mail 
                    FROM emails e 
                    WHERE e.domain_status = 1 
                    AND e.validation_status = 'valid' 
                    AND e.raw_emailid IS NOT NULL 
                    AND e.raw_emailid <> ''
                    AND NOT EXISTS (
                        SELECT 1 FROM mail_blaster mb 
                        WHERE mb.campaign_id = $campaign_id 
                        AND mb.to_mail = e.raw_emailid
                    )
                    ORDER BY e.id ASC 
                    LIMIT 1");
            }
            
            if (!$res || $res->num_rows === 0) {
                if ($depth === 0) { workerLog("claimNextEmail: no unclaimed eligible email found"); }
                return null;
            }
        }
        
        $row = $res->fetch_assoc();
        $to = $conn->real_escape_string($row['to_mail']);
        workerLog("claimNextEmail: attempting to claim $to (email.id={$row['id']}, depth=$depth)");
        
        $conn->query("INSERT INTO mail_blaster (campaign_id,to_mail,smtpid,delivery_date,delivery_time,status,error_message,attempt_count) 
            VALUES ($campaign_id,'$to',NULL,CURDATE(),CURTIME(),'pending',NULL,0)
            ON DUPLICATE KEY UPDATE
                campaign_id = VALUES(campaign_id),
                to_mail = VALUES(to_mail),
                smtpid = VALUES(smtpid),
                delivery_date = VALUES(delivery_date),
                delivery_time = VALUES(delivery_time),
                status = IF(mail_blaster.status='success','success','pending'),
                error_message = NULL,
                attempt_count = mail_blaster.attempt_count");
                
        if ($conn->errno) { 
            workerLog("claimNextEmail: DB error while claiming $to: " . $conn->error); 
            return null; 
        }
        
        // Verify that we successfully claimed it (check if we won the race)
        $chk = $conn->query("SELECT status, attempt_count, smtpid FROM mail_blaster WHERE campaign_id = $campaign_id AND to_mail = '$to' LIMIT 1");
        if ($chk && $chk->num_rows > 0) {
            $mb = $chk->fetch_assoc();
            if ($mb['status'] === 'success' || intval($mb['attempt_count']) >= 5) {
                workerLog("claimNextEmail: $to already in status={$mb['status']} attempts={$mb['attempt_count']} — seeking next (depth=$depth)");
                if ($depth > 50) { workerLog("claimNextEmail: depth limit reached, giving up"); return null; }
                return claimNextEmail($conn, $campaign_id, $depth + 1);
            }
        }
        
        workerLog("claimNextEmail: ✓ successfully claimed $to");
        return ['id' => $row['id'], 'to_mail' => $row['to_mail']];
    }

    function fetchNextPending($conn, $campaign_id, $server_id) {
        // Quick check: if no pending emails exist at all, return immediately
        $quickCheck = $conn->query("SELECT 1 FROM mail_blaster WHERE campaign_id = $campaign_id AND status IN ('pending', 'failed') AND attempt_count < 5 LIMIT 1");
        if (!$quickCheck || $quickCheck->num_rows === 0) {
            workerLog("fetchNextPending: No pending/failed emails in queue");
            return null;
        }
        
        // Fetch next pending/failed email that hasn't exceeded 5 retry attempts
        // Any worker can retry any failed email (simple round-robin retry)
        $query = "SELECT to_mail, attempt_count, smtpid FROM mail_blaster ";
        $query .= "WHERE campaign_id = $campaign_id ";
        $query .= "AND status IN ('pending', 'failed') ";
        $query .= "AND attempt_count < 5 ";
        $query .= "ORDER BY attempt_count ASC, delivery_date ASC, id ASC LIMIT 1";
        
        $res = $conn->query($query);
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            workerLog("fetchNextPending: Found pending email {$row['to_mail']} (attempt #{$row['attempt_count']})");
            return ['to_mail' => $row['to_mail'], 'attempt_count' => $row['attempt_count']];
        }
        
        workerLog("fetchNextPending: No pending emails found");
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
            $conn->query("UPDATE mail_blaster SET smtpid = $account_id, delivery_date = CURDATE(), delivery_time = CURTIME() WHERE campaign_id = $campaign_id AND to_mail = '$to' AND status = 'pending'");
        }
    }

    // Utility: table existence check (no schema changes)
    function tableExists($conn, $name) {
        $n = $conn->real_escape_string($name);
        $res = @$conn->query("SHOW TABLES LIKE '" . $n . "'");
        return ($res && $res->num_rows > 0);
    }
