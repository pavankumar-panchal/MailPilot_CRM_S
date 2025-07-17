<?php
// Main database connection
$servername = "127.0.0.1";
$username = "root";
$password = "";
$dbname = "CRM";

// Log database connection
$log_dbname = "CRM_logs";

// Create main connection
$main_conn = new mysqli($servername, $username, $password, $dbname);
$main_conn->set_charset("utf8mb4");
if ($main_conn->connect_error) exit(1);

// Create log connection
$log_conn = new mysqli($servername, $username, $password, $log_dbname);
$log_conn->set_charset("utf8mb4");
if ($log_conn->connect_error) {
    log_worker("Log DB connection failed: " . $log_conn->connect_error, "CONNECTION");
    exit(1);
}

$start_id = $argv[1] ?? 0;
$end_id = $argv[2] ?? 0;

// Only process emails with domain_status=2 (retryable)
$query = "SELECT id, raw_emailid, sp_domain FROM emails WHERE id BETWEEN $start_id AND $end_id AND domain_status=2";
$result = $main_conn->query($query);

function log_worker($msg, $id_range = '') {
    $logfile = __DIR__ . "/../storage/retry_smtp_worker.log";
    $ts = date('Y-m-d H:i:s');
    file_put_contents($logfile, "[$ts][$id_range] $msg\n", FILE_APPEND);
}

function insert_smtp_log($log_conn, $email, $steps, $validation, $validation_response) {
    $stmt = $log_conn->prepare("INSERT INTO email_smtp_checks 
        (email, smtp_connection, ehlo, mail_from, rcpt_to, validation, validation_response) 
        VALUES (?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        log_worker("Prepare failed: " . $log_conn->error, "LOG_INSERT");
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
        log_worker("Execute failed: " . $stmt->error, "LOG_INSERT");
    }
    $stmt->close();
    return $success;
}

function verifyEmailViaSMTP($email, $domain, $main_conn, $log_conn) {
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

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $email = $row["raw_emailid"];
        $domain = $row["sp_domain"];
        $email_id = $row["id"];
        $verify = verifyEmailViaSMTP($email, $domain, $main_conn, $log_conn);

        // Sanitize validation_response
        if (isset($verify['validation_response'])) {
            $verify['validation_response'] = mb_convert_encoding($verify['validation_response'], 'UTF-8', 'UTF-8');
            $verify['validation_response'] = mb_substr($verify['validation_response'], 0, 1000, 'UTF-8');
        }

        $update = $main_conn->prepare("UPDATE emails SET 
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
    log_worker("Query failed: " . $main_conn->error, "$start_id-$end_id");
}

$main_conn->close();
$log_conn->close();




