<?php
/**
 * Campaign Email Verification System
 * Ensures NO emails are missed during campaign execution
 * 
 * Call this BEFORE starting campaign to verify all recipients are in queue
 * Call this AFTER campaign completion to verify all emails were processed
 */

require_once __DIR__ . '/../config/db.php';

/**
 * Initialize mail_blaster queue from recipients
 * Ensures EVERY email is tracked before campaign starts
 * 
 * @param mysqli $conn Database connection
 * @param int $campaign_id Campaign ID
 * @return array Statistics: [total_recipients, queued, already_queued, errors]
 */
function initializeEmailQueue($conn, $campaign_id) {
    $stats = [
        'total_recipients' => 0,
        'queued' => 0,
        'already_queued' => 0,
        'skipped_invalid' => 0,
        'errors' => []
    ];
    
    // Get campaign details
    $campaign = $conn->query("SELECT import_batch_id, csv_list_id, user_id FROM campaign_master WHERE campaign_id = $campaign_id")->fetch_assoc();
    if (!$campaign) {
        $stats['errors'][] = "Campaign not found";
        return $stats;
    }
    
    $import_batch_id = $campaign['import_batch_id'];
    $csv_list_id = $campaign['csv_list_id'];
    $user_id = $campaign['user_id'];
    
    // Determine recipient source
    if ($import_batch_id) {
        // Excel import: Get emails from imported_recipients
        $batch_escaped = $conn->real_escape_string($import_batch_id);
        $recipientsQuery = "
            SELECT DISTINCT Emails as email 
            FROM imported_recipients 
            WHERE import_batch_id = '$batch_escaped' 
            AND is_active = 1 
            AND Emails IS NOT NULL 
            AND Emails <> ''
        ";
    } elseif ($csv_list_id) {
        // CSV list: Get emails from emails table
        $recipientsQuery = "
            SELECT DISTINCT email 
            FROM emails 
            WHERE domain_status = 1 
            AND csv_list_id = $csv_list_id
        ";
    } else {
        // No specific source - use ALL valid emails
        $recipientsQuery = "
            SELECT DISTINCT email 
            FROM emails 
            WHERE domain_status = 1
        ";
    }
    
    $recipients = $conn->query($recipientsQuery);
    if (!$recipients) {
        $stats['errors'][] = "Failed to fetch recipients: " . $conn->error;
        return $stats;
    }
    
    $stats['total_recipients'] = $recipients->num_rows;
    
    // Start transaction for atomic queue initialization
    $conn->query("START TRANSACTION");
    
    while ($row = $recipients->fetch_assoc()) {
        $email = trim($row['email']);
        
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $stats['skipped_invalid']++;
            continue;
        }
        
        $email_escaped = $conn->real_escape_string($email);
        
        // Check if already in queue
        $existingCheck = $conn->query("
            SELECT status FROM mail_blaster 
            WHERE campaign_id = $campaign_id 
            AND to_mail = '$email_escaped'
        ");
        
        if ($existingCheck && $existingCheck->num_rows > 0) {
            $existing = $existingCheck->fetch_assoc();
            // If already sent successfully, count as already queued
            if ($existing['status'] === 'success') {
                $stats['already_queued']++;
            } else {
                // Reset failed/pending to pending for retry
                $conn->query("
                    UPDATE mail_blaster 
                    SET status = 'pending', 
                        attempt_count = 0,
                        error_message = NULL,
                        delivery_time = NOW()
                    WHERE campaign_id = $campaign_id 
                    AND to_mail = '$email_escaped'
                    AND status != 'success'
                ");
                $stats['already_queued']++;
            }
        } else {
            // Insert new record
            $insertResult = $conn->query("
                INSERT IGNORE INTO mail_blaster 
                (campaign_id, to_mail, csv_list_id, status, delivery_date, delivery_time, attempt_count, user_id) 
                VALUES (
                    $campaign_id, 
                    '$email_escaped', 
                    " . ($csv_list_id ? $csv_list_id : "NULL") . ", 
                    'pending', 
                    CURDATE(), 
                    NOW(), 
                    0,
                    " . ($user_id ? $user_id : "NULL") . "
                )
            ");
            
            if ($insertResult && $conn->affected_rows > 0) {
                $stats['queued']++;
            } else {
                // Log error but continue
                $stats['errors'][] = "Failed to queue: $email";
            }
        }
    }
    
    $conn->query("COMMIT");
    
    return $stats;
}

/**
 * Verify campaign completion - ensure all emails were processed
 * 
 * @param mysqli $conn Database connection
 * @param int $campaign_id Campaign ID
 * @return array Verification results: [complete, missing_emails, pending_retries, stuck_processing]
 */
function verifyCampaignCompletion($conn, $campaign_id) {
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
            SELECT DISTINCT email 
            FROM emails 
            WHERE domain_status = 1 
            AND csv_list_id = $csv_list_id
        ");
    } else {
        $allRecipients = $conn->query("
            SELECT DISTINCT email 
            FROM emails 
            WHERE domain_status = 1
        ");
    }
    
    $verification['total_recipients'] = $allRecipients ? $allRecipients->num_rows : 0;
    
    // Get mail_blaster statistics
    $stats = $conn->query("
        SELECT 
            status,
            COUNT(*) as count,
            TIMESTAMPDIFF(MINUTE, MAX(delivery_time), NOW()) as minutes_since_last
        FROM mail_blaster 
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
    $pendingRetry = $conn->query("
        SELECT COUNT(*) as count 
        FROM mail_blaster 
        WHERE campaign_id = $campaign_id 
        AND status = 'failed' 
        AND attempt_count < 5
    ");
    $verification['pending_retry'] = $pendingRetry ? (int)$pendingRetry->fetch_assoc()['count'] : 0;
    
    // Count final failures (attempt_count >= 5)
    $finalFailed = $conn->query("
        SELECT COUNT(*) as count 
        FROM mail_blaster 
        WHERE campaign_id = $campaign_id 
        AND status = 'failed' 
        AND attempt_count >= 5
    ");
    $verification['failed_final'] = $finalFailed ? (int)$finalFailed->fetch_assoc()['count'] : 0;
    
    // Find stuck emails (processing for >5 minutes)
    $stuckEmails = $conn->query("
        SELECT to_mail, delivery_time, TIMESTAMPDIFF(MINUTE, delivery_time, NOW()) as stuck_minutes
        FROM mail_blaster 
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
            $inQueue = $conn->query("SELECT 1 FROM mail_blaster WHERE campaign_id = $campaign_id AND to_mail = '$email'");
            
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
    $result = $conn->query("
        UPDATE mail_blaster 
        SET status = 'pending', 
            delivery_time = NOW()
        WHERE campaign_id = $campaign_id 
        AND status = 'processing' 
        AND delivery_time < DATE_SUB(NOW(), INTERVAL $stuck_minutes MINUTE)
    ");
    
    return $conn->affected_rows;
}

/**
 * Get detailed campaign progress report
 */
function getCampaignProgressReport($conn, $campaign_id) {
    $report = [
        'campaign_id' => $campaign_id,
        'timestamp' => date('Y-m-d H:i:s'),
        'status_breakdown' => [],
        'hourly_progress' => [],
        'smtp_usage' => [],
        'error_summary' => []
    ];
    
    // Status breakdown
    $statusBreakdown = $conn->query("
        SELECT 
            status,
            COUNT(*) as count,
            MIN(delivery_time) as first_sent,
            MAX(delivery_time) as last_sent
        FROM mail_blaster 
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
    $hourlyProgress = $conn->query("
        SELECT 
            DATE_FORMAT(delivery_time, '%Y-%m-%d %H:00:00') as hour,
            COUNT(*) as emails_sent
        FROM mail_blaster 
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
    $smtpUsage = $conn->query("
        SELECT 
            sa.email as smtp_email,
            COUNT(*) as emails_sent,
            SUM(CASE WHEN mb.status = 'success' THEN 1 ELSE 0 END) as successful,
            SUM(CASE WHEN mb.status = 'failed' THEN 1 ELSE 0 END) as failed
        FROM mail_blaster mb
        JOIN smtp_accounts sa ON sa.id = mb.smtpid
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
    $errorSummary = $conn->query("
        SELECT 
            SUBSTRING(error_message, 1, 100) as error_snippet,
            COUNT(*) as occurrences
        FROM mail_blaster 
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

// CLI execution support
if (php_sapi_name() === 'cli' && isset($argv[1])) {
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
