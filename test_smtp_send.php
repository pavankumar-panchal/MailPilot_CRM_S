<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// PHPMailer autoload (YOUR EXACT PATH)
require '/opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/backend/vendor/autoload.php';

// Collect POST data
$smtp_host       = $_POST['smtp_host'] ?? '';
$smtp_port       = (int)($_POST['smtp_port'] ?? 0);
$smtp_user       = $_POST['smtp_user'] ?? '';
$smtp_pass       = $_POST['smtp_pass'] ?? '';
$smtp_encryption = $_POST['smtp_encryption'] ?? 'tls';
$to_email        = $_POST['to_email'] ?? '';
$message         = $_POST['message'] ?? 'SMTP test email';

$mail = new PHPMailer(true);

echo "<pre>";

try {
    // SMTP configuration
    $mail->isSMTP();
    $mail->Host = $smtp_host;
    $mail->Port = $smtp_port;
    $mail->SMTPAuth = true;
    $mail->Username = $smtp_user;
    $mail->Password = $smtp_pass;
    $mail->Timeout = 30;

    // Required for localhost testing
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
        ]
    ];

    if ($smtp_encryption === 'ssl') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } else {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    }

    // SMTP debug output
    $mail->SMTPDebug = 2;

    // Email
    $mail->setFrom($smtp_user, 'MailPilot SMTP Test');
    $mail->addAddress($to_email);
    $mail->Subject = 'SMTP Test Email';
    $mail->Body = $message;

    // Send
    $mail->send();

    echo "✅ SMTP TEST SUCCESSFUL\n";
    echo "From: $smtp_user\n";
    echo "To: $to_email\n";

} catch (Exception $e) {
    echo "❌ SMTP TEST FAILED\n";
    echo "Error: " . $mail->ErrorInfo . "\n";
}

echo "</pre>";
echo '<br><a href="test_smtp_ui.php">⬅ Back to SMTP Test</a>';
