<?php
/**
 * Campaign Email Verification System
 * Ensures NO emails are missed during campaign execution
 * 
 * Call this BEFORE starting campaign to verify all recipients are in queue
 * Call this AFTER campaign completion to verify all emails were processed
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/db_campaign.php'; // Campaign DB for mail_blaster

/**
 * Initialize mail_blaster queue from recipients
 * Ensures EVERY email is tracked before campaign starts
 * 
 * @param mysqli $conn Database connection (Main DB for campaign_master, recipients)
 * @param int $campaign_id Campaign ID
 * @return array Statistics: [total_recipients, queued, already_queued, errors]
 */
function initializeEmailQueue($conn, $campaign_id, $distribute_to_servers = true) {
    global $conn_heavy; // Campaign DB for mail_blaster
    
    error_log("[initializeEmailQueue] START - Campaign ID: $campaign_id");
    error_log("[initializeEmailQueue] conn_heavy host: " . ($conn_heavy->host_info ?? 'unknown'));
    
    $stats = [
        'total_recipients' => 0,
        'queued' => 0,
        'already_queued' => 0,
        'skipped_invalid' => 0,
        'errors' => []
    ];
    
    // DEFENSIVE: Check if validation_status column exists in emails table
    // Production DB might not have this column yet
    $hasValidationStatus = $conn->query("SHOW COLUMNS FROM emails LIKE 'validation_status'");
    $validationFilter = ($hasValidationStatus && $hasValidationStatus->num_rows > 0) ? "AND validation_status = 'valid'" : "";
    $validationFilterNoAnd = ($hasValidationStatus && $hasValidationStatus->num_rows > 0) ? "AND e.validation_status = 'valid'" : "";
    
    // Get campaign details
    $campaign = $conn->query("SELECT import_batch_id, csv_list_id, user_id FROM campaign_master WHERE campaign_id = $campaign_id")->fetch_assoc();
    if (!$campaign) {
        error_log("[initializeEmailQueue] ERROR: Campaign #$campaign_id not found in campaign_master");
        $stats['errors'][] = "Campaign not found";
        return $stats;
    }
    
    $import_batch_id = $campaign['import_batch_id'];
    $csv_list_id = $campaign['csv_list_id'];
    $user_id = $campaign['user_id'];
    
    error_log("[initializeEmailQueue] Campaign details - import_batch_id: $import_batch_id, csv_list_id: $csv_list_id, user_id: $user_id");
    
    // OPTIMIZED: Use INSERT...SELECT for bulk processing instead of loop
    // This is 100x faster than individual INSERT per email
    
    if ($import_batch_id) {
        // Excel import: Bulk insert from imported_recipients
        $batch_escaped = $conn->real_escape_string($import_batch_id);
        
        error_log("[initializeEmailQueue] Using IMPORT BATCH: $batch_escaped");
        
        // First, get count of already queued (Server 2)
        $alreadyQueuedRes = $conn_heavy->query("
            SELECT COUNT(*) as cnt
            FROM CRM.mail_blaster
            WHERE campaign_id = $campaign_id
        ");
        if ($alreadyQueuedRes) {
            $stats['already_queued'] = (int)$alreadyQueuedRes->fetch_assoc()['cnt'];
            error_log("[initializeEmailQueue] Already queued in CRM.mail_blaster: {$stats['already_queued']}");
        } else {
            error_log("[initializeEmailQueue] ERROR querying CRM.mail_blaster: " . $conn_heavy->error);
        }
        
        // Get total recipient count
        $totalRes = $conn->query("
            SELECT COUNT(DISTINCT Emails) as cnt 
            FROM imported_recipients 
            WHERE import_batch_id = '$batch_escaped' 
            AND is_active = 1 
            AND Emails IS NOT NULL 
            AND Emails <> ''
        ");
        if ($totalRes) {
            $stats['total_recipients'] = (int)$totalRes->fetch_assoc()['cnt'];
            error_log("[initializeEmailQueue] Total recipients from imported_recipients: {$stats['total_recipients']}");
        } else {
            error_log("[initializeEmailQueue] ERROR counting imported_recipients: " . $conn->error);
        }
        
        // Fetch emails from Server 1, then insert to Server 2
        error_log("[initializeEmailQueue] Fetching emails from imported_recipients (batch=$batch_escaped)...");
        $emailsResult = $conn->query("
            SELECT DISTINCT Emails
            FROM imported_recipients
            WHERE import_batch_id = '$batch_escaped'
            AND is_active = 1
            AND Emails IS NOT NULL
            AND Emails <> ''
        ");
        
        if (!$emailsResult) {
            error_log("[initializeEmailQueue] ✗ Failed to fetch from imported_recipients: " . $conn->error);
            $stats['errors'][] = "Failed to fetch emails: " . $conn->error;
        } else {
            $emailsToInsert = [];
            while ($row = $emailsResult->fetch_assoc()) {
                $emailsToInsert[] = $row['Emails'];
            }
            error_log("[initializeEmailQueue] Fetched " . count($emailsToInsert) . " emails");
            
            // Insert into Server 2 in batches
            $inserted = 0;
            $batchSize = 500;
            for ($i = 0; $i < count($emailsToInsert); $i += $batchSize) {
                $batch = array_slice($emailsToInsert, $i, $batchSize);
                $values = [];
                
                foreach ($batch as $email) {
                    $email_escaped = $conn_heavy->real_escape_string($email);
                    $values[] = "($campaign_id, '$email_escaped', NULL, 'pending', CURDATE(), NOW(), 0, " . ($user_id ? $user_id : "NULL") . ", 0, 0, '')";
                }
                
                if (empty($values)) continue;
                
                $insertSQL = "INSERT IGNORE INTO CRM.mail_blaster 
                    (campaign_id, to_mail, csv_list_id, status, delivery_date, delivery_time, attempt_count, user_id, smtpid, smtp_account_id, smtp_email) 
                    VALUES " . implode(', ', $values);
                
                $insertResult = $conn_heavy->query($insertSQL);
                
                if ($insertResult) {
                    $inserted += $conn_heavy->affected_rows;
                    error_log("[initializeEmailQueue] ✓ Batch inserted: {$conn_heavy->affected_rows} rows");
                } else {
                    error_log("[initializeEmailQueue] ✗ Batch INSERT FAILED: " . $conn_heavy->error);
                    $stats['errors'][] = "Batch insert failed: " . $conn_heavy->error;
                }
            }
            
            $stats['queued'] = $inserted;
            error_log("[initializeEmailQueue] ✓ TOTAL INSERT SUCCESS (import_batch) - Rows: {$stats['queued']}");
        }
            
    } elseif ($csv_list_id) {
        // CSV list: Bulk insert from emails table
        
        error_log("[initializeEmailQueue] Using CSV LIST: $csv_list_id");
        
        // First, get count of already queued (Server 2)
        $alreadyQueuedRes = $conn_heavy->query("
            SELECT COUNT(*) as cnt
            FROM CRM.mail_blaster
            WHERE campaign_id = $campaign_id
        ");
        if ($alreadyQueuedRes) {
            $stats['already_queued'] = (int)$alreadyQueuedRes->fetch_assoc()['cnt'];
            error_log("[initializeEmailQueue] Already queued in CRM.mail_blaster (csv_list): {$stats['already_queued']}");
        } else {
            error_log("[initializeEmailQueue] ERROR querying CRM.mail_blaster (csv_list): " . $conn_heavy->error);
        }
        
        // Get total recipient count
        $totalRes = $conn->query("
            SELECT COUNT(DISTINCT raw_emailid) as cnt 
            FROM emails 
            WHERE domain_status = 1 
            $validationFilter
            AND csv_list_id = $csv_list_id
            AND raw_emailid IS NOT NULL
            AND raw_emailid <> ''
        ");
        if ($totalRes) {
            $stats['total_recipients'] = (int)$totalRes->fetch_assoc()['cnt'];
            error_log("[initializeEmailQueue] Total recipients from emails table (csv_list): {$stats['total_recipients']}");
        } else {
            error_log("[initializeEmailQueue] ERROR counting emails (csv_list): " . $conn->error);
        }
        
        // Bulk INSERT using INSERT...SELECT (only emails not already in queue)
        // NOTE: Cannot use INSERT...SELECT across servers - fetch from Server 1 first, then insert to Server 2
        error_log("[initializeEmailQueue] Fetching emails from Server 1 emails table (csv_list_id=$csv_list_id)...");
        
        $emailsResult = $conn->query("
            SELECT DISTINCT raw_emailid, csv_list_id
            FROM emails
            WHERE domain_status = 1 
            $validationFilter
            AND csv_list_id = $csv_list_id
            AND raw_emailid IS NOT NULL
            AND raw_emailid <> ''
        ");
        
        if (!$emailsResult) {
            error_log("[initializeEmailQueue] ✗ Failed to fetch emails from Server 1: " . $conn->error);
            $stats['errors'][] = "Failed to fetch emails: " . $conn->error;
        } else {
            $emailsToInsert = [];
            while ($row = $emailsResult->fetch_assoc()) {
                $emailsToInsert[] = $row;
            }
            error_log("[initializeEmailQueue] Fetched " . count($emailsToInsert) . " emails from Server 1");
            
            // Now insert into Server 2 in batches
            $inserted = 0;
            $batchSize = 500;
            for ($i = 0; $i < count($emailsToInsert); $i += $batchSize) {
                $batch = array_slice($emailsToInsert, $i, $batchSize);
                $values = [];
                
                foreach ($batch as $email) {
                    $email_escaped = $conn_heavy->real_escape_string($email['raw_emailid']);
                    $csv_id = (int)$email['csv_list_id'];
                    $values[] = "($campaign_id, '$email_escaped', $csv_id, 'pending', CURDATE(), NOW(), 0, " . ($user_id ? $user_id : "NULL") . ", 0, 0, '')";
                }
                
                if (empty($values)) continue;
                
                $insertSQL = "INSERT IGNORE INTO CRM.mail_blaster 
                    (campaign_id, to_mail, csv_list_id, status, delivery_date, delivery_time, attempt_count, user_id, smtpid, smtp_account_id, smtp_email) 
                    VALUES " . implode(', ', $values);
                
                error_log("[initializeEmailQueue] Inserting batch of " . count($values) . " emails into CRM.mail_blaster...");
                $insertResult = $conn_heavy->query($insertSQL);
                
                if ($insertResult) {
                    $inserted += $conn_heavy->affected_rows;
                    error_log("[initializeEmailQueue] ✓ Batch inserted: {$conn_heavy->affected_rows} rows");
                } else {
                    error_log("[initializeEmailQueue] ✗ Batch INSERT FAILED - MySQL Error: " . $conn_heavy->error);
                    $stats['errors'][] = "Batch insert failed: " . $conn_heavy->error;
                }
            }
            
            $stats['queued'] = $inserted;
            error_log("[initializeEmailQueue] ✓ TOTAL INSERT SUCCESS - Rows inserted into CRM.mail_blaster: {$stats['queued']}");
        }
        
    } else {
        // No specific source - use ALL valid emails
        
        error_log("[initializeEmailQueue] Using ALL VALID EMAILS (no csv_list_id or import_batch_id)");
        
        // First, get count of already queued (Server 2)
        $alreadyQueuedRes = $conn_heavy->query("
            SELECT COUNT(*) as cnt
            FROM CRM.mail_blaster
            WHERE campaign_id = $campaign_id
        ");
        if ($alreadyQueuedRes) {
            $stats['already_queued'] = (int)$alreadyQueuedRes->fetch_assoc()['cnt'];
            error_log("[initializeEmailQueue] Already queued in CRM.mail_blaster (all emails): {$stats['already_queued']}");
        } else {
            error_log("[initializeEmailQueue] ERROR querying CRM.mail_blaster (all emails): " . $conn_heavy->error);
        }
        
        // Get total recipient count
        $totalRes = $conn->query("
            SELECT COUNT(DISTINCT raw_emailid) as cnt 
            FROM emails 
            WHERE domain_status = 1
            $validationFilter
            AND raw_emailid IS NOT NULL
            AND raw_emailid <> ''
        ");
        if ($totalRes) {
            $stats['total_recipients'] = (int)$totalRes->fetch_assoc()['cnt'];
            error_log("[initializeEmailQueue] Total recipients from emails table (all emails): {$stats['total_recipients']}");
        } else {
            error_log("[initializeEmailQueue] ERROR counting emails (all emails): " . $conn->error);
        }
        
        // Fetch emails from Server 1, then insert to Server 2
        error_log("[initializeEmailQueue] Fetching ALL valid emails from Server 1...");
        $emailsResult = $conn->query("
            SELECT DISTINCT raw_emailid, csv_list_id
            FROM emails
            WHERE domain_status = 1
            $validationFilter
            AND raw_emailid IS NOT NULL
            AND raw_emailid <> ''
        ");
        
        if (!$emailsResult) {
            error_log("[initializeEmailQueue] ✗ Failed to fetch all emails: " . $conn->error);
            $stats['errors'][] = "Failed to fetch emails: " . $conn->error;
        } else {
            $emailsToInsert = [];
            while ($row = $emailsResult->fetch_assoc()) {
                $emailsToInsert[] = $row;
            }
            error_log("[initializeEmailQueue] Fetched " . count($emailsToInsert) . " emails");
            
            // Insert into Server 2 in batches
            $inserted = 0;
            $batchSize = 500;
            for ($i = 0; $i < count($emailsToInsert); $i += $batchSize) {
                $batch = array_slice($emailsToInsert, $i, $batchSize);
                $values = [];
                
                foreach ($batch as $email) {
                    $email_escaped = $conn_heavy->real_escape_string($email['raw_emailid']);
                    $csv_id = (int)($email['csv_list_id'] ?? 0);
                    $values[] = "($campaign_id, '$email_escaped', $csv_id, 'pending', CURDATE(), NOW(), 0, " . ($user_id ? $user_id : "NULL") . ", 0, 0, '')";
                }
                
                if (empty($values)) continue;
                
                $insertSQL = "INSERT IGNORE INTO CRM.mail_blaster 
                    (campaign_id, to_mail, csv_list_id, status, delivery_date, delivery_time, attempt_count, user_id, smtpid, smtp_account_id, smtp_email) 
                    VALUES " . implode(', ', $values);
                
                $insertResult = $conn_heavy->query($insertSQL);
                
                if ($insertResult) {
                    $inserted += $conn_heavy->affected_rows;
                    error_log("[initializeEmailQueue] ✓ Batch inserted: {$conn_heavy->affected_rows} rows");
                } else {
                    error_log("[initializeEmailQueue] ✗ Batch INSERT FAILED: " . $conn_heavy->error);
                    $stats['errors'][] = "Batch insert failed: " . $conn_heavy->error;
                }
            }
            
            $stats['queued'] = $inserted;
            error_log("[initializeEmailQueue] ✓ TOTAL INSERT SUCCESS (all emails) - Rows: {$stats['queued']}");
        }
    }
    
    error_log("[initializeEmailQueue] COMPLETE - Total: {$stats['total_recipients']}, Queued: {$stats['queued']}, Already queued: {$stats['already_queued']}, Errors: " . count($stats['errors']));
    
    return $stats;
}

/**
 * Verify campaign completion - ensure all emails were processed
 * 
 * @param mysqli $conn Database connection (Main DB for campaign_master)
 * @param int $campaign_id Campaign ID
 * @return array Verification results: [complete, missing_emails, pending_retries, stuck_processing]
 */
function verifyCampaignCompletion($conn, $campaign_id) {
    global $conn_heavy; // Campaign DB for mail_blaster
    
    $verification = [
        'complete' => false,
        'total_recipients' => 0,
        'successfully_sent' => 0,
        'failed_final' => 0,
        'pending_retry' => 0,
        'stuck_processing' => 0,
        'missing_emails' => [],
        'stuck_emails' => [],
        'recommendations' => []
    ];
    
    // Get campaign source
    $campaign = $conn->query("SELECT import_batch_id, csv_list_id FROM campaign_master WHERE campaign_id = $campaign_id")->fetch_assoc();
    if (!$campaign) {
        $verification['recommendations'][] = "Campaign not found";
        return $verification;
    }
    
    $import_batch_id = $campaign['import_batch_id'];
    $csv_list_id = $campaign['csv_list_id'];
    
    // DEFENSIVE: Check if validation_status column exists
    $hasValidationStatus = $conn->query("SHOW COLUMNS FROM emails LIKE 'validation_status'");
    $validationFilterNoAnd = ($hasValidationStatus && $hasValidationStatus->num_rows > 0) ? "AND validation_status = 'valid'" : "";
    
    // Get all recipients from source
    if ($import_batch_id) {
        $batch_escaped = $conn->real_escape_string($import_batch_id);
        $allRecipients = $conn->query("
            SELECT DISTINCT Emails as email 
            FROM imported_recipients 
            WHERE import_batch_id = '$batch_escaped' 
            AND is_active = 1 
            AND Emails IS NOT NULL 
            AND Emails <> ''
        ");
    } elseif ($csv_list_id) {
        $allRecipients = $conn->query("
            SELECT DISTINCT raw_emailid as email 
            FROM emails 
            WHERE domain_status = 1
            $validationFilterNoAnd 
            AND csv_list_id = $csv_list_id
        ");
    } else {
        $allRecipients = $conn->query("
            SELECT DISTINCT raw_emailid as email 
            FROM emails 
            WHERE domain_status = 1
            $validationFilterNoAnd
        ");
    }
    
    $verification['total_recipients'] = $allRecipients ? $allRecipients->num_rows : 0;
    
    // Get mail_blaster statistics
    $stats = $conn_heavy->query("
        SELECT 
            status,
            COUNT(*) as count,
            TIMESTAMPDIFF(MINUTE, MAX(delivery_time), NOW()) as minutes_since_last
        FROM CRM.mail_blaster 
        WHERE campaign_id = $campaign_id
        GROUP BY status
    ");
    
    $statusCounts = [];
    while ($stat = $stats->fetch_assoc()) {
        $statusCounts[$stat['status']] = [
            'count' => (int)$stat['count'],
            'minutes_ago' => (int)$stat['minutes_since_last']
        ];
    }
    
    $verification['successfully_sent'] = isset($statusCounts['success']) ? $statusCounts['success']['count'] : 0;
    
    // Count pending retries (failed but attempt_count < 5)
    $pendingRetry = $conn_heavy->query("
        SELECT COUNT(*) as count 
        FROM CRM.mail_blaster 
        WHERE campaign_id = $campaign_id 
        AND status = 'failed' 
        AND attempt_count < 5
    ");
    $verification['pending_retry'] = $pendingRetry ? (int)$pendingRetry->fetch_assoc()['count'] : 0;
    
    // Count final failures (attempt_count >= 5)
    $finalFailed = $conn_heavy->query("
        SELECT COUNT(*) as count 
        FROM CRM.mail_blaster 
        WHERE campaign_id = $campaign_id 
        AND status = 'failed' 
        AND attempt_count >= 5
    ");
    $verification['failed_final'] = $finalFailed ? (int)$finalFailed->fetch_assoc()['count'] : 0;
    
    // Find stuck emails (processing for >5 minutes)
    $stuckEmails = $conn_heavy->query("
        SELECT to_mail, delivery_time, TIMESTAMPDIFF(MINUTE, delivery_time, NOW()) as stuck_minutes
        FROM CRM.mail_blaster 
        WHERE campaign_id = $campaign_id 
        AND status = 'processing' 
        AND delivery_time < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ");
    
    if ($stuckEmails && $stuckEmails->num_rows > 0) {
        while ($stuck = $stuckEmails->fetch_assoc()) {
            $verification['stuck_processing']++;
            $verification['stuck_emails'][] = [
                'email' => $stuck['to_mail'],
                'stuck_for_minutes' => (int)$stuck['stuck_minutes']
            ];
        }
        $verification['recommendations'][] = "Found {$verification['stuck_processing']} stuck emails - reset to pending for retry";
    }
    
    // Check for missing emails (in source but not in mail_blaster)
    if ($allRecipients) {
        $allRecipients->data_seek(0); // Reset pointer
        while ($row = $allRecipients->fetch_assoc()) {
            $email = $conn->real_escape_string($row['email']);
            $inQueue = $conn_heavy->query("SELECT 1 FROM CRM.mail_blaster WHERE campaign_id = $campaign_id AND to_mail = '$email'");
            
            if (!$inQueue || $inQueue->num_rows === 0) {
                $verification['missing_emails'][] = $row['email'];
            }
        }
    }
    
    // Determine if complete
    $totalProcessed = $verification['successfully_sent'] + $verification['failed_final'];
    $verification['complete'] = (
        $totalProcessed >= $verification['total_recipients'] &&
        $verification['pending_retry'] === 0 &&
        $verification['stuck_processing'] === 0 &&
        count($verification['missing_emails']) === 0
    );
    
    // Generate recommendations
    if (count($verification['missing_emails']) > 0) {
        $verification['recommendations'][] = "Found " . count($verification['missing_emails']) . " missing emails - run initializeEmailQueue() to add them";
    }
    
    if ($verification['pending_retry'] > 0) {
        $verification['recommendations'][] = "{$verification['pending_retry']} emails pending retry - resume campaign";
    }
    
    if ($verification['complete']) {
        $verification['recommendations'][] = "Campaign fully processed: {$verification['successfully_sent']} sent, {$verification['failed_final']} failed permanently";
    }
    
    return $verification;
}

/**
 * Reset stuck emails to pending for retry
 */
function resetStuckEmails($conn, $campaign_id, $stuck_minutes = 5) {
    global $conn_heavy; // Campaign DB for mail_blaster
    
    $result = $conn_heavy->query("
        UPDATE CRM.mail_blaster 
        SET status = 'pending', 
            delivery_time = NOW()
        WHERE campaign_id = $campaign_id 
        AND status = 'processing' 
        AND delivery_time < DATE_SUB(NOW(), INTERVAL $stuck_minutes MINUTE)
    ");
    
    return $conn_heavy->affected_rows;
}

/**
 * Get detailed campaign progress report
 */
function getCampaignProgressReport($conn, $campaign_id) {
    global $conn_heavy; // Campaign DB for mail_blaster
    
    $report = [
        'campaign_id' => $campaign_id,
        'timestamp' => date('Y-m-d H:i:s'),
        'status_breakdown' => [],
        'hourly_progress' => [],
        'smtp_usage' => [],
        'error_summary' => []
    ];
    
    // Status breakdown
    $statusBreakdown = $conn_heavy->query("
        SELECT 
            status,
            COUNT(*) as count,
            MIN(delivery_time) as first_sent,
            MAX(delivery_time) as last_sent
        FROM CRM.mail_blaster 
        WHERE campaign_id = $campaign_id
        GROUP BY status
    ");
    
    while ($row = $statusBreakdown->fetch_assoc()) {
        $report['status_breakdown'][$row['status']] = [
            'count' => (int)$row['count'],
            'first_sent' => $row['first_sent'],
            'last_sent' => $row['last_sent']
        ];
    }
    
    // Hourly sending rate
    $hourlyProgress = $conn_heavy->query("
        SELECT 
            DATE_FORMAT(delivery_time, '%Y-%m-%d %H:00:00') as hour,
            COUNT(*) as emails_sent
        FROM CRM.mail_blaster 
        WHERE campaign_id = $campaign_id 
        AND status = 'success'
        GROUP BY hour
        ORDER BY hour DESC
        LIMIT 24
    ");
    
    while ($row = $hourlyProgress->fetch_assoc()) {
        $report['hourly_progress'][] = [
            'hour' => $row['hour'],
            'emails_sent' => (int)$row['emails_sent']
        ];
    }
    
    // SMTP account usage
    $smtpUsage = $conn_heavy->query("
        SELECT 
            sa.email as smtp_email,
            COUNT(*) as emails_sent,
            SUM(CASE WHEN mb.status = 'success' THEN 1 ELSE 0 END) as successful,
            SUM(CASE WHEN mb.status = 'failed' THEN 1 ELSE 0 END) as failed
        FROM CRM.mail_blaster mb
        JOIN CRM.smtp_accounts sa ON sa.id = mb.smtpid
        WHERE mb.campaign_id = $campaign_id
        GROUP BY sa.email
        ORDER BY emails_sent DESC
    ");
    
    while ($row = $smtpUsage->fetch_assoc()) {
        $report['smtp_usage'][] = [
            'smtp_email' => $row['smtp_email'],
            'total_sent' => (int)$row['emails_sent'],
            'successful' => (int)$row['successful'],
            'failed' => (int)$row['failed'],
            'success_rate' => $row['emails_sent'] > 0 ? round(($row['successful'] / $row['emails_sent']) * 100, 2) : 0
        ];
    }
    
    // Top error messages
    $errorSummary = $conn_heavy->query("
        SELECT 
            SUBSTRING(error_message, 1, 100) as error_snippet,
            COUNT(*) as occurrences
        FROM CRM.mail_blaster 
        WHERE campaign_id = $campaign_id 
        AND status = 'failed'
        AND error_message IS NOT NULL
        GROUP BY error_snippet
        ORDER BY occurrences DESC
        LIMIT 10
    ");
    
    while ($row = $errorSummary->fetch_assoc()) {
        $report['error_summary'][] = [
            'error' => $row['error_snippet'],
            'count' => (int)$row['occurrences']
        ];
    }
    
    return $report;
}

// CLI execution support - ONLY if executed directly
if (php_sapi_name() === 'cli' && isset($argv[1]) && realpath($_SERVER['SCRIPT_FILENAME']) == realpath(__FILE__)) {
    $command = $argv[1];
    $campaign_id = isset($argv[2]) ? (int)$argv[2] : 0;
    
    if ($campaign_id === 0) {
        die("Usage: php campaign_email_verification.php [initialize|verify|reset|report] <campaign_id>\n");
    }
    
    switch ($command) {
        case 'initialize':
            echo "Initializing email queue for campaign #$campaign_id...\n";
            $stats = initializeEmailQueue($conn, $campaign_id);
            echo "Total Recipients: {$stats['total_recipients']}\n";
            echo "Newly Queued: {$stats['queued']}\n";
            echo "Already Queued: {$stats['already_queued']}\n";
            echo "Skipped Invalid: {$stats['skipped_invalid']}\n";
            if (!empty($stats['errors'])) {
                echo "Errors:\n";
                foreach ($stats['errors'] as $error) {
                    echo "  - $error\n";
                }
            }
            break;
            
        case 'verify':
            echo "Verifying campaign #$campaign_id completion...\n";
            $verification = verifyCampaignCompletion($conn, $campaign_id);
            echo "Total Recipients: {$verification['total_recipients']}\n";
            echo "Successfully Sent: {$verification['successfully_sent']}\n";
            echo "Failed Final: {$verification['failed_final']}\n";
            echo "Pending Retry: {$verification['pending_retry']}\n";
            echo "Stuck Processing: {$verification['stuck_processing']}\n";
            echo "Missing Emails: " . count($verification['missing_emails']) . "\n";
            echo "Complete: " . ($verification['complete'] ? 'YES' : 'NO') . "\n";
            if (!empty($verification['recommendations'])) {
                echo "\nRecommendations:\n";
                foreach ($verification['recommendations'] as $rec) {
                    echo "  - $rec\n";
                }
            }
            break;
            
        case 'reset':
            echo "Resetting stuck emails for campaign #$campaign_id...\n";
            $reset = resetStuckEmails($conn, $campaign_id);
            echo "Reset $reset stuck emails to pending\n";
            break;
            
        case 'report':
            echo "Generating progress report for campaign #$campaign_id...\n";
            $report = getCampaignProgressReport($conn, $campaign_id);
            echo json_encode($report, JSON_PRETTY_PRINT) . "\n";
            break;
            
        default:
            die("Unknown command: $command\n");
    }
}
