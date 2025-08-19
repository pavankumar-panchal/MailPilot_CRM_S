<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require __DIR__ . '/../vendor/autoload.php';

date_default_timezone_set('Asia/Kolkata');
$campaign_id=1;
// Get campaign ID from command line
// $campaign_id = isset($argv[1]) ? intval($argv[1]) : die("No campaign ID specified");
$GLOBALS['campaign_id'] = $campaign_id;

// Create PID file for process tracking
$pid_dir = __DIR__ . '/../tmp';
if (!is_dir($pid_dir)) {
    mkdir($pid_dir, 0777, true);
}
$pid_file = $pid_dir . "/email_blaster_{$campaign_id}.pid";
file_put_contents($pid_file, getmypid());

// Register shutdown function to clean up PID file
register_shutdown_function(function () use ($pid_file) {
    if (file_exists($pid_file)) {
        unlink($pid_file);
    }
});

// Main processing loop
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

        // --- Only lock/check status in a short transaction ---
        $max_retries = 3;
        $retry = 0;
        $status = null;
        while (true) {
            try {
                $conn->begin_transaction();
                $result = $conn->query("
                    SELECT status, total_emails, sent_emails, pending_emails, failed_emails
                    FROM campaign_status 
                    WHERE campaign_id = $campaign_id
                    FOR UPDATE");
                if ($result->num_rows === 0) {
                    logMessage("Campaign not found. Exiting.");
                    $conn->commit();
                    break 2;
                }
                $campaign_data = $result->fetch_assoc();
                $status = $campaign_data['status'];
                $conn->commit();
                break; // Success, exit retry loop
            } catch (mysqli_sql_exception $e) {
                $conn->rollback();
                if (strpos($e->getMessage(), 'Lock wait timeout exceeded') !== false) {
                    logMessage("Lock wait timeout exceeded, retrying in 1 second...");
                    sleep(1);
                    continue;
                } else {
                    throw $e;
                }
            }
        }
        if ($retry >= $max_retries) {
            logMessage("Lock wait timeout exceeded after $max_retries attempts. Exiting.");
            break;
        }

        if ($status !== 'running') {
            logMessage("Campaign status is '$status'. Exiting process.");
            break;
        }

        if (!checkNetworkConnectivity()) {
            logMessage("Network connection unavailable. Waiting to retry...", 'WARNING');
            sleep(60);
            continue;
        }

        // --- The rest of your logic (no transaction needed here) ---
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

        if ($processed_count > 0) {
            sleep(5);
        } else {
            $status_check = $conn->query("SELECT status FROM campaign_status WHERE campaign_id = $campaign_id")->fetch_assoc();
            if ($status_check['status'] !== 'running') {
                break;
            }
            sleep(30);
        }
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

    try {
        $db->query("SET SESSION innodb_lock_wait_timeout = 10");
        $db->begin_transaction();

        // Get campaign details
        $campaign = $db->query("
            SELECT mail_subject, mail_body 
            FROM campaign_master 
            WHERE campaign_id = $campaign_id
        ")->fetch_assoc();

        if (!$campaign) {
            throw new Exception("Campaign not found");
        }

        // Get all active smtp_accounts (not from campaign_distribution)
        $smtp_accounts = $db->query("
            SELECT sa.*, ss.host, ss.port, ss.encryption
            FROM smtp_accounts sa
            INNER JOIN smtp_servers ss ON sa.smtp_server_id = ss.id
            WHERE sa.is_active = 1 AND ss.is_active = 1
        ")->fetch_all(MYSQLI_ASSOC);

        if (empty($smtp_accounts)) {
            throw new Exception("No active SMTP accounts available");
        }

        // Calculate available capacity for each smtp_account
        $available_accounts = [];
        foreach ($smtp_accounts as $acc) {
            $daily_sent = getSmtpAccountSent($db, $acc['id'], 'daily');
            $hourly_sent = getSmtpAccountSent($db, $acc['id'], 'hourly');
            $daily_remaining = max(0, $acc['daily_limit'] - $daily_sent);
            $hourly_remaining = max(0, $acc['hourly_limit'] - $hourly_sent);
            $remaining_capacity = min($daily_remaining, $hourly_remaining);

            if ($remaining_capacity > 0) {
                $acc['remaining_capacity'] = $remaining_capacity;
                $available_accounts[] = $acc;
            }
        }

        if (empty($available_accounts)) {
            logMessage("No SMTP accounts available within limits");
            $db->commit();
            return 0;
        }

        // Get emails to process (one per smtp_account per batch)
        foreach ($available_accounts as $smtp_acc) {
            $emails_to_process = $smtp_acc['remaining_capacity'];
            if ($emails_to_process <= 0) continue;

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
                ORDER BY mb.attempt_count ASC, mb.id ASC
                LIMIT $emails_to_process
            ")->fetch_all(MYSQLI_ASSOC);

            foreach ($emails as $email) {
                try {
                    $status = $db->query("
                        SELECT status FROM campaign_status 
                        WHERE campaign_id = $campaign_id
                    ")->fetch_assoc()['status'];

                    if ($status !== 'running') {
                        logMessage("Campaign paused or stopped during processing");
                        break 2;
                    }

                    sendEmail($smtp_acc, $email['raw_emailid'], $campaign['mail_subject'], $campaign['mail_body']);

                    recordDelivery($db, $smtp_acc['id'], $email['id'], $campaign_id, $email['raw_emailid'], 'success');

                    $db->query("
                        UPDATE campaign_status 
                        SET sent_emails = sent_emails + 1, 
                            pending_emails = GREATEST(0, pending_emails - 1) 
                        WHERE campaign_id = $campaign_id
                    ");

                    $processed_count++;
                    usleep(300000);

                } catch (Exception $e) {
                    recordDelivery($db, $smtp_acc['id'], $email['id'], $campaign_id, $email['raw_emailid'], 'failed', $e->getMessage());

                    $db->query("
                        UPDATE campaign_status 
                        SET failed_emails = failed_emails + 1, 
                            pending_emails = GREATEST(0, pending_emails - 1) 
                        WHERE campaign_id = $campaign_id
                    ");

                    logMessage("Failed to send to {$email['raw_emailid']}: " . $e->getMessage(), 'ERROR');
                }
            }
        }

        $db->commit();
        return $processed_count;
    } catch (Exception $e) {
        $db->rollback();
        logMessage("Error processing batch: " . $e->getMessage(), 'ERROR');
        return 0;
    }
}

function getSmtpAccountSent($db, $smtp_account_id, $type = 'daily')
{
    if ($type === 'daily') {
        $result = $db->query("
            SELECT COUNT(*) as cnt FROM mail_blaster 
            WHERE smtpid = $smtp_account_id AND status = 'success' AND delivery_date = CURDATE()
        ");
    } else {
        $hour = date('H');
        $result = $db->query("
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
            attempt_count = mail_blaster.attempt_count + 1
    ");
}

function logMessage($message, $level = 'INFO')
{
    $logDir = __DIR__ . '/logs';
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $log = "[" . date('Y-m-d H:i:s') . "] [$level] " . $message . "\n";
    $campaign_id = $GLOBALS['campaign_id'] ?? 'unknown';
    file_put_contents("$logDir/campaign_{$campaign_id}.log", $log, FILE_APPEND);

    if (php_sapi_name() === 'cli') {
        echo $log;
    }
}