<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

function getSMTPServers($conn) {
    $result = $conn->query("SELECT id, daily_limit, hourly_limit FROM smtp_servers WHERE is_active = 1");
    return $result->fetch_all(MYSQLI_ASSOC);
}

function calculateOptimalDistribution($total_emails, $smtp_servers) {
    $distribution = [];
    $total_capacity = 0;
    foreach ($smtp_servers as $server) {
        $daily_capacity = min($server['daily_limit'], $server['hourly_limit'] * 24);
        $total_capacity += $daily_capacity;
    }
    if ($total_capacity > 0) {
        foreach ($smtp_servers as $server) {
            $daily_capacity = min($server['daily_limit'], $server['hourly_limit'] * 24);
            $percentage = ($daily_capacity / $total_capacity) * 100;
            $distribution[] = [
                'smtp_id' => $server['id'],
                'percentage' => round($percentage, 2)
            ];
        }
    }
    return $distribution;
}

$input = json_decode(file_get_contents('php://input'), true);
$cid = intval($input['campaign_id'] ?? 0);

if (!$cid) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'campaign_id required']);
    exit;
}

$res = $conn->query("SELECT COUNT(*) AS total FROM emails WHERE domain_status = 1");
$total_emails = $res->fetch_assoc()['total'] ?? 0;
$smtp_servers = getSMTPServers($conn);
$distribution = calculateOptimalDistribution($total_emails, $smtp_servers);

$conn->begin_transaction();
try {
    $conn->query("DELETE FROM campaign_distribution WHERE campaign_id = $cid");
    $stmt = $conn->prepare("INSERT INTO campaign_distribution (campaign_id, smtp_id, percentage) VALUES (?, ?, ?)");
    foreach ($distribution as $dist) {
        $stmt->bind_param("iid", $cid, $dist['smtp_id'], $dist['percentage']);
        $stmt->execute();
    }
    $conn->commit();
    echo json_encode(['success' => true, 'distribution' => $distribution]);
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}