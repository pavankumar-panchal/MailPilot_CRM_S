<?php
require_once __DIR__ . '/../config/db.php';

echo "=== All Campaigns Check ===\n\n";

// Get all campaigns
$allCampaigns = $conn->query("
    SELECT cm.campaign_id, cm.description, cm.import_batch_id, cm.csv_list_id,
           cs.status, cs.total_emails, cs.sent_emails, cs.failed_emails, cs.pending_emails
    FROM campaign_master cm
    LEFT JOIN campaign_status cs ON cm.campaign_id = cs.campaign_id
    ORDER BY cm.campaign_id DESC
    LIMIT 10
");

echo "Found " . $allCampaigns->num_rows . " campaigns:\n\n";

while ($campaign = $allCampaigns->fetch_assoc()) {
    $campaign_id = $campaign['campaign_id'];
    echo "Campaign #{$campaign_id}: {$campaign['description']}\n";
    echo "  Status: " . ($campaign['status'] ?? 'NO STATUS RECORD') . "\n";
    
    if ($campaign['status']) {
        echo "  Total: {$campaign['total_emails']}, Sent: {$campaign['sent_emails']}, Failed: {$campaign['failed_emails']}, Pending: {$campaign['pending_emails']}\n";
        
        // Check mail_blaster
        $blasterRes = $conn->query("
            SELECT 
                COUNT(DISTINCT CASE WHEN status = 'success' THEN to_mail END) as sent,
                COUNT(DISTINCT CASE WHEN status = 'failed' THEN to_mail END) as failed
            FROM mail_blaster
            WHERE campaign_id = $campaign_id
        ");
        
        if ($blasterRes && $blasterRes->num_rows > 0) {
            $blaster = $blasterRes->fetch_assoc();
            echo "  mail_blaster: Sent={$blaster['sent']}, Failed={$blaster['failed']}\n";
        }
        
        // Check if should be completed
        $processed = intval($campaign['sent_emails']) + intval($campaign['failed_emails']);
        $should_complete = ($processed >= intval($campaign['total_emails']) && intval($campaign['total_emails']) > 0) 
                        || (intval($campaign['pending_emails']) == 0 && $processed > 0);
        
        if ($campaign['status'] === 'running' && $should_complete) {
            echo "  >>> SHOULD BE COMPLETED! <<<\n";
        }
    }
    
    echo "\n";
}

// Now fix them
echo "\n=== Fixing Completed Campaigns ===\n";
$fixed = $conn->query("
    UPDATE campaign_status cs
    SET cs.status = 'completed',
        cs.pending_emails = 0,
        cs.end_time = CASE WHEN cs.end_time IS NULL THEN NOW() ELSE cs.end_time END
    WHERE cs.status = 'running'
    AND (
        (cs.sent_emails + cs.failed_emails >= cs.total_emails AND cs.total_emails > 0)
        OR (cs.pending_emails = 0 AND (cs.sent_emails > 0 OR cs.failed_emails > 0))
    )
");

echo "Updated " . $conn->affected_rows . " campaigns to completed status.\n";

$conn->close();
