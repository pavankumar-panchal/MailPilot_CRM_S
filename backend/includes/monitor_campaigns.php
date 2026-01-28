<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/session_config.php';
require_once __DIR__ . '/user_filtering.php';
require_once __DIR__ . '/auth_helper.php';

// Require authentication
$currentUser = requireAuth();
$userFilter = getAuthFilterWhere();

$result = $conn->query("
    SELECT cm.campaign_id, cm.description, 
        COALESCE(cs.status, 'pending') as campaign_status, 
        COALESCE(cs.total_emails, 0) as total_emails, 
        COALESCE(cs.pending_emails, 0) as pending_emails, 
        COALESCE(cs.sent_emails, 0) as sent_emails, 
        COALESCE(cs.failed_emails, 0) as failed_emails
    FROM campaign_master cm
    LEFT JOIN campaign_status cs ON cm.campaign_id = cs.campaign_id
    $userFilter
    ORDER BY cm.campaign_id DESC
");
$rows = [];
while ($row = $result->fetch_assoc()) {
    $total = max((int)$row['total_emails'], 1);
    $sent = min((int)$row['sent_emails'], $total);
    $row['progress'] = round(($sent / $total) * 100);
    $rows[] = $row;
}
echo json_encode($rows);