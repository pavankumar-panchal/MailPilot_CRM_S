<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

// ADD worker
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['workername']) || !isset($data['ip'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing workername or ip']);
        exit;
    }

    $workername = trim($data['workername']);
    $ip = trim($data['ip']);

    if ($workername === '' || $ip === '') {
        http_response_code(400);
        echo json_encode(['error' => 'workername and ip cannot be empty']);
        exit;
    }

    // Add this validation:
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid IP address format']);
        exit;
    }

    // Support optional is_active parameter (defaults to 1 if not provided)
    $is_active = isset($data['is_active']) ? (int)$data['is_active'] : 1;
    
    $stmt = $conn->prepare("INSERT INTO workers (workername, ip, is_active) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $workername, $ip, $is_active);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Worker added successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to add worker']);
    }
    $stmt->close();
    exit;
}

// UPDATE worker
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    parse_str(file_get_contents("php://input"), $put_vars);
    $data = json_decode(file_get_contents('php://input'), true);

    $id = isset($_GET['id']) ? intval($_GET['id']) : (isset($data['id']) ? intval($data['id']) : 0);
    $workername = isset($data['workername']) ? trim($data['workername']) : '';
    $ip = isset($data['ip']) ? trim($data['ip']) : '';

    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing id']);
        exit;
    }

    // Support partial updates for status toggle
    if (isset($data['is_active']) && !isset($data['workername']) && !isset($data['ip'])) {
        // Status toggle only
        $is_active = (int)$data['is_active'];
        $stmt = $conn->prepare("UPDATE workers SET is_active = ? WHERE id = ?");
        $stmt->bind_param("ii", $is_active, $id);
        
        if ($stmt->execute()) {
            $status_text = $is_active ? 'activated' : 'deactivated';
            echo json_encode(['success' => true, 'message' => "Worker {$status_text} successfully"]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update worker status']);
        }
        $stmt->close();
        exit;
    }

    // Full update
    if ($workername === '' || $ip === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Missing workername or ip']);
        exit;
    }

    $is_active = isset($data['is_active']) ? (int)$data['is_active'] : 1;
    $stmt = $conn->prepare("UPDATE workers SET workername = ?, ip = ?, is_active = ? WHERE id = ?");
    $stmt->bind_param("ssii", $workername, $ip, $is_active, $id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Worker updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update worker']);
    }
    $stmt->close();
    exit;
}

// DELETE worker
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    // Support id from query string or JSON body
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if (!$id) {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = isset($data['id']) ? intval($data['id']) : 0;
    }
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing id']);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM workers WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Worker deleted successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete worker']);
    }
    $stmt->close();
    exit;
}

// GET workers
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $result = $conn->query("SELECT id, workername, ip, is_active FROM workers ORDER BY is_active DESC, id DESC");
    $workers = [];
    while ($row = $result->fetch_assoc()) {
        $row['is_active'] = (int)$row['is_active'];
        $workers[] = $row;
    }
    echo json_encode($workers);
    exit;
}

// If not GET, POST, PUT, DELETE
http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
exit;