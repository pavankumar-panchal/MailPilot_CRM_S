<?php
// Minimal CLI runner to initialize email queue and start campaign worker.
// Usage: php async_start_campaign.php <campaign_id>

// DIRECT DATABASE CONNECTIONS - No config files
// SERVER 1: For campaign_master and campaign_status
$conn = new mysqli('127.0.0.1', 'email_id', '55y60jgW*', 'email_id');
if ($conn->connect_error) {
    error_log("CRITICAL: Server 1 DB Connection failed: " . $conn->connect_error);
    exit(1);
}
$conn->set_charset("utf8mb4");
error_log("✓ async_start: Connected to Server 1 - Database: email_id");

// SERVER 2: For mail_blaster ONLY
$conn_heavy = new mysqli('207.244.80.245', 'CRM', '55y60jgW*', 'CRM');
if ($conn_heavy->connect_error) {
    error_log("CRITICAL: Server 2 DB Connection failed: " . $conn_heavy->connect_error);
    exit(1);
}
$conn_heavy->set_charset("utf8mb4");
error_log("✓ async_start: Connected to Server 2 - Database: CRM");

require_once __DIR__ . "/../includes/campaign_email_verification.php";

$campaign_id = isset($argv[1]) ? intval($argv[1]) : 0;
if ($campaign_id <= 0) {
    error_log("async_start_campaign: missing campaign_id");
    exit(1);
}

error_log("[async_start_campaign] Starting campaign #$campaign_id");

// Defensive: set unlimited time when running from CLI

set_time_limit(0);

try {
    if (!isset($conn) || !$conn) {
        throw new Exception('Database connection unavailable');
    }

        error_log("[async_start_campaign] Initializing queue for campaign #$campaign_id on Server 2");
        $queueStats = initializeEmailQueue($conn, $campaign_id, false);
        error_log("[async_start_campaign] Queue stats: " . json_encode($queueStats));

    $totalQueued = (int)(($queueStats['queued'] ?? 0) + ($queueStats['already_queued'] ?? 0));
    if ($totalQueued === 0) {
        error_log("[async_start_campaign] No emails queued for campaign #$campaign_id");
        exit(3);
    }

    // Get campaign owner from SERVER 1
    $campaignUserId = null;
    $campaignRow = $conn->query("SELECT user_id FROM email_id.campaign_master WHERE campaign_id = " . intval($campaign_id));
    if ($campaignRow && $campaignRow->num_rows > 0) {
        $campaignUserId = intval($campaignRow->fetch_assoc()['user_id']);
    }

    $total_valid = (int)($queueStats['total_recipients'] ?? $totalQueued);
    $pending = $totalQueued;
    $sent = 0;
    $failed = 0;

    $userSql = $campaignUserId ? $campaignUserId : 'NULL';
    
    // Update campaign_status on SERVER 1 (email_id database)
    $insertSql = "INSERT INTO email_id.campaign_status (campaign_id, total_emails, pending_emails, sent_emails, failed_emails, status, start_time, user_id) VALUES (" . intval($campaign_id) . ", $total_valid, $pending, $sent, $failed, 'running', NOW(), $userSql) ON DUPLICATE KEY UPDATE status='running', total_emails=VALUES(total_emails), pending_emails=VALUES(pending_emails), sent_emails=VALUES(sent_emails), failed_emails=VALUES(failed_emails), start_time=IFNULL(start_time, NOW()), end_time=NULL, user_id=VALUES(user_id)";
    $conn->query($insertSql);
    error_log("[async_start_campaign] ✓ Campaign #$campaign_id marked 'running' in Server 1 email_id.campaign_status");

    error_log("[async_start_campaign] Launching email blaster...");

    // Launch the parallel blaster script in background.
    // Robust PHP binary detection
    $php_candidates = [
        '/opt/plesk/php/8.1/bin/php',   // Plesk PHP 8.1
        '/usr/bin/php8.1',              // Standard PHP 8.1
        '/opt/lampp/bin/php',           // XAMPP/LAMPP
        '/usr/local/bin/php',
        '/usr/bin/php'
    ];
    
    $php = null;
    foreach ($php_candidates as $candidate) {
        if (file_exists($candidate) && is_executable($candidate)) {
            $php = $candidate;
            break;
        }
    }
    
    if (!$php) {
        if (defined('PHP_BINARY') && PHP_BINARY && file_exists(PHP_BINARY)) {
            $php = PHP_BINARY;
        } else {
            $php = trim(shell_exec('command -v php 2>/dev/null'));
            if (!$php) $php = 'php';
        }
    }

    $parallel = escapeshellarg(__DIR__ . "/../includes/email_blast_parallel.php");
    $cmd = "$php -f $parallel " . intval($campaign_id) . " > /dev/null 2>&1 &";
    error_log("[async_start_campaign] exec: $cmd");
    @exec($cmd, $out, $ret);
    if ($ret !== 0) {
        error_log("[async_start_campaign] Failed to launch email_blaster_parallel (exit $ret)");
        exit(4);
    }

    error_log("[async_start_campaign] Launched email_blaster for campaign #$campaign_id");
    exit(0);

} catch (Exception $e) {
    // error_log("[async_start_campaign] Exception: " . $e->getMessage()); // Disabled
    exit(5);
}
