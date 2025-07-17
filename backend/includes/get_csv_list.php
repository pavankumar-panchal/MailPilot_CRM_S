<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';


$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 10;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$params = [];
$where = '';

if ($search !== '') {
    $where = "WHERE csv_list.list_name LIKE ? OR csv_list.file_name LIKE ? OR EXISTS (
        SELECT 1 FROM emails WHERE emails.csv_list_id = csv_list.id AND emails.email LIKE ?
    )";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Get total count
$countSql = "SELECT COUNT(*) as total FROM csv_list $where";
$stmt = $conn->prepare($countSql);
if ($where !== '')
    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
$stmt->execute();
$countResult = $stmt->get_result();
$total = $countResult->fetch_assoc()['total'] ?? 0;

// Get paginated data
$sql = "SELECT * FROM csv_list $where ORDER BY id DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$bindTypes = ($where !== '') ? str_repeat('s', count($params)) . "ii" : "ii";
$bindParams = ($where !== '') ? array_merge($params, [$limit, $offset]) : [$limit, $offset];
$stmt->bind_param($bindTypes, ...$bindParams);
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

echo json_encode([
    'data' => $lists,
    'total' => intval($total),
    'page' => $page,
    'limit' => $limit
]);