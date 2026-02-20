<?php
/**
 * Reset Daily SMTP Account Counters
 * 
 * This script should be run daily at midnight via cron:
 * 0 0 * * * /opt/lampp/bin/php /opt/lampp/htdocs/verify_emails/MailPilot_CRM/backend/scripts/reset_daily_counters.php
 * 
 * Or add to your crontab:
 * crontab -e
 * Then add: 0 0 * * * /opt/lampp/bin/php /path/to/reset_daily_counters.php
 */

require_once __DIR__ . '/../includes/ProcessManager.php';
require_once __DIR__ . '/../config/db.php';

// Acquire lock - exit if already running
$lock = new ProcessManager('reset_daily_counters', 600); // 10 minute timeout
if (!$lock->acquire()) {
    exit(0);
}

echo "===== Daily Counter Reset Script =====\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// Get current counts before reset
$stats = $conn->query("
    SELECT 
        COUNT(*) as total_accounts,
        SUM(sent_today) as total_sent_today,
        MAX(sent_today) as max_sent_today,
        AVG(sent_today) as avg_sent_today
    FROM smtp_accounts
    WHERE is_active = 1
")->fetch_assoc();

echo "Current Statistics:\n";
echo "  Total Active Accounts: {$stats['total_accounts']}\n";
echo "  Total Sent Today: {$stats['total_sent_today']}\n";
echo "  Max Sent by One Account: {$stats['max_sent_today']}\n";
echo "  Average Sent per Account: " . round($stats['avg_sent_today'], 2) . "\n\n";

// Show top senders
echo "Top 10 Senders Today:\n";
$top_senders = $conn->query("
    SELECT sa.email, sa.sent_today, sa.daily_limit,
           CONCAT(ROUND((sa.sent_today / NULLIF(sa.daily_limit, 0)) * 100, 1), '%') as usage_percent
    FROM smtp_accounts sa
    WHERE sa.is_active = 1 AND sa.sent_today > 0
    ORDER BY sa.sent_today DESC
    LIMIT 10
");

while ($sender = $top_senders->fetch_assoc()) {
    $limit_info = ($sender['daily_limit'] > 0) ? " / {$sender['daily_limit']} ({$sender['usage_percent']})" : " / unlimited";
    echo "  {$sender['email']}: {$sender['sent_today']}{$limit_info}\n";
}

// Reset all counters
echo "\nResetting daily counters...\n";
$result = $conn->query("UPDATE smtp_accounts SET sent_today = 0 WHERE sent_today > 0");

if ($result) {
    $affected = $conn->affected_rows;
    echo "✅ Successfully reset {$affected} accounts\n";
    
    // Log the reset
    $log_message = date('Y-m-d H:i:s') . " - Daily counters reset. Total sent today: {$stats['total_sent_today']}\n";
    // file_put_contents(__DIR__ . '/../storage/daily_reset.log', $log_message, FILE_APPEND); // Disabled
    
    echo "\nReset completed successfully!\n";
} else {
    echo "❌ Error resetting counters: " . $conn->error . "\n";
    exit(1);
}

// Verify reset
$verify = $conn->query("SELECT COUNT(*) as cnt FROM smtp_accounts WHERE sent_today > 0")->fetch_assoc();
if ($verify['cnt'] == 0) {
    echo "✅ Verification passed: All counters are now 0\n";
} else {
    echo "⚠️ Warning: {$verify['cnt']} accounts still have non-zero counters\n";
}

$conn->close();
$lock->release();

echo "\n===== Reset Complete =====\n";
?>
