<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/session_config.php';
require_once __DIR__ . '/security_helpers.php';
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/user_filtering.php';
require_once __DIR__ . '/api_optimization.php';

// Start performance tracking
$startTime = microtime(true);

// Enable response compression
enableCompression();

header('Content-Type: application/json');

// Set security headers
setSecurityHeaders();

// Handle CORS securely
handleCors();

// Get current user (supports both session and token auth)
$currentUser = getAuthenticatedUser();

// Require authentication for all operations except OPTIONS
if ($_SERVER['REQUEST_METHOD'] !== 'OPTIONS' && !$currentUser) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Please log in.']);
    exit;
}

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
    
    // Get current user ID
    $user_id = $currentUser['id'];
    
    $stmt = $conn->prepare("INSERT INTO workers (workername, ip, is_active, user_id) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssii", $workername, $ip, $is_active, $user_id);

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
    
    // Check if user can access this worker (admin can access all, user only their own)
    if (!isAuthenticatedAdmin()) {
        $checkStmt = $conn->prepare("SELECT user_id FROM workers WHERE id = ?");
        $checkStmt->bind_param("i", $id);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $worker = $checkResult->fetch_assoc();
        $checkStmt->close();
        
        if (!$worker || (int)$worker['user_id'] !== (int)$currentUser['id']) {
            http_response_code(403);
            echo json_encode(['error' => 'You do not have permission to update this worker']);
            exit;
        }
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
    
    // Check if user can access this worker (admin can access all, user only their own)
    if (!isAuthenticatedAdmin()) {
        $checkStmt = $conn->prepare("SELECT user_id FROM workers WHERE id = ?");
        $checkStmt->bind_param("i", $id);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $worker = $checkResult->fetch_assoc();
        $checkStmt->close();
        
        if (!$worker || (int)$worker['user_id'] !== (int)$currentUser['id']) {
            http_response_code(403);
            echo json_encode(['error' => 'You do not have permission to delete this worker']);
            exit;
        }
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
    // Set cache headers for GET requests (2 minutes)
    setCacheHeaders(getCacheDurationForResource('workers'));
    
    // Admin can see all workers, regular users only see their own
    if (isAuthenticatedAdmin()) {
        // Admin: get all workers
        $result = $conn->query("SELECT id, workername, ip, is_active, user_id FROM workers ORDER BY is_active DESC, id DESC");
    } else {
        // Regular user: get only their workers
        $user_id = (int)$currentUser['id'];
        $stmt = $conn->prepare("SELECT id, workername, ip, is_active, user_id FROM workers WHERE user_id = ? ORDER BY is_active DESC, id DESC");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
    }
    
    $workers = [];
    while ($row = $result->fetch_assoc()) {
        $row['is_active'] = (int)$row['is_active'];
        $row['user_id'] = (int)$row['user_id'];
        $workers[] = $row;
    }
    
    // Add performance headers
    addPerformanceHeaders($startTime);
    
    // Send optimized JSON response
    echo json_encode($workers);
    exit;
    exit;
}

// If not GET, POST, PUT, DELETE
http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
exit;