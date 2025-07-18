<?php
require_once __DIR__ . '/../config/db.php';

$data = json_decode(file_get_contents('php://input'), true);
$cid = intval($data['campaign_id'] ?? 0);
$dists = $data['distribution'] ?? [];
$success = false;
$message = '';

if ($cid && is_array($dists)) {
    $conn->begin_transaction();
    try {
        $conn->query("DELETE FROM campaign_distribution WHERE campaign_id = $cid");
        $stmt = $conn->prepare("INSERT INTO campaign_distribution (campaign_id, smtp_id, percentage) VALUES (?, ?, ?)");
        $total = 0;
        foreach ($dists as $dist) {
            $smtp_id = intval($dist['smtp_id']);
            $perc = floatval($dist['percentage']);
            $total += $perc;
            $stmt->bind_param("iid", $cid, $smtp_id, $perc);
            $stmt->execute();
        }
        if ($total > 100) throw new Exception("Total percentage > 100");
        $conn->commit();
        $success = true;
        $message = "Distribution saved!";
    } catch (Exception $e) {
        $conn->rollback();
        $message = $e->getMessage();
    }
}
echo json_encode(['success' => $success, 'message' => $message]);