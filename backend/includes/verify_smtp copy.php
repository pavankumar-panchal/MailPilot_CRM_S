<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
set_time_limit(0);

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

// Main DB configuration
$main_db_host = "174.141.233.174";
$main_db_user = "email_id";
$main_db_pass = "55y60jgW*";
$main_db_name = "email_id";

// Create main connection
$conn = new mysqli($main_db_host, $main_db_user, $main_db_pass, $main_db_name);
$conn->set_charset("utf8mb4");
if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "Main DB connection failed: " . $conn->connect_error]));
}

// Log DB configuration (local)
$log_db_host = "127.0.0.1";
$log_db_user = "CRM_logs";
$log_db_pass = "55y60jgW*";
$log_db_name = "CRM_logs";

$conn_logs = new mysqli($log_db_host, $log_db_user, $log_db_pass, $log_db_name);
$conn_logs->set_charset("utf8mb4");
if ($conn_logs->connect_error) {
    die(json_encode(["status" => "error", "message" => "Log DB connection failed: " . $conn_logs->connect_error]));
}

// Configuration
define('MAX_WORKERS', 50); // Adjust for your server
define('EMAILS_PER_WORKER', 500);
define('WORKER_SCRIPT', __DIR__ . '/smtp_worker.php');
define('LOG_FILE', __DIR__ . '/storage/smtp_parallel.log'); // Make sure this directory exists

set_time_limit(0);
ini_set('memory_limit', '512M');

// Create worker script if not exists
if (!file_exists(WORKER_SCRIPT)) {
    $worker_code = <<<'EOC'



<?php
// Main DB configuration
$main_db_host = "174.141.233.174";
$main_db_user = "email_id";
$main_db_pass = "55y60jgW*";
$main_db_name = "email_id";

$conn = new mysqli($main_db_host, $main_db_user, $main_db_pass, $main_db_name);
$conn->set_charset("utf8mb4");
if ($conn->connect_error) exit(1);

// Log DB configuration (local)
$log_db_host = "127.0.0.1";
$log_db_user = "CRM_logs";
$log_db_pass = "55y60jgW*";
$log_db_name = "CRM_logs";

$conn_logs = new mysqli($log_db_host, $log_db_user, $log_db_pass, $log_db_name);
$conn_logs->set_charset("utf8mb4");
if ($conn_logs->connect_error) {
    die(json_encode(["status" => "error", "message" => "Log DB connection failed: " . $conn_logs->connect_error]));
}
define('WORKER_ID', 1);

$start_id = isset($argv[1]) ? (int)$argv[1] : 0;
$end_id = isset($argv[2]) ? (int)$argv[2] : 0;

$query = "SELECT id, raw_emailid, sp_domain FROM emails 
          WHERE id BETWEEN $start_id AND $end_id 
          AND domain_status = 1 
          AND domain_processed = 0 
          AND worker_id = " . WORKER_ID;
		  

$result = $conn->query($query);

function log_worker($msg, $id_range = '') {
    $logfile = __DIR__ . "/storage/smtp_worker.log";
    $ts = date('Y-m-d H:i:s');
    file_put_contents($logfile, "[$ts][$id_range] $msg\n", FILE_APPEND);
}

function insert_smtp_log($conn_logs, $email, $steps, $validation, $validation_response) {
    $stmt = $conn_logs->prepare("INSERT IGNORE INTO email_smtp_checks2 
        (email, smtp_connection, ehlo, mail_from, rcpt_to, validation, validation_response) 
        VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param(
        "sssssss",
        $email,
        $steps['smtp_connection'],
        $steps['ehlo'],
        $steps['mail_from'],
        $steps['rcpt_to'],
        $validation,
        $validation_response
    );
    $stmt->execute();
    $stmt->close();
}

function verifyEmailViaSMTP($email, $domain, $conn_logs) {
    $ip = false;
    $mxHost = null;
    $steps = [
        'smtp_connection' => 'No',
        'ehlo' => 'No',
        'mail_from' => 'No',
        'rcpt_to' => 'No'
    ];

    // Try MX record first
    if (getmxrr($domain, $mxhosts) && !empty($mxhosts)) {
        $mxIp = @gethostbyname($mxhosts[0]);
        if ($mxIp !== $mxhosts[0] && filter_var($mxIp, FILTER_VALIDATE_IP)) {
            $ip = $mxIp;
            $mxHost = $mxhosts[0];
        }
    }

    // Fallback to A record if no MX
    if (!$ip) {
        $aRecord = @gethostbyname($domain);
        if ($aRecord !== $domain && filter_var($aRecord, FILTER_VALIDATE_IP)) {
            $ip = $aRecord;
            $mxHost = $domain;
        }
    }

    if (!$ip) {
        insert_smtp_log($conn_logs, $email, $steps, "No valid MX or A record found", "No valid MX or A record found");
        return [
            "status" => "invalid",
            "result" => 0,
            "response" => "No valid MX or A record found",
            "domain_status" => 0,
            "validation_status" => "invalid",
            "validation_response" => "No valid MX or A record found"
        ];
    }

    // SMTP check
    $port = 25;
    $timeout = 15;
    $smtp = @stream_socket_client("tcp://$ip:$port", $errno, $errstr, $timeout);
    if (!$smtp) {
        insert_smtp_log($conn_logs, $email, $steps, "Connection failed: $errstr", "Connection failed: $errstr");
        return [
            "status" => "invalid",
            "result" => 0,
            "response" => "Connection failed: $errstr",
            "domain_status" => 0,
            "validation_status" => "invalid",
            "validation_response" => "Connection failed: $errstr"
        ];
    }
    $steps['smtp_connection'] = 'Yes';
    stream_set_timeout($smtp, $timeout);
    $response = fgets($smtp, 4096);
    if ($response === false || substr($response, 0, 3) != "220") {
        fclose($smtp);
        insert_smtp_log($conn_logs, $email, $steps, "SMTP server not ready or no response", "SMTP server not ready or no response");
        return [
            "status" => "invalid",
            "result" => 0,
            "response" => "SMTP server not ready or no response",
            "domain_status" => 0,
            "validation_status" => "invalid",
            "validation_response" => "SMTP server not ready or no response"
        ];
    }
    fputs($smtp, "EHLO server.relyon.co.in\r\n");
    $ehlo_ok = false;
    while ($line = fgets($smtp, 4096)) {
        if (substr($line, 3, 1) == " ") {
            $ehlo_ok = true;
            break;
        }
    }
    if (!$ehlo_ok) {
        fclose($smtp);
        $steps['ehlo'] = 'No';
        insert_smtp_log($conn_logs, $email, $steps, "EHLO failed", "EHLO failed");
        return [
            "status" => "invalid",
            "result" => 0,
            "response" => "EHLO failed",
            "domain_status" => 0,
            "validation_status" => "invalid",
            "validation_response" => "EHLO failed"
        ];
    }
    $steps['ehlo'] = 'Yes';
    fputs($smtp, "MAIL FROM:<info@relyon.co.in>\r\n");
    $mailfrom_resp = fgets($smtp, 4096);
    if ($mailfrom_resp === false) {
        fclose($smtp);
        $steps['mail_from'] = 'No';
        insert_smtp_log($conn_logs, $email, $steps, "MAIL FROM failed", "MAIL FROM failed");
        return [
            "status" => "invalid",
            "result" => 0,
            "response" => "MAIL FROM failed",
            "domain_status" => 0,
            "validation_status" => "invalid",
            "validation_response" => "MAIL FROM failed"
        ];
    }
    $steps['mail_from'] = 'Yes';
    fputs($smtp, "RCPT TO:<$email>\r\n");
    $rcpt_resp = fgets($smtp, 4096);
    $responseCode = $rcpt_resp !== false ? substr($rcpt_resp, 0, 3) : null;
    $steps['rcpt_to'] = ($responseCode == "250" || $responseCode == "251") ? 'Yes' : 'No';
    fputs($smtp, "QUIT\r\n");
    fclose($smtp);

    // --- Sanitize validation_response for utf8mb4 ---
    $validation_response = $rcpt_resp !== false ? mb_convert_encoding($rcpt_resp, 'UTF-8', 'UTF-8') : '';
    $validation_response = mb_substr($validation_response, 0, 1000, 'UTF-8');

    if ($responseCode == "250" || $responseCode == "251") {
        insert_smtp_log($conn_logs, $email, $steps, $ip, $validation_response);
        return [
            "status" => "valid",
            "result" => 1,
            "response" => $ip,
            "domain_status" => 1,
            "validation_status" => "valid",
            "validation_response" => $ip
        ];
    } elseif (in_array($responseCode, ["450", "451", "452"])) {
        insert_smtp_log($conn_logs, $email, $steps, $rcpt_resp, $validation_response);
        return [
            "status" => "retryable",
            "result" => 2,
            "response" => $rcpt_resp,
            "domain_status" => 2,
            "validation_status" => "retryable",
            "validation_response" => $rcpt_resp
        ];
    } else {
        insert_smtp_log($conn_logs, $email, $steps, $rcpt_resp, $validation_response);
        return [
            "status" => "invalid",
            "result" => 0,
            "response" => $rcpt_resp,
            "domain_status" => 0,
            "validation_status" => "invalid",
            "validation_response" => $rcpt_resp
        ];
    }
}

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $email = $row["raw_emailid"];
        $domain = $row["sp_domain"];
        $email_id = $row["id"];
        $verify = verifyEmailViaSMTP($email, $domain, $conn_logs);

        // --- Sanitize validation_response for utf8mb4 ---
        if (isset($verify['validation_response'])) {
            $verify['validation_response'] = mb_convert_encoding($verify['validation_response'], 'UTF-8', 'UTF-8');
            $verify['validation_response'] = mb_substr($verify['validation_response'], 0, 1000, 'UTF-8');
        }

        $update = $conn->prepare("UPDATE emails SET 
            domain_status = ?, 
            domain_processed = 1, 
            validation_status = ?, 
            validation_response = ? 
            WHERE id = ?");
        if ($update) {
            $update->bind_param(
                "issi",
                $verify['domain_status'],
                $verify['validation_status'],
                $verify['validation_response'],
                $email_id
            );
            $update->execute();
            $update->close();
        }

        log_worker("Processed $email_id ($email): {$verify['status']} - {$verify['response']}", "$start_id-$end_id");
    }
} else {
    log_worker("Query failed: " . $conn->error, "$start_id-$end_id");
}
$conn->close();
$conn_logs->close();



EOC;
    file_put_contents(WORKER_SCRIPT, $worker_code);
}

// --- Logging ---
function write_log($msg)
{
    $ts = date('Y-m-d H:i:s');
    file_put_contents(LOG_FILE, "[$ts] $msg\n", FILE_APPEND);
}

// --- Atomically assign emails to workers ---
function assign_emails_to_workers($conn, $num_workers, $batch_size)
{
    $assigned = [];
    for ($worker_id = 1; $worker_id <= $num_workers; $worker_id++) {
        // Atomically select and lock a batch for this worker
        $conn->query("START TRANSACTION");
        $result = $conn->query("SELECT id FROM emails WHERE domain_status=1 AND domain_processed=0 AND processing=0 LIMIT $batch_size FOR UPDATE");
        $ids = [];
        while ($row = $result->fetch_assoc()) {
            $ids[] = $row['id'];
        }
        if (!empty($ids)) {
            $id_list = implode(',', $ids);
            $conn->query("UPDATE emails SET processing=1, worker_id=$worker_id WHERE id IN ($id_list)");
            $assigned[$worker_id] = $ids;
        }
        $conn->query("COMMIT");
    }
    return $assigned;
}

// --- Parallel SMTP processing ---
function process_in_parallel($conn)
{
    $num_workers = MAX_WORKERS;
    $batch_size = EMAILS_PER_WORKER;
    $assigned = assign_emails_to_workers($conn, $num_workers, $batch_size);
    $active_procs = [];
    $processed = 0;

    write_log("Starting parallel SMTP processing with MAX_WORKERS=" . $num_workers);

    foreach ($assigned as $worker_id => $id_chunk) {
        if (empty($id_chunk)) continue;
        $id_list = implode(',', $id_chunk);
        $cmd = "php " . escapeshellarg(WORKER_SCRIPT) . " " . escapeshellarg($id_list) . " $worker_id";
        $descriptorspec = [0 => ["pipe", "r"], 1 => ["pipe", "w"], 2 => ["pipe", "w"]];
        $proc = proc_open($cmd, $descriptorspec, $pipes);
        if (is_resource($proc)) {
            $active_procs[] = [
                'proc' => $proc,
                'pipes' => $pipes,
                'worker_id' => $worker_id,
                'count' => count($id_chunk)
            ];
            write_log("Started worker $worker_id for " . count($id_chunk) . " emails");
            $processed += count($id_chunk);
        }
    }

    // Wait for all workers to finish
    foreach ($active_procs as $worker) {
        $status = proc_get_status($worker['proc']);
        while ($status['running']) {
            usleep(100000);
            $status = proc_get_status($worker['proc']);
        }
        $stdout = stream_get_contents($worker['pipes'][1]);
        $stderr = stream_get_contents($worker['pipes'][2]);
        if (trim($stdout)) write_log("Worker {$worker['worker_id']} OUTPUT: $stdout");
        if (trim($stderr)) write_log("Worker {$worker['worker_id']} ERROR: $stderr");
        foreach ($worker['pipes'] as $pipe) fclose($pipe);
        proc_close($worker['proc']);
    }

    // Log how many emails remain to process
    $result = $conn->query("SELECT COUNT(*) as remaining FROM emails WHERE validation_status IS NULL");
    $row = $result->fetch_assoc();
    $remaining = $row ? $row['remaining'] : 0;
    write_log("Processing complete. Remaining unprocessed emails: $remaining");

    return $processed;
}

// --- Main execution ---
try {
    // Update status before processing
    $conn->query("UPDATE csv_list SET status = 'running' WHERE status = 'pending'");

    $start_time = microtime(true);
    $processed = process_in_parallel($conn);
    $total_time = microtime(true) - $start_time;

    // Get verification stats
    $total = $conn->query("SELECT COUNT(*) as total FROM emails")->fetch_row()[0];
    $verified = $conn->query("SELECT COUNT(*) as verified FROM emails WHERE validation_status = 'valid'")->fetch_row()[0];

    echo json_encode([
        "status" => "success",
        "processed" => (int) $processed,
        "total_emails" => (int) $total,
        "verified_emails" => (int) $verified,
        "time_seconds" => round($total_time, 2),
        "rate_per_second" => round($processed / $total_time, 2),
        "message" => "Parallel SMTP processing completed"
    ]);

} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
} finally {
    $conn->close();
    $conn_logs->close();
}
?>