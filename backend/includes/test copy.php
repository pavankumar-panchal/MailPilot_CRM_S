<?php
// Enable error reporting
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Set headers
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

// Database configuration
$dbConfig = [
    'host' => '127.0.0.1',
    'username' => 'root',
    'password' => '',
    'name' => 'CRM',
    'port' => 3306
];


// Log file configuration
define('LOG_FILE', __DIR__ . '/../storage/domain_verification.log');
logMessage("Script started");

// Database connection
try {
    $conn = new mysqli(
        $dbConfig['host'],
        $dbConfig['username'],
        $dbConfig['password'],
        $dbConfig['name'],
        $dbConfig['port']
    );

    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    http_response_code(500);
    logMessage("Database error: " . $e->getMessage());
    die(json_encode(['status' => 'error', 'message' => $e->getMessage()]));
}

// Set execution limits
set_time_limit(0);
ini_set('memory_limit', '512M');

/**
 * Log messages to file
 */
function logMessage($message)
{
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents(LOG_FILE, "[$timestamp] $message\n", FILE_APPEND);
}

/**
 * Verify a domain's DNS records
 */
function verifyDomain($domain)
{
    if (empty($domain)) {
        return ['status' => 0, 'ip' => null, 'error' => 'Empty domain'];
    }

    // Check MX records first
    if (getmxrr($domain, $mxhosts)) {
        $mxIp = gethostbyname($mxhosts[0]);
        if (filter_var($mxIp, FILTER_VALIDATE_IP)) {
            return ['status' => 1, 'ip' => $mxIp, 'error' => null];
        }
    }

    // Fallback to A record
    $aRecord = gethostbyname($domain);
    if (filter_var($aRecord, FILTER_VALIDATE_IP)) {
        return ['status' => 1, 'ip' => $aRecord, 'error' => null];
    }

    return ['status' => 0, 'ip' => null, 'error' => 'Invalid response'];
}

/**
 * Process domain verification sequentially (no parallel processing)
 */
function verifyDomains($conn)
{
    $processed = 0;
    $errors = 0;

    // Get unverified domains
    $query = "SELECT id, sp_domain FROM emails WHERE domain_verified = 0 LIMIT 1000";
    $result = $conn->query($query);

    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }

    logMessage("Found " . $result->num_rows . " domains to verify");

    $domains = [];
    while ($row = $result->fetch_assoc()) {
        $domains[] = $row;
    }

    if (count($domains) === 0) {
        return [
            'processed' => 0,
            'errors' => 0,
            'message' => "No domains to verify"
        ];
    }

    foreach ($domains as $row) {
        try {
            $verification = verifyDomain($row['sp_domain']);
            $response = $verification['ip'] ?? $verification['error'];

            $stmt = $conn->prepare("UPDATE emails SET 
                domain_verified = 1,
                domain_status = ?,
                validation_response = ?
                WHERE id = ?");

            if ($stmt === false) {
                logMessage("Prepare failed: " . $conn->error);
                $errors++;
                continue;
            }

            $status = (int) $verification['status'];
            $domainId = (int) $row['id'];
            $stmt->bind_param("isi", $status, $response, $domainId);

            if (!$stmt->execute()) {
                logMessage("Update failed for ID {$row['id']}: " . $stmt->error);
                $errors++;
            } else {
                $processed++;
            }

            $stmt->close();
            usleep(50000); // 50ms delay

        } catch (Throwable $e) {
            logMessage("Warning: " . $e->getMessage());
            $errors++;
        }
    }

    $totalProcessed = $conn->query("SELECT COUNT(*) FROM emails WHERE domain_verified = 1")->fetch_row()[0];
    $totalErrors = $conn->query("SELECT COUNT(*) FROM emails WHERE domain_verified = 0")->fetch_row()[0];

    return [
        'processed' => $totalProcessed,
        'errors' => $totalErrors,
        'message' => "Completed batch processing (sequential)"
    ];
}
try {
    $conn->query("UPDATE csv_list SET status = 'running' WHERE status = 'pending'");
    $startTime = microtime(true);
    $result = verifyDomains($conn);
    $duration = round(microtime(true) - $startTime, 2);

    // Get stats
    $total = $conn->query("SELECT COUNT(*) FROM emails")->fetch_row()[0];
    $verified = $conn->query("SELECT COUNT(*) FROM emails WHERE domain_verified = 1")->fetch_row()[0];

    $response = [
        'status' => 'success',
        'processed' => $result['processed'],
        'errors' => $result['errors'],
        'total_domains' => (int) $total,
        'verified_domains' => (int) $verified,
        'time_seconds' => $duration,
        'rate_per_second' => $duration > 0 ? round($result['processed'] / $duration, 2) : 0,
        'message' => $result['message']
    ];

    echo json_encode($response);

} catch (Throwable $e) { // Catch all errors, not just Exception
    http_response_code(500);
    logMessage("Warning: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'An internal error occurred. Please try again later.'
    ]);
} finally {
    logMessage("Script completed successfully");
    if (isset($conn)) {
        $conn->close();
    }
}