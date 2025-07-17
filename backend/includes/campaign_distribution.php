<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

function getInputData() {
    $input = file_get_contents('php://input');
    return json_decode($input, true) ?? [];
}

try {
    if ($method === 'GET') {
        if (!isset($_GET['campaign_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'campaign_id required']);
            exit;
        }
        $cid = intval($_GET['campaign_id']);
        $stmt = $conn->prepare("SELECT cd.smtp_id, cd.percentage, ss.name, ss.daily_limit, ss.hourly_limit
                                FROM campaign_distribution cd
                                JOIN smtp_servers ss ON cd.smtp_id = ss.id
                                WHERE cd.campaign_id = ?");
        $stmt->bind_param("i", $cid);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($row = $res->fetch_assoc()) $rows[] = $row;
        echo json_encode($rows);
        exit;
    }

    if ($method === 'POST') {
        $data = getInputData();
        $cid = intval($data['campaign_id'] ?? 0);
        $distribution = $data['distribution'] ?? [];
        if (!$cid || !is_array($distribution)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid input']);
            exit;
        }
        $conn->begin_transaction();
        try {
            $conn->query("DELETE FROM campaign_distribution WHERE campaign_id = $cid");
            $stmt = $conn->prepare("INSERT INTO campaign_distribution (campaign_id, smtp_id, percentage) VALUES (?, ?, ?)");
            $total = 0;
            foreach ($distribution as $dist) {
                $smtp_id = intval($dist['smtp_id']);
                $perc = floatval($dist['percentage']);
                $total += $perc;
                $stmt->bind_param("iid", $cid, $smtp_id, $perc);
                $stmt->execute();
            }
            if ($total > 100) throw new Exception("Total percentage cannot exceed 100%");
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Distribution saved']);
        } catch (Exception $e) {
            $conn->rollback();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}