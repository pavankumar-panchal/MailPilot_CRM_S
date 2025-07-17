<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
set_time_limit(0);

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

// Database configuration
$servername = "127.0.0.1";
$username = "root";
$password = "";
$dbname = "CRM";
$log_dbname = "CRM_logs";

// Create main connection
$conn = new mysqli($servername, $username, $password, $dbname);
$conn->set_charset("utf8mb4");
if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "Connection failed: " . $conn->connect_error]));
}

// Create log connection
$log_conn = new mysqli($servername, $username, $password, $log_dbname);
$log_conn->set_charset("utf8mb4");
if ($log_conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "Log DB connection failed: " . $log_conn->connect_error]));
}

// Configuration
define('LOG_FILE', __DIR__ . '/../storage/retry_smtp.log');
ini_set('memory_limit', '512M');

// Logging function
function write_log($msg)
{
    $ts = date('Y-m-d H:i:s');
    file_put_contents(LOG_FILE, "[$ts] $msg\n", FILE_APPEND);
}

function insert_smtp_log($log_conn, $email, $steps, $validation, $validation_response)
{
    $stmt = $log_conn->prepare("INSERT INTO email_smtp_checks 
        (email, smtp_connection, ehlo, mail_from, rcpt_to, validation, validation_response) 
        VALUES (?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        write_log("Prepare failed for SMTP log: " . $log_conn->error);
        return false;
    }
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
    $success = $stmt->execute();
    if (!$success) {
        write_log("Execute failed for SMTP log: " . $stmt->error);
    }
    $stmt->close();
    return $success;
}

function verifyEmailViaSMTP($email, $domain, $conn, $log_conn)
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
        insert_smtp_log($log_conn, $email, $steps, "No valid MX or A record found", "No valid MX or A record found");
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
        insert_smtp_log($log_conn, $email, $steps, "Connection failed: $errstr", "Connection failed: $errstr");
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
        insert_smtp_log($log_conn, $email, $steps, "SMTP server not ready or no response", "SMTP server not ready or no response");
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
        insert_smtp_log($log_conn, $email, $steps, "EHLO failed", "EHLO failed");
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
        insert_smtp_log($log_conn, $email, $steps, "MAIL FROM failed", "MAIL FROM failed");
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
        insert_smtp_log($log_conn, $email, $steps, $ip, $validation_response);
        return [
            "status" => "valid",
            "result" => 1,
            "response" => $ip,
            "domain_status" => 1,
            "validation_status" => "valid",
            "validation_response" => $ip
        ];
    } else {
        insert_smtp_log($log_conn, $email, $steps, $rcpt_resp, $validation_response);
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

function process_emails($conn, $log_conn)
{
    $processed = 0;
    $batch_size = 100; // Process emails in batches of 100

    // Get total count of retryable emails
    $result = $conn->query("SELECT COUNT(*) as total FROM emails WHERE domain_status=2");
    $row = $result->fetch_assoc();
    $total = $row['total'];
    write_log("Starting SMTP processing for $total retryable emails");

    $offset = 0;
    while (true) {
        // Get batch of emails to process
        $query = "SELECT id, raw_emailid, sp_domain FROM emails WHERE domain_status=2 LIMIT $offset, $batch_size";
        $result = $conn->query($query);

        if (!$result || $result->num_rows == 0) {
            break; // No more emails to process
        }

        while ($row = $result->fetch_assoc()) {
            $email = $row["raw_emailid"];
            $domain = $row["sp_domain"];
            $email_id = $row["id"];

            $verify = verifyEmailViaSMTP($email, $domain, $conn, $log_conn);

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

        $offset += $batch_size;
    }

    return $processed;
}

// Update csv_list status and counts
function updateCsvListCounts($conn)
{
    $result = $conn->query("
        SELECT DISTINCT c.id
        FROM csv_list c
        JOIN emails e ON c.id = e.csv_list_id
    ");
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $campaignId = $row['id'];
            $stmt = $conn->prepare("
                SELECT 
                    COUNT(*) AS total_emails,
                    SUM(domain_status = 1) AS valid_count,
                    SUM(domain_status = 0) AS invalid_count
                FROM emails
                WHERE csv_list_id = ?
            ");
            $stmt->bind_param("i", $campaignId);
            $stmt->execute();
            $stmt->bind_result($total, $valid, $invalid);
            $stmt->fetch();
            $stmt->close();

            $valid = $valid ?? 0;
            $invalid = $invalid ?? 0;
            $total = $total ?? 0;

            $updateStmt = $conn->prepare("
                UPDATE csv_list 
                SET total_emails = ?, valid_count = ?, invalid_count = ?
                WHERE id = ?
            ");
            $updateStmt->bind_param("iiii", $total, $valid, $invalid, $campaignId);
            $updateStmt->execute();
            $updateStmt->close();
        }
    }
}

// Main execution
try {
    $start_time = microtime(true);
    $processed = process_emails($conn, $log_conn);
    $total_time = microtime(true) - $start_time;

    // Update campaign stats
    updateCsvListCounts($conn);

    // Check if all emails are processed (no more retryable)
    $remainingOtherStatus = $conn->query("SELECT COUNT(*) FROM emails WHERE domain_status=2")->fetch_row()[0];
    if ($remainingOtherStatus == 0) {
        $conn->query("UPDATE csv_list SET status = 'completed' WHERE status = 'running'");
    }

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
        "message" => "Retry SMTP processing completed"
    ]);

} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
} finally {
    $conn->close();
    $log_conn->close();
}