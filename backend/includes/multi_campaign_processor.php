<?php
/**
 * Multi-Campaign Parallel Processor
 * 
 * Runs multiple campaigns simultaneously, distributing SMTP accounts efficiently
 * Each campaign gets its dedicated SMTP accounts for maximum speed
 */

// === RESOURCE MANAGEMENT: Prevent affecting other applications ===
require_once __DIR__ . '/resource_manager.php';
ResourceManager::initCampaignProcess('orchestrator');

error_reporting(E_ALL);
ini_set('display_errors', 0);
// Memory limit (512M) and time limit (3600s) set by ResourceManager
date_default_timezone_set('Asia/Kolkata');

require_once __DIR__ . '/../config/db.php';

/**
 * Get all active campaigns that need processing
 */
function getActiveCampaigns($conn) {
    $query = "
        SELECT 
            cs.campaign_id,
            cs.status,
            cs.total_emails,
            cs.sent_emails,
            cs.pending_emails,
            cm.user_id,
            cm.mail_subject
        FROM campaign_status cs
        JOIN campaign_master cm ON cm.campaign_id = cs.campaign_id
        WHERE cs.status IN ('pending', 'running')
        AND cs.pending_emails > 0
        ORDER BY cs.campaign_id ASC
    ";
    
    $result = $conn->query($query);
    $campaigns = [];
    
    while ($row = $result->fetch_assoc()) {
        $campaigns[] = $row;
    }
    
    return $campaigns;
}

/**
 * Get available SMTP servers with their accounts grouped by user
 */
function getAvailableSmtpByUser($conn) {
    $today = date('Y-m-d');
    $current_hour = intval(date('G'));
    
    $query = "
        SELECT 
            ss.id as server_id,
            ss.user_id,
            sa.id as account_id,
            sa.email,
            sa.daily_limit,
            sa.hourly_limit,
            COALESCE(daily_usage.sent_today, 0) as sent_today,
            COALESCE(hourly_usage.emails_sent, 0) as sent_this_hour
        FROM smtp_servers ss
        JOIN smtp_accounts sa ON sa.smtp_server_id = ss.id
        LEFT JOIN (
            SELECT smtp_id, SUM(emails_sent) as sent_today
            FROM smtp_usage
            WHERE date = '$today'
            GROUP BY smtp_id
        ) daily_usage ON daily_usage.smtp_id = sa.id
        LEFT JOIN smtp_usage hourly_usage ON hourly_usage.smtp_id = sa.id 
            AND hourly_usage.date = '$today' AND hourly_usage.hour = $current_hour
        WHERE ss.is_active = 1
        AND sa.is_active = 1
        AND (sa.daily_limit = 0 OR COALESCE(daily_usage.sent_today, 0) < sa.daily_limit)
        AND (sa.hourly_limit = 0 OR COALESCE(hourly_usage.emails_sent, 0) < sa.hourly_limit)
        ORDER BY ss.user_id, ss.id, sa.id
    ";
    
    $result = $conn->query($query);
    $smtp_by_user = [];
    
    while ($row = $result->fetch_assoc()) {
        $user_id = $row['user_id'];
        if (!isset($smtp_by_user[$user_id])) {
            $smtp_by_user[$user_id] = [
                'servers' => [],
                'accounts' => []
            ];
        }
        
        $server_id = $row['server_id'];
        if (!isset($smtp_by_user[$user_id]['servers'][$server_id])) {
            $smtp_by_user[$user_id]['servers'][$server_id] = [
                'id' => $server_id,
                'accounts' => []
            ];
        }
        
        $smtp_by_user[$user_id]['servers'][$server_id]['accounts'][] = [
            'id' => $row['account_id'],
            'email' => $row['email'],
            'capacity' => min(
                $row['daily_limit'] > 0 ? ($row['daily_limit'] - $row['sent_today']) : PHP_INT_MAX,
                $row['hourly_limit'] > 0 ? ($row['hourly_limit'] - $row['sent_this_hour']) : PHP_INT_MAX
            )
        ];
        
        $smtp_by_user[$user_id]['accounts'][] = $row['account_id'];
    }
    
    return $smtp_by_user;
}

/**
 * Start a campaign process
 */
function startCampaignProcess($conn, $campaign_id) {
    // Use existing email_blast_parallel.php to start campaign
    $php_cli_candidates = [
        '/opt/plesk/php/8.1/bin/php',
        '/usr/bin/php8.1',
        '/usr/local/bin/php',
        '/usr/bin/php'
    ];
    
    $php_cli = null;
    foreach ($php_cli_candidates as $candidate) {
        if (file_exists($candidate) && is_executable($candidate)) {
            $php_cli = $candidate;
            break;
        }
    }
    
    if (!$php_cli) {
        $php_cli = trim(shell_exec('command -v php 2>/dev/null')) ?: 'php';
    }
    
    $script = __DIR__ . '/email_blast_parallel.php';
    // Launch with LOW PRIORITY to prevent affecting other applications
    $cmd = sprintf(
        'nice -n 10 %s %s %d > /dev/null 2>&1 &',
        escapeshellarg($php_cli),
        escapeshellarg($script),
        $campaign_id
    );
    
    exec($cmd, $output, $ret);
    
    // Update status to running - with row-level locking and SHORT timeout
    try {
        // Set short lock timeout to avoid blocking frontend queries
        $conn->query("SET SESSION innodb_lock_wait_timeout = 3");
        
        $conn->begin_transaction();
        $conn->query("SELECT campaign_id FROM campaign_status WHERE campaign_id = $campaign_id FOR UPDATE");
        $conn->query("UPDATE campaign_status SET status = 'running', start_time = NOW() WHERE campaign_id = $campaign_id");
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Failed to update campaign #$campaign_id status to running: " . $e->getMessage());
        return false;
    }
    
    return true;
}

/**
 * Check if campaign is already running
 */
function isCampaignRunning($conn, $campaign_id) {
    $result = $conn->query("
        SELECT process_pid 
        FROM campaign_status 
        WHERE campaign_id = $campaign_id 
        AND status = 'running'
    ");
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $pid = $row['process_pid'];
        
        // Check if PID is actually running
        if ($pid && file_exists('/proc/' . intval($pid))) {
            return true;
        }
    }
    
    return false;
}

/**
 * Main multi-campaign processor
 */
function processMultipleCampaigns($conn, $max_concurrent = 5) {
    echo "[" . date('Y-m-d H:i:s') . "] Multi-Campaign Processor Starting...\n";
    
    // Get all active campaigns
    $campaigns = getActiveCampaigns($conn);
    echo "[" . date('Y-m-d H:i:s') . "] Found " . count($campaigns) . " campaigns needing processing\n";
    
    if (empty($campaigns)) {
        echo "[" . date('Y-m-d H:i:s') . "] No campaigns to process\n";
        return;
    }
    
    // Get available SMTP accounts by user
    $smtp_by_user = getAvailableSmtpByUser($conn);
    echo "[" . date('Y-m-d H:i:s') . "] SMTP accounts available for " . count($smtp_by_user) . " users\n";
    
    // Start campaigns up to max_concurrent limit
    $started = 0;
    $running = 0;
    
    foreach ($campaigns as $campaign) {
        // Check if already running
        if (isCampaignRunning($conn, $campaign['campaign_id'])) {
            echo "[" . date('Y-m-d H:i:s') . "] Campaign #{$campaign['campaign_id']} already running\n";
            $running++;
            continue;
        }
        
        // Check if we've hit concurrent limit
        if ($running >= $max_concurrent) {
            echo "[" . date('Y-m-d H:i:s') . "] Reached max concurrent campaigns ($max_concurrent)\n";
            break;
        }
        
        // Check if user has SMTP accounts available
        $user_id = $campaign['user_id'];
        if (!isset($smtp_by_user[$user_id]) || empty($smtp_by_user[$user_id]['accounts'])) {
            echo "[" . date('Y-m-d H:i:s') . "] Campaign #{$campaign['campaign_id']}: No SMTP accounts available for user #$user_id\n";
            continue;
        }
        
        // Start campaign
        echo "[" . date('Y-m-d H:i:s') . "] Starting campaign #{$campaign['campaign_id']} ({$campaign['mail_subject']})...\n";
        echo "[" . date('Y-m-d H:i:s') . "]   User: #{$user_id}, Pending: {$campaign['pending_emails']}, Sent: {$campaign['sent_emails']}/{$campaign['total_emails']}\n";
        
        if (startCampaignProcess($conn, $campaign['campaign_id'])) {
            $started++;
            $running++;
            echo "[" . date('Y-m-d H:i:s') . "] Campaign #{$campaign['campaign_id']} started successfully\n";
            
            // Small delay between launches
            usleep(100000); // 100ms
        }
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] Multi-Campaign Processor Complete: Started $started new, $running total running\n";
}

// Main execution
if (php_sapi_name() === 'cli') {
    $max_concurrent = isset($argv[1]) ? intval($argv[1]) : 5;
    processMultipleCampaigns($conn, $max_concurrent);
    $conn->close();
}
