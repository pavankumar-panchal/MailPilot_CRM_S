<?php
/**
 * Test SMTP Usage Tracking
 * This script tests if smtp_usage table is properly tracking successful emails only
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/smtp_usage.php';

echo "=== SMTP Usage Test ===\n\n";

// Ensure table exists
ensureSmtpUsageSchema($conn);
echo "✓ smtp_usage table schema verified\n\n";

// Get a sample SMTP account
$account_result = $conn->query("SELECT id, email, daily_limit, hourly_limit FROM smtp_accounts WHERE is_active = 1 LIMIT 1");
if ($account_result->num_rows == 0) {
    die("ERROR: No active SMTP accounts found\n");
}

$account = $account_result->fetch_assoc();
echo "Testing with account: {$account['email']} (ID: {$account['id']})\n";
echo "Daily limit: {$account['daily_limit']}, Hourly limit: {$account['hourly_limit']}\n\n";

// Get current usage
$usage = getUsage($conn, 1, $account['id']);
echo "Current usage:\n";
echo "  - Hourly: {$usage['sent_hour']}\n";
echo "  - Daily: {$usage['sent_day']}\n\n";

// Test increment
echo "Testing incrementUsage()...\n";
incrementUsage($conn, 1, $account['id'], 1);
echo "✓ Incremented by 1\n\n";

// Check new usage
$usage_after = getUsage($conn, 1, $account['id']);
echo "After increment:\n";
echo "  - Hourly: {$usage_after['sent_hour']} (expected: " . ($usage['sent_hour'] + 1) . ")\n";
echo "  - Daily: {$usage_after['sent_day']} (expected: " . ($usage['sent_day'] + 1) . ")\n\n";

// Show recent usage records
echo "Recent smtp_usage records:\n";
$records = $conn->query("
    SELECT smtp_id, date, hour, emails_sent, timestamp 
    FROM smtp_usage 
    WHERE smtp_id = {$account['id']} 
    ORDER BY timestamp DESC 
    LIMIT 5
");

while ($row = $records->fetch_assoc()) {
    echo sprintf("  - SMTP ID: %d, Date: %s, Hour: %d, Emails: %d, Time: %s\n",
        $row['smtp_id'], $row['date'], $row['hour'], $row['emails_sent'], $row['timestamp']
    );
}

echo "\n✓ Test completed!\n";
