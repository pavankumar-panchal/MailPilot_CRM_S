<?php

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

function getJsonInput() {
    $input = file_get_contents('php://input');
    return json_decode($input, true);
}

// GET: List all SMTP servers with their accounts
if ($method === 'GET') {
    $serversRes = $conn->query("SELECT * FROM smtp_servers ORDER BY id DESC");
    $servers = [];

    while ($server = $serversRes->fetch_assoc()) {
        $server['is_active'] = (bool)$server['is_active'];

        // Fetch accounts for each server
        $accountsRes = $conn->query("SELECT * FROM smtp_accounts WHERE smtp_server_id = {$server['id']} ORDER BY id ASC");
        $accounts = [];
        while ($acc = $accountsRes->fetch_assoc()) {
            $acc['is_active'] = (bool)$acc['is_active'];
            $accounts[] = $acc;
        }
        $server['accounts'] = $accounts;
        $servers[] = $server;
    }
    echo json_encode(['data' => $servers]);
    $conn->close();
    exit;
}

// POST: Add new SMTP server + accounts
if ($method === 'POST') {
    $data = getJsonInput();

    // Insert server
    $name = $conn->real_escape_string($data['name'] ?? '');
    $host = $conn->real_escape_string($data['host'] ?? '');
    $port = intval($data['port'] ?? 465);
    $encryption = $conn->real_escape_string($data['encryption'] ?? '');
    $received_email = $conn->real_escape_string($data['received_email'] ?? '');
    $is_active = !empty($data['is_active']) ? 1 : 0;

    $sql = "INSERT INTO smtp_servers (name, host, port, encryption, received_email, is_active)
            VALUES ('$name', '$host', $port, '$encryption', '$received_email', $is_active)";

    if (!$conn->query($sql)) {
        echo json_encode(['success' => false, 'message' => 'Error adding SMTP server: ' . $conn->error]);
        exit;
    }

    $serverId = $conn->insert_id;

    // Insert accounts if provided
    if (!empty($data['accounts']) && is_array($data['accounts'])) {
        foreach ($data['accounts'] as $acc) {
            $email = trim($conn->real_escape_string($acc['email'] ?? ''));
            $password = trim($conn->real_escape_string($acc['password'] ?? ''));
            if ($email === '' || $password === '') continue;

            $daily_limit = intval($acc['daily_limit'] ?? 500);
            $hourly_limit = intval($acc['hourly_limit'] ?? 100);
            $acc_active = !empty($acc['is_active']) ? 1 : 0;

            $conn->query("INSERT INTO smtp_accounts (smtp_server_id, email, password, daily_limit, hourly_limit, is_active)
                          VALUES ($serverId, '$email', '$password', $daily_limit, $hourly_limit, $acc_active)");
        }
    }

    echo json_encode(['success' => true, 'message' => 'SMTP server and accounts added successfully!']);
    $conn->close();
    exit;
}

// PUT: Update SMTP server or accounts
if ($method === 'PUT') {
    parse_str($_SERVER['QUERY_STRING'], $query);
    $id = intval($query['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID']);
        $conn->close();
        exit;
    }

    $data = getJsonInput();

    // Update server
    if (!empty($data['server'])) {
        $srv = $data['server'];
        $name = $conn->real_escape_string($srv['name'] ?? '');
        $host = $conn->real_escape_string($srv['host'] ?? '');
        $port = intval($srv['port'] ?? 465);
        $encryption = $conn->real_escape_string($srv['encryption'] ?? '');
        $received_email = $conn->real_escape_string($srv['received_email'] ?? '');
        $is_active = !empty($srv['is_active']) ? 1 : 0;

        $conn->query("UPDATE smtp_servers SET
            name = '$name',
            host = '$host',
            port = $port,
            encryption = '$encryption',
            received_email = '$received_email',
            is_active = $is_active
            WHERE id = $id");
    }

    // Update accounts
    if (!empty($data['accounts']) && is_array($data['accounts'])) {
        foreach ($data['accounts'] as $acc) {
            if (!empty($acc['id'])) {
                // Update existing account
                $accId = intval($acc['id']);
                $email = $conn->real_escape_string($acc['email'] ?? '');
                $password = $conn->real_escape_string($acc['password'] ?? '');
                $daily_limit = intval($acc['daily_limit'] ?? 500);
                $hourly_limit = intval($acc['hourly_limit'] ?? 100);
                $acc_active = !empty($acc['is_active']) ? 1 : 0;

                $conn->query("UPDATE smtp_accounts SET
                    email = '$email',
                    password = '$password',
                    daily_limit = $daily_limit,
                    hourly_limit = $hourly_limit,
                    is_active = $acc_active
                    WHERE id = $accId AND smtp_server_id = $id");
            } else {
                // Insert new account
                $email = $conn->real_escape_string($acc['email'] ?? '');
                $password = $conn->real_escape_string($acc['password'] ?? '');
                $daily_limit = intval($acc['daily_limit'] ?? 500);
                $hourly_limit = intval($acc['hourly_limit'] ?? 100);
                $acc_active = !empty($acc['is_active']) ? 1 : 0;

                $conn->query("INSERT INTO smtp_accounts (smtp_server_id, email, password, daily_limit, hourly_limit, is_active)
                              VALUES ($id, '$email', '$password', $daily_limit, $hourly_limit, $acc_active)");
            }
        }
    }

    echo json_encode(['success' => true, 'message' => 'SMTP server/accounts updated successfully!']);
    $conn->close();
    exit;
}

// DELETE: Delete server and its accounts
if ($method === 'DELETE') {
    parse_str($_SERVER['QUERY_STRING'], $query);
    $id = intval($query['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID']);
        $conn->close();
        exit;
    }

    $conn->query("DELETE FROM smtp_servers WHERE id = $id");

    echo json_encode(['success' => true, 'message' => 'SMTP server and accounts deleted successfully!']);
    $conn->close();
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
$conn->close();
exit;
