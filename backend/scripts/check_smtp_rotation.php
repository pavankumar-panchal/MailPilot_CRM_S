<?php
/**
 * SMTP Rotation Monitor
 * Shows current rotation state and SMTP usage statistics
 */

$conn = new mysqli('127.0.0.1', 'root', '', 'CRM', 3306);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "=== SMTP ROTATION STATUS ===" . PHP_EOL . PHP_EOL;

// Get rotation state
$rotation = $conn->query("SELECT * FROM smtp_rotation WHERE id = 1")->fetch_assoc();
if ($rotation) {
    echo "Current Position: SMTP Index [{$rotation['last_smtp_index']}]" . PHP_EOL;
    echo "Total SMTP Count: {$rotation['total_smtp_count']}" . PHP_EOL;
    echo "Last Updated: {$rotation['last_updated']}" . PHP_EOL;
    
    if ($rotation['last_smtp_id']) {
        $smtp = $conn->query("SELECT email FROM smtp_accounts WHERE id = {$rotation['last_smtp_id']}")->fetch_assoc();
        if ($smtp) {
            echo "Last Used SMTP: {$smtp['email']} (ID: {$rotation['last_smtp_id']})" . PHP_EOL;
        }
    }
    
    $next_index = ($rotation['last_smtp_index'] + 1) % $rotation['total_smtp_count'];
    echo "Next SMTP Index: [$next_index]" . PHP_EOL;
}

echo PHP_EOL . "=== SMTP USAGE TODAY ===" . PHP_EOL;

// Get today's usage per SMTP
$usage = $conn->query("
    SELECT 
        sa.id,
        sa.email,
        COUNT(mb.id) as emails_sent,
        sa.daily_limit,
        ROUND((COUNT(mb.id) / sa.daily_limit * 100), 2) as usage_percent
    FROM smtp_accounts sa
    LEFT JOIN mail_blaster mb ON mb.smtpid = sa.id 
        AND mb.delivery_date = CURDATE() 
        AND mb.status = 'success'
    WHERE sa.is_active = 1
    GROUP BY sa.id, sa.email, sa.daily_limit
    ORDER BY emails_sent DESC
    LIMIT 20
");

if ($usage->num_rows > 0) {
    printf("%-5s %-40s %-12s %-12s %-10s\n", "ID", "Email", "Sent Today", "Daily Limit", "Usage %");
    echo str_repeat("-", 85) . PHP_EOL;
    
    while ($row = $usage->fetch_assoc()) {
        printf(
            "%-5s %-40s %-12s %-12s %-10s\n",
            $row['id'],
            $row['email'],
            $row['emails_sent'],
            $row['daily_limit'],
            $row['usage_percent'] . '%'
        );
    }
} else {
    echo "No emails sent today yet." . PHP_EOL;
}

echo PHP_EOL . "=== ROTATION CYCLE INFO ===" . PHP_EOL;
$total_active = $conn->query("
    SELECT COUNT(*) as cnt 
    FROM smtp_accounts sa 
    JOIN smtp_servers ss ON sa.smtp_server_id = ss.id 
    WHERE sa.is_active = 1 AND ss.is_active = 1
")->fetch_assoc()['cnt'];

echo "Total Active SMTPs: $total_active" . PHP_EOL;
echo "Emails per complete cycle: $total_active" . PHP_EOL;
echo "Pattern: SMTP[0] → SMTP[1] → ... → SMTP[" . ($total_active - 1) . "] → SMTP[0] (repeat)" . PHP_EOL;

$conn->close();
