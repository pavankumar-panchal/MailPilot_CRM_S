<?php

require_once __DIR__ . '/../config/db.php';

// Production-safe error handling
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output
ini_set('log_errors', 1); // Log errors instead
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
set_time_limit(0);

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Preflight
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['status' => 'ok']);
    exit;
}

// Custom error handler to return JSON
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Server error: ' . $errstr,
        'file' => basename($errfile),
        'line' => $errline
    ]);
    exit;
});

// Custom exception handler
set_exception_handler(function($exception) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Exception: ' . $exception->getMessage()
    ]);
    exit;
});

// Configuration
define('LOG_FILE', __DIR__ . '/../storage/retry_smtp.log');
ini_set('memory_limit', '512M');

// Logging function
function write_log($msg)
{
    $ts = date('Y-m-d H:i:s');
    file_put_contents(LOG_FILE, "[$ts] $msg\n", FILE_APPEND);
}

function verifyEmailViaSMTP($email, $domain, $conn, $ehloHost = null, $mailFrom = null)
{
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
        return [
            "status" => "invalid",
            "result" => 0,
            "response" => "SMTP server not ready or no response",
            "domain_status" => 0,
            "validation_status" => "invalid",
            "validation_response" => "SMTP server not ready or no response"
        ];
    }
    // Pick EHLO host and MAIL FROM
    $ehlo = $ehloHost ?: 'localhost.localdomain';
    $from = $mailFrom ?: 'no-reply@localhost.localdomain';

    fputs($smtp, "EHLO {$ehlo}\r\n");
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
    fputs($smtp, "MAIL FROM:<{$from}>\r\n");
    $mailfrom_resp = fgets($smtp, 4096);
    if ($mailfrom_resp === false) {
        fclose($smtp);
        $steps['mail_from'] = 'No';
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

    // Sanitize validation_response
    $validation_response = $rcpt_resp !== false ? mb_convert_encoding($rcpt_resp, 'UTF-8', 'UTF-8') : '';
    $validation_response = mb_substr($validation_response, 0, 1000, 'UTF-8');

    if ($responseCode == "250" || $responseCode == "251") {
        return [
            "status" => "valid",
            "result" => 1,
            "response" => $ip,
            "domain_status" => 1,
            "validation_status" => "valid",
            "validation_response" => $ip
        ];
    } else {
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

// Accept input params
$csv_list_id = isset($_GET['csv_list_id']) ? intval($_GET['csv_list_id']) : (isset($_POST['csv_list_id']) ? intval($_POST['csv_list_id']) : 0);

// Optional: choose EHLO host / MAIL FROM dynamically
function getHostFromRequest() {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost.localdomain';
    // Remove port if present
    $host = preg_replace('/:\\d+$/', '', $host);
    // Ensure we have at least one dot
    if (strpos($host, '.') === false) {
        $host = $host . '.localdomain';
    }
    return $host;
}

$param_ehlo = trim($_GET['ehlo_host'] ?? $_POST['ehlo_host'] ?? '');
$param_mail_from = trim($_GET['mail_from'] ?? $_POST['mail_from'] ?? '');
$param_server_id = isset($_GET['server_id']) ? intval($_GET['server_id']) : (isset($_POST['server_id']) ? intval($_POST['server_id']) : 0);

// Derive defaults
$defaultHost = getHostFromRequest();
$defaultFrom = 'verify@' . $defaultHost;

// If server_id is provided, try to fetch an email to use as MAIL FROM
$resolvedMailFrom = $param_mail_from;
if ($param_server_id > 0) {
    // Prefer an active account email for this server
    if ($stmt = $conn->prepare("SELECT email FROM smtp_accounts WHERE smtp_server_id = ? AND is_active = 1 ORDER BY id ASC LIMIT 1")) {
        $stmt->bind_param('i', $param_server_id);
        if ($stmt->execute()) {
            $stmt->bind_result($accEmail);
            if ($stmt->fetch() && filter_var($accEmail, FILTER_VALIDATE_EMAIL)) {
                $resolvedMailFrom = $accEmail;
            }
        }
        $stmt->close();
    }
    // Fallback to server received_email if available
    if (!$resolvedMailFrom && ($stmt2 = $conn->prepare("SELECT received_email FROM smtp_servers WHERE id = ? LIMIT 1"))) {
        $stmt2->bind_param('i', $param_server_id);
        if ($stmt2->execute()) {
            $stmt2->bind_result($recvEmail);
            if ($stmt2->fetch() && filter_var($recvEmail, FILTER_VALIDATE_EMAIL)) {
                $resolvedMailFrom = $recvEmail;
            }
        }
        $stmt2->close();
    }
}

$ehloHost = $param_ehlo !== '' ? $param_ehlo : $defaultHost;
$mailFrom = $resolvedMailFrom !== '' ? $resolvedMailFrom : $defaultFrom;

if (!$csv_list_id) {
    echo json_encode([
        "status" => "error",
        "message" => "csv_list_id is required"
    ]);
    exit;
}

function process_emails($conn, $csv_list_id, $ehloHost, $mailFrom)
{
    $processed = 0;
    $batch_size = 100; // Process emails in batches of 100

    // Get total count of retryable emails for this list
    $result = $conn->prepare("SELECT COUNT(*) as total FROM emails WHERE domain_status=2 AND csv_list_id=?");
    $result->bind_param("i", $csv_list_id);
    $result->execute();
    $result_data = $result->get_result()->fetch_assoc();
    $total = $result_data['total'];
    $result->close();

    write_log("Starting SMTP processing for $total retryable emails in list $csv_list_id");

    $offset = 0;
    while (true) {
        // Get batch of emails to process for this list
        $query = $conn->prepare("SELECT id, raw_emailid, sp_domain FROM emails WHERE domain_status=2 AND csv_list_id=? LIMIT ?, ?");
        $query->bind_param("iii", $csv_list_id, $offset, $batch_size);
        $query->execute();
        $result = $query->get_result();

        if (!$result || $result->num_rows == 0) {
            $query->close();
            break; // No more emails to process
        }

        while ($row = $result->fetch_assoc()) {
            $email = $row["raw_emailid"];
            $domain = $row["sp_domain"];
            $email_id = $row["id"];

            $verify = verifyEmailViaSMTP($email, $domain, $conn, $ehloHost, $mailFrom);

            // Sanitize validation_response
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

            write_log("Processed $email_id ($email): {$verify['status']} - {$verify['response']}");
            $processed++;
        }

        $query->close();
        $offset += $batch_size;
    }

    return $processed;
}

// Update csv_list status and counts for a specific list
function updateCsvListCounts($conn, $csv_list_id)
{
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) AS total_emails,
            SUM(domain_status = 1) AS valid_count,
            SUM(domain_status = 0) AS invalid_count
        FROM emails
        WHERE csv_list_id = ?
    ");
    $stmt->bind_param("i", $csv_list_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    $total = (int)($row['total_emails'] ?? 0);
    $valid = (int)($row['valid_count'] ?? 0);
    $invalid = (int)($row['invalid_count'] ?? 0);

    $updateStmt = $conn->prepare("
        UPDATE csv_list 
        SET total_emails = ?, valid_count = ?, invalid_count = ?
        WHERE id = ?
    ");
    $updateStmt->bind_param("iiii", $total, $valid, $invalid, $csv_list_id);
    $updateStmt->execute();
    $updateStmt->close();
}

// Main execution
try {
    $start_time = microtime(true);
    $processed = process_emails($conn, $csv_list_id, $ehloHost, $mailFrom);
    $total_time = microtime(true) - $start_time;

    // Update campaign stats for this list
    updateCsvListCounts($conn, $csv_list_id);

    // Check if all emails are processed (no more retryable) for this list
    $remainingOtherStatus = 0;
    $stmt = $conn->prepare("SELECT COUNT(*) FROM emails WHERE domain_status=2 AND csv_list_id=?");
    $stmt->bind_param("i", $csv_list_id);
    $stmt->execute();
    $stmt->bind_result($remainingOtherStatus);
    $stmt->fetch();
    $stmt->close();

    if ($remainingOtherStatus == 0) {
        $updateStatus = $conn->prepare("UPDATE csv_list SET status = 'completed' WHERE id = ? AND status = 'running'");
        $updateStatus->bind_param("i", $csv_list_id);
        $updateStatus->execute();
        $updateStatus->close();
    }

    // Get verification stats for this list
    $total = 0;
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM emails WHERE csv_list_id=?");
    $stmt->bind_param("i", $csv_list_id);
    $stmt->execute();
    $stmt->bind_result($total);
    $stmt->fetch();
    $stmt->close();

    $verified = 0;
    $stmt = $conn->prepare("SELECT COUNT(*) as verified FROM emails WHERE validation_status = 'valid' AND csv_list_id=?");
    $stmt->bind_param("i", $csv_list_id);
    $stmt->execute();
    $stmt->bind_result($verified);
    $stmt->fetch();
    $stmt->close();

    echo json_encode([
        "status" => "success",
        "processed" => (int) $processed,
        "total_emails" => (int) $total,
        "verified_emails" => (int) $verified,
        "time_seconds" => round($total_time, 2),
        "rate_per_second" => $total_time > 0 ? round($processed / $total_time, 2) : 0,
        "message" => "Retry SMTP processing completed for list $csv_list_id",
        "ehlo_host" => $ehloHost,
        "mail_from" => $mailFrom
    ]);

} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
} finally {
    $conn->close();
}