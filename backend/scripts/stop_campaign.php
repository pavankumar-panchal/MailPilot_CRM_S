#!/usr/bin/env php
<?php
// Minimal CLI tool to force-stop a running email campaign.
// Usage:
//   php backend/scripts/stop_campaign.php <campaign_id>
//
// Actions:
// - Mark campaign status as 'stopped' in DB
// - Kill orchestrator using PID file backend/tmp/email_blaster_<id>.pid
// - Kill all workers for the campaign from worker_heartbeat PIDs

declare(strict_types=1);

date_default_timezone_set('Asia/Kolkata');

$root = dirname(__DIR__);
$pidDir = $root . DIRECTORY_SEPARATOR . 'tmp';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from CLI.\n");
    exit(1);
}

$campaignId = $argv[1] ?? null;
if (!$campaignId || !ctype_digit((string)$campaignId)) {
    fwrite(STDERR, "Usage: php backend/scripts/stop_campaign.php <campaign_id>\n");
    exit(1);
}
$campaignId = (int)$campaignId;

require_once $root . '/config/db.php';

function db_now(mysqli $conn): string {
    $res = $conn->query("SELECT DATE_FORMAT(CONVERT_TZ(NOW(), @@session.time_zone, '+05:30'), '%Y-%m-%d %H:%i:%s') AS now_ist");
    if ($res && ($row = $res->fetch_assoc())) {
        return $row['now_ist'];
    }
    return date('Y-m-d H:i:s');
}

$exitCode = 0;
$messages = [];

// Ensure heartbeat table exists (best-effort)
$conn->query("CREATE TABLE IF NOT EXISTS worker_heartbeat (
  id INT AUTO_INCREMENT PRIMARY KEY,
  campaign_id INT NOT NULL,
  server_id INT NULL,
  pid INT NULL,
  status VARCHAR(32) DEFAULT 'running',
  last_seen DATETIME NULL,
  KEY idx_campaign (campaign_id),
  KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 1) Mark campaign as stopped in status table if exists
$conn->query("UPDATE campaign_status SET status='stopped', updated_at='" . $conn->real_escape_string(db_now($conn)) . "' WHERE campaign_id=" . $campaignId);
if ($conn->affected_rows >= 0) {
    $messages[] = "campaign_status updated to 'stopped' (if present)";
}
// Also try campaign_master (best-effort)
$conn->query("UPDATE campaign_master SET status='stopped', updated_at='" . $conn->real_escape_string(db_now($conn)) . "' WHERE id=" . $campaignId);
if ($conn->affected_rows >= 0) {
    $messages[] = "campaign_master updated to 'stopped' (if present)";
}

// 2) Kill orchestrator via PID file
$pidFile = $pidDir . DIRECTORY_SEPARATOR . "email_blaster_{$campaignId}.pid";
if (is_file($pidFile)) {
    $pid = (int)trim((string)@file_get_contents($pidFile));
    if ($pid > 0) {
        // Try TERM then KILL
        @posix_kill($pid, SIGTERM);
        usleep(300000); // 300ms grace
        if (file_exists("/proc/{$pid}")) {
            @posix_kill($pid, SIGKILL);
        }
        $messages[] = "Killed orchestrator PID {$pid}";
    }
    @unlink($pidFile);
    $messages[] = "Removed PID file {$pidFile}";
} else {
    // Fallback: best-effort pkill by cmdline pattern
    @exec("pkill -f 'email_blast_parallel.php {$campaignId}'", $out, $code);
    if ($code === 0) {
        $messages[] = "Killed orchestrator by pattern";
    } else {
        $messages[] = "Orchestrator PID file not found; pattern kill may not match";
    }
}

// 3) Kill workers via heartbeat PIDs
$killed = 0;
$res = $conn->query("SELECT id, pid FROM worker_heartbeat WHERE campaign_id=" . $campaignId . " AND (status='running' OR status IS NULL)");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $pid = (int)$row['pid'];
        if ($pid > 0) {
            @posix_kill($pid, SIGTERM);
            usleep(200000);
            if (file_exists("/proc/{$pid}")) {
                @posix_kill($pid, SIGKILL);
            }
            $killed++;
        }
    }
}
$conn->query("UPDATE worker_heartbeat SET status='stopped', last_seen='" . $conn->real_escape_string(db_now($conn)) . "' WHERE campaign_id=" . $campaignId);
$messages[] = "Workers signaled to stop: {$killed}";

// 4) Optional: mark pending items as not-in-progress if you use claim locks (best-effort)
$conn->query("UPDATE mail_blaster SET in_progress=0 WHERE campaign_id=" . $campaignId . " AND in_progress=1");
if ($conn->affected_rows >= 0) {
    $messages[] = "Cleared in_progress flags (if used)";
}

// Output
fwrite(STDOUT, "Stopped campaign #{$campaignId}\n");
foreach ($messages as $m) {
    fwrite(STDOUT, "- {$m}\n");
}

exit($exitCode);
