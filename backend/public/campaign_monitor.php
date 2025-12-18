<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/db.php';

// --- API JSON output for React frontend ---
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");

try {
    // FIRST: Auto-update any campaigns that should be completed
    // Mark campaigns as completed if all emails are processed (pending = 0)
    $update_result = $conn->query("
        UPDATE campaign_status 
        SET status = 'completed',
            end_time = CASE WHEN end_time IS NULL THEN NOW() ELSE end_time END
        WHERE status = 'running' 
        AND pending_emails = 0
    ");
    
    if (!$update_result) {
        error_log("Campaign completion update failed: " . $conn->error);
    }

    // THEN: Fetch all campaigns with current status
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
    
    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }
    
    while ($row = $result->fetch_assoc()) {
        $total = max($row['total_emails'], 1);
        $sent = min($row['sent_emails'], $total);
        $row['progress'] = round(($sent / $total) * 100);
        $campaigns[] = $row;
    }
    
    echo json_encode($campaigns);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

$conn->close();
exit;

