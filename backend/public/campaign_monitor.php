<?php
require_once __DIR__ . '/../config/db.php';

// --- API JSON output for React frontend ---
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");

$campaigns = [];
$result = $conn->query("
    SELECT cm.*, 
        COALESCE(cs.status, 'pending') as campaign_status, 
        COALESCE(cs.total_emails, 0) as total_emails, 
        COALESCE(cs.pending_emails, 0) as pending_emails, 
        COALESCE(cs.sent_emails, 0) as sent_emails, 
        COALESCE(cs.failed_emails, 0) as failed_emails,
        cs.start_time, 
        cs.end_time
    FROM campaign_master cm
    LEFT JOIN campaign_status cs ON cm.campaign_id = cs.campaign_id
    ORDER BY cm.campaign_id DESC
");
while ($row = $result->fetch_assoc()) {
    $total = max($row['total_emails'], 1);
    $sent = min($row['sent_emails'], $total);
    $row['progress'] = round(($sent / $total) * 100);
    $campaigns[] = $row;
}
echo json_encode($campaigns);
$conn->close();
exit;
