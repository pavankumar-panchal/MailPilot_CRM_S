<?php
require __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Simple CLI script to send one test email using an SMTP account stored in the DB.
// Usage: php send_test_smtp.php recipient@example.com panchlpavan800@gmail.com [campaign_id]

$recipient = $argv[1] ?? null;
$accountEmail = $argv[2] ?? null; // optional: smtp account email to use. If not provided, first active account will be used.
$campaignId = isset($argv[3]) ? intval($argv[3]) : null;

if (!$recipient) {
    echo "Usage: php send_test_smtp.php recipient@example.com [smtp_account_email] [campaign_id]\n";
    exit(1);
}

$dbConfig = [
    'host' => '127.0.0.1',
    'username' => 'root',
    'password' => '',
    'name' => 'CRM',
    'port' => 3306
];

$conn = new mysqli($dbConfig['host'], $dbConfig['username'], $dbConfig['password'], $dbConfig['name'], $dbConfig['port']);
if ($conn->connect_error) {
    die("DB connect error: " . $conn->connect_error . "\n");
}

// Fetch SMTP account
$sql = "SELECT sa.id AS smtp_account_id, sa.email AS smtp_email, sa.password AS smtp_password, ss.host, ss.port, ss.encryption, ss.received_email
        FROM smtp_accounts sa
        JOIN smtp_servers ss ON sa.smtp_server_id = ss.id
        WHERE sa.is_active = 1 AND ss.is_active = 1";
if ($accountEmail) {
    $accEsc = $conn->real_escape_string($accountEmail);
    $sql .= " AND sa.email = '$accEsc'";
}
$sql .= " ORDER BY sa.id ASC LIMIT 1";

$res = $conn->query($sql);
if (!$res || $res->num_rows === 0) {
    echo "No active SMTP account found.\n";
    exit(1);
}

$smtp = $res->fetch_assoc();

// If campaignId is provided, try to fetch campaign content
$subject = 'Test email from MailPilot_CRM';
$body = '<p>This is a test email sent from MailPilot_CRM using stored SMTP credentials.</p>';
if ($campaignId) {
    $cid = intval($campaignId);
    $cRes = $conn->query("SELECT mail_subject, mail_body FROM campaign_master WHERE campaign_id = $cid LIMIT 1");
    if ($cRes && $cRes->num_rows > 0) {
        $c = $cRes->fetch_assoc();
        $subject = $c['mail_subject'] ?: $subject;
        $body = $c['mail_body'] ?: $body;
    } else {
        echo "Campaign ID $campaignId not found, using default subject/body.\n";
    }
}

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = $smtp['host'];
    $mail->Port = (int)$smtp['port'];
    $mail->SMTPAuth = true;
    $mail->Username = $smtp['smtp_email'];
    $mail->Password = $smtp['smtp_password'];
    $mail->Timeout = 30;

    if ($smtp['encryption'] === 'ssl') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } elseif ($smtp['encryption'] === 'tls') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    }

    $mail->setFrom($smtp['smtp_email']);
    if (!empty($smtp['received_email'])) {
        $mail->clearReplyTos();
        $mail->addReplyTo($smtp['received_email']);
    }

    $mail->addAddress($recipient);
    $mail->Subject = $subject;
    $mail->Body = $body;
    $mail->isHTML(true);

    if ($mail->send()) {
        echo "Test email sent to $recipient via {$smtp['smtp_email']} ({$smtp['host']}:{$smtp['port']}).\n";
    } else {
        echo "Failed to send: " . $mail->ErrorInfo . "\n";
    }
} catch (Exception $e) {
    echo "Exception sending email: " . $e->getMessage() . "\n";
}

$conn->close();

?>