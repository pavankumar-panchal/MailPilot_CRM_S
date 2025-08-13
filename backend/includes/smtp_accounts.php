<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$smtp_server_id = intval($_GET['smtp_server_id'] ?? 0);

function getJsonInput() {
    $input = file_get_contents('php://input');
    return json_decode($input, true);   
}

// Handle preflight OPTIONS request
if ($method === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Add new account to a server
if ($method === 'POST') {
    $data = getJsonInput();
    $email = trim($conn->real_escape_string($data['email'] ?? ''));
    $password = trim($conn->real_escape_string($data['password'] ?? ''));
    if ($email === '' || $password === '') {
        echo json_encode(['success' => false, 'message' => 'Email and password required.']);
        exit;
    }
    $daily_limit = intval($data['daily_limit'] ?? 500);
    $hourly_limit = intval($data['hourly_limit'] ?? 100);
    $is_active = !empty($data['is_active']) ? 1 : 0;

    $sql = "INSERT INTO smtp_accounts (smtp_server_id, email, password, daily_limit, hourly_limit, is_active)
            VALUES ($smtp_server_id, '$email', '$password', $daily_limit, $hourly_limit, $is_active)";
    if ($conn->query($sql)) {
        echo json_encode(['success' => true, 'message' => 'Account added.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $conn->error]);
    }
    exit;
}

// Delete an account from a server
if ($method === 'DELETE') {
    $account_id = intval($_GET['account_id'] ?? 0);
    if ($account_id > 0 && $smtp_server_id > 0) {
        $conn->query("DELETE FROM smtp_accounts WHERE id = $account_id AND smtp_server_id = $smtp_server_id");
        echo json_encode(['success' => true, 'message' => 'Account deleted.']);
        exit;
    }
    echo json_encode(['success' => false, 'message' => 'Invalid account ID']);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
exit;