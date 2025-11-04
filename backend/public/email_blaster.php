<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require __DIR__ . '/../vendor/autoload.php';

date_default_timezone_set('Asia/Kolkata');


$campaign_id = isset($argv[1]) ? intval($argv[1]) : die("No campaign ID specified");
$GLOBALS['campaign_id'] = $campaign_id;

// Create PID file for process tracking
$pid_dir = __DIR__ . '/../tmp';
if (!is_dir($pid_dir)) {
    mkdir($pid_dir, 0777, true);
}
$pid_file = $pid_dir . "/email_blaster_{$campaign_id}.pid";
file_put_contents($pid_file, getmypid());

register_shutdown_function(function () use ($pid_file) {
    if (file_exists($pid_file)) {
        unlink($pid_file);
    }
});

while (true) {
    try {
        $dbConfig = [
            'host' => '127.0.0.1',
            'username' => 'root',
            'password' => '',
            'name' => 'CRM',
            'port' => 3306
        ];

        $conn = new mysqli($dbConfig['host'], $dbConfig['username'], $dbConfig['password'], $dbConfig['name'], $dbConfig['port']);
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }

        // Check campaign status
        $result = $conn->query("
            SELECT status, total_emails, sent_emails, pending_emails, failed_emails
            FROM campaign_status 
            WHERE campaign_id = $campaign_id
        ");

        if ($result->num_rows === 0) {
            logMessage("Campaign not found. Exiting.");
            break;
        }

        $campaign_data = $result->fetch_assoc();
        $status = $campaign_data['status'];

        if ($status !== 'running') {
            logMessage("Campaign status is '$status'. Exiting process.");
            break;
        }

        // Check network connectivity
        if (!checkNetworkConnectivity()) {
            logMessage("Network connection unavailable. Waiting to retry...", 'WARNING');
            sleep(60);
            continue;
        }

        // Check remaining emails
        $remaining_result = $conn->query("
            SELECT COUNT(*) as remaining FROM emails e
            WHERE e.domain_status = 1
            AND NOT EXISTS (
                SELECT 1 FROM mail_blaster mb 
                WHERE mb.to_mail = e.raw_emailid 
                AND mb.campaign_id = $campaign_id
                AND mb.status = 'success'
            )");
        $remaining_count = $remaining_result->fetch_assoc()['remaining'];

        if ($remaining_count == 0) {
            $conn->query("UPDATE campaign_status SET status = 'completed', pending_emails = 0, end_time = NOW() 
                      WHERE campaign_id = $campaign_id");
            logMessage("All valid emails processed. Campaign completed.");
            break;
        }

        $result = $conn->query("SELECT COUNT(*) AS total FROM emails e 
        LEFT JOIN mail_blaster mb ON mb.to_mail = e.raw_emailid AND mb.campaign_id = $campaign_id
        WHERE e.domain_status = 1
        AND (mb.id IS NULL OR mb.status IN ('failed', 'pending'))
        AND NOT EXISTS (
            SELECT 1 FROM mail_blaster mb2 
            WHERE mb2.to_mail = e.raw_emailid 
            AND mb2.campaign_id = $campaign_id
            AND mb2.status = 'success'
        )
    ");
        $total_email_count = (int) $result->fetch_assoc()['total'];

        // Process emails
        $processed_count = processEmailBatch($conn, $campaign_id, $total_email_count);

        // Adjust sleep time based on processing results
        if ($processed_count > 0) {
            // No sleep - continue immediately to next batch for fast sending
            usleep(100000); // 0.1 second minimal pause to avoid hammering the DB
        } else {
            $status_check = $conn->query("SELECT status FROM campaign_status WHERE campaign_id = $campaign_id")->fetch_assoc();
            if ($status_check['status'] !== 'running') {
                break;
            }
            sleep(5); // Wait 5s when no emails to process
        }
        $conn->close();
    } catch (Exception $e) {
        logMessage("Error in main loop: " . $e->getMessage(), 'ERROR');
        sleep(60);
    }
}

function checkNetworkConnectivity()
{
    $connected = @fsockopen("8.8.8.8", 53, $errno, $errstr, 5);
    if ($connected) {
        fclose($connected);
        return true;
    }
    return false;
}

function processEmailBatch($db, $campaign_id, $total_email_count)
{
    $processed_count = 0;
    $max_retries = 3;

    // Get campaign details including attachments and images
    $dbNameRes = $db->query("SELECT DATABASE() as db");
    $dbName = $dbNameRes ? $dbNameRes->fetch_assoc()['db'] : '';
    $columns = [];
    
    if ($dbName) {
        $colCheck = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '" . $db->real_escape_string($dbName) . "' AND TABLE_NAME = 'campaign_master'");
        while ($col = $colCheck->fetch_assoc()) {
            $columns[] = $col['COLUMN_NAME'];
        }
    }

    $select = "SELECT mail_subject, mail_body";
    if (in_array('send_as_html', $columns)) $select .= ", send_as_html";
    if (in_array('attachment_path', $columns)) $select .= ", attachment_path";
    if (in_array('images_paths', $columns)) $select .= ", images_paths";
    if (in_array('reply_to', $columns)) $select .= ", reply_to";
    $select .= " FROM campaign_master WHERE campaign_id = $campaign_id";
    
    $campaign = $db->query($select)->fetch_assoc();

    if (!$campaign) {
        logMessage("Campaign not found", 'ERROR');
        return 0;
    }
        
        // Normalize mail_body: convert escaped sequences ("\\r\\n") to real newlines
        if (isset($campaign['mail_body'])) {
            $normalized = stripcslashes($campaign['mail_body']);
            // If sending as HTML, decode HTML entities
            if (!empty($campaign['send_as_html'])) {
                $normalized = html_entity_decode($normalized, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
            $campaign['mail_body'] = $normalized;
        } else {
            $campaign['mail_body'] = '';
        }


        // Validate campaign has at least one content: mail_body, images, or attachment
        $trimmedBody = trim($campaign['mail_body'] ?? '');
        $hasImages = !empty($campaign['images_paths']) && is_array(json_decode($campaign['images_paths'], true)) && count(json_decode($campaign['images_paths'], true)) > 0;
        $hasAttachment = !empty($campaign['attachment_path']);
        $textOnly = !empty($campaign['send_as_html']) ? trim(strip_tags($campaign['mail_body'])) : $trimmedBody;

        if (empty($textOnly) && !$hasImages && !$hasAttachment) {
            logMessage("[ERROR] Campaign #$campaign_id has no content (body, images, or attachment). Cannot send emails.", 'ERROR');
            $db->query("UPDATE campaign_status SET status = 'paused', error_message = 'No email content (body, images, or attachment)' WHERE campaign_id = $campaign_id");
            die("ERROR: Campaign has no content. Please add text, image, or attachment before sending.\n");
        }

        // Validate mail subject is not empty
        if (empty(trim($campaign['mail_subject']))) {
            logMessage("[ERROR] Campaign #$campaign_id has empty mail_subject. Cannot send emails.", 'ERROR');
            die("ERROR: Campaign mail_subject is empty. Please add email subject before sending.\n");
        }

    // Get all active SMTP accounts and their server details
    $smtp_accounts = $db->query("
        SELECT 
            sa.id AS smtp_account_id,
            sa.email AS smtp_email,
            sa.password AS smtp_password,
            sa.daily_limit,
            sa.hourly_limit,
            ss.host,
            ss.port,
            ss.encryption,
            ss.received_email
        FROM smtp_accounts sa
        JOIN smtp_servers ss ON sa.smtp_server_id = ss.id
        WHERE sa.is_active = 1 AND ss.is_active = 1
        ORDER BY sa.id ASC
    ")->fetch_all(MYSQLI_ASSOC);

    if (empty($smtp_accounts)) {
        logMessage("No active SMTP accounts found.", 'ERROR');
        return 0;
    }

    // Get emails to send (one by one, up to 100 per batch)
    $emails = $db->query("
        SELECT e.id, e.raw_emailid
        FROM emails e
        LEFT JOIN mail_blaster mb ON mb.to_mail = e.raw_emailid AND mb.campaign_id = $campaign_id
        WHERE e.domain_status = 1
              AND (mb.id IS NULL OR mb.status IN ('failed', 'pending'))
          AND NOT EXISTS (
              SELECT 1 FROM mail_blaster mb2 
              WHERE mb2.to_mail = e.raw_emailid 
              AND mb2.campaign_id = $campaign_id
              AND mb2.status = 'success'
          )
        ORDER BY IFNULL(mb.attempt_count,0) ASC, e.id ASC
        LIMIT 100
    ")->fetch_all(MYSQLI_ASSOC);

    if (empty($emails)) {
        logMessage("No emails to process in this batch.", 'INFO');
        return 0;
    }

    // Track SMTP usage for limits
    $smtp_usage = [];
    foreach ($smtp_accounts as $smtp) {
        $smtp_usage[$smtp['smtp_account_id']] = [
            'daily' => getSmtpAccountSent($db, $smtp['smtp_account_id'], 'daily'),
            'hourly' => getSmtpAccountSent($db, $smtp['smtp_account_id'], 'hourly')
        ];
    }

    $smtp_count = count($smtp_accounts);

    foreach ($emails as $email) {
        $to = $email['raw_emailid'];
        $emailId = (int)$email['id'];

        $attempts = 0;
        $sent = false;

        // Try across SMTPs until success or all SMTPs attempted once
        while (!$sent && $attempts < $smtp_count) {
            // Get next SMTP from rotation atomically (with row locking)
            $smtp_found = false;
            $all_smtp_checked = 0;

            while (!$smtp_found) {
                $db->begin_transaction();
                try {
                    $rotation = $db->query("SELECT last_smtp_index, total_smtp_count FROM smtp_rotation WHERE id = 1 FOR UPDATE")->fetch_assoc();

                    if (!$rotation) {
                        $db->query("INSERT INTO smtp_rotation (id, last_smtp_index, total_smtp_count) VALUES (1, -1, $smtp_count) ON DUPLICATE KEY UPDATE total_smtp_count = $smtp_count");
                        $smtp_index = 0;
                        $db->query("UPDATE smtp_rotation SET last_smtp_index = 0, total_smtp_count = $smtp_count WHERE id = 1");
                        $all_smtp_checked = 0;
                    } elseif ($rotation['total_smtp_count'] != $smtp_count) {
                        $smtp_index = 0;
                        $db->query("UPDATE smtp_rotation SET last_smtp_index = 0, total_smtp_count = $smtp_count WHERE id = 1");
                        $all_smtp_checked = 0;
                    } else {
                        $smtp_index = ($rotation['last_smtp_index'] + 1) % $smtp_count;
                    }

                    $smtp = $smtp_accounts[$smtp_index];
                    $sid = $smtp['smtp_account_id'];

                    if ($smtp_usage[$sid]['daily'] < $smtp['daily_limit'] && $smtp_usage[$sid]['hourly'] < $smtp['hourly_limit']) {
                        $db->query("UPDATE smtp_rotation SET last_smtp_index = $smtp_index, last_smtp_id = $sid WHERE id = 1");
                        $db->commit();
                        $smtp_found = true;
                        $all_smtp_checked = 0;
                        logMessage("[ROTATION] Email to $to → SMTP[$smtp_index] {$smtp['smtp_email']} (ID: $sid)", 'INFO');
                    } else {
                        $db->query("UPDATE smtp_rotation SET last_smtp_index = $smtp_index WHERE id = 1");
                        $db->commit();
                        $all_smtp_checked++;
                        logMessage("[ROTATION] SMTP[$smtp_index] {$smtp['smtp_email']} at limit, trying next...", 'WARNING');
                        if ($all_smtp_checked >= $smtp_count) {
                            logMessage("[WAIT] All $smtp_count SMTPs at limit. Waiting 60s to refresh limits...", 'WARNING');
                            sleep(60);
                            foreach ($smtp_accounts as $smtpX) {
                                $sidX = $smtpX['smtp_account_id'];
                                $smtp_usage[$sidX]['daily'] = getSmtpAccountSent($db, $sidX, 'daily');
                                $smtp_usage[$sidX]['hourly'] = getSmtpAccountSent($db, $sidX, 'hourly');
                            }
                            $all_smtp_checked = 0;
                            logMessage("[REFRESH] SMTP usage limits refreshed. Retrying...", 'INFO');
                        }
                    }
                } catch (Exception $e) {
                    $db->rollback();
                    logMessage("[ROTATION] Lock error: " . $e->getMessage(), 'ERROR');
                    usleep(100000);
                }
            }

            // Log which SMTP and recipient is being used
            logMessage("[SEND_ATTEMPT] To: $to | SMTP: {$smtp['smtp_email']} (ID: {$smtp['smtp_account_id']})", 'INFO');

            try {
                $isHtml = !empty($campaign['send_as_html']);
                sendEmail(
                    [
                        'host' => $smtp['host'],
                        'port' => $smtp['port'],
                        'email' => $smtp['smtp_email'],
                        'password' => $smtp['smtp_password'],
                        'encryption' => $smtp['encryption'],
                        'received_email' => $smtp['received_email']
                    ],
                    $to,
                    $campaign['mail_subject'],
                    $campaign['mail_body'],
                    $isHtml,
                    $campaign
                );

                recordDelivery($db, $smtp['smtp_account_id'], $emailId, $campaign_id, $to, 'success');
                logMessage("[SEND_SUCCESS] To: $to | SMTP: {$smtp['smtp_email']} (ID: {$smtp['smtp_account_id']})", 'SUCCESS');
                logEmailDetail($campaign_id, $to, $smtp['smtp_account_id'], $smtp['smtp_email'], 'success');

                $db->query("UPDATE campaign_status SET sent_emails = sent_emails + 1, pending_emails = GREATEST(0, pending_emails - 1) WHERE campaign_id = $campaign_id");

                $smtp_usage[$smtp['smtp_account_id']]['daily']++;
                $smtp_usage[$smtp['smtp_account_id']]['hourly']++;
                $processed_count++;
                $sent = true;
            } catch (Exception $e) {
                $msg = $e->getMessage();
                // If transient connection issue, skip counting as failed and try next SMTP
                if (isTransientSmtpError($msg)) {
                    logMessage("[SKIP_SMTP] Transient SMTP issue on {$smtp['smtp_email']} (ID: {$smtp['smtp_account_id']}): $msg", 'WARNING');
                    $attempts++;
                    // Try next SMTP in rotation
                    continue;
                }

                // Non-transient error: record as failed
                recordDelivery($db, $smtp['smtp_account_id'], $emailId, $campaign_id, $to, 'failed', $msg);
                logMessage("[SEND_FAILED] To: $to | SMTP: {$smtp['smtp_email']} (ID: {$smtp['smtp_account_id']}) | Error: $msg", 'ERROR');
                logEmailDetail($campaign_id, $to, $smtp['smtp_account_id'], $smtp['smtp_email'], 'failed', $msg);
                $db->query("UPDATE campaign_status SET failed_emails = failed_emails + 1, pending_emails = GREATEST(0, pending_emails - 1) WHERE campaign_id = $campaign_id");
                // Stop attempting for this email on non-transient errors
                break;
            }
        }

        if (!$sent && $attempts >= $smtp_count) {
            // Leave email as pending to retry later without logging as failed
            logMessage("[REQUEUE] No reachable SMTPs for $to after $attempts attempts. Will retry later.", 'WARNING');
        }

        // No per-email throttle - send as fast as possible
        // The per-email SMTP rotation already handles safe concurrent access
    }

    return $processed_count;
}

function getSmtpAccountSent($conn, $smtp_account_id, $type = 'daily')
{
    if ($type === 'daily') {
        $result = $conn->query("
            SELECT COUNT(*) as cnt FROM mail_blaster 
            WHERE smtpid = $smtp_account_id AND status = 'success' AND delivery_date = CURDATE()
        ");
    } else {
        $hour = date('H');
        $result = $conn->query("
            SELECT COUNT(*) as cnt FROM mail_blaster 
            WHERE smtpid = $smtp_account_id AND status = 'success' AND delivery_date = CURDATE() AND HOUR(delivery_time) = $hour
        ");
    }
    $row = $result->fetch_assoc();
    return (int)($row['cnt'] ?? 0);
}

function sendEmail($smtp, $to_email, $subject, $body, $isHtml = false, $campaign = [])
{
    // Validate email format before attempting to send
    if (!filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Invalid email format: $to_email");
    }
    
    // Additional validation: Check for common typos and invalid patterns
    $to_email = trim($to_email);
    if (empty($to_email) || strlen($to_email) > 254) { // RFC 5321 limit
        throw new Exception("Email address too long or empty: $to_email");
    }
    
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = $smtp['host'];
        $mail->Port = $smtp['port'];                            
        $mail->SMTPAuth = true;
        $mail->Username = $smtp['email'];
        $mail->Password = $smtp['password'];
        $mail->Timeout = 30;
        
        // Enable SMTP KeepAlive for better performance with multiple sends
        $mail->SMTPKeepAlive = false; // Set to false to avoid connection issues
        
        // Disable SMTP debug output in production
        $mail->SMTPDebug = 0;

        if ($smtp['encryption'] === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($smtp['encryption'] === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }
        
        // Enhanced SMTPOptions for maximum compatibility with all domains
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
                'crypto_method' => STREAM_CRYPTO_METHOD_TLS_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT
            )
        );
        
        // Enable automatic retry on connection failure
        $mail->SMTPAutoTLS = true;

        $mail->setFrom($smtp['email']);
        $mail->addAddress($to_email);
        $mail->Subject = $subject;

        // Ensure proper charset for international emails (supports UTF-8 characters)
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64'; // Better compatibility with all mail servers
        
        // Add headers for better deliverability across all domain types
        $mail->XMailer = ' '; // Hide X-Mailer header to avoid spam filters
        
        // Add standard email headers for better acceptance
        $mail->addCustomHeader('MIME-Version', '1.0');
        $mail->addCustomHeader('X-Priority', '3'); // Normal priority
        $mail->addCustomHeader('X-MSMail-Priority', 'Normal');
        $mail->addCustomHeader('Importance', 'Normal');
        
        // Add List-Unsubscribe header (best practice for bulk email)
        if (!empty($campaign['unsubscribe_url'])) {
            $mail->addCustomHeader('List-Unsubscribe', '<' . $campaign['unsubscribe_url'] . '>');
        }

        // Validate body is not empty before sending
        if (empty(trim($body))) {
            throw new Exception("Message body is empty. Cannot send email to $to_email");
        }

        // Set HTML mode first (but don't set body yet - will be set after image processing)
        if ($isHtml) {
            $mail->isHTML(true);
        } else {
            $mail->isHTML(false);
        }

        // Set Reply-To from campaign or fallback to received_email from smtp_servers
        if (!empty($campaign['reply_to'])) {
            $mail->clearReplyTos();
            $mail->addReplyTo($campaign['reply_to']);
        } elseif (!empty($smtp['received_email'])) {
            $mail->clearReplyTos();
            $mail->addReplyTo($smtp['received_email']);
        }

        // Add attachment if present
        if (!empty($campaign['attachment_path'])) {
            $attachmentPath = __DIR__ . '/../' . $campaign['attachment_path'];
            if (file_exists($attachmentPath)) {
                try {
                    $mail->addAttachment($attachmentPath);
                    logMessage("Attachment added: " . basename($attachmentPath), 'INFO');
                } catch (Exception $e) {
                    logMessage("Failed to add attachment: " . $e->getMessage(), 'ERROR');
                    throw new Exception("Failed to add attachment: " . $e->getMessage());
                }
            } else {
                logMessage("[WARNING] Attachment file not found, continuing without it: " . basename($campaign['attachment_path']), 'ERROR');
                logMessage("Expected path: " . $attachmentPath, 'ERROR');
               
            }
        }

        // Add embedded images if present
        if (!empty($campaign['images_paths'])) {
            $images = is_string($campaign['images_paths']) 
                ? json_decode($campaign['images_paths'], true) 
                : $campaign['images_paths'];
            
            if (is_array($images)) {
                logMessage("[IMAGE_EMBED] Processing " . count($images) . " embedded images", 'INFO');
                
                foreach ($images as $index => $imagePath) {
                    $fullPath = __DIR__ . '/../' . $imagePath;
                    if (file_exists($fullPath)) {
                        // Generate unique CID for each image
                        $cid = 'image_' . $index . '_' . uniqid();
                        $mail->addEmbeddedImage($fullPath, $cid);
                        logMessage("[IMAGE_EMBED] Added image $index: " . basename($imagePath) . " as CID: $cid", 'INFO');
                        
                        // Replace image references in HTML body if HTML mode is enabled
                        if ($isHtml) {
                            $filename = basename($imagePath);
                            
                            // More comprehensive patterns to catch all possible image URL formats
                            $escapedPath = preg_quote($imagePath, '/');
                            $escapedFilename = preg_quote($filename, '/');
                            
                            // Pattern 1: Match any src attribute containing the full path
                            $count = 0;
                            $body = preg_replace(
                                '/(<img[^>]+src=["\'])[^"\']*' . $escapedPath . '(["\'])/i',
                                '${1}cid:' . $cid . '${2}',
                                $body,
                                -1,
                                $count
                            );
                            
                            if ($count > 0) {
                                logMessage("[IMAGE_REPLACE] Replaced $count occurrence(s) using full path pattern", 'INFO');
                            } else {
                                // Pattern 2: Match src containing just the filename
                                $body = preg_replace(
                                    '/(<img[^>]+src=["\'])[^"\']*' . $escapedFilename . '(["\'])/i',
                                    '${1}cid:' . $cid . '${2}',
                                    $body,
                                    -1,
                                    $count
                                );
                                
                                if ($count > 0) {
                                    logMessage("[IMAGE_REPLACE] Replaced $count occurrence(s) using filename pattern", 'INFO');
                                } else {
                                    logMessage("[IMAGE_REPLACE] WARNING: No matches found for image: $filename", 'WARNING');
                                }
                            }
                        }
                    } else {
                        logMessage("[IMAGE_EMBED] WARNING: Image file not found: $fullPath", 'WARNING');
                    }
                }
                
                // Body will be set after the loop
                logMessage("[IMAGE_EMBED] Completed processing all images, CID references updated in body", 'INFO');
            }
        }

        // NOW set the body and alt body AFTER all image processing is complete
        // This ensures CID references are included for ALL email types
        if ($isHtml) {
            $mail->Body = $body;
            $mail->AltBody = trim(strip_tags($body)); // Plain text version for non-HTML clients
            logMessage("[BODY_SET] HTML body set with " . strlen($body) . " characters", 'INFO');
        } else {
            $mail->Body = $body;
            $mail->AltBody = $body;
            logMessage("[BODY_SET] Plain text body set with " . strlen($body) . " characters", 'INFO');
        }

        // Final validation before sending
        if (empty(trim($mail->Body))) {
            logMessage("[ERROR] PHPMailer Body is empty right before send. Original body length: " . strlen($body), 'ERROR');
            throw new Exception("Message body became empty during processing");
        }

        logMessage("Attempting to send email...", 'INFO');
        
        if (!$mail->send()) {
            throw new Exception($mail->ErrorInfo);
        }
        
        logMessage("Email sent successfully via PHPMailer", 'INFO');
    } catch (Exception $e) {
        // Don't log here; the caller decides how to classify and log the error (transient vs non-transient)
        throw new Exception("SMTP error: " . $e->getMessage());
    }
}

// Treat connection-related SMTP errors as transient so we can try next SMTP without marking the email failed
function isTransientSmtpError($message)
{
    $message = strtolower($message);
    $patterns = [
        'could not connect to smtp host',
        'failed to connect to server',
        'smtp connect() failed',
        'connection timed out',
        'timed out waiting for',
        'unable to connect',
        'connection refused',
        'tls handshake failed',
        'certificate verify failed',
        'temporary lookup failure',
        'network is unreachable',
        'host not found',
    ];
    foreach ($patterns as $p) {
        if (strpos($message, $p) !== false) {
            return true;
        }
    }
    return false;
}

function recordDelivery($db, $smtpId, $emailId, $campaignId, $to_email, $status, $error = null)
{
    $db->query("
        INSERT INTO mail_blaster 
        (campaign_id, to_mail, smtpid, delivery_date, delivery_time, status, error_message, attempt_count)
        VALUES (
            $campaignId, 
            '" . $db->real_escape_string($to_email) . "', 
            $smtpId, 
            CURDATE(), 
            CURTIME(), 
            '" . $db->real_escape_string($status) . "', 
            " . ($error ? "'" . $db->real_escape_string($error) . "'" : "NULL") . ",
            1
        )
        ON DUPLICATE KEY UPDATE
            smtpid = VALUES(smtpid),
            delivery_date = VALUES(delivery_date),
            delivery_time = VALUES(delivery_time),
            status = IF(mail_blaster.status = 'success', 'success', VALUES(status)),
            error_message = VALUES(error_message),
            attempt_count = IF(mail_blaster.status = 'success', mail_blaster.attempt_count, mail_blaster.attempt_count + 1)
    ");

    // Update SMTP usage with exact timestamp (only for successful sends)
    if ($status === 'success') {
        $current_hour = date('G');
        $db->query("
            INSERT INTO smtp_usage 
            (smtp_id, date, hour, timestamp, emails_sent)
            VALUES (
                $smtpId,
                CURDATE(),
                $current_hour,
                NOW(),
                1
            )
            ON DUPLICATE KEY UPDATE
                emails_sent = emails_sent + 1,
                timestamp = VALUES(timestamp)
        ");
    }
}

function logMessage($message, $level = 'INFO')
{
    $storageDir = __DIR__ . '/../storage';
    if (!file_exists($storageDir)) {
        mkdir($storageDir, 0755, true);
    }

    $campaign_id = $GLOBALS['campaign_id'] ?? 'unknown';
    $log = "[" . date('Y-m-d H:i:s') . "] [$level] $message\n";
    
    // Main logs.log file in storage directory
    file_put_contents("$storageDir/logs.log", $log, FILE_APPEND);
    
    // Also keep campaign-specific logs in logs subdirectory
    $logDir = "$storageDir/logs";
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
    file_put_contents("$logDir/campaign_{$campaign_id}.log", $log, FILE_APPEND);

    // Additionally, log errors to a separate file for quick review
    if ($level === 'ERROR') {
        file_put_contents("$logDir/campaign_{$campaign_id}_errors.log", $log, FILE_APPEND);
    }

    if (php_sapi_name() === 'cli') {
        echo $log;
    }
}

/**
 * Log detailed email sending information to structured log files
 */
function logEmailDetail($campaign_id, $to_email, $smtp_account_id, $smtp_email, $status, $error_msg = '')
{
    $storageDir = __DIR__ . '/../storage';
    $logDir = "$storageDir/logs";
    
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $timestamp = date('Y-m-d H:i:s');
    $date = date('Y-m-d');
    
    // Write to main logs.log file in storage directory (simple format)
    $mainLog = sprintf(
        "[%s] Campaign: %s | To: %s | SMTP: %s (ID: %s) | Status: %s%s\n",
        $timestamp,
        $campaign_id,
        $to_email,
        $smtp_email,
        $smtp_account_id,
        strtoupper($status),
        $error_msg ? " | Error: $error_msg" : ""
    );
    file_put_contents("$storageDir/logs.log", $mainLog, FILE_APPEND);
    
    // Main detailed log with CSV-like format for easy parsing
    $detailLog = sprintf(
        "%s|%s|%s|%s|%s|%s|%s\n",
        $timestamp,
        $campaign_id,
        $to_email,
        $smtp_account_id,
        $smtp_email,
        $status,
        str_replace(["\r", "\n", "|"], [" ", " ", " "], $error_msg) // Clean error message
    );
    file_put_contents("$logDir/email_details_{$date}.log", $detailLog, FILE_APPEND);
    
    // Separate success log for quick analysis
    if ($status === 'success') {
        $successLog = sprintf(
            "%s | Campaign: %s | To: %s | SMTP: %s (ID: %s)\n",
            $timestamp,
            $campaign_id,
            $to_email,
            $smtp_email,
            $smtp_account_id
        );
        file_put_contents("$logDir/success_{$date}.log", $successLog, FILE_APPEND);
    }
    
    // Separate failure log with detailed error messages
    if ($status === 'failed') {
        $failLog = sprintf(
            "%s | Campaign: %s | To: %s | SMTP: %s (ID: %s) | Error: %s\n",
            $timestamp,
            $campaign_id,
            $to_email,
            $smtp_email,
            $smtp_account_id,
            $error_msg
        );
        file_put_contents("$logDir/failures_{$date}.log", $failLog, FILE_APPEND);
    }
    
    // Per-SMTP account tracking log
    $smtpLog = sprintf(
        "%s | Campaign: %s | To: %s | Status: %s | Error: %s\n",
        $timestamp,
        $campaign_id,
        $to_email,
        $status,
        $error_msg
    );
    file_put_contents("$logDir/smtp_{$smtp_account_id}_{$date}.log", $smtpLog, FILE_APPEND);
}