<?php


header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php';

$dbConfig = [
    'host' => '127.0.0.1',
    'username' => 'root',
    'password' => '',
    'name' => 'CRM',
    'port' => 3306
];

$conn = new mysqli($dbConfig['host'], $dbConfig['username'], $dbConfig['password'], $dbConfig['name'], $dbConfig['port']);
if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "DB connection failed"]);
    exit;
}

// Read JSON input from frontend
$data = json_decode(file_get_contents("php://input"), true);
$account_id = isset($data['account_id']) ? intval($data['account_id']) : 0;
$to = trim($data['to'] ?? '');
$subject = trim($data['subject'] ?? '');
$body = trim($data['body'] ?? '');

if (!$account_id || !$to || !$subject || !$body) {
    echo json_encode(["success" => false, "message" => "Missing required fields"]);
    exit;
}

// Fetch SMTP details
$stmt = $conn->prepare("SELECT host, port, email, password, encryption FROM smtp_servers WHERE id = ?");
$stmt->bind_param("i", $account_id);
$stmt->execute();
$result = $stmt->get_result();
$smtp = $result->fetch_assoc();
$stmt->close();

if (!$smtp) {
    echo json_encode(["success" => false, "message" => "SMTP server not found"]);
    exit;
}

// Send mail using PHPMailer
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
    $mail->addAddress($to);
    $mail->Subject = $subject;
    $mail->Body = $body;
    $mail->isHTML(false); // Plain text reply

    $mail->send();

    echo json_encode(["success" => true, "message" => "Reply sent!"]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Mailer Error: " . $mail->ErrorInfo
    ]);
}

$conn->close();
