<?php
/**
 * Fix Campaign Status Counts
 * 
 * Recalculates and updates all campaign_status table counts from the mail_blaster table
 * (the source of truth). This ensures pending/sent/failed counts are accurate.
 * 
 * Usage: php fix_campaign_status_counts.php [campaign_id]
 *        If campaign_id is provided, only that campaign is fixed.
 *        Otherwise, all campaigns are recalculated.
 */

require_once __DIR__ . '/../config/db.php';

function log_message($msg) {
    echo "[" . date('Y-m-d H:i:s') . "] $msg\n";
}

// Get campaign_id from command line if provided
$specific_campaign = null;
if ($argc > 1) {
    $specific_campaign = intval($argv[1]);
    log_message("Fixing specific campaign: $specific_campaign");
} else {
    log_message("Fixing all campaigns...");
}

// Build campaign filter
$campaignFilter = $specific_campaign ? "WHERE campaign_id = $specific_campaign" : "";

// Get all campaigns to fix
$campaignsQuery = "SELECT DISTINCT campaign_id FROM mail_blaster $campaignFilter";
$campaignsRes = $conn->query($campaignsQuery);

if (!$campaignsRes) {
    log_message("ERROR: Failed to query campaigns: " . $conn->error);
    exit(1);
}

$fixed_count = 0;
$skipped_count = 0;

while ($campaign = $campaignsRes->fetch_assoc()) {
    $campaign_id = intval($campaign['campaign_id']);
    
    // Get accurate counts from mail_blaster (source of truth)
    $statsQuery = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success_count,
        SUM(CASE WHEN status = 'failed' AND attempt_count >= 5 THEN 1 ELSE 0 END) as failed_count,
        SUM(CASE WHEN status IN ('pending', 'failed') AND attempt_count < 5 THEN 1 ELSE 0 END) as retryable_count
    FROM mail_blaster 
    WHERE campaign_id = $campaign_id";
    
    $statsRes = $conn->query($statsQuery);
    if (!$statsRes) {
        log_message("  Campaign $campaign_id: ERROR querying stats - " . $conn->error);
        $skipped_count++;
        continue;
    }
    
    $stats = $statsRes->fetch_assoc();
    $total = intval($stats['total']);
    $successCount = intval($stats['success_count']);
    $failedCount = intval($stats['failed_count']);
    $retryableCount = intval($stats['retryable_count']);
    
    if ($total === 0) {
        log_message("  Campaign $campaign_id: No emails found in mail_blaster, skipping");
        $skipped_count++;
        continue;
    }
    
    // Get current campaign_status values for comparison
    $currentQuery = "SELECT total_emails, sent_emails, failed_emails, pending_emails, status 
        FROM campaign_status WHERE campaign_id = $campaign_id";
    $currentRes = $conn->query($currentQuery);
    $current = $currentRes ? $currentRes->fetch_assoc() : null;
    
    if (!$current) {
        log_message("  Campaign $campaign_id: No status row exists, creating one");
        $insertQuery = "INSERT INTO campaign_status 
            (campaign_id, status, total_emails, sent_emails, failed_emails, pending_emails) 
            VALUES ($campaign_id, 'running', $total, $successCount, $failedCount, $retryableCount)";
        if ($conn->query($insertQuery)) {
            log_message("  Campaign $campaign_id: Created status row (Total=$total, Sent=$successCount, Failed=$failedCount, Pending=$retryableCount)");
            $fixed_count++;
        } else {
            log_message("  Campaign $campaign_id: ERROR creating status row - " . $conn->error);
            $skipped_count++;
        }
        continue;
    }
    
    // Check if update is needed
    $needsUpdate = false;
    $changes = [];
    
    if (intval($current['total_emails']) !== $total) {
        $changes[] = "total: {$current['total_emails']} → $total";
        $needsUpdate = true;
    }
    if (intval($current['sent_emails']) !== $successCount) {
        $changes[] = "sent: {$current['sent_emails']} → $successCount";
        $needsUpdate = true;
    }
    if (intval($current['failed_emails']) !== $failedCount) {
        $changes[] = "failed: {$current['failed_emails']} → $failedCount";
        $needsUpdate = true;
    }
    if (intval($current['pending_emails']) !== $retryableCount) {
        $changes[] = "pending: {$current['pending_emails']} → $retryableCount";
        $needsUpdate = true;
    }
    
    if (!$needsUpdate) {
        log_message("  Campaign $campaign_id: Already correct (Total=$total, Sent=$successCount, Failed=$failedCount, Pending=$retryableCount)");
        continue;
    }
    
    // Update campaign_status with accurate counts
    $updateQuery = "UPDATE campaign_status 
        SET total_emails = $total,
            sent_emails = $successCount,
            failed_emails = $failedCount,
            pending_emails = $retryableCount
        WHERE campaign_id = $campaign_id";
    
    if ($conn->query($updateQuery)) {
        log_message("  Campaign $campaign_id: FIXED - " . implode(", ", $changes));
        
        // Check if campaign should be marked complete
        if ($retryableCount === 0 && $current['status'] !== 'completed') {
            $completeQuery = "UPDATE campaign_status 
                SET status = 'completed', end_time = NOW(), process_pid = NULL, pending_emails = 0 
                WHERE campaign_id = $campaign_id";
            if ($conn->query($completeQuery)) {
                log_message("  Campaign $campaign_id: Marked as COMPLETED (all retries exhausted)");
            }
        }
        
        $fixed_count++;
    } else {
        log_message("  Campaign $campaign_id: ERROR updating - " . $conn->error);
        $skipped_count++;
    }
}

log_message("\n=== Summary ===");
log_message("Fixed: $fixed_count campaigns");
log_message("Skipped: $skipped_count campaigns");
log_message("Done!");
