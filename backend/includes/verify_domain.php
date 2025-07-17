<?php


error_reporting(E_ALL);
ini_set('display_errors', 1);


header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

// require_once __DIR__ . '/../config/db.php';

error_reporting(0);
$servername = "127.0.0.1";  // Use IP instead of localhost
$username = "root";
$password = "";
$dbname = "CRM";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Configuration
define('MAX_WORKERS', 5); // Number of parallel processes
define('BATCH_SIZE', 100); // Domains per worker
define('WORKER_SCRIPT', __DIR__ . '/domain_worker.php');

// Optimization settings
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
set_time_limit(0);
ini_set('memory_limit', '512M');

// Command line output
function consoleOutput($message)
{
    if (php_sapi_name() === 'cli') {
        echo $message . PHP_EOL;
    }
}

// Get first IP for a domain (MX or A record)
function getDomainIP($domain)
{
    // Check MX records first
    if (getmxrr($domain, $mxhosts)) {
        $mxIp = @gethostbyname($mxhosts[0]);
        if ($mxIp !== $mxhosts[0]) {
            return $mxIp;
        }
    }
    // Fallback to A record
    $aRecord = @gethostbyname($domain);
    return ($aRecord !== $domain) ? $aRecord : false;
}

// Create worker script for parallel processing
function createDomainWorkerScript()
{
    $workerCode = '<?php
    require __DIR__ . \'/../db.php\';
    
    $offset = $argv[1] ?? 0;
    $limit = $argv[2] ?? ' . BATCH_SIZE . ';
    
    // Only process domains that need verification
    $domains = $conn->query("SELECT id, sp_domain FROM emails WHERE domain_verified = 0 LIMIT $offset, $limit");
    
    while ($row = $domains->fetch_assoc()) {
        $domain = $row[\'sp_domain\'];
        $ip = false;
        
        // Basic hostname validation
        if (filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            $ip = getDomainIP($domain);
        }
        
        $status = $ip ? 1 : 0;
        $response = $ip ?: "Not valid domain";
        
        $conn->query("UPDATE emails SET 
                     domain_verified = 1,
                     domain_status = $status,
                     validation_response = \'" . $conn->real_escape_string($response) . "\'
                     WHERE id = {$row[\'id\']}");
    }
    
    function getDomainIP($domain) {
        // Check MX records first
        if (getmxrr($domain, $mxhosts)) {
            $mxIp = @gethostbyname($mxhosts[0]);
            if ($mxIp !== $mxhosts[0]) {
                return $mxIp;
            }
        }
    
        // Fallback to A record
        $aRecord = @gethostbyname($domain);
        return ($aRecord !== $domain) ? $aRecord : false;
    }
    ?>';

    file_put_contents(WORKER_SCRIPT, $workerCode);
}

// Parallel processing function
function processDomainsInParallel()
{
    global $conn;

    if (!file_exists(WORKER_SCRIPT)) {
        createDomainWorkerScript();
    }

    // Count unverified domains
    $total = $conn->query("SELECT COUNT(*) FROM emails WHERE domain_verified = 0")->fetch_row()[0];
    consoleOutput("Total domains to process: $total");

    if ($total == 0) {
        consoleOutput("All domains have already been verified.");
        return 0;
    }

    $batches = ceil($total / BATCH_SIZE);
    $workers = min(MAX_WORKERS, $batches);
    $procs = [];
    $processed = 0;

    for ($i = 0; $i < $batches; $i++) {
        $offset = $i * BATCH_SIZE;
        $cmd = "php " . WORKER_SCRIPT . " $offset " . BATCH_SIZE;
        $procs[] = proc_open($cmd, [], $pipes);

        if (count($procs) >= $workers) {
            proc_close(array_shift($procs));
            $processed += BATCH_SIZE;
            consoleOutput("Processed: $processed of $total");
        }
    }

    while (count($procs) > 0) {
        proc_close(array_shift($procs));
        $processed += BATCH_SIZE;
        consoleOutput("Processed: $processed of $total");
    }

    return $processed;
}

// Set all pending csv_list to running before verification starts
$conn->query("UPDATE csv_list SET status = 'running' WHERE status = 'pending'");

// Main execution
try {
    if ($conn->connect_error) {
        throw new Exception("Database connection failed");
    }

    $start = microtime(true);
    $processed = processDomainsInParallel();
    $time = microtime(true) - $start;

    // Get total count for response
    $totalResult = $conn->query("SELECT COUNT(*) as total FROM emails");
    $total = $totalResult->fetch_assoc()['total'];

    echo json_encode([
        "status" => "success",
        "processed" => $processed,
        "total" => $total,
        "time_seconds" => round($time, 2),
        "rate_per_second" => round($processed / $time, 2)
    ]);

} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}


$conn->close();


exec('php  includes/verify_smtp.php > /dev/null 2>&1 &');

?>