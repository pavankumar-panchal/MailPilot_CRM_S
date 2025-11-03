<?php
// Utility to send exactly one pending/failed email from any running campaign
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

error_reporting(E_ALL);
ini_set('display_errors', 1);

date_default_timezone_set('Asia/Kolkata');

require __DIR__ . '/../vendor/autoload.php';

// DB connect (same config as email_blaster)
$dbConfig = [
    'host' => '127.0.0.1',
    'username' => 'root',
    'password' => '',
    'name' => 'CRM',
    'port' => 3306
];

$db = new mysqli($dbConfig['host'], $dbConfig['username'], $dbConfig['password'], $dbConfig['name'], $dbConfig['port']);
if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

function logCli($msg, $level='INFO') { echo '['.date('Y-m-d H:i:s')."] [$level] $msg\n"; }

// 1) Find a running campaign
$res = $db->query("SELECT campaign_id FROM campaign_status WHERE status='running' ORDER BY campaign_id DESC LIMIT 1");
if (!$res || $res->num_rows === 0) {
    logCli('No running campaigns found', 'ERROR');
    exit(1);
}
$campaign_id = (int)$res->fetch_assoc()['campaign_id'];
logCli("Using running campaign #$campaign_id");

// 2) Build campaign column-aware select
$dbNameRes = $db->query("SELECT DATABASE() as db");
$dbName = $dbNameRes ? $dbNameRes->fetch_assoc()['db'] : '';
$columns = [];
if ($dbName) {
    $colCheck = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='".$db->real_escape_string($dbName)."' AND TABLE_NAME='campaign_master'");
    while ($col = $colCheck->fetch_assoc()) { $columns[] = $col['COLUMN_NAME']; }
}
$select = "SELECT mail_subject, mail_body";
if (in_array('send_as_html', $columns)) $select .= ", send_as_html";
if (in_array('attachment_path', $columns)) $select .= ", attachment_path";
if (in_array('images_paths', $columns)) $select .= ", images_paths";
if (in_array('reply_to', $columns)) $select .= ", reply_to";
$select .= " FROM campaign_master WHERE campaign_id = $campaign_id";
$campaign = $db->query($select)->fetch_assoc();
if (!$campaign) { logCli('Campaign not found', 'ERROR'); exit(1); }
// Normalize body like email_blaster
if (isset($campaign['mail_body'])) {
    $normalized = stripcslashes($campaign['mail_body']);
    if (!empty($campaign['send_as_html'])) {
        $normalized = html_entity_decode($normalized, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    $campaign['mail_body'] = $normalized;
} else { $campaign['mail_body'] = ''; }

// 3) Load active SMTP accounts
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
if (empty($smtp_accounts)) { logCli('No active SMTP accounts found', 'ERROR'); exit(1); }
$smtp_count = count($smtp_accounts);

// Build usage counts
function getSmtpAccountSentLocal($conn, $smtp_account_id, $type = 'daily') {
    if ($type === 'daily') {
        $result = $conn->query("SELECT COUNT(*) as cnt FROM mail_blaster WHERE smtpid = $smtp_account_id AND status='success' AND delivery_date = CURDATE()");
    } else {
        $hour = date('H');
        $result = $conn->query("SELECT COUNT(*) as cnt FROM mail_blaster WHERE smtpid = $smtp_account_id AND status='success' AND delivery_date = CURDATE() AND HOUR(delivery_time) = $hour");
    }
    $row = $result->fetch_assoc();
    return (int)($row['cnt'] ?? 0);
}
$smtp_usage = [];
foreach ($smtp_accounts as $smtp) {
    $smtp_usage[$smtp['smtp_account_id']] = [
        'daily' => getSmtpAccountSentLocal($db, $smtp['smtp_account_id'], 'daily'),
        'hourly' => getSmtpAccountSentLocal($db, $smtp['smtp_account_id'], 'hourly')
    ];
}

// 4) Get exactly one candidate email (failed/pending unlimited, exclude success)
$emailRes = $db->query("
    SELECT e.id, e.raw_emailid
    FROM emails e
    LEFT JOIN mail_blaster mb ON mb.to_mail = e.raw_emailid AND mb.campaign_id = $campaign_id
    WHERE e.domain_status = 1
      AND (mb.id IS NULL OR mb.status IN ('failed','pending'))
      AND NOT EXISTS (
          SELECT 1 FROM mail_blaster mb2
          WHERE mb2.to_mail = e.raw_emailid
          AND mb2.campaign_id = $campaign_id
          AND mb2.status = 'success'
      )
    ORDER BY IFNULL(mb.attempt_count,0) ASC, e.id ASC
    LIMIT 1
");
if (!$emailRes || $emailRes->num_rows === 0) {
    logCli('No candidate email found to send. It may have already been sent.', 'INFO');
    exit(0);
}
$email = $emailRes->fetch_assoc();
$to = $email['raw_emailid'];
$emailId = (int)$email['id'];
logCli("Sending one email to $to for campaign #$campaign_id");

// 5) Rotation per-email (no retry limit)
$smtp_found = false; $all_checked = 0; $smtp = null;
while (!$smtp_found) {
    $db->begin_transaction();
    try {
        $rotation = $db->query("SELECT last_smtp_index, total_smtp_count FROM smtp_rotation WHERE id = 1 FOR UPDATE")->fetch_assoc();
        if (!$rotation || (int)$rotation['total_smtp_count'] !== $smtp_count) {
            $smtp_index = 0;
            $db->query("UPDATE smtp_rotation SET last_smtp_index = 0, total_smtp_count = $smtp_count WHERE id = 1");
            $all_checked = 0;
        } else {
            $smtp_index = ((int)$rotation['last_smtp_index'] + 1) % $smtp_count;
        }
        $smtpCand = $smtp_accounts[$smtp_index];
        $sid = $smtpCand['smtp_account_id'];
        if ($smtp_usage[$sid]['daily'] < $smtpCand['daily_limit'] && $smtp_usage[$sid]['hourly'] < $smtpCand['hourly_limit']) {
            $db->query("UPDATE smtp_rotation SET last_smtp_index = $smtp_index, last_smtp_id = $sid WHERE id = 1");
            $db->commit();
            $smtp_found = true;
            $smtp = $smtpCand;
            logCli("Using SMTP[$smtp_index] {$smtp['smtp_email']} (ID: $sid)");
        } else {
            $db->query("UPDATE smtp_rotation SET last_smtp_index = $smtp_index WHERE id = 1");
            $db->commit();
            $all_checked++;
            logCli("SMTP[$smtp_index] {$smtpCand['smtp_email']} at limit, trying next...", 'WARN');
            if ($all_checked >= $smtp_count) {
                logCli("All $smtp_count SMTPs at limit. Waiting 60s...", 'WARN');
                sleep(60);
                foreach ($smtp_accounts as $sa) {
                    $sid2 = $sa['smtp_account_id'];
                    $smtp_usage[$sid2]['daily'] = getSmtpAccountSentLocal($db, $sid2, 'daily');
                    $smtp_usage[$sid2]['hourly'] = getSmtpAccountSentLocal($db, $sid2, 'hourly');
                }
                $all_checked = 0;
            }
        }
    } catch (Exception $e) {
        $db->rollback();
        logCli('Rotation lock error: '.$e->getMessage(), 'ERROR');
        usleep(100000);
    }
}

// 6) Send email via PHPMailer (simplified copy of sendEmail)
function sendViaSmtp($smtp, $to_email, $subject, $body, $isHtml = false, $campaign = []) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $smtp['host'];
        $mail->Port = $smtp['port'];
        $mail->SMTPAuth = true;
        $mail->Username = $smtp['smtp_email'];
        $mail->Password = $smtp['smtp_password'];
        $mail->Timeout = 30;
        $mail->SMTPDebug = 0;
        if ($smtp['encryption'] === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($smtp['encryption'] === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }
        $mail->SMTPOptions = [ 'ssl' => ['verify_peer'=>false,'verify_peer_name'=>false,'allow_self_signed'=>true] ];
        $mail->setFrom($smtp['smtp_email']);
        $mail->addAddress($to_email);
        $mail->Subject = $subject;
        $mail->CharSet = 'UTF-8';
        if (!empty($campaign['reply_to'])) { $mail->clearReplyTos(); $mail->addReplyTo($campaign['reply_to']); }
        elseif (!empty($smtp['received_email'])) { $mail->clearReplyTos(); $mail->addReplyTo($smtp['received_email']); }
        if (!empty($campaign['attachment_path'])) {
            $attachmentPath = __DIR__ . '/../' . $campaign['attachment_path'];
            if (file_exists($attachmentPath)) { $mail->addAttachment($attachmentPath); }
        }
        $isHtml = !empty($campaign['send_as_html']);
        if ($isHtml) { $mail->isHTML(true); $mail->Body = $body; $mail->AltBody = trim(strip_tags($body)); }
        else { $mail->isHTML(false); $mail->Body = $body; $mail->AltBody = $body; }
        if (!$mail->send()) { throw new Exception($mail->ErrorInfo); }
    } catch (Exception $e) { throw new Exception('SMTP error: '.$e->getMessage()); }
}

try {
    sendViaSmtp($smtp, $to, $campaign['mail_subject'], $campaign['mail_body'], !empty($campaign['send_as_html']), $campaign);
    // Record success
    $db->query("INSERT INTO mail_blaster (campaign_id, to_mail, smtpid, delivery_date, delivery_time, status, attempt_count)
                VALUES ($campaign_id, '".$db->real_escape_string($to)."', {$smtp['smtp_account_id']}, CURDATE(), CURTIME(), 'success', 1)
                ON DUPLICATE KEY UPDATE smtpid=VALUES(smtpid), delivery_date=VALUES(delivery_date), delivery_time=VALUES(delivery_time), status='success', attempt_count=IF(mail_blaster.status='success', mail_blaster.attempt_count, mail_blaster.attempt_count+1)");
    $hour = date('G');
    $db->query("INSERT INTO smtp_usage (smtp_id, date, hour, timestamp, emails_sent) VALUES ({$smtp['smtp_account_id']}, CURDATE(), $hour, NOW(), 1) ON DUPLICATE KEY UPDATE emails_sent=emails_sent+1, timestamp=VALUES(timestamp)");
    $db->query("UPDATE campaign_status SET sent_emails = sent_emails + 1, pending_emails = GREATEST(0, pending_emails - 1) WHERE campaign_id = $campaign_id");
    logCli("SUCCESS: Sent to $to using {$smtp['smtp_email']}", 'SUCCESS');
} catch (Exception $e) {
    $db->query("INSERT INTO mail_blaster (campaign_id, to_mail, smtpid, delivery_date, delivery_time, status, error_message, attempt_count)
                VALUES ($campaign_id, '".$db->real_escape_string($to)."', {$smtp['smtp_account_id']}, CURDATE(), CURTIME(), 'failed', '".$db->real_escape_string($e->getMessage())."', 1)
                ON DUPLICATE KEY UPDATE smtpid=VALUES(smtpid), delivery_date=VALUES(delivery_date), delivery_time=VALUES(delivery_time), status=IF(mail_blaster.status='success','success','failed'), error_message=VALUES(error_message), attempt_count=IF(mail_blaster.status='success', mail_blaster.attempt_count, mail_blaster.attempt_count+1)");
    logCli('FAILED: '.$e->getMessage(), 'ERROR');
    exit(2);
}

?>
