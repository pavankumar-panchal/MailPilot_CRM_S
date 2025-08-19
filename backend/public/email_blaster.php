<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require __DIR__ . '/../vendor/autoload.php';

date_default_timezone_set('Asia/Kolkata');

// Get campaign ID from command line

// $campaign_id=2;
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
        AND (mb.id IS NULL OR (mb.status IN ('failed', 'pending') AND mb.attempt_count < 3))
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

    // Get campaign details
    $campaign = $db->query("
        SELECT mail_subject, mail_body 
        FROM campaign_master 
        WHERE campaign_id = $campaign_id
    ")->fetch_assoc();

    if (!$campaign) {
        logMessage("Campaign not found", 'ERROR');
        return 0;
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
          AND (mb.id IS NULL OR (mb.status IN ('failed', 'pending') AND mb.attempt_count < $max_retries))
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

    // Round-robin through SMTPs for each email
    $smtp_count = count($smtp_accounts);
    $smtp_index = 0;

    foreach ($emails as $email) {
        $to = $email['raw_emailid'];
        $emailId = (int)$email['id'];

        // Find next available SMTP within limits
        $smtp_found = false;
        $start_index = $smtp_index;
        do {
            $smtp = $smtp_accounts[$smtp_index];
            $sid = $smtp['smtp_account_id'];
            if ($smtp_usage[$sid]['daily'] < $smtp['daily_limit'] && $smtp_usage[$sid]['hourly'] < $smtp['hourly_limit']) {
                $smtp_found = true;
                break;
            }
            $smtp_index = ($smtp_index + 1) % $smtp_count;
        } while ($smtp_index !== $start_index);

        if (!$smtp_found) {
            logMessage("All SMTP accounts at limit. Waiting 60s before retrying...", 'WARNING');
            sleep(60);
            // Refresh usage
            foreach ($smtp_accounts as $smtp) {
                $sid = $smtp['smtp_account_id'];
                $smtp_usage[$sid]['daily'] = getSmtpAccountSent($db, $sid, 'daily');
                $smtp_usage[$sid]['hourly'] = getSmtpAccountSent($db, $sid, 'hourly');
            }
            continue;
        }

        // Log which SMTP and recipient is being used
        logMessage("[SEND_ATTEMPT] To: $to | SMTP: {$smtp['smtp_email']} (ID: {$smtp['smtp_account_id']})", 'INFO');

        try {
            sendEmail([
                'host' => $smtp['host'],
                'port' => $smtp['port'],
                'email' => $smtp['smtp_email'],
                'password' => $smtp['smtp_password'],
                'encryption' => $smtp['encryption'],
                'received_email' => $smtp['received_email'] // <-- Pass this!
            ], $to, $campaign['mail_subject'], $campaign['mail_body']);

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

        $smtp_index = ($smtp_index + 1) % $smtp_count;
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

function sendEmail($smtp, $to_email, $subject, $body)
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

        if ($smtp['encryption'] === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($smtp['encryption'] === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }

        $mail->setFrom($smtp['email']);
        $mail->addAddress($to_email);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->isHTML(true);

        // Always set Reply-To to received_email from smtp_servers
        if (!empty($smtp['received_email'])) {
            $mail->clearReplyTos();
            $mail->addReplyTo($smtp['received_email']);
        }

        if (!$mail->send()) {
            throw new Exception($mail->ErrorInfo);
        }
    } catch (Exception $e) {
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