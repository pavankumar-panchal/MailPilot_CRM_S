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
            sleep(5);
        } else {
            $status_check = $conn->query("SELECT status FROM campaign_status WHERE campaign_id = $campaign_id")->fetch_assoc();
            if ($status_check['status'] !== 'running') {
                break;
            }
            sleep(30);
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

        // Get next SMTP from rotation atomically (with row locking)
        // This ensures each email gets a unique SMTP across all campaigns
        $smtp_found = false;
        $all_smtp_checked = 0;
        
        // Keep trying until we find an available SMTP (no retry limit)
        while (!$smtp_found) {
            // Start transaction with row lock
            $db->begin_transaction();
            
            try {
                // Lock the rotation row - prevents race conditions
                $rotation = $db->query("SELECT last_smtp_index, total_smtp_count FROM smtp_rotation WHERE id = 1 FOR UPDATE")->fetch_assoc();
                
                // Ensure rotation row exists and is in sync
                if (!$rotation) {
                    // Create the rotation row if it doesn't exist
                    $db->query("INSERT INTO smtp_rotation (id, last_smtp_index, total_smtp_count) VALUES (1, -1, $smtp_count) ON DUPLICATE KEY UPDATE total_smtp_count = $smtp_count");
                    $smtp_index = 0;
                    $db->query("UPDATE smtp_rotation SET last_smtp_index = 0, total_smtp_count = $smtp_count WHERE id = 1");
                    $all_smtp_checked = 0; // Reset counter
                } elseif ($rotation['total_smtp_count'] != $smtp_count) {
                    // SMTP count changed, realign rotation index
                    $smtp_index = 0;
                    $db->query("UPDATE smtp_rotation SET last_smtp_index = 0, total_smtp_count = $smtp_count WHERE id = 1");
                    $all_smtp_checked = 0; // Reset counter
                } else {
                    // Get next SMTP in sequence
                    $smtp_index = ($rotation['last_smtp_index'] + 1) % $smtp_count;
                }
                
                $smtp = $smtp_accounts[$smtp_index];
                $sid = $smtp['smtp_account_id'];
                
                // Check if this SMTP is within limits
                if ($smtp_usage[$sid]['daily'] < $smtp['daily_limit'] && 
                    $smtp_usage[$sid]['hourly'] < $smtp['hourly_limit']) {
                    
                    // This SMTP is available! Update rotation and use it
                    $db->query("UPDATE smtp_rotation SET last_smtp_index = $smtp_index, last_smtp_id = $sid WHERE id = 1");
                    $db->commit();
                    $smtp_found = true;
                    $all_smtp_checked = 0; // Reset counter
                    
                    logMessage("[ROTATION] Email to $to → SMTP[$smtp_index] {$smtp['smtp_email']} (ID: $sid)", 'INFO');
                } else {
                    // This SMTP is at limit, update rotation to skip it and try next
                    $db->query("UPDATE smtp_rotation SET last_smtp_index = $smtp_index WHERE id = 1");
                    $db->commit();
                    $all_smtp_checked++;
                    
                    logMessage("[ROTATION] SMTP[$smtp_index] {$smtp['smtp_email']} at limit, trying next...", 'WARNING');
                    
                    // If we've checked all SMTPs and none are available, wait and refresh
                    if ($all_smtp_checked >= $smtp_count) {
                        logMessage("[WAIT] All $smtp_count SMTPs at limit. Waiting 60s to refresh limits...", 'WARNING');
                        $db->commit(); // Make sure transaction is closed
                        sleep(60);
                        
                        // Refresh usage counters
                        foreach ($smtp_accounts as $smtp) {
                            $sid = $smtp['smtp_account_id'];
                            $smtp_usage[$sid]['daily'] = getSmtpAccountSent($db, $sid, 'daily');
                            $smtp_usage[$sid]['hourly'] = getSmtpAccountSent($db, $sid, 'hourly');
                        }
                        $all_smtp_checked = 0; // Reset counter after refresh
                        logMessage("[REFRESH] SMTP usage limits refreshed. Retrying...", 'INFO');
                    }
                }
                
            } catch (Exception $e) {
                $db->rollback();
                logMessage("[ROTATION] Lock error: " . $e->getMessage(), 'ERROR');
                usleep(100000); // Wait 0.1s before retry
            }
        }

        // Log which SMTP and recipient is being used
        logMessage("[SEND_ATTEMPT] To: $to | SMTP: {$smtp['smtp_email']} (ID: {$smtp['smtp_account_id']})", 'INFO');

        try {
            // send as HTML if campaign has send_as_html set and it's truthy
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
                $campaign // Pass full campaign data for attachments/images/reply_to
            );

            recordDelivery($db, $smtp['smtp_account_id'], $emailId, $campaign_id, $to, 'success');

            logMessage("[SEND_SUCCESS] To: $to | SMTP: {$smtp['smtp_email']} (ID: {$smtp['smtp_account_id']})", 'SUCCESS');

            $db->query("
                UPDATE campaign_status 
                SET sent_emails = sent_emails + 1, 
                    pending_emails = GREATEST(0, pending_emails - 1) 
                WHERE campaign_id = $campaign_id
            ");

            $smtp_usage[$smtp['smtp_account_id']]['daily']++;
            $smtp_usage[$smtp['smtp_account_id']]['hourly']++;
            $processed_count++;
        } catch (Exception $e) {
            recordDelivery($db, $smtp['smtp_account_id'], $emailId, $campaign_id, $to, 'failed', $e->getMessage());

            logMessage("[SEND_FAILED] To: $to | SMTP: {$smtp['smtp_email']} (ID: {$smtp['smtp_account_id']}) | Error: " . $e->getMessage(), 'ERROR');

            $db->query("
                UPDATE campaign_status 
                SET failed_emails = failed_emails + 1, 
                    pending_emails = GREATEST(0, pending_emails - 1) 
                WHERE campaign_id = $campaign_id
            ");
        }

        // Move to next SMTP for the next email in this batch
        $smtp_index = ($smtp_index + 1) % $smtp_count;
        
        // Update rotation state atomically for next campaign/batch
        // Use a quick transaction to prevent race conditions
        $db->begin_transaction();
        try {
            $next_smtp_id = $smtp_accounts[$smtp_index]['smtp_account_id'];
            $db->query("UPDATE smtp_rotation SET last_smtp_index = $smtp_index, last_smtp_id = $next_smtp_id WHERE id = 1");
            $db->commit();
        } catch (Exception $e) {
            $db->rollback();
        }
        
        usleep(300000); // 0.3 seconds throttle
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
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = $smtp['host'];
        $mail->Port = $smtp['port'];
        $mail->SMTPAuth = true;
        $mail->Username = $smtp['email'];
        $mail->Password = $smtp['password'];
        $mail->Timeout = 30;
        
        // Disable SMTP debug output in production
        $mail->SMTPDebug = 0;

        if ($smtp['encryption'] === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($smtp['encryption'] === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }
        
        // SMTPOptions for better compatibility
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        $mail->setFrom($smtp['email']);
        $mail->addAddress($to_email);
        $mail->Subject = $subject;

        // Ensure proper charset
        $mail->CharSet = 'UTF-8';

        if ($isHtml) {
            // in the recipient's HTML view.
            $mail->isHTML(true);
            $mail->Body = $body;
           
            // not decode entities to avoid changing the textual content.
            $mail->AltBody = trim(strip_tags($body));
        } else {
            // Send as literal plain text so recipients see HTML tags as text and
            // whitespace is preserved.
            $mail->isHTML(false);
            $mail->Body = $body;
            $mail->AltBody = $body;
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
                }
            } else {
                logMessage("Attachment file not found at: " . $attachmentPath, 'ERROR');
                logMessage("Campaign attachment_path: " . $campaign['attachment_path'], 'ERROR');
            }
        }

        // Add embedded images if present
        if (!empty($campaign['images_paths'])) {
            $images = is_string($campaign['images_paths']) 
                ? json_decode($campaign['images_paths'], true) 
                : $campaign['images_paths'];
            
            if (is_array($images)) {
                foreach ($images as $index => $imagePath) {
                    $fullPath = __DIR__ . '/../' . $imagePath;
                    if (file_exists($fullPath)) {
                        // Generate unique CID for each image
                        $cid = 'image_' . $index . '_' . uniqid();
                        $mail->addEmbeddedImage($fullPath, $cid);
                        
                        // Replace image references in HTML body if HTML mode is enabled
                        if ($isHtml) {
                            $filename = basename($imagePath);
                            
                            // Build multiple URL patterns that might appear in the HTML
                            $patterns = [
                                // Full URL with http
                                'http://[^"\']+/' . preg_quote($imagePath, '/'),
                                // Full URL with https
                                'https://[^"\']+/' . preg_quote($imagePath, '/'),
                                // Relative path from backend
                                preg_quote('/verify_emails/MailPilot_CRM/backend/' . $imagePath, '/'),
                                // Just the storage path
                                preg_quote($imagePath, '/'),
                                // Just the filename
                                preg_quote($filename, '/')
                            ];
                            
                            // Try each pattern to replace image URLs with CID
                            foreach ($patterns as $pattern) {
                                $body = preg_replace(
                                    '/(<img[^>]+src=["\'])' . $pattern . '(["\'][^>]*>)/i',
                                    '${1}cid:' . $cid . '${2}',
                                    $body
                                );
                            }
                            
                            $mail->Body = $body;
                        }
                    }
                }
            }
        }

        logMessage("Attempting to send email...", 'INFO');
        
        if (!$mail->send()) {
            throw new Exception($mail->ErrorInfo);
        }
        
        logMessage("Email sent successfully via PHPMailer", 'INFO');
    } catch (Exception $e) {
        logMessage("PHPMailer exception: " . $e->getMessage(), 'ERROR');
        throw new Exception("SMTP error: " . $e->getMessage());
    }
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
    $logDir = __DIR__ . '/logs';
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $campaign_id = $GLOBALS['campaign_id'] ?? 'unknown';
    $log = "[" . date('Y-m-d H:i:s') . "] [$level] $message\n";
    file_put_contents("$logDir/campaign_{$campaign_id}.log", $log, FILE_APPEND);

    // Additionally, log errors to a separate file for quick review
    if ($level === 'ERROR') {
        file_put_contents("$logDir/campaign_{$campaign_id}_errors.log", $log, FILE_APPEND);
    }

    if (php_sapi_name() === 'cli') {
        echo $log;
    }
}