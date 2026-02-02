<?php
/**
 * Campaign Performance Monitor
 * 
 * Shows real-time campaign sending statistics and performance metrics
 */

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");

require_once __DIR__ . '/../config/db.php';

$campaign_id = isset($_GET['campaign_id']) ? intval($_GET['campaign_id']) : 0;

if ($campaign_id > 0) {
    // Get specific campaign performance
    $campaign = $conn->query("
        SELECT 
            cs.*,
            cm.mail_subject,
            cm.user_id,
            TIMESTAMPDIFF(SECOND, cs.start_time, NOW()) as elapsed_seconds,
            CASE 
                WHEN cs.sent_emails > 0 AND TIMESTAMPDIFF(SECOND, cs.start_time, NOW()) > 0
                THEN cs.sent_emails / TIMESTAMPDIFF(SECOND, cs.start_time, NOW())
                ELSE 0
            END as emails_per_second
        FROM campaign_status cs
        JOIN campaign_master cm ON cm.campaign_id = cs.campaign_id
        WHERE cs.campaign_id = $campaign_id
    ")->fetch_assoc();
    
    if (!$campaign) {
        echo json_encode(['error' => 'Campaign not found']);
        exit;
    }
    
    // Get active workers count
    $workers = $conn->query("
        SELECT COUNT(DISTINCT smtpid) as worker_count
        FROM mail_blaster
        WHERE campaign_id = $campaign_id
        AND status = 'processing'
        AND delivery_time >= DATE_SUB(NOW(), INTERVAL 60 SECOND)
    ")->fetch_assoc();
    
    // Get SMTP account usage
    $smtp_usage = $conn->query("
        SELECT 
            sa.id,
            sa.email,
            COUNT(*) as emails_sent_now,
            sa.daily_limit,
            sa.hourly_limit
        FROM mail_blaster mb
        JOIN smtp_accounts sa ON sa.id = mb.smtpid
        WHERE mb.campaign_id = $campaign_id
        AND mb.delivery_date = CURDATE()
        GROUP BY sa.id
        ORDER BY emails_sent_now DESC
        LIMIT 10
    ")->fetch_all(MYSQLI_ASSOC);
    
    // Calculate estimated completion time
    $emails_per_second = floatval($campaign['emails_per_second']);
    $pending = intval($campaign['pending_emails']);
    $eta_seconds = $emails_per_second > 0 ? ($pending / $emails_per_second) : 0;
    
    echo json_encode([
        'campaign_id' => $campaign_id,
        'status' => $campaign['status'],
        'mail_subject' => $campaign['mail_subject'],
        'progress' => [
            'total' => intval($campaign['total_emails']),
            'sent' => intval($campaign['sent_emails']),
            'failed' => intval($campaign['failed_emails']),
            'pending' => $pending,
            'percentage' => $campaign['total_emails'] > 0 ? 
                round((intval($campaign['sent_emails']) / intval($campaign['total_emails'])) * 100, 2) : 0
        ],
        'performance' => [
            'elapsed_seconds' => intval($campaign['elapsed_seconds']),
            'emails_per_second' => round($emails_per_second, 2),
            'emails_per_minute' => round($emails_per_second * 60, 2),
            'emails_per_hour' => round($emails_per_second * 3600, 2),
            'active_workers' => intval($workers['worker_count']),
            'eta_seconds' => round($eta_seconds),
            'eta_formatted' => gmdate('H:i:s', $eta_seconds)
        ],
        'smtp_usage' => $smtp_usage,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} else {
    // Get all active campaigns summary
    $campaigns = $conn->query("
        SELECT 
            cs.campaign_id,
            cm.mail_subject,
            cs.status,
            cs.total_emails,
            cs.sent_emails,
            cs.pending_emails,
            TIMESTAMPDIFF(SECOND, cs.start_time, NOW()) as elapsed_seconds,
            CASE 
                WHEN cs.sent_emails > 0 AND TIMESTAMPDIFF(SECOND, cs.start_time, NOW()) > 0
                THEN cs.sent_emails / TIMESTAMPDIFF(SECOND, cs.start_time, NOW())
                ELSE 0
            END as emails_per_second,
            (SELECT COUNT(DISTINCT smtpid) 
             FROM mail_blaster mb 
             WHERE mb.campaign_id = cs.campaign_id 
             AND mb.status = 'processing'
             AND mb.delivery_time >= DATE_SUB(NOW(), INTERVAL 60 SECOND)
            ) as active_workers
        FROM campaign_status cs
        JOIN campaign_master cm ON cm.campaign_id = cs.campaign_id
        WHERE cs.status IN ('running', 'pending')
        ORDER BY cs.campaign_id DESC
    ")->fetch_all(MYSQLI_ASSOC);
    
    // Overall system stats
    $system_stats = $conn->query("
        SELECT 
            COUNT(DISTINCT cs.campaign_id) as active_campaigns,
            SUM(cs.sent_emails) as total_sent_today,
            SUM(cs.pending_emails) as total_pending,
            (SELECT COUNT(*) FROM smtp_accounts WHERE is_active = 1) as total_smtp_accounts,
            (SELECT COUNT(*) FROM smtp_servers WHERE is_active = 1) as total_smtp_servers
        FROM campaign_status cs
        WHERE cs.status IN ('running', 'pending')
    ")->fetch_assoc();
    
    echo json_encode([
        'system_stats' => $system_stats,
        'campaigns' => array_map(function($c) {
            return [
                'campaign_id' => intval($c['campaign_id']),
                'mail_subject' => $c['mail_subject'],
                'status' => $c['status'],
                'progress' => [
                    'total' => intval($c['total_emails']),
                    'sent' => intval($c['sent_emails']),
                    'pending' => intval($c['pending_emails']),
                    'percentage' => $c['total_emails'] > 0 ? 
                        round((intval($c['sent_emails']) / intval($c['total_emails'])) * 100, 2) : 0
                ],
                'performance' => [
                    'emails_per_second' => round(floatval($c['emails_per_second']), 2),
                    'emails_per_minute' => round(floatval($c['emails_per_second']) * 60, 2),
                    'active_workers' => intval($c['active_workers'])
                ]
            ];
        }, $campaigns),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

$conn->close();
