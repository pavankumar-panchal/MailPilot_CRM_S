<?php
// filepath: /opt/lampp/htdocs/Verify_email/backend/includes/master_smtps.php

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

require_once __DIR__ . '/../config/db.php';

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

// Helper: get JSON input
function getJsonInput() {
    $input = file_get_contents('php://input');
    return json_decode($input, true);
}

// GET: List all SMTP servers
if ($method === 'GET') {
    $result = $conn->query("SELECT * FROM smtp_servers ORDER BY id DESC");
    $servers = [];
    while ($row = $result->fetch_assoc()) {
        $row['is_active'] = (bool)$row['is_active'];
        $servers[] = $row;
    }
    echo json_encode(['data' => $servers]);
    $conn->close();
    exit;
}

// POST: Add new SMTP server
if ($method === 'POST') {
    $data = getJsonInput();
    $name = $conn->real_escape_string($data['name'] ?? '');
    $host = $conn->real_escape_string($data['host'] ?? '');
    $port = intval($data['port'] ?? 465);
    $encryption = $conn->real_escape_string($data['encryption'] ?? '');
    $email = $conn->real_escape_string($data['email'] ?? '');
    $password = $conn->real_escape_string($data['password'] ?? '');
    $daily_limit = intval($data['daily_limit'] ?? 500);
    $hourly_limit = intval($data['hourly_limit'] ?? 100);
    $is_active = !empty($data['is_active']) ? 1 : 0;

    $sql = "INSERT INTO smtp_servers (name, host, port, encryption, email, password, daily_limit, hourly_limit, is_active)
            VALUES ('$name', '$host', $port, '$encryption', '$email', '$password', $daily_limit, $hourly_limit, $is_active)";
    if ($conn->query($sql)) {
        echo json_encode(['success' => true, 'message' => 'SMTP server added successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error adding SMTP server: ' . $conn->error]);
    }
    $conn->close();
    exit;
}

// PUT: Update SMTP server
if ($method === 'PUT') {
    // Parse id from query string (for fetch PUT ...?id=xx)
    parse_str($_SERVER['QUERY_STRING'], $query);
    $id = intval($query['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID']);
        $conn->close();
        exit;
    }
    $data = getJsonInput();
    $name = $conn->real_escape_string($data['name'] ?? '');
    $host = $conn->real_escape_string($data['host'] ?? '');
    $port = intval($data['port'] ?? 465);
    $encryption = $conn->real_escape_string($data['encryption'] ?? '');
    $email = $conn->real_escape_string($data['email'] ?? '');
    $password = $conn->real_escape_string($data['password'] ?? '');
    $daily_limit = intval($data['daily_limit'] ?? 500);
    $hourly_limit = intval($data['hourly_limit'] ?? 100);
    $is_active = !empty($data['is_active']) ? 1 : 0;

    $sql = "UPDATE smtp_servers SET
                name = '$name',
                host = '$host',
                port = $port,
                encryption = '$encryption',
                email = '$email',
                password = '$password',
                daily_limit = $daily_limit,
                hourly_limit = $hourly_limit,
                is_active = $is_active
            WHERE id = $id";
    if ($conn->query($sql)) {
        echo json_encode(['success' => true, 'message' => 'SMTP server updated successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating SMTP server: ' . $conn->error]);
    }
    $conn->close();
    exit;
}

// DELETE: Delete SMTP server
if ($method === 'DELETE') {
    parse_str($_SERVER['QUERY_STRING'], $query);
    $id = intval($query['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID']);
        $conn->close();
        exit;
    }
    $sql = "DELETE FROM smtp_servers WHERE id = $id";
    if ($conn->query($sql)) {
        echo json_encode(['success' => true, 'message' => 'SMTP server deleted successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error deleting SMTP server: ' . $conn->error]);
    }
    $conn->close();
    exit;
}

// If no valid method matched
http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
$conn->close();
exit;