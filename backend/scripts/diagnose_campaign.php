<?php
// Diagnostic script to check why campaigns aren't sending
// Usage: php diagnose_campaign.php <campaign_id>

require_once __DIR__ . '/../config/db.php';

$campaign_id = isset($argv[1]) ? intval($argv[1]) : 0;

if ($campaign_id <= 0) {
    die("Usage: php diagnose_campaign.php <campaign_id>\n");
}

echo "=== CAMPAIGN DIAGNOSTIC FOR #$campaign_id ===\n\n";

// 1. Check campaign exists
echo "1. Checking campaign exists...\n";
$campaign = $conn->query("SELECT * FROM campaign_master WHERE campaign_id = $campaign_id")->fetch_assoc();
if (!$campaign) {
    die("ERROR: Campaign #$campaign_id not found!\n");
}
echo "   ✓ Campaign exists\n";
echo "   User ID: " . ($campaign['user_id'] ?? 'NULL') . "\n";
echo "   CSV List ID: " . ($campaign['csv_list_id'] ?? 'NULL') . "\n";
echo "   Import Batch: " . ($campaign['import_batch_id'] ?? 'NULL') . "\n\n";

// 2. Check campaign status
echo "2. Checking campaign_status...\n";
$status = $conn->query("SELECT * FROM campaign_status WHERE campaign_id = $campaign_id")->fetch_assoc();
if ($status) {
    echo "   Status: {$status['status']}\n";
    echo "   Total: {$status['total_emails']}\n";
    echo "   Sent: {$status['sent_emails']}\n";
    echo "   Failed: {$status['failed_emails']}\n";
    echo "   Pending: {$status['pending_emails']}\n";
    echo "   PID: " . ($status['process_pid'] ?? 'NULL') . "\n\n";
} else {
    echo "   WARNING: No campaign_status record!\n\n";
}

// 3. Check mail_blaster queue
echo "3. Checking mail_blaster queue...\n";
$queued = $conn->query("SELECT COUNT(*) as cnt FROM mail_blaster WHERE campaign_id = $campaign_id")->fetch_assoc()['cnt'];
$pending = $conn->query("SELECT COUNT(*) as cnt FROM mail_blaster WHERE campaign_id = $campaign_id AND status = 'pending'")->fetch_assoc()['cnt'];
$success = $conn->query("SELECT COUNT(*) as cnt FROM mail_blaster WHERE campaign_id = $campaign_id AND status = 'success'")->fetch_assoc()['cnt'];
$failed = $conn->query("SELECT COUNT(*) as cnt FROM mail_blaster WHERE campaign_id = $campaign_id AND status = 'failed'")->fetch_assoc()['cnt'];

echo "   Total queued: $queued\n";
echo "   Pending: $pending\n";
echo "   Success: $success\n";
echo "   Failed: $failed\n\n";

// 4. Check SMTP accounts for this user
echo "4. Checking SMTP accounts...\n";
$user_id = $campaign['user_id'] ?? null;
if ($user_id) {
    $smtp_query = "SELECT sa.id, sa.email, sa.is_active, sa.daily_limit, sa.hourly_limit, sa.sent_today, ss.name as server_name
                   FROM smtp_accounts sa
                   JOIN smtp_servers ss ON sa.smtp_server_id = ss.id
                   WHERE sa.user_id = $user_id AND sa.is_active = 1 AND ss.is_active = 1";
    $smtp_result = $conn->query($smtp_query);
    
    if ($smtp_result && $smtp_result->num_rows > 0) {
        echo "   Found " . $smtp_result->num_rows . " active SMTP accounts:\n";
        while ($smtp = $smtp_result->fetch_assoc()) {
            $daily_remaining = $smtp['daily_limit'] > 0 ? ($smtp['daily_limit'] - $smtp['sent_today']) : 'unlimited';
            echo "     - #{$smtp['id']}: {$smtp['email']} (Server: {$smtp['server_name']})\n";
            echo "       Daily: {$smtp['sent_today']}/{$smtp['daily_limit']} (Remaining: $daily_remaining)\n";
        }
    } else {
        echo "   ❌ ERROR: NO ACTIVE SMTP ACCOUNTS FOUND FOR USER #$user_id!\n";
        echo "   This is why emails aren't sending!\n";
    }
} else {
    echo "   WARNING: Campaign has no user_id!\n";
}
echo "\n";

// 5. Check if workers are running
echo "5. Checking for running processes...\n";
$pid_file = __DIR__ . "/../tmp/email_blaster_{$campaign_id}.pid";
if (file_exists($pid_file)) {
    $pid = trim(file_get_contents($pid_file));
    echo "   PID file exists: $pid\n";
    
    // Check if process is running
    if (file_exists("/proc/$pid")) {
        echo "   ✓ Process is running\n";
    } else {
        echo "   ❌ Process NOT running (stale PID file)\n";
    }
} else {
    echo "   No PID file found\n";
}
echo "\n";

// 6. Check PHP binary
echo "6. Checking PHP binary...\n";
$php_candidates = [
    '/opt/plesk/php/8.1/bin/php',
    '/usr/bin/php8.1',
    '/usr/bin/php',
    '/opt/lampp/bin/php'
];

foreach ($php_candidates as $php) {
    if (file_exists($php) && is_executable($php)) {
        echo "   ✓ Found: $php\n";
        $version = shell_exec("$php -v | head -1");
        echo "     Version: " . trim($version) . "\n";
        break;
    }
}
echo "\n";

// 7. Sample a few emails from queue
echo "7. Sample emails from queue (first 3)...\n";
$sample = $conn->query("SELECT to_mail, status, attempt_count, delivery_time FROM mail_blaster WHERE campaign_id = $campaign_id LIMIT 3");
if ($sample && $sample->num_rows > 0) {
    while ($row = $sample->fetch_assoc()) {
        echo "   - {$row['to_mail']}: {$row['status']} (attempts: {$row['attempt_count']})\n";
    }
} else {
    echo "   No emails in queue\n";
}
echo "\n";

echo "=== DIAGNOSTIC COMPLETE ===\n";
