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
require_once __DIR__ . '/session_config.php';
require_once __DIR__ . '/auth_helper.php';

// Get current user for user_id tracking
$currentUser = getAuthenticatedUser();
$user_id = $currentUser ? $currentUser['id'] : null;

$campaign_id = isset($_POST['campaign_id']) ? (int)$_POST['campaign_id'] : 0;
if ($campaign_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'campaign_id is required']);
    exit();
}

try {
    // Get current user for filtering
    require_once __DIR__ . '/auth_helper.php';
    $currentUser = getAuthenticatedUser();
    $isAdmin = isAuthenticatedAdmin();
    $userId = $currentUser ? $currentUser['id'] : 0;
    
    // Validate campaign exists and get csv_list_id with user filtering
    $userFilter = $isAdmin ? '' : ' AND user_id = ' . intval($userId);
    $stmt = $conn->prepare('SELECT campaign_id, mail_subject, mail_body, csv_list_id, user_id FROM campaign_master WHERE campaign_id = ?' . $userFilter);
    $stmt->bind_param('i', $campaign_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $campaign = $result->fetch_assoc();
    if (!$campaign) {
        http_response_code(404);
        echo json_encode(['error' => 'Campaign not found or access denied']);
        exit();
    }
    if (empty(trim($campaign['mail_subject'])) || empty(trim($campaign['mail_body']))) {
        http_response_code(400);
        echo json_encode(['error' => 'Campaign missing subject or body']);
        exit();
    }

    $csv_list_id = $campaign['csv_list_id'];
    $import_batch_id = isset($campaign['import_batch_id']) ? $campaign['import_batch_id'] : null;
    $csvListFilter = $csv_list_id ? " AND csv_list_id = " . (int)$csv_list_id : "";

    // CRITICAL: Initialize email queue to ensure NO emails are missed
    require_once __DIR__ . '/campaign_email_verification.php';
    $queueStats = initializeEmailQueue($conn, $campaign_id);
    
    // Log queue initialization results
    error_log("[CAMPAIGN $campaign_id] Queue initialized: {$queueStats['total_recipients']} recipients, {$queueStats['queued']} new, {$queueStats['already_queued']} existing");
    
    // PRODUCTION SAFETY: Verify queue was properly initialized
    if ($queueStats['total_recipients'] > 0 && ($queueStats['queued'] + $queueStats['already_queued']) === 0) {
        error_log("[CAMPAIGN $campaign_id] ERROR: Queue initialization failed - no emails were queued!");
        http_response_code(500);
        echo json_encode(['error' => 'Failed to initialize email queue. Please try again.']);
        exit();
    }
    
    // Determine total valid emails from queue
    $mbRes = $conn->query("SELECT COUNT(*) AS mb_total FROM mail_blaster WHERE campaign_id = " . (int)$campaign_id);
    $mbRow = $mbRes ? $mbRes->fetch_assoc() : ['mb_total' => 0];
    $totalEmails = (int)($mbRow['mb_total'] ?? 0);

    if ($totalEmails === 0) {
        http_response_code(400);
        if ($import_batch_id) {
            $message = 'No recipients found in the imported Excel batch.';
        } elseif ($csv_list_id) {
            $message = 'No recipients found for this campaign in the selected CSV list.';
        } else {
            $message = 'No recipients found for this campaign. Please add recipients to mail_blaster first.';
        }
        echo json_encode(['error' => $message]);
        exit();
    }

    // Check existing status row
    $statusRes = $conn->query("SELECT status, sent_emails, failed_emails FROM campaign_status WHERE campaign_id = $campaign_id");
    $statusRow = $statusRes ? $statusRes->fetch_assoc() : null;

    // CRITICAL: Count from mail_blaster table (source of truth)
    // sent = success count, failed = attempt_count >= 5, pending = retryable (< 5 attempts)
    if ($totalEmails > 0) {
        $statsQuery = "SELECT 
            SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as sent_count,
            SUM(CASE WHEN status = 'failed' AND attempt_count >= 5 THEN 1 ELSE 0 END) as failed_count,
            SUM(CASE WHEN status IN ('pending', 'failed') AND attempt_count < 5 THEN 1 ELSE 0 END) as pending_count
        FROM mail_blaster WHERE campaign_id = $campaign_id";
        $statsRes = $conn->query($statsQuery);
        $stats = $statsRes ? $statsRes->fetch_assoc() : ['sent_count' => 0, 'failed_count' => 0, 'pending_count' => 0];
        $sent = (int)($stats['sent_count'] ?? 0);
        $failed = (int)($stats['failed_count'] ?? 0);
        $pending = (int)($stats['pending_count'] ?? 0);
    } else {
        $sent = (int)($statusRow['sent_emails'] ?? 0);
        $failed = (int)($statusRow['failed_emails'] ?? 0);
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

    // Check if PID column exists
    $hasPid = false;
    if ($dbName) {
        $pidCheck = $conn->query("SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '" . $conn->real_escape_string($dbName) . "' AND TABLE_NAME = 'campaign_status' AND COLUMN_NAME = 'process_pid'");
        if ($pidCheck) {
            $hasPid = (int)$pidCheck->fetch_assoc()['cnt'] > 0;
        }
    }

    // If PID column exists, check if campaign is already running with an active process
    if ($hasPid && $statusRow && $statusRow['status'] === 'running') {
        $existingPid = isset($statusRow['process_pid']) ? (int)$statusRow['process_pid'] : 0;
        if ($existingPid > 0) {
            // Check if process is still running
            $pidExists = file_exists("/proc/$existingPid");
            if ($pidExists) {
                echo json_encode([
                    'status' => 'already_running',
                    'message' => 'Campaign is already running with PID ' . $existingPid,
                    'campaign_id' => $campaign_id,
                    'pid' => $existingPid
                ]);
                exit();
            }
        }
    }

    if (!$statusRow) {
        // Insert new status row with user_id (PID will be updated after process starts)
        if ($hasStartTime) {
            $insertSql = "INSERT INTO campaign_status (campaign_id, status, total_emails, pending_emails, sent_emails, failed_emails, start_time, user_id) VALUES ($campaign_id, 'running', $totalEmails, $pending, $sent, $failed, NOW(), " . ($user_id ? $user_id : "NULL") . ")";
            $conn->query($insertSql);
        } else {
            $insertSql = "INSERT INTO campaign_status (campaign_id, status, total_emails, pending_emails, sent_emails, failed_emails, user_id) VALUES ($campaign_id, 'running', $totalEmails, $pending, $sent, $failed, " . ($user_id ? $user_id : "NULL") . ")";
            $conn->query($insertSql);
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
    // Use email_blast_parallel.php as the orchestrator
    $script = realpath(__DIR__ . '/email_blast_parallel.php');
    if (!$script || !is_file($script)) {
        http_response_code(500);
        echo json_encode(['error' => 'Email blast script missing']);
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


    // Store PID in campaign_status if column exists
    if ($hasPid && $pid) {
        // Reconnect to database to store PID
        require_once __DIR__ . '/../config/db.php';
        $conn->query("UPDATE campaign_status SET process_pid = $pid WHERE campaign_id = $campaign_id");
    }

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
