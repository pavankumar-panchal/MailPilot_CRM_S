<?php
/**
 * SMTP Status and Health Monitor
 * Shows which SMTP accounts are working and their current usage
 */

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");

require_once __DIR__ . '/../config/db.php';

$today = date('Y-m-d');
$current_hour = intval(date('G'));

// Get all SMTP accounts with their status
$query = "
    SELECT 
        sa.id,
        sa.email,
        sa.smtp_server_id,
        ss.name as server_name,
        ss.host,
        ss.port,
        ss.user_id,
        sa.is_active,
        sa.daily_limit,
        sa.hourly_limit,
        COALESCE(daily_usage.sent_today, 0) as sent_today,
        COALESCE(hourly_usage.emails_sent, 0) as sent_this_hour,
        CASE 
            WHEN sa.is_active = 0 THEN 'inactive'
            WHEN sa.daily_limit > 0 AND COALESCE(daily_usage.sent_today, 0) >= sa.daily_limit THEN 'daily_limit'
            WHEN sa.hourly_limit > 0 AND COALESCE(hourly_usage.emails_sent, 0) >= sa.hourly_limit THEN 'hourly_limit'
            ELSE 'available'
        END as status,
        (SELECT COUNT(*) 
         FROM mail_blaster mb 
         WHERE mb.smtpid = sa.id 
         AND mb.delivery_time >= DATE_SUB(NOW(), INTERVAL 60 SECOND)
         AND mb.status IN ('processing', 'success')
        ) as recent_activity,
        (SELECT COUNT(*) 
         FROM mail_blaster mb 
         WHERE mb.smtpid = sa.id 
         AND mb.delivery_date = CURDATE()
         AND mb.status = 'success'
        ) as sent_today_count,
        (SELECT COUNT(*) 
         FROM mail_blaster mb 
         WHERE mb.smtpid = sa.id 
         AND mb.delivery_date = CURDATE()
         AND mb.status = 'failed'
        ) as failed_today_count
    FROM smtp_accounts sa
    JOIN smtp_servers ss ON ss.id = sa.smtp_server_id
    LEFT JOIN (
        SELECT smtp_id, SUM(emails_sent) as sent_today
        FROM smtp_usage
        WHERE date = '$today'
        GROUP BY smtp_id
    ) daily_usage ON daily_usage.smtp_id = sa.id
    LEFT JOIN smtp_usage hourly_usage ON hourly_usage.smtp_id = sa.id 
        AND hourly_usage.date = '$today' AND hourly_usage.hour = $current_hour
    WHERE ss.is_active = 1
    ORDER BY ss.user_id, ss.id, sa.id
";

$result = $conn->query($query);
$smtp_accounts = [];
$summary = [
    'total' => 0,
    'available' => 0,
    'at_daily_limit' => 0,
    'at_hourly_limit' => 0,
    'inactive' => 0,
    'working_now' => 0
];

while ($row = $result->fetch_assoc()) {
    $summary['total']++;
    
    switch ($row['status']) {
        case 'available':
            $summary['available']++;
            break;
        case 'daily_limit':
            $summary['at_daily_limit']++;
            break;
        case 'hourly_limit':
            $summary['at_hourly_limit']++;
            break;
        case 'inactive':
            $summary['inactive']++;
            break;
    }
    
    if ($row['recent_activity'] > 0) {
        $summary['working_now']++;
    }
    
    $daily_remaining = $row['daily_limit'] > 0 
        ? max(0, $row['daily_limit'] - $row['sent_today']) 
        : 999999;
    
    $hourly_remaining = $row['hourly_limit'] > 0 
        ? max(0, $row['hourly_limit'] - $row['sent_this_hour']) 
        : 999999;
    
    $smtp_accounts[] = [
        'id' => intval($row['id']),
        'email' => $row['email'],
        'server_name' => $row['server_name'],
        'host' => $row['host'],
        'port' => intval($row['port']),
        'user_id' => intval($row['user_id']),
        'status' => $row['status'],
        'is_active' => intval($row['is_active']) === 1,
        'limits' => [
            'daily_limit' => intval($row['daily_limit']),
            'hourly_limit' => intval($row['hourly_limit']),
            'daily_remaining' => $daily_remaining,
            'hourly_remaining' => $hourly_remaining
        ],
        'usage' => [
            'sent_today' => intval($row['sent_today_count']),
            'failed_today' => intval($row['failed_today_count']),
            'recent_activity' => intval($row['recent_activity']) > 0
        ]
    ];
}

// Get campaigns using each SMTP
$campaign_usage = $conn->query("
    SELECT 
        mb.smtpid,
        mb.campaign_id,
        cm.mail_subject,
        COUNT(*) as emails_processing
    FROM mail_blaster mb
    JOIN campaign_master cm ON cm.campaign_id = mb.campaign_id
    WHERE mb.status IN ('processing', 'pending')
    AND mb.delivery_time >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    GROUP BY mb.smtpid, mb.campaign_id
")->fetch_all(MYSQLI_ASSOC);

echo json_encode([
    'summary' => $summary,
    'smtp_accounts' => $smtp_accounts,
    'campaign_usage' => $campaign_usage,
    'timestamp' => date('Y-m-d H:i:s')
]);

$conn->close();
