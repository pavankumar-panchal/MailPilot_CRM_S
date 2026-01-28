<?php
/**
 * Test script to manually trigger campaign email sending
 * This simulates what happens when campaign_cron.php detects a running campaign
 */

echo "==============================================\n";
echo "CAMPAIGN EMAIL SENDING TEST\n";
echo "==============================================\n\n";

require_once __DIR__ . '/backend/config/db.php';

// Get campaign ID from command line or use default
$campaign_id = isset($argv[1]) ? intval($argv[1]) : 3;

echo "Testing campaign ID: $campaign_id\n\n";

// Check campaign exists and get details
$result = $conn->query("
    SELECT 
        cm.campaign_id,
        cm.description,
        cm.mail_subject,
        cm.user_id,
        cm.csv_list_id,
        cs.status,
        cs.total_emails,
        cs.sent_emails,
        cs.pending_emails
    FROM campaign_master cm
    LEFT JOIN campaign_status cs ON cm.campaign_id = cs.campaign_id
    WHERE cm.campaign_id = $campaign_id
");

if (!$result || $result->num_rows == 0) {
    die("❌ Campaign #$campaign_id not found!\n");
}

$campaign = $result->fetch_assoc();
echo "✅ Campaign found:\n";
echo "   - Description: {$campaign['description']}\n";
echo "   - Subject: {$campaign['mail_subject']}\n";
echo "   - User ID: {$campaign['user_id']}\n";
echo "   - CSV List ID: {$campaign['csv_list_id']}\n";
echo "   - Status: {$campaign['status']}\n";
echo "   - Total: {$campaign['total_emails']}\n";
echo "   - Sent: {$campaign['sent_emails']}\n";
echo "   - Pending: {$campaign['pending_emails']}\n\n";

// Check user has SMTP accounts
$user_id = $campaign['user_id'];
$smtpCheck = $conn->query("
    SELECT COUNT(*) as count
    FROM smtp_accounts sa
    JOIN smtp_servers ss ON sa.smtp_server_id = ss.id
    WHERE sa.user_id = $user_id
    AND sa.is_active = 1
    AND ss.is_active = 1
");

$smtpCount = $smtpCheck->fetch_assoc()['count'];
echo "SMTP Accounts for user #$user_id: $smtpCount\n";

if ($smtpCount == 0) {
    die("❌ No active SMTP accounts found for user #$user_id!\n");
}

echo "✅ User has $smtpCount active SMTP account(s)\n\n";

// Check valid emails in CSV list
$csv_list_id = $campaign['csv_list_id'];
$emailCheck = $conn->query("
    SELECT COUNT(*) as count
    FROM emails
    WHERE csv_list_id = $csv_list_id
    AND domain_status = 1
    AND validation_status = 'valid'
");

$emailCount = $emailCheck->fetch_assoc()['count'];
echo "Valid emails in CSV list #$csv_list_id: $emailCount\n\n";

if ($emailCount == 0) {
    die("❌ No valid emails found in CSV list #$csv_list_id!\n");
}

// Set campaign to running if not already
if ($campaign['status'] != 'running') {
    echo "Setting campaign status to 'running'...\n";
    $conn->query("UPDATE campaign_status SET status = 'running', start_time = NOW() WHERE campaign_id = $campaign_id");
    echo "✅ Campaign status updated to 'running'\n\n";
}

// Launch the orchestrator process
echo "==============================================\n";
echo "LAUNCHING EMAIL ORCHESTRATOR\n";
echo "==============================================\n\n";

$script_path = __DIR__ . '/backend/includes/email_blast_parallel.php';
$php_path = '/opt/lampp/bin/php';

echo "PHP: $php_path\n";
echo "Script: $script_path\n";
echo "Campaign ID: $campaign_id\n\n";

// Run the orchestrator in foreground for testing (so we can see output)
echo "Starting orchestrator...\n\n";
$cmd = "$php_path $script_path $campaign_id 2>&1";
echo "Command: $cmd\n\n";

passthru($cmd);

echo "\n\n==============================================\n";
echo "TEST COMPLETE\n";
echo "==============================================\n";
