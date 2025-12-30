<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/db.php';

// --- API JSON output for React frontend ---
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");

try {
    // FIRST: Ensure every campaign has a campaign_status row
    // a) from mail_blaster (already sent)
    $conn->query("
        INSERT INTO campaign_status (campaign_id, status, total_emails, sent_emails, failed_emails, pending_emails, start_time)
        SELECT 
            mb.campaign_id,
            'running' as status,
            0 as total_emails,
            COUNT(DISTINCT CASE WHEN mb.status = 'success' THEN mb.to_mail END) as sent_emails,
            COUNT(DISTINCT CASE WHEN mb.status = 'failed' THEN mb.to_mail END) as failed_emails,
            0 as pending_emails,
            MIN(mb.delivery_time) as start_time
        FROM mail_blaster mb
        WHERE mb.campaign_id NOT IN (SELECT campaign_id FROM campaign_status)
        GROUP BY mb.campaign_id
        ON DUPLICATE KEY UPDATE campaign_id = campaign_id
    ");
    // b) from campaign_master (not started yet)
    $conn->query("
        INSERT INTO campaign_status (campaign_id, status, total_emails, pending_emails, sent_emails, failed_emails)
        SELECT cm.campaign_id, 'pending', 0, 0, 0, 0
        FROM campaign_master cm
        WHERE cm.campaign_id NOT IN (SELECT campaign_id FROM campaign_status)
    ");
    
    // SECOND: Update totals for all campaigns based on their source (always refresh to stay accurate)
    $allCampaigns = $conn->query("
        SELECT cs.campaign_id, cm.import_batch_id, cm.csv_list_id
        FROM campaign_status cs
        JOIN campaign_master cm ON cs.campaign_id = cm.campaign_id
    ");
    
    if ($allCampaigns) {
        while ($camp = $allCampaigns->fetch_assoc()) {
            $cid = $camp['campaign_id'];
            $import_batch_id = $camp['import_batch_id'];
            $csv_list_id = intval($camp['csv_list_id']);
            
            $total = 0;
            if ($import_batch_id) {
                $batch_escaped = $conn->real_escape_string($import_batch_id);
                $totalRes = $conn->query("SELECT COUNT(*) as total FROM imported_recipients WHERE import_batch_id = '$batch_escaped' AND is_active = 1 AND Emails IS NOT NULL AND Emails <> ''");
                $total = intval($totalRes->fetch_assoc()['total']);
            } elseif ($csv_list_id > 0) {
                $totalRes = $conn->query("SELECT COUNT(*) as total FROM emails WHERE csv_list_id = $csv_list_id AND domain_status = 1 AND validation_status = 'valid' AND raw_emailid IS NOT NULL AND raw_emailid <> ''");
                $total = intval($totalRes->fetch_assoc()['total']);
            }
            
            // Always update total_emails to the current expected count
            $conn->query("UPDATE campaign_status SET total_emails = $total WHERE campaign_id = $cid");
        }
    }
    
    // THIRD: Auto-update campaigns to completed based on mail_blaster actual counts
    // Get all running campaigns and check if they're actually completed
    $runningCampaigns = $conn->query("
        SELECT cs.campaign_id, cm.import_batch_id, cm.csv_list_id,
               cs.total_emails, cs.sent_emails, cs.failed_emails, cs.pending_emails
        FROM campaign_status cs
        JOIN campaign_master cm ON cs.campaign_id = cm.campaign_id
        WHERE cs.status = 'running'
    ");
    
    if ($runningCampaigns) {
        while ($campaign = $runningCampaigns->fetch_assoc()) {
            $campaign_id = $campaign['campaign_id'];
            $import_batch_id = $campaign['import_batch_id'];
            $csv_list_id = intval($campaign['csv_list_id']);
            
            // Get actual counts from mail_blaster
            $blasterStats = $conn->query("
                SELECT 
                    COUNT(DISTINCT to_mail) as total_in_blaster,
                    COUNT(DISTINCT CASE WHEN status = 'success' THEN to_mail END) as actual_sent,
                    COUNT(DISTINCT CASE WHEN status = 'failed' AND attempt_count >= 5 THEN to_mail END) as actual_failed
                FROM mail_blaster
                WHERE campaign_id = $campaign_id
            ");
            
            if ($blasterStats && $blasterStats->num_rows > 0) {
                $stats = $blasterStats->fetch_assoc();
                $actual_sent = intval($stats['actual_sent']);
                $actual_failed = intval($stats['actual_failed']);
                $total_in_blaster = intval($stats['total_in_blaster']);
                
                // CRITICAL: Check for unclaimed emails (not in mail_blaster yet)
                $unclaimed = 0;
                if ($import_batch_id) {
                    $batch_escaped = $conn->real_escape_string($import_batch_id);
                    $unclaimedRes = $conn->query("
                        SELECT COUNT(*) as unclaimed FROM imported_recipients ir
                        WHERE ir.import_batch_id = '$batch_escaped'
                        AND ir.is_active = 1
                        AND ir.Emails IS NOT NULL
                        AND ir.Emails <> ''
                        AND NOT EXISTS (
                            SELECT 1 FROM mail_blaster mb
                            WHERE mb.campaign_id = $campaign_id
                            AND mb.to_mail COLLATE utf8mb4_unicode_ci = ir.Emails
                        )
                    ");
                    if ($unclaimedRes) {
                        $unclaimed = intval($unclaimedRes->fetch_assoc()['unclaimed']);
                    }
                } elseif ($csv_list_id > 0) {
                    $unclaimedRes = $conn->query("
                        SELECT COUNT(*) as unclaimed FROM emails e
                        WHERE e.domain_status = 1
                        AND e.validation_status = 'valid'
                        AND e.raw_emailid IS NOT NULL
                        AND e.raw_emailid <> ''
                        AND e.csv_list_id = $csv_list_id
                        AND NOT EXISTS (
                            SELECT 1 FROM mail_blaster mb
                            WHERE mb.campaign_id = $campaign_id
                            AND mb.to_mail = e.raw_emailid
                        )
                    ");
                    if ($unclaimedRes) {
                        $unclaimed = intval($unclaimedRes->fetch_assoc()['unclaimed']);
                    }
                }
                
                // Get expected total from source (fallback to mail_blaster count if source total is 0)
                $expected_total = 0;
                if ($import_batch_id) {
                    // Excel import
                    $batch_escaped = $conn->real_escape_string($import_batch_id);
                    $totalRes = $conn->query("
                        SELECT COUNT(*) as total 
                        FROM imported_recipients 
                        WHERE import_batch_id = '$batch_escaped' 
                        AND is_active = 1 
                        AND Emails IS NOT NULL 
                        AND Emails <> ''
                    ");
                    $expected_total = intval($totalRes->fetch_assoc()['total']);
                } elseif ($csv_list_id > 0) {
                    // CSV list
                    $totalRes = $conn->query("
                        SELECT COUNT(*) as total 
                        FROM emails 
                        WHERE csv_list_id = $csv_list_id 
                        AND domain_status = 1 
                        AND validation_status = 'valid'
                        AND raw_emailid IS NOT NULL 
                        AND raw_emailid <> ''
                    ");
                    $expected_total = intval($totalRes->fetch_assoc()['total']);
                } else {
                    // All emails - use total from campaign_status
                    $expected_total = intval($campaign['total_emails']);
                }
                
                // If source total not available, trust mail_blaster total
                if ($expected_total === 0 && $total_in_blaster > 0) {
                    $expected_total = $total_in_blaster;
                }

                // Compute pending based on actual sends/failures
                $pending = max(0, $expected_total - $actual_sent - $actual_failed);
                $pending_in_blaster = max(0, $total_in_blaster - $actual_sent - $actual_failed);

                // Determine if campaign should be completed
                // ONLY mark completed when ALL emails are claimed (unclaimed = 0) AND processed
                $should_complete = false;
                if ($unclaimed === 0 && $expected_total > 0 && ($actual_sent + $actual_failed) >= $expected_total) {
                    // No unclaimed emails AND all expected emails are processed
                    $should_complete = true;
                } elseif ($unclaimed === 0 && $total_in_blaster > 0 && $pending_in_blaster === 0 && $expected_total > 0) {
                    // No unclaimed emails AND all in mail_blaster are processed
                    $should_complete = true;
                }
                
                // Update the campaign status
                if ($should_complete) {
                    $conn->query("
                        UPDATE campaign_status 
                        SET status = 'completed',
                            total_emails = $expected_total,
                            sent_emails = $actual_sent,
                            failed_emails = $actual_failed,
                            pending_emails = 0,
                            end_time = CASE WHEN end_time IS NULL THEN NOW() ELSE end_time END
                        WHERE campaign_id = $campaign_id
                    ");
                } else {
                    // Just update counts
                    $conn->query("
                        UPDATE campaign_status 
                        SET total_emails = $expected_total,
                            sent_emails = $actual_sent,
                            failed_emails = $actual_failed,
                            pending_emails = $pending
                        WHERE campaign_id = $campaign_id
                    ");
                }
            }
        }
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
        
        // Auto-complete if progress is 100% but status is still running
        if ($row['campaign_status'] === 'running' && $row['progress'] >= 100 && $row['total_emails'] > 0) {
            $conn->query("
                UPDATE campaign_status 
                SET status = 'completed',
                    pending_emails = 0,
                    end_time = CASE WHEN end_time IS NULL THEN NOW() ELSE end_time END
                WHERE campaign_id = {$row['campaign_id']}
            ");
            $row['campaign_status'] = 'completed';
        }
        
        $campaigns[] = $row;
    }
    
    echo json_encode($campaigns);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

$conn->close();
exit;

