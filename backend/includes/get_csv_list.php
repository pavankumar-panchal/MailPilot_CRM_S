<?php
// Set timezone to Asia/Kolkata
date_default_timezone_set('Asia/Kolkata');

// ABSOLUTE FIRST LOG - write to file directly
file_put_contents('/tmp/get_csv_list_debug.log', date('Y-m-d H:i:s') . " - REQUEST START\n", FILE_APPEND);
file_put_contents('/tmp/get_csv_list_debug.log', "Origin: " . ($_SERVER['HTTP_ORIGIN'] ?? 'none') . "\n", FILE_APPEND);
file_put_contents('/tmp/get_csv_list_debug.log', "Method: " . ($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN') . "\n", FILE_APPEND);

// Log immediately
error_log(">>> get_csv_list.php REQUEST START - Method: " . ($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN'));
error_log(">>> get_csv_list.php - Origin: " . ($_SERVER['HTTP_ORIGIN'] ?? 'none'));

// Enable error output to see what's happening
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', '/opt/lampp/logs/php_error_log');

// CRITICAL: Load security helpers FIRST (before session starts) to handle CORS
require_once __DIR__ . '/security_helpers.php';
error_log(">>> Loaded security_helpers.php");

// Handle CORS BEFORE session starts
handleCors();
error_log(">>> handleCors() completed");

// Set Content-Type header early
header('Content-Type: application/json');
error_log(">>> Set headers");

// Set security headers (but skip CSP that might interfere)
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header_remove("X-Powered-By");

// Now start session
error_log(">>> About to load session_config.php");
require_once __DIR__ . '/session_config.php';
error_log(">>> Loaded session_config.php");

require_once __DIR__ . '/../config/db.php';
error_log(">>> Loaded db.php");

require_once __DIR__ . '/user_filtering.php';
error_log(">>> Loaded user_filtering.php");

error_log(">>> About to load auth_helper.php");
require_once __DIR__ . '/auth_helper.php';
error_log(">>> Loaded auth_helper.php");

error_log("=== get_csv_list.php START ===");

// Ensure user_id columns exist (skip errors)
try {
    ensureUserIdColumns($conn);
} catch (Exception $e) {
    error_log("ensureUserIdColumns error (non-fatal): " . $e->getMessage());
}

// Require authentication - will exit with 401 if not authenticated
try {
    $currentUser = requireAuth();
    error_log("get_csv_list.php - User authenticated: " . json_encode($currentUser));
    error_log("get_csv_list.php - User role: " . $currentUser['role']);
} catch (Exception $e) {
    error_log("requireAuth failed: " . $e->getMessage());
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication failed', 'details' => $e->getMessage()]);
    exit;
}

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 10;

// Support fetching all records when limit is set to -1 or 'all'
$fetchAll = (isset($_GET['limit']) && ($_GET['limit'] == -1 || $_GET['limit'] == 'all'));

$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$params = [];
$where = '';

// User filtering
$userFilter = getAuthFilterWhere('csv_list');
$currentUserData = getAuthenticatedUser();
error_log("get_csv_list.php - User filter: $userFilter");
error_log("get_csv_list.php - Current user: " . json_encode($currentUserData));
error_log("get_csv_list.php - Is admin: " . (isAuthenticatedAdmin() ? 'YES' : 'NO'));
if ($userFilter !== '') {
    $where = $userFilter;
}

if ($search !== '') {
    if ($where !== '') {
        $where .= " AND csv_list.list_name LIKE ?";
    } else {
        $where = "WHERE csv_list.list_name LIKE ?";
    }
    $params[] = "%$search%";
}

error_log("get_csv_list.php - WHERE clause: $where, Params: " . json_encode($params));

// Get total count
$countSql = "SELECT COUNT(*) as total FROM csv_list $where";
if (!empty($params)) {
    $stmt = $conn->prepare($countSql);
    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
    $stmt->execute();
    $countResult = $stmt->get_result();
    $total = $countResult->fetch_assoc()['total'] ?? 0;
    $stmt->close();
} else {
    $countResult = $conn->query($countSql);
    $total = $countResult->fetch_assoc()['total'] ?? 0;
}

// Get paginated or all data
if ($fetchAll) {
    // Fetch all records without LIMIT
    $sql = "SELECT * FROM csv_list $where ORDER BY id DESC";
    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(str_repeat('s', count($params)), ...$params);
    } else {
        $stmt = $conn->prepare($sql);
    }
} else {
    // Fetch paginated data
    $sql = "SELECT * FROM csv_list $where ORDER BY id DESC LIMIT ? OFFSET ?";
    if (!empty($params)) {
        $bindTypes = str_repeat('s', count($params)) . "ii";
        $bindParams = array_merge($params, [$limit, $offset]);
    } else {
        $bindTypes = "ii";
        $bindParams = [$limit, $offset];
    }
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($bindTypes, ...$bindParams);
}

$stmt->execute();
$result = $stmt->get_result();

$lists = [];
while ($row = $result->fetch_assoc()) {
    // Fetch retryable (failed) count for this list
    $failedStmt = $conn->prepare("SELECT COUNT(*) as failed_count FROM emails WHERE csv_list_id = ? AND domain_status = 2");
    $failedStmt->bind_param("i", $row['id']);
    $failedStmt->execute();
    $failedResult = $failedStmt->get_result();
    $failedRow = $failedResult->fetch_assoc();
    $row['failed_count'] = intval($failedRow['failed_count'] ?? 0);
    $failedStmt->close();

    $lists[] = $row;
}
$stmt->close();

error_log("get_csv_list.php - Found " . count($lists) . " lists, total: $total");
if (count($lists) > 0) {
    error_log("get_csv_list.php - First list: " . json_encode($lists[0]));
} else {
    error_log("get_csv_list.php - No lists found. SQL: $sql, WHERE: $where");
    error_log("get_csv_list.php - User ID: " . $currentUser['id'] . ", Role: " . $currentUser['role']);
}

// Always return success with data (even if empty array)
echo json_encode([
    'success' => true,
    'data' => $lists,
    'total' => intval($total),
    'page' => $page,
    'limit' => $limit,
    'debug' => [
        'user_id' => $currentUser['id'],
        'user_role' => $currentUser['role'],
        'filter_applied' => $userFilter !== '' ? $userFilter : 'none (admin)',
        'lists_count' => count($lists)
    ]
]);
