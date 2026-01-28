<?php
// Set PHP session after login
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$user = $input['user'] ?? null;
$token = $input['token'] ?? null;

if ($user && $token) {
    $_SESSION['mailpilot_user'] = $user;
    $_SESSION['mailpilot_token'] = $token;
    echo json_encode(['success' => true]);
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid session data']);
}
