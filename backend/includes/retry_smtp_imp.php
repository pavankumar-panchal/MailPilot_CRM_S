<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
set_time_limit(0);

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

// Use the provided DB config
$dbConfig = [
    'host' => '127.0.0.1',
    'username' => 'root',
    'password' => '',
    'name' => 'CRM',
    'port' => 3306
];

define('BATCH_SIZE', 1000);
define('MAX_PROCESSES', 10); // Parallel processing
define('LOG_FILE', __DIR__ . '/../storage/domain_verification.log');
define('RETRY_LOG_FILE', __DIR__ . '/../storage/retry.log');

// --- LOGGING ---

function logMessage($message)
{
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents(LOG_FILE, "[$timestamp] $message\n", FILE_APPEND);
}

function logRetry($message)
{
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents(RETRY_LOG_FILE, "[$timestamp] $message\n", FILE_APPEND);
}

// --- DOMAIN VERIFICATION ---
function getDomainIP($domain)
{
    if (getmxrr($domain, $mxhosts)) {
        $mxIp = @gethostbyname($mxhosts[0]);
        if ($mxIp !== $mxhosts[0]) {
            return $mxIp;
        }
    }
    $aRecord = @gethostbyname($domain);
    return ($aRecord !== $domain) ? $aRecord : false;
}

function processDomainsParallel($dbConfig)
{
    $mainConn = new mysqli(
        $dbConfig['host'],
        $dbConfig['username'],
        $dbConfig['password'],
        $dbConfig['name'],
        $dbConfig['port']
    );
    $mainConn->set_charset("utf8mb4");

    $totalDomains = $mainConn->query("SELECT COUNT(*) FROM emails WHERE domain_verified = 0")->fetch_row()[0];
    if ($totalDomains == 0) {
        logMessage("No domains to verify.");
        return 0;
    }

    $batches = ceil($totalDomains / BATCH_SIZE);
    $processes = min(MAX_PROCESSES, $batches);
    logMessage("Domain verification started: $totalDomains domains, $batches batches, $processes processes.");

    $processed = 0;
    $children = [];

    for ($i = 0; $i < $batches; $i++) {
        if (MAX_PROCESSES > 1) {
            $pid = pcntl_fork();
            if ($pid == -1) {
                logMessage("Could not fork process for batch $i");
                continue;
            } elseif ($pid) {
                $children[] = $pid;
                if (count($children) >= MAX_PROCESSES) {
                    pcntl_wait($status);
                    array_shift($children);
                }
                continue;
            }
        }

        // Child or sequential
        $conn = new mysqli(
            $dbConfig['host'],
            $dbConfig['username'],
            $dbConfig['password'],
            $dbConfig['name'],
            $dbConfig['port']
        );
        $conn->set_charset("utf8mb4");

        $domains = $conn->query("SELECT id, sp_domain FROM emails WHERE domain_verified = 0 LIMIT " . BATCH_SIZE);
        if (!$domains) {
            logMessage("Batch $i: Query failed: " . $conn->error);
            if (MAX_PROCESSES > 1)
                exit(1);
            continue;
        }

        while ($row = $domains->fetch_assoc()) {
            $domain = $row['sp_domain'];
            $ip = false;
            if (filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
                $ip = getDomainIP($domain);
            }
            $status = $ip ? 1 : 0;
            $response = $ip ?: "Not valid domain";

            $update = $conn->prepare("UPDATE emails SET 
                domain_verified = 1,
                domain_status = ?,
                validation_response = ?
                WHERE id = ?");
            if (!$update) {
                logMessage("Batch $i: Prepare failed for ID {$row['id']}: " . $conn->error);
                continue;
            }
            $update->bind_param("isi", $status, $response, $row['id']);
            if (!$update->execute()) {
                logMessage("Batch $i: Update failed for ID {$row['id']}: " . $update->error);
            }
            $update->close();
            $processed++;
            usleep(20000); // 20ms delay
        }
        $conn->close();
        if (MAX_PROCESSES > 1)
            exit(0);
    }

    // Parent waits for all children
    foreach ($children as $child) {
        pcntl_waitpid($child, $status);
    }
    $mainConn->close();
    logMessage("Domain verification completed.");
    return $processed;
}

// --- SMTP VERIFICATION ---
function verifyEmailViaSMTP($email, $domain)
{
    if (!getmxrr($domain, $mxhosts)) {
        logMessage("SMTP: No MX record for $domain");
        return ["status" => "error", "message" => "No MX record found"];
    }
    $mxIP = gethostbyname($mxhosts[0]);
    $port = 25;
    $timeout = 30;

    $smtp = @stream_socket_client(
        "tcp://$mxIP:$port",
        $errno,
        $errstr,
        $timeout
    );
    if (!$smtp) {
        logMessage("SMTP: Connection failed for $email@$domain: $errstr");
        return ["status" => "error", "message" => "Connection failed: $errstr"];
    }

    stream_set_timeout($smtp, $timeout);
    $response = fgets($smtp, 4096);
    if (substr($response, 0, 3) != "220") {
        fclose($smtp);
        logMessage("SMTP: Server not ready for $email@$domain");
        return ["status" => "error", "message" => "SMTP server not ready"];
    }

    fputs($smtp, "EHLO server.relyon.co.in\r\n");
    while ($line = fgets($smtp, 4096)) {
        if (substr($line, 3, 1) == " ")
            break;
    }
    fputs($smtp, "MAIL FROM:<info@relyon.co.in>\r\n");
    fgets($smtp, 4096);
    fputs($smtp, "RCPT TO:<$email>\r\n");
    $response = fgets($smtp, 4096);
    $result = (substr($response, 0, 3) == "250") ? 1 : 0;
    fputs($smtp, "QUIT\r\n");
    fclose($smtp);

    return $result === 1
        ? ["status" => "success", "result" => 1, "message" => $mxIP]
        : ["status" => "success", "result" => 0, "message" => "Invalid response"];
}

function processSmtpParallel($dbConfig)
{
    $mainConn = new mysqli(
        $dbConfig['host'],
        $dbConfig['username'],
        $dbConfig['password'],
        $dbConfig['name'],
        $dbConfig['port']
    );
    $mainConn->set_charset("utf8mb4");

    $totalEmails = $mainConn->query("SELECT COUNT(*) FROM emails WHERE domain_status=2 AND domain_processed=1")->fetch_row()[0];
    if ($totalEmails == 0) {
        logMessage("No emails to verify via SMTP.");
        return 0;
    }

    $batches = ceil($totalEmails / BATCH_SIZE);
    $processes = min(MAX_PROCESSES, $batches);
    logMessage("SMTP verification started: $totalEmails emails, $batches batches, $processes processes.");

    $processed = 0;
    $children = [];

    for ($i = 0; $i < $batches; $i++) {
        if (MAX_PROCESSES > 1) {
            $pid = pcntl_fork();
            if ($pid == -1) {
                logMessage("Could not fork process for SMTP batch $i");
                continue;
            } elseif ($pid) {
                $children[] = $pid;
                if (count($children) >= MAX_PROCESSES) {
                    pcntl_wait($status);
                    array_shift($children);
                }
                continue;
            }
        }

        // Child or sequential
        $conn = new mysqli(
            $dbConfig['host'],
            $dbConfig['username'],
            $dbConfig['password'],
            $dbConfig['name'],
            $dbConfig['port']
        );
        $conn->set_charset("utf8mb4");

        $emails = $conn->query("SELECT id, raw_emailid, sp_domain FROM emails WHERE domain_status=2 AND domain_processed=1 LIMIT " . BATCH_SIZE);
        if (!$emails) {
            logMessage("SMTP Batch $i: Query failed: " . $conn->error);
            if (MAX_PROCESSES > 1)
                exit(1);
            continue;
        }

        while ($row = $emails->fetch_assoc()) {
            $email = $row['raw_emailid'];
            $domain = $row['sp_domain'];
            $verification = verifyEmailViaSMTP($email, $domain);

            if ($verification['status'] === 'success') {
                $status = $verification['result'];
                $message = $conn->real_escape_string($verification['message']);
            } else {
                $status = 0;
                $message = "Verification failed";
            }

            // Force status to 0 if not verified (no more retries)
            if ($status != 1) {
                $status = 0;
            }

            $update = $conn->prepare("UPDATE emails SET 
                domain_status = ?,
                validation_response = ?,
                domain_processed = 1
                WHERE id = ?");
            if (!$update) {
                logMessage("SMTP Batch $i: Prepare failed for ID {$row['id']}: " . $conn->error);
                continue;
            }
            $update->bind_param("isi", $status, $message, $row['id']);
            if (!$update->execute()) {
                logMessage("SMTP Batch $i: Update failed for ID {$row['id']}: " . $update->error);
            }
            $update->close();
            $processed++;
            usleep(50000); // 50ms delay for SMTP
        }
        $conn->close();
        if (MAX_PROCESSES > 1)
            exit(0);
    }

    foreach ($children as $child) {
        pcntl_waitpid($child, $status);
    }
    $mainConn->close();
    logMessage("SMTP verification completed.");
    return $processed;
}

// --- UPDATE CSV LIST COUNTS ---
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

// --- MAIN EXECUTION ---
try {
    $start = microtime(true);

    // 1. DOMAIN VERIFICATION (parallel)
    $domainProcessed = processDomainsParallel($dbConfig);
    $domainTime = microtime(true) - $start;

    // 2. SMTP VERIFICATION (only for domain_status = 2)
    $conn = new mysqli(
        $dbConfig['host'],
        $dbConfig['username'],
        $dbConfig['password'],
        $dbConfig['name'],
        $dbConfig['port']
    );
    $conn->set_charset("utf8mb4");

    // Only process SMTP where domain_status = 2
    $pendingSmtp = $conn->query("SELECT COUNT(*) FROM emails WHERE domain_status = 2")->fetch_row()[0];
    $smtpProcessed = 0;
    $smtpTime = 0;

    if ($pendingSmtp > 0) {
        $startSmtp = microtime(true);
        $smtpProcessed = processSmtpParallel($dbConfig);
        $smtpTime = microtime(true) - $startSmtp;
    }

    // 3. UPDATE COUNTS
    updateCsvListCounts($conn);

    // 4. ONLY update csv_list to completed when ALL domain_status are 0 or 1
    $remainingOtherStatus = $conn->query("SELECT COUNT(*) FROM emails WHERE domain_status NOT IN (0,1)")->fetch_row()[0];
    if ($remainingOtherStatus == 0) {
        $conn->query("UPDATE csv_list SET status = 'completed' WHERE status = 'running'");
    }

    // 5. RESPONSE
    $totalResult = $conn->query("SELECT COUNT(*) as total FROM emails");
    $total = $totalResult->fetch_assoc()['total'];

    logMessage("Script completed successfully.");

    echo json_encode([
        "status" => "success",
        "domain_processed" => $domainProcessed,
        "smtp_processed" => $smtpProcessed,
        "total" => $total,
        "domain_time_seconds" => round($domainTime, 2),
        "smtp_time_seconds" => round($smtpTime, 2)
    ]);

    $conn->close();
} catch (Exception $e) {
    logMessage("ERROR: " . $e->getMessage());
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}

// Retry process (if needed)
logRetry("Retry process started.");

header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['ids']) || !is_array($data['ids'])) {
    echo json_encode(['status' => 'error', 'message' => 'No IDs provided']);
    exit;
}

$ids = $data['ids'];
// Example: update status to 'pending' for retry
require_once __DIR__ . '/../db.php'; // adjust path as needed

foreach ($ids as $id) {
    $stmt = $pdo->prepare("UPDATE csv_lists SET status = 'pending', domain_status = 0 WHERE id = ?");
    $stmt->execute([$id]);
    // Optionally, reset other fields as needed
}

// Optionally, trigger your verification process here

echo json_encode(['status' => 'success', 'message' => 'Retry started']);