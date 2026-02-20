<?php
/**
 * Force Start Workers - Manually trigger worker launch for debugging
 */

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");

require_once __DIR__ . '/../config/db.php';

$campaign_id = isset($_GET['campaign_id']) ? intval($_GET['campaign_id']) : 0;

if ($campaign_id <= 0) {
    echo json_encode(['error' => 'campaign_id required']);
    exit;
}

// Get campaign details
$campaign = $conn->query("
    SELECT cs.*, cm.user_id, cm.mail_subject
    FROM campaign_status cs
    JOIN campaign_master cm ON cm.campaign_id = cs.campaign_id
    WHERE cs.campaign_id = $campaign_id
")->fetch_assoc();

if (!$campaign) {
    echo json_encode(['error' => 'Campaign not found']);
    exit;
}

// Find PHP CLI
$php_cli_candidates = [
    '/opt/plesk/php/8.1/bin/php',
    '/usr/bin/php8.1',
    '/usr/local/bin/php',
    '/usr/bin/php'
];

$php_cli = null;
foreach ($php_cli_candidates as $candidate) {
    if (file_exists($candidate) && is_executable($candidate)) {
        $php_cli = $candidate;
        break;
    }
}

if (!$php_cli) {
    $php_cli = trim(shell_exec('command -v php 2>/dev/null')) ?: 'php';
}

$script = __DIR__ . '/../includes/email_blast_parallel.php';

if (!file_exists($script)) {
    echo json_encode(['error' => 'Worker script not found', 'path' => $script]);
    exit;
}

// Launch worker - redirect to /dev/null (no log files)
$cmd = sprintf(
    '%s %s %d > /dev/null 2>&1 & echo $!',
    escapeshellarg($php_cli),
    escapeshellarg($script),
    $campaign_id
);

exec($cmd, $output, $ret);
$pid = isset($output[0]) ? intval($output[0]) : 0;

// Update campaign status
$conn->query("UPDATE campaign_status SET process_pid = $pid, status = 'running' WHERE campaign_id = $campaign_id");

echo json_encode([
    'success' => true,
    'campaign_id' => $campaign_id,
    'mail_subject' => $campaign['mail_subject'],
    'pid' => $pid,
    'php_cli' => $php_cli,
    'command' => $cmd,
    'message' => "Workers launched (no log files created)."
]);

$conn->close();
