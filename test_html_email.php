<?php
// Test if HTML emails are being sent properly
require_once __DIR__ . '/backend/config/db.php';

$campaign_id = 19; // Use your test campaign ID

// Fetch campaign
$result = $conn->query("SELECT * FROM campaign_master WHERE campaign_id = $campaign_id");
$campaign = $result->fetch_assoc();

echo "=== Campaign Data ===\n";
echo "send_as_html: " . ($campaign['send_as_html'] ?? 'not set') . "\n";
echo "mail_body length: " . strlen($campaign['mail_body']) . " bytes\n";
echo "\n=== Raw mail_body (first 500 chars) ===\n";
echo substr($campaign['mail_body'], 0, 500) . "\n";
echo "\n=== After stripcslashes ===\n";
echo substr(stripcslashes($campaign['mail_body']), 0, 500) . "\n";

// Test PHPMailer HTML mode
require_once __DIR__ . '/backend/vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;

$mail = new PHPMailer(true);
$mail->CharSet = 'UTF-8';
$mail->Encoding = 'base64';

$isHtml = !empty($campaign['send_as_html']);
$mail->isHTML($isHtml);

echo "\n=== PHPMailer Config ===\n";
echo "isHTML: " . ($isHtml ? 'true' : 'false') . "\n";
echo "ContentType: " . $mail->ContentType . "\n";

$mail->Body = $campaign['mail_body'];
echo "\n=== Email Body Preview ===\n";
echo substr($mail->Body, 0, 500) . "\n";
?>
