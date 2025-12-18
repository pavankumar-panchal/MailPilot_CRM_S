<?php
error_reporting(0);
header('Content-Type: application/json');

date_default_timezone_set('Asia/Kolkata');
require_once __DIR__ . '/../config/db.php';

// Ensure table exists (idempotent)
$conn->query("CREATE TABLE IF NOT EXISTS worker_heartbeat (
    server_id INT NOT NULL,
    campaign_id INT NOT NULL,
    pid INT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'running',
    last_seen DATETIME NOT NULL,
    PRIMARY KEY (server_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$threshold = isset($_GET['threshold']) ? max(5, intval($_GET['threshold'])) : 30; // seconds

$res = $conn->query("SELECT server_id, campaign_id, pid, status, last_seen, 
    TIMESTAMPDIFF(SECOND, last_seen, NOW()) AS age_seconds
    FROM worker_heartbeat ORDER BY server_id ASC");
$rows = [];
$running = 0; $total = 0;
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $total++;
        $alive = ($r['status'] === 'running' && intval($r['age_seconds']) <= $threshold);
        if ($alive) $running++;
        $r['alive'] = $alive;
        $rows[] = $r;
    }
}

// Optionally include all known servers even if heartbeat missing
$serversRes = $conn->query("SELECT id AS server_id FROM smtp_servers WHERE is_active = 1 ORDER BY id ASC");
if ($serversRes) {
    $present = array_column($rows, null, 'server_id');
    while ($s = $serversRes->fetch_assoc()) {
        $sid = $s['server_id'];
        if (!isset($present[$sid])) {
            $rows[] = [
                'server_id' => intval($sid),
                'campaign_id' => null,
                'pid' => null,
                'status' => 'missing',
                'last_seen' => null,
                'age_seconds' => null,
                'alive' => false
            ];
        }
    }
}

usort($rows, function($a, $b){ return intval($a['server_id']) <=> intval($b['server_id']); });

echo json_encode([
    'now_ist' => date('Y-m-d H:i:s'),
    'threshold_seconds' => $threshold,
    'running' => $running,
    'expected' => count($rows),
    'workers' => $rows
]);

$conn->close();
