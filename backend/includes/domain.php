<?php
// Enable error reporting for debugging (disable in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set headers
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

// Database configuration
$dbConfig = [
    'host' => '127.0.0.1',
    'username' => 'email_id',
    'password' => '55y60jgW*',
    'name' => 'email_id',
    'port' => 3306
];

// Connect to database
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
} catch (Exception $e) {
    http_response_code(500);
    die(json_encode([
        'status' => 'error',
        'message' => 'Database connection error',
        'error' => $e->getMessage()
    ]));
}

// Set execution limits
set_time_limit(0);

ini_set('memory_limit', '256M');

// Function to verify a single domain
function verifyDomain($domain) {
    $ip = false;
    
    // First try MX records
    if (getmxrr($domain, $mxhosts)) {
        $mxIp = @gethostbyname($mxhosts[0]);
        if ($mxIp !== $mxhosts[0]) {
            $ip = $mxIp;
        }
    }
    
    // Fallback to A record
    if (!$ip) {
        $aRecord = @gethostbyname($domain);
        if ($aRecord !== $domain) {
            $ip = $aRecord;
        }
    }
    
    return $ip ? ['status' => 1, 'ip' => $ip] : ['status' => 0, 'ip' => false];
}

// Main verification function
function verifyDomains($conn) {
    $processed = 0;
    $errors = 0;
    
    // Update status to running
    $conn->query("UPDATE csv_list SET status = 'running' WHERE status = 'pending'");
    
    // Get unverified domains
    $query = "SELECT id, sp_domain FROM emails WHERE domain_verified = 0 LIMIT 1000";
    $result = $conn->query($query);
    
    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }
    
    while ($row = $result->fetch_assoc()) {
        try {
            $verification = verifyDomain($row['sp_domain']);
            
            $update = $conn->prepare("UPDATE emails SET 
                domain_verified = 1,
                domain_status = ?,
                validation_response = ?
                WHERE id = ?");
                
            $response = $verification['ip'] ? $verification['ip'] : "Invalid domain";
            $update->bind_param("isi", $verification['status'], $response, $row['id']);
            
            if (!$update->execute()) {
                $errors++;
                error_log("Update failed for ID {$row['id']}: " . $conn->error);
            } else {
                $processed++;
            }
            
            // Small delay to prevent overloading
            usleep(100000); // 0.1 second
            
        } catch (Exception $e) {
            $errors++;
            error_log("Error verifying {$row['sp_domain']}: " . $e->getMessage());
        }
    }
    
    return [
        'processed' => $processed,
        'errors' => $errors
    ];
}

// Main execution
try {
    $start = microtime(true);
    $result = verifyDomains($conn);
    $time = round(microtime(true) - $start, 2);
    
    // Get total count for response
    $totalResult = $conn->query("SELECT COUNT(*) as total FROM emails");
    $total = $totalResult ? $totalResult->fetch_row()[0] : 0;
    
       
    // Response
    echo json_encode([
        'status' => 'success',
        'processed' => $result['processed'],
        'errors' => $result['errors'],
        'total' => $total,
        'time_seconds' => $time,
        'rate_per_second' => $time > 0 ? round($result['processed'] / $time, 2) : 0
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'An error occurred during verification',
        'error' => $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>