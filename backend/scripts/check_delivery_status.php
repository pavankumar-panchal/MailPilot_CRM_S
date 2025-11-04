<?php
require __DIR__ . '/../config/db.php';

$email = 'pavankumar.c@relyonsoft.com';

echo "==============================================\n";
echo "Delivery Status for: $email\n";
echo "==============================================\n\n";

// Check recent deliveries
$result = $db->query("
    SELECT mb.campaign_id, mb.status, mb.error_message, 
           mb.delivery_date, mb.delivery_time, mb.smtpid,
           sa.email as smtp_email
    FROM mail_blaster mb
    LEFT JOIN smtp_accounts sa ON mb.smtpid = sa.id
    WHERE mb.to_mail = '$email'
    ORDER BY mb.delivery_date DESC, mb.delivery_time DESC
    LIMIT 10
");

echo "Recent 10 delivery attempts:\n";
echo "-------------------------------------------\n";

while ($row = $result->fetch_assoc()) {
    echo sprintf(
        "Campaign: %2d | Status: %-7s | %s %s | SMTP: %s (ID: %d)\n",
        $row['campaign_id'],
        strtoupper($row['status']),
        $row['delivery_date'],
        $row['delivery_time'],
        $row['smtp_email'] ?? 'Unknown',
        $row['smtpid']
    );
    
    if ($row['error_message']) {
        echo "    Error: {$row['error_message']}\n";
    }
}

// Count success vs failed
$stats = $db->query("
    SELECT 
        SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
        COUNT(*) as total
    FROM mail_blaster
    WHERE to_mail = '$email'
")->fetch_assoc();

echo "\n-------------------------------------------\n";
echo "Summary:\n";
echo "  Total attempts: {$stats['total']}\n";
echo "  Successful: {$stats['successful']}\n";
echo "  Failed: {$stats['failed']}\n";
echo "==============================================\n";
?>
