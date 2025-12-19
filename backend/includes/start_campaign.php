<?php
/**
 * Start (or resume) a campaign by spawning the parallel email blast daemon.
 * Expected POST param: campaign_id
 */
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/BackgroundProcess.php';

$campaign_id = isset($_POST['campaign_id']) ? (int)$_POST['campaign_id'] : 0;
if ($campaign_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'campaign_id is required']);
    exit();
}

try {
    // Validate campaign exists and get csv_list_id
    $stmt = $conn->prepare('SELECT campaign_id, mail_subject, mail_body, csv_list_id FROM campaign_master WHERE campaign_id = ?');
    $stmt->bind_param('i', $campaign_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $campaign = $result->fetch_assoc();
    if (!$campaign) {
        http_response_code(404);
        echo json_encode(['error' => 'Campaign not found']);
        exit();
    }
    if (empty(trim($campaign['mail_subject'])) || empty(trim($campaign['mail_body']))) {
        http_response_code(400);
        echo json_encode(['error' => 'Campaign missing subject or body']);
        exit();
    }

    $csv_list_id = $campaign['csv_list_id'];
    $csvListFilter = $csv_list_id ? " AND csv_list_id = " . (int)$csv_list_id : "";

    // Determine total valid emails eligible for sending.
    // Prefer existing `mail_blaster` entries for this campaign (authoritative recipients list).
    $mbRes = $conn->query("SELECT COUNT(*) AS mb_total FROM mail_blaster WHERE campaign_id = " . (int)$campaign_id);
    $mbRow = $mbRes ? $mbRes->fetch_assoc() : ['mb_total' => 0];
    $mbTotal = (int)($mbRow['mb_total'] ?? 0);
    if ($mbTotal > 0) {
        $totalEmails = $mbTotal;
    } else {
        // Fallback to emails table (filtered by csv_list_id if specified)
        $totalRes = $conn->query("SELECT COUNT(*) AS total_valid FROM emails WHERE domain_status = 1" . $csvListFilter);
        $totalRow = $totalRes ? $totalRes->fetch_assoc() : ['total_valid' => 0];
        $totalEmails = (int)($totalRow['total_valid'] ?? 0);
    }

    if ($totalEmails === 0) {
        http_response_code(400);
        $message = $csv_list_id ? 'No recipients found for this campaign in the selected CSV list.' : 'No recipients found for this campaign. Please add recipients to mail_blaster first.';
        echo json_encode(['error' => $message]);
        exit();
    }

    // Check existing status row
    $statusRes = $conn->query("SELECT status, sent_emails, failed_emails FROM campaign_status WHERE campaign_id = $campaign_id");
    $statusRow = $statusRes ? $statusRes->fetch_assoc() : null;

    $sent = (int)($statusRow['sent_emails'] ?? 0);
    $failed = (int)($statusRow['failed_emails'] ?? 0);
    // If mail_blaster exists, compute pending from its rows (retry-aware); otherwise use total - (sent+failed)
    if ($mbTotal > 0) {
        $pendRes = $conn->query("SELECT COUNT(*) AS pending FROM mail_blaster WHERE campaign_id = " . (int)$campaign_id . " AND (status IS NULL OR status = 'pending' OR (status='failed' AND attempt_count < 3))");
        $pendRow = $pendRes ? $pendRes->fetch_assoc() : ['pending' => 0];
        $pending = (int)$pendRow['pending'];
    } else {
        $pending = max($totalEmails - ($sent + $failed), 0);
    }

    // Detect if already running
    if ($statusRow && $statusRow['status'] === 'running') {
        echo json_encode([
            'status' => 'already_running',
            'message' => 'Campaign already running',
            'campaign_id' => $campaign_id,
        ]);
        exit();
    }

    // Minimal info_schema check for optional columns (start_time)
    $dbNameRes = $conn->query('SELECT DATABASE() as db');
    $dbName = $dbNameRes ? ($dbNameRes->fetch_assoc()['db'] ?? '') : '';
    $hasStartTime = false;
    if ($dbName) {
        $colCheck = $conn->query("SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '" . $conn->real_escape_string($dbName) . "' AND TABLE_NAME = 'campaign_status' AND COLUMN_NAME = 'start_time'");
        if ($colCheck) {
            $hasStartTime = (int)$colCheck->fetch_assoc()['cnt'] > 0;
        }
    }

    if (!$statusRow) {
        // Insert new status row
        if ($hasStartTime) {
            $conn->query("INSERT INTO campaign_status (campaign_id, status, total_emails, pending_emails, sent_emails, failed_emails, start_time) VALUES ($campaign_id, 'running', $totalEmails, $pending, $sent, $failed, NOW())");
        } else {
            $conn->query("INSERT INTO campaign_status (campaign_id, status, total_emails, pending_emails, sent_emails, failed_emails) VALUES ($campaign_id, 'running', $totalEmails, $pending, $sent, $failed)");
        }
    } else {
        // Update existing row to running and refresh counts
        if ($hasStartTime) {
            $conn->query("UPDATE campaign_status SET status = 'running', total_emails = $totalEmails, pending_emails = $pending, start_time = IF(start_time IS NULL, NOW(), start_time) WHERE campaign_id = $campaign_id");
        } else {
            $conn->query("UPDATE campaign_status SET status = 'running', total_emails = $totalEmails, pending_emails = $pending WHERE campaign_id = $campaign_id");
        }
    }

    // Spawn background orchestrator process (per-server parallel sender)
    // Prefer server-based orchestrator if available
    $script = realpath(__DIR__ . '/email_sender_orchestrator.php');
    if (!$script || !is_file($script)) {
        http_response_code(500);
        echo json_encode(['error' => 'New orchestrator script missing']);
        exit();
    }
    if (!$script || !is_file($script)) {
        http_response_code(500);
        echo json_encode(['error' => 'Orchestrator script missing']);
        exit();
    }

    // Detect PHP CLI path reliably; prefer system PHP (/usr/bin/php) that has mysqli
    $phpCandidates = [
        '/usr/bin/php',
        '/usr/local/bin/php',
        '/opt/lampp/bin/php',
    ];
    $php = null;
    foreach ($phpCandidates as $cand) {
        if (is_executable($cand)) { $php = $cand; break; }
    }
    if (!$php) {
        // Fallback to environment php if available
        $which = trim(shell_exec('which php 2>/dev/null'));
        if ($which) {
            $php = $which;
        } else {
            $php = 'php';
        }
    }

    // Ensure logs directory exists for orchestrator outputs (if any)
    $logsDir = realpath(__DIR__ . '/../logs') ?: (__DIR__ . '/../logs');
    if (!is_dir($logsDir)) {
        @mkdir($logsDir, 0775, true);
    }

    // Prepare for background execution (closes DB connection)
    if (isset($stmt)) $stmt->close();
    BackgroundProcess::prepareForBackground($conn);

    // Use BackgroundProcess helper for proper async execution
    $logFile = $logsDir . '/campaign_' . $campaign_id . '.log';
    $pid = BackgroundProcess::execute($php, $script, [
        'campaign_id' => $campaign_id
    ], $logFile);


    // Log the start
    error_log("[" . date('Y-m-d H:i:s') . "] Started campaign $campaign_id with PID $pid\n", 3, $logsDir . '/campaign_starts.log');

    // Return immediately to frontend
    echo json_encode([
        'status' => 'started',
        'message' => 'Campaign sending started in background',
        'campaign_id' => $campaign_id,
        'total_emails' => $totalEmails,
        'pending_emails' => $pending,
        'php' => $php,
        'pid' => $pid,
    ]);
    
    // Ensure output is sent immediately
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
