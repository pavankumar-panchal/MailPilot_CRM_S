<?php
require_once __DIR__ . '/../config/db.php';

$data = json_decode(file_get_contents('php://input'), true);
$cid = intval($data['campaign_id'] ?? 0);
$action = $data['action'] ?? '';

$message = '';
$success = false;

if ($cid && $action) {
    // You can move your PHP logic from your previous code here
    if ($action === 'start_campaign') {
        // ... call your startCampaign($conn, $cid) ...
        $success = true;
        $message = "Campaign started!";
    } elseif ($action === 'pause_campaign') {
        // ... call your pauseCampaign($conn, $cid) ...
        $success = true;
        $message = "Campaign paused!";
    } elseif ($action === 'retry_failed') {
        // ... call your retryFailedEmails($conn, $cid) ...
        $success = true;
        $message = "Retrying failed emails!";
    } elseif ($action === 'auto_distribute') {
        // ... call your auto-distribution logic ...
        $success = true;
        $message = "Auto-distribution done!";
    }
}
echo json_encode(['success' => $success, 'message' => $message]);