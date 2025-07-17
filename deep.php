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
$log_db_user = "email_id";
$log_db_pass = "55y60jgW*";
$log_db_name = "email_id";

$conn_logs = new mysqli($log_db_host, $log_db_user, $log_db_pass, $log_db_name);
$conn_logs->set_charset("utf8mb4");
if ($conn_logs->connect_error) {
    die(json_encode(["status" => "error", "message" => "Log DB connection failed: " . $conn_logs->connect_error]));
}

// Configuration
define('MAX_WORKERS', 50);
define('EMAILS_PER_WORKER', 500);
define('WORKER_SCRIPT', __DIR__ . '/smtp_worker.php');
define('LOG_FILE', __DIR__ . '/storage/smtp_parallel.log');
define('LOCK_FILE', __DIR__ . '/smtp_processor.lock');
define('WORKER_TIMEOUT', 300); // 5 minutes

set_time_limit(0);
ini_set('memory_limit', '512M');

// Check for existing lock file
if (file_exists(LOCK_FILE)) {
    $lock_time = filemtime(LOCK_FILE);
    if (time() - $lock_time < 3600) { // 1 hour timeout
        die(json_encode(["status" => "error", "message" => "Another process is already running"]));
    }
    // Remove stale lock
    unlink(LOCK_FILE);
}

// Create lock file
file_put_contents(LOCK_FILE, getmypid());

// Register shutdown function to clean up lock file
register_shutdown_function(function() {
    if (file_exists(LOCK_FILE)) {
        unlink(LOCK_FILE);
    }
});

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
$log_db_user = "email_id";
$log_db_pass = "55y60jgW*";
$log_db_name = "email_id";

$conn_logs = new mysqli($log_db_host, $log_db_user, $log_db_pass, $log_db_name);
$conn_logs->set_charset("utf8mb4");
if ($conn_logs->connect_error) {
    die(json_encode(["status" => "error", "message" => "Log DB connection failed: " . $conn_logs->connect_error]));
}

$start_id = isset($argv[1]) ? (int)$argv[1] : 0;
$end_id = isset($argv[2]) ? (int)$argv[2] : 0;
$worker_id = getmypid();

// Assign this batch to current worker
$conn->query("UPDATE emails SET worker_id = $worker_id WHERE id BETWEEN $start_id AND $end_id AND domain_processed = 2");

$query = "SELECT id, raw_emailid, sp_domain FROM emails 
          WHERE id BETWEEN $start_id AND $end_id 
          AND domain_status = 1 
          AND domain_processed = 2 
          AND worker_id = $worker_id";

$result = $conn->query($query);

function log_worker($msg, $id_range = '') {
    $logfile = __DIR__ . "/storage/smtp_worker.log";
    $ts = date('Y-m-d H:i:s');
    file_put_contents($logfile, "[$ts][$id_range] $msg\n", FILE_APPEND);
}

function insert_smtp_log($conn_logs, $email, $steps, $validation, $validation_response) {
    $stmt = $conn_logs->prepare("INSERT INTO email_smtp_checks2 
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

    // Sanitize validation_response for utf8mb4
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

        // Sanitize validation_response for utf8mb4
        if (isset($verify['validation_response'])) {
            $verify['validation_response'] = mb_convert_encoding($verify['validation_response'], 'UTF-8', 'UTF-8');
            $verify['validation_response'] = mb_substr($verify['validation_response'], 0, 1000, 'UTF-8');
        }

        $update = $conn->prepare("UPDATE emails SET 
            domain_status = ?, 
            domain_processed = 1, 
            validation_status = ?, 
            validation_response = ?,
            worker_id = NULL 
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

// Mark any unprocessed emails in this range as available for retry
$conn->query("UPDATE emails SET domain_processed = 0, worker_id = NULL 
              WHERE id BETWEEN $start_id AND $end_id AND domain_processed = 2");

$conn->close();
$conn_logs->close();
EOC;
    file_put_contents(WORKER_SCRIPT, $worker_code);
}

// Logging function
function write_log($msg) {
    $ts = date('Y-m-d H:i:s');
    file_put_contents(LOG_FILE, "[$ts] $msg\n", FILE_APPEND);
}

// Get ID ranges for parallel processing
function get_id_ranges($conn, $batch_size) {
    $ranges = [];

    // Check for and reset any stuck records from previous runs
    $stuck_count = $conn->query("SELECT COUNT(*) FROM emails WHERE domain_processed = 2")->fetch_row()[0];
    if ($stuck_count > 0) {
        $conn->query("UPDATE emails SET domain_processed = 0, worker_id = NULL WHERE domain_processed = 2");
        write_log("Reset $stuck_count stuck records from previous run");
    }

    // Lock batch of emails to be processed
    $conn->autocommit(false);
    try {
        $lock_query = "UPDATE emails 
                      SET domain_processed = 2
                      WHERE domain_status = 1 
                      AND domain_processed = 0 
                      ORDER BY id ASC 
                      LIMIT " . (MAX_WORKERS * $batch_size);
        $conn->query($lock_query);
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
    $conn->autocommit(true);

    // Now fetch only locked emails
    $result = $conn->query("SELECT id FROM emails WHERE domain_processed = 2 ORDER BY id ASC");
    $ids = [];
    while ($row = $result->fetch_assoc()) {
        $ids[] = (int)$row['id'];
    }

    if (empty($ids)) return [];

    $chunks = array_chunk($ids, $batch_size);
    foreach ($chunks as $chunk) {
        $ranges[] = [
            'start' => $chunk[0],
            'end' => end($chunk),
            'count' => count($chunk)
        ];
    }

    write_log("Locked and batched " . count($ids) . " emails into " . count($ranges) . " batches.");
    return $ranges;
}

// Parallel SMTP processing
function process_in_parallel($conn) {
    $batch_size = EMAILS_PER_WORKER;
    $ranges = get_id_ranges($conn, $batch_size);
    $total_batches = count($ranges);
    $processed = 0;
    $active_procs = [];
    $batch_idx = 0;

    write_log("Starting parallel SMTP processing with MAX_WORKERS=" . MAX_WORKERS);

    while ($batch_idx < $total_batches || count($active_procs) > 0) {
        // Start new workers if under limit
        while (count($active_procs) < MAX_WORKERS && $batch_idx < $total_batches) {
            $range = $ranges[$batch_idx];
            $cmd = "/opt/plesk/php/8.2/bin/php " . escapeshellarg(WORKER_SCRIPT) . " {$range['start']} {$range['end']}";
            $descriptorspec = [0 => ["pipe", "r"], 1 => ["pipe", "w"], 2 => ["pipe", "w"]];
            $proc = proc_open($cmd, $descriptorspec, $pipes);
            if (is_resource($proc)) {
                $active_procs[] = [
                    'proc' => $proc,
                    'pipes' => $pipes,
                    'range' => $range,
                    'starttime' => time()
                ];
                write_log("Started worker for IDs {$range['start']} - {$range['end']} ({$range['count']} emails)");
            }
            $processed += $range['count'];
            $batch_idx++;
        }

        // Check for finished workers
        foreach ($active_procs as $key => $worker) {
            $status = proc_get_status($worker['proc']);
            if (!$status['running'] || (time() - $worker['starttime'] > WORKER_TIMEOUT)) {
                if ($status['running']) {
                    // Kill timed out worker
                    proc_terminate($worker['proc']);
                    write_log("Terminated stuck worker for IDs {$worker['range']['start']}-{$worker['range']['end']}");
                }
                
                // Read output and errors
                $stdout = stream_get_contents($worker['pipes'][1]);
                $stderr = stream_get_contents($worker['pipes'][2]);
                if (trim($stdout)) {
                    write_log("Worker [IDs {$worker['range']['start']}-{$worker['range']['end']}] OUTPUT: $stdout");
                }
                if (trim($stderr)) {
                    write_log("Worker [IDs {$worker['range']['start']}-{$worker['range']['end']}] ERROR: $stderr");
                }
                
                // Close pipes and remove from active list
                foreach ($worker['pipes'] as $pipe) {
                    fclose($pipe);
                }
                proc_close($worker['proc']);
                unset($active_procs[$key]);
            }
        }
        
        // Prevent busy waiting
        usleep(100000); // 0.1s
        $active_procs = array_values($active_procs); // reindex
    }

    // Log how many emails remain to process
    $result = $conn->query("SELECT COUNT(*) as remaining FROM emails WHERE validation_status IS NULL");
    $row = $result->fetch_assoc();
    $remaining = $row ? $row['remaining'] : 0;
    write_log("Processing complete. Remaining unprocessed emails: $remaining");

    return $processed;
}

// Main execution
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
    
    // Clean up lock file if it still exists
    if (file_exists(LOCK_FILE)) {
        unlink(LOCK_FILE);
    }
}