<?php
/**
 * Worker Diagnostics - Debug why workers aren't starting
 */

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");

require_once __DIR__ . '/../config/db.php';

$diagnostics = [];

// Check running campaigns
$campaigns = $conn->query("
    SELECT 
        cs.*,
        cm.user_id,
        cm.mail_subject
    FROM campaign_status cs
    JOIN campaign_master cm ON cm.campaign_id = cs.campaign_id
    WHERE cs.status = 'running'
")->fetch_all(MYSQLI_ASSOC);

$diagnostics['campaigns'] = $campaigns;

// Check PID files
$pid_dir = __DIR__ . '/../tmp';
$pid_files = [];
if (is_dir($pid_dir)) {
    foreach (glob($pid_dir . '/email_blaster_*.pid') as $pid_file) {
        $pid = intval(file_get_contents($pid_file));
        $is_running = file_exists('/proc/' . $pid);
        $pid_files[] = [
            'file' => basename($pid_file),
            'pid' => $pid,
            'running' => $is_running
        ];
    }
}
$diagnostics['pid_files'] = $pid_files;

// Check for workers in last 5 minutes
$diagnostics['recent_workers'] = $conn->query("
    SELECT 
        DISTINCT smtpid,
        campaign_id,
        COUNT(*) as emails,
        MAX(delivery_time) as last_activity
    FROM mail_blaster
    WHERE delivery_time >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    GROUP BY smtpid, campaign_id
")->fetch_all(MYSQLI_ASSOC);

// Check SMTP availability for each campaign
foreach ($campaigns as $campaign) {
    $campaign_id = $campaign['campaign_id'];
    $user_id = $campaign['user_id'];
    
    $available_smtp = $conn->query("
        SELECT COUNT(*) as cnt
        FROM smtp_accounts sa
        JOIN smtp_servers ss ON sa.smtp_server_id = ss.id
        WHERE sa.is_active = 1
        AND ss.is_active = 1
        AND sa.user_id = $user_id
    ")->fetch_assoc();
    
    $diagnostics['campaign_smtp'][$campaign_id] = [
        'user_id' => $user_id,
        'available_smtp' => intval($available_smtp['cnt'])
    ];
}

// Check mail_blaster queue
$diagnostics['queue_status'] = [];
foreach ($campaigns as $campaign) {
    $campaign_id = $campaign['campaign_id'];
    
    $queue = $conn->query("
        SELECT 
            status,
            COUNT(*) as cnt
        FROM mail_blaster
        WHERE campaign_id = $campaign_id
        GROUP BY status
    ")->fetch_all(MYSQLI_ASSOC);
    
    $diagnostics['queue_status'][$campaign_id] = $queue;
}

// Check if email_blast_parallel.php exists
$parallel_script = __DIR__ . '/../includes/email_blast_parallel.php';
$diagnostics['scripts'] = [
    'parallel_script' => [
        'path' => $parallel_script,
        'exists' => file_exists($parallel_script),
        'readable' => is_readable($parallel_script)
    ],
    'worker_script' => [
        'path' => __DIR__ . '/../includes/email_blast_worker.php',
        'exists' => file_exists(__DIR__ . '/../includes/email_blast_worker.php'),
        'readable' => is_readable(__DIR__ . '/../includes/email_blast_worker.php')
    ]
];

// Check PHP CLI
$php_candidates = [
    '/opt/plesk/php/8.1/bin/php',
    '/usr/bin/php8.1',
    '/usr/local/bin/php',
    '/usr/bin/php'
];

$diagnostics['php_cli'] = [];
foreach ($php_candidates as $php) {
    $diagnostics['php_cli'][$php] = [
        'exists' => file_exists($php),
        'executable' => is_executable($php)
    ];
}

echo json_encode($diagnostics, JSON_PRETTY_PRINT);
$conn->close();
