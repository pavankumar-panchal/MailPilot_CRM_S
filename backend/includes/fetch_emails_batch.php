<?php
/**
 * Batch Email Fetcher for Server 2
 * 
 * This script fetches emails from Server 1 and stores them in mail_blaster on Server 2
 * in batches to avoid memory issues and network timeouts.
 * 
 * IMPORTANT: This runs on Server 2 and fetches data from Server 1
 */

require_once __DIR__ . '/../config/db.php';           // Server 1 connection ($conn)
require_once __DIR__ . '/../config/db_campaign.php';  // Server 2 connection ($conn_heavy)

// Batch configuration
define('FETCH_BATCH_SIZE', 1000);  // Fetch 1000 emails per batch from Server 1
define('INSERT_BATCH_SIZE', 500);  // Insert 500 emails at a time into Server 2

/**
 * Fetch emails from Server 1 and populate mail_blaster on Server 2 in batches
 * 
 * @param int $campaign_id Campaign ID
 * @param string|null $import_batch_id Import batch ID (for imported campaigns)
 * @param int|null $csv_list_id CSV list ID (for CSV campaigns)
 * @return array Statistics: [total_fetched, inserted, already_exists, errors]
 */
function fetchAndStoreEmailsBatch($campaign_id, $import_batch_id = null, $csv_list_id = null) {
    global $conn, $conn_heavy;
    
    $stats = [
        'total_fetched' => 0,
        'inserted' => 0,
        'already_exists' => 0,
        'errors' => []
    ];
    
    $log = function($msg) use ($campaign_id) {
        error_log("[CAMPAIGN $campaign_id] [BATCH_FETCH] $msg");
    };
    
    $log("========== BATCH EMAIL FETCH START ==========");
    $log("Source - import_batch_id: " . ($import_batch_id ?: 'NULL') . ", csv_list_id: " . ($csv_list_id ?: 'NULL'));
    
    // Check connections
    if (!$conn || $conn->connect_error) {
        $stats['errors'][] = "Server 1 connection failed";
        $log("ERROR: Server 1 connection failed");
        return $stats;
    }
    
    if (!$conn_heavy || $conn_heavy->connect_error) {
        $stats['errors'][] = "Server 2 connection failed";
        $log("ERROR: Server 2 connection failed");
        return $stats;
    }
    
    try {
        // Determine the source and build query
        if ($import_batch_id) {
            // Fetch from imported_recipients on Server 1
            $log("Fetching from imported_recipients (Server 1)...");
            $batch_escaped = $conn->real_escape_string($import_batch_id);
            
            // Get total count first
            $countRes = $conn->query("
                SELECT COUNT(DISTINCT Emails) as cnt 
                FROM imported_recipients 
                WHERE import_batch_id = '$batch_escaped' 
                AND is_active = 1 
                AND Emails IS NOT NULL 
                AND Emails <> ''
            ");
            $totalCount = $countRes ? (int)$countRes->fetch_assoc()['cnt'] : 0;
            $log("Total recipients to fetch: $totalCount");
            
            // Check how many already exist in mail_blaster
            $existingRes = $conn_heavy->query("
                SELECT COUNT(*) as cnt 
                FROM mail_blaster 
                WHERE campaign_id = $campaign_id
            ");
            $existingCount = $existingRes ? (int)$existingRes->fetch_assoc()['cnt'] : 0;
            $log("Already in mail_blaster: $existingCount");
            
            if ($existingCount >= $totalCount) {
                $log("All emails already in mail_blaster, skipping fetch");
                $stats['already_exists'] = $existingCount;
                return $stats;
            }
            
            // Fetch emails in batches with offset
            $offset = 0;
            while ($offset < $totalCount) {
                $log("Fetching batch: offset=$offset, limit=" . FETCH_BATCH_SIZE);
                
                // Fetch batch from Server 1
                $fetchQuery = "
                    SELECT DISTINCT Emails as email, Name as name
                    FROM imported_recipients 
                    WHERE import_batch_id = '$batch_escaped' 
                    AND is_active = 1 
                    AND Emails IS NOT NULL 
                    AND Emails <> ''
                    ORDER BY id
                    LIMIT " . FETCH_BATCH_SIZE . " OFFSET $offset
                ";
                
                $result = $conn->query($fetchQuery);
                if (!$result) {
                    $stats['errors'][] = "Failed to fetch batch at offset $offset: " . $conn->error;
                    $log("ERROR: Failed to fetch batch - " . $conn->error);
                    break;
                }
                
                $batchEmails = [];
                while ($row = $result->fetch_assoc()) {
                    $batchEmails[] = $row;
                }
                
                $batchCount = count($batchEmails);
                $log("Fetched $batchCount emails from Server 1");
                
                if ($batchCount === 0) {
                    break; // No more emails
                }
                
                // Insert batch into mail_blaster on Server 2
                $inserted = insertEmailsToMailBlaster($campaign_id, $batchEmails, $csv_list_id, $log);
                $stats['inserted'] += $inserted;
                $stats['total_fetched'] += $batchCount;
                
                $offset += FETCH_BATCH_SIZE;
                
                // Small delay to prevent overwhelming the servers
                usleep(100000); // 100ms delay
            }
            
        } elseif ($csv_list_id > 0) {
            // Fetch from emails table on Server 1 with csv_list_id filter
            $log("Fetching from emails table with csv_list_id=$csv_list_id (Server 1)...");
            
            // Get total count first
            $countRes = $conn->query("
                SELECT COUNT(*) as cnt 
                FROM emails 
                WHERE domain_status = 1 
                AND raw_emailid IS NOT NULL 
                AND raw_emailid <> ''
                AND csv_list_id = " . (int)$csv_list_id
            );
            $totalCount = $countRes ? (int)$countRes->fetch_assoc()['cnt'] : 0;
            $log("Total emails to fetch: $totalCount");
            
            // Check how many already exist in mail_blaster
            $existingRes = $conn_heavy->query("
                SELECT COUNT(*) as cnt 
                FROM mail_blaster 
                WHERE campaign_id = $campaign_id 
                AND csv_list_id = " . (int)$csv_list_id
            );
            $existingCount = $existingRes ? (int)$existingRes->fetch_assoc()['cnt'] : 0;
            $log("Already in mail_blaster: $existingCount");
            
            if ($existingCount >= $totalCount) {
                $log("All emails already in mail_blaster, skipping fetch");
                $stats['already_exists'] = $existingCount;
                return $stats;
            }
            
            // Fetch emails in batches
            $offset = 0;
            while ($offset < $totalCount) {
                $log("Fetching batch: offset=$offset, limit=" . FETCH_BATCH_SIZE);
                
                $fetchQuery = "
                    SELECT raw_emailid as email, NULL as name
                    FROM emails 
                    WHERE domain_status = 1 
                    AND raw_emailid IS NOT NULL 
                    AND raw_emailid <> ''
                    AND csv_list_id = " . (int)$csv_list_id . "
                    ORDER BY id
                    LIMIT " . FETCH_BATCH_SIZE . " OFFSET $offset
                ";
                
                $result = $conn->query($fetchQuery);
                if (!$result) {
                    $stats['errors'][] = "Failed to fetch batch at offset $offset: " . $conn->error;
                    $log("ERROR: Failed to fetch batch - " . $conn->error);
                    break;
                }
                
                $batchEmails = [];
                while ($row = $result->fetch_assoc()) {
                    $batchEmails[] = $row;
                }
                
                $batchCount = count($batchEmails);
                $log("Fetched $batchCount emails from Server 1");
                
                if ($batchCount === 0) {
                    break;
                }
                
                // Insert batch into mail_blaster on Server 2
                $inserted = insertEmailsToMailBlaster($campaign_id, $batchEmails, $csv_list_id, $log);
                $stats['inserted'] += $inserted;
                $stats['total_fetched'] += $batchCount;
                
                $offset += FETCH_BATCH_SIZE;
                usleep(100000); // 100ms delay
            }
            
        } else {
            // Fetch all valid emails from emails table on Server 1
            $log("Fetching from emails table - ALL valid emails (Server 1)...");
            
            // Get total count first
            $countRes = $conn->query("
                SELECT COUNT(*) as cnt 
                FROM emails 
                WHERE domain_status = 1 
                AND raw_emailid IS NOT NULL 
                AND raw_emailid <> ''
            ");
            $totalCount = $countRes ? (int)$countRes->fetch_assoc()['cnt'] : 0;
            $log("Total emails to fetch: $totalCount");
            
            // Check how many already exist in mail_blaster
            $existingRes = $conn_heavy->query("
                SELECT COUNT(*) as cnt 
                FROM mail_blaster 
                WHERE campaign_id = $campaign_id
            ");
            $existingCount = $existingRes ? (int)$existingRes->fetch_assoc()['cnt'] : 0;
            $log("Already in mail_blaster: $existingCount");
            
            if ($existingCount >= $totalCount) {
                $log("All emails already in mail_blaster, skipping fetch");
                $stats['already_exists'] = $existingCount;
                return $stats;
            }
            
            // Fetch emails in batches
            $offset = 0;
            while ($offset < $totalCount) {
                $log("Fetching batch: offset=$offset, limit=" . FETCH_BATCH_SIZE);
                
                $fetchQuery = "
                    SELECT raw_emailid as email, NULL as name
                    FROM emails 
                    WHERE domain_status = 1 
                    AND raw_emailid IS NOT NULL 
                    AND raw_emailid <> ''
                    ORDER BY id
                    LIMIT " . FETCH_BATCH_SIZE . " OFFSET $offset
                ";
                
                $result = $conn->query($fetchQuery);
                if (!$result) {
                    $stats['errors'][] = "Failed to fetch batch at offset $offset: " . $conn->error;
                    $log("ERROR: Failed to fetch batch - " . $conn->error);
                    break;
                }
                
                $batchEmails = [];
                while ($row = $result->fetch_assoc()) {
                    $batchEmails[] = $row;
                }
                
                $batchCount = count($batchEmails);
                $log("Fetched $batchCount emails from Server 1");
                
                if ($batchCount === 0) {
                    break;
                }
                
                // Insert batch into mail_blaster on Server 2
                $inserted = insertEmailsToMailBlaster($campaign_id, $batchEmails, null, $log);
                $stats['inserted'] += $inserted;
                $stats['total_fetched'] += $batchCount;
                
                $offset += FETCH_BATCH_SIZE;
                usleep(100000); // 100ms delay
            }
        }
        
        $log("========== BATCH EMAIL FETCH COMPLETE ==========");
        $log("Total fetched: {$stats['total_fetched']}, Inserted: {$stats['inserted']}, Already exists: {$stats['already_exists']}");
        
    } catch (Exception $e) {
        $stats['errors'][] = "Exception: " . $e->getMessage();
        $log("ERROR: Exception - " . $e->getMessage());
    }
    
    return $stats;
}

/**
 * Insert emails into mail_blaster on Server 2
 * 
 * @param int $campaign_id Campaign ID
 * @param array $emails Array of email records [['email' => '...', 'name' => '...']]
 * @param int|null $csv_list_id CSV list ID
 * @param callable $log Logging function
 * @return int Number of emails inserted
 */
function insertEmailsToMailBlaster($campaign_id, $emails, $csv_list_id, $log) {
    global $conn_heavy;
    
    if (empty($emails)) {
        return 0;
    }
    
    $inserted = 0;
    $log("Inserting " . count($emails) . " emails into mail_blaster (Server 2)...");
    
    // Insert in smaller batches to avoid query size limits
    $chunks = array_chunk($emails, INSERT_BATCH_SIZE);
    
    foreach ($chunks as $chunkIndex => $chunk) {
        $values = [];
        foreach ($chunk as $email) {
            $email_escaped = $conn_heavy->real_escape_string($email['email']);
            $csv_list_value = $csv_list_id ? (int)$csv_list_id : 'NULL';
            
            // Insert with status='pending' and delivery_time=NOW()
            $values[] = "($campaign_id, '$email_escaped', $csv_list_value, NULL, CURDATE(), NOW(), 'pending', 0)";
        }
        
        if (!empty($values)) {
            $insertQuery = "
                INSERT IGNORE INTO mail_blaster 
                (campaign_id, to_mail, csv_list_id, smtpid, delivery_date, delivery_time, status, attempt_count)
                VALUES " . implode(', ', $values);
            
            $result = $conn_heavy->query($insertQuery);
            
            if ($result) {
                $affectedRows = $conn_heavy->affected_rows;
                $inserted += $affectedRows;
                $log("Chunk " . ($chunkIndex + 1) . ": Inserted $affectedRows emails");
            } else {
                $log("ERROR: Failed to insert chunk " . ($chunkIndex + 1) . " - " . $conn_heavy->error);
            }
        }
        
        // Small delay between chunks
        usleep(50000); // 50ms
    }
    
    $log("Total inserted from this batch: $inserted emails");
    return $inserted;
}

/**
 * Check if campaign needs email fetching from Server 1
 * 
 * @param int $campaign_id Campaign ID
 * @return bool True if emails need to be fetched
 */
function needsEmailFetching($campaign_id) {
    global $conn, $conn_heavy;
    
    // Check campaign status - must be 'running'
    $statusRes = $conn->query("SELECT status FROM campaign_status WHERE campaign_id = $campaign_id");
    if (!$statusRes) {
        return false;
    }
    
    $statusRow = $statusRes->fetch_assoc();
    if (!$statusRow || $statusRow['status'] !== 'running') {
        return false;
    }
    
    // Check if mail_blaster has any emails for this campaign
    $mbRes = $conn_heavy->query("SELECT COUNT(*) as cnt FROM mail_blaster WHERE campaign_id = $campaign_id");
    if (!$mbRes) {
        return false;
    }
    
    $mbCount = (int)$mbRes->fetch_assoc()['cnt'];
    
    // If no emails in mail_blaster, we need to fetch
    return ($mbCount === 0);
}
