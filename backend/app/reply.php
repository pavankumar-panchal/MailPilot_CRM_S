<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

$db = new mysqli("localhost", "root", "", "CRM");
if ($db->connect_error) {
    echo json_encode(["success" => false, "message" => "DB connection failed"]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);
$to = $input['to'] ?? '';
$subject = $input['subject'] ?? '';
$body = $input['body'] ?? '';
$account_id = intval($input['account_id'] ?? 0);

// Get SMTP credentials
$stmt = $db->prepare("SELECT a.email, a.password, s.host, s.port
                      FROM smtp_accounts a
                      JOIN smtp_servers s ON s.id = a.smtp_server_id
                      WHERE s.id = ? AND a.is_active = 1");
$stmt->bind_param("i", $account_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$result) {
    echo json_encode(["success" => false, "message" => "SMTP account not found"]);
    exit;
}

// Send email (use PHPMailer or native mail)
// Example using PHPMailer:
require_once 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = $result['host'];
    $mail->SMTPAuth = true;
    $mail->Username = $result['email'];
    $mail->Password = $result['password'];
    $mail->SMTPSecure = 'ssl';
    $mail->Port = $result['port'];

    $mail->setFrom($result['email']);
    $mail->addAddress($to);
    $mail->Subject = $subject;
    $mail->Body = $body;

    $mail->send();
    echo json_encode(["success" => true]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $mail->ErrorInfo]);
}
?>