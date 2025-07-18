<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

// Database configuration
$servername = "127.0.0.1";
$username = "root";
$password = "";
$dbname = "CRM";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "Connection failed: " . $conn->connect_error]));
}

// Configuration
define('MAX_WORKERS', 100); // Optimal for most servers
define('DOMAINS_PER_WORKER', 1000);
define('WORKER_SCRIPT', __DIR__ . '/domain_worker.php');
define('LOG_FILE', __DIR__ . '/../storage/domain_parallel.log'); // Make sure this directory exists

// Set execution limits
set_time_limit(0);
ini_set('memory_limit', '512M');

// Create worker script if not exists
if (!file_exists(WORKER_SCRIPT)) {
    $worker_code = '<?php
    $servername = "127.0.0.1";
    $username = "root";
    $password = "";
    $dbname = "CRM";

    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) exit(1);

    $start_id = $argv[1] ?? 0;
    $end_id = $argv[2] ?? 0;

    $query = "SELECT id, sp_domain FROM emails 
              WHERE id BETWEEN $start_id AND $end_id 
              AND domain_verified = 0";
    $result = $conn->query($query);

    while ($row = $result->fetch_assoc()) {
        $domain = $row["sp_domain"];
        $ip = false;

        // Check MX records
        if (getmxrr($domain, $mxhosts)) {
            $mxIp = gethostbyname($mxhosts[0]);
            if (filter_var($mxIp, FILTER_VALIDATE_IP)) $ip = $mxIp;
        }

        // Fallback to A record
        if (!$ip) {
            $aRecord = gethostbyname($domain);
            if (filter_var($aRecord, FILTER_VALIDATE_IP)) $ip = $aRecord;
        }

        $status = $ip ? 1 : 0;
        $response = $ip ? $ip : "Invalid domain";

        $update = $conn->prepare("UPDATE emails SET 
                                domain_verified = 1,
                                domain_status = ?,
                                validation_response = ?
                                WHERE id = ?");
        $update->bind_param("isi", $status, $response, $row["id"]);
        $update->execute();
        $update->close();
    }
    $conn->close();
    ?>';

    file_put_contents(WORKER_SCRIPT, $worker_code);
}

function write_log($msg)
{
    $ts = date('Y-m-d H:i:s');
    file_put_contents(LOG_FILE, "[$ts] $msg\n", FILE_APPEND);
}

// Get ID range for parallel processing
function get_id_ranges($conn, $batch_size)
{
    $ranges = [];
    $result = $conn->query("SELECT MIN(id) as min_id, MAX(id) as max_id, COUNT(*) as total FROM emails WHERE domain_verified = 0");
    $row = $result->fetch_assoc();
    if (!$row || $row['min_id'] === null || $row['max_id'] === null)
        return $ranges;

    $total = $row['total'];
    write_log("Total emails to process: $total");

    for ($i = $row['min_id']; $i <= $row['max_id']; $i += $batch_size) {
        $end = min($i + $batch_size - 1, $row['max_id']);
        $ranges[] = [
            'start' => $i,
            'end' => $end,
            'count' => $end - $i + 1
        ];
    }
    write_log("Total batches: " . count($ranges) . ", Batch size: $batch_size");
    return $ranges;
}

// Improved parallel processing function
function process_in_parallel($conn)
{
    $batch_size = DOMAINS_PER_WORKER;
    $ranges = get_id_ranges($conn, $batch_size);
    $total_batches = count($ranges);
    $processed = 0;
    $active_procs = [];
    $batch_idx = 0;

    write_log("Starting parallel processing with MAX_WORKERS=" . MAX_WORKERS);

    while ($batch_idx < $total_batches || count($active_procs) > 0) {
        // Start new workers if under limit
        while (count($active_procs) < MAX_WORKERS && $batch_idx < $total_batches) {
            $range = $ranges[$batch_idx];
            $cmd = "php " . escapeshellarg(WORKER_SCRIPT) . " {$range['start']} {$range['end']}";
            $descriptorspec = [0 => ["pipe", "r"], 1 => ["pipe", "w"], 2 => ["pipe", "w"]];
            $proc = proc_open($cmd, $descriptorspec, $pipes);
            if (is_resource($proc)) {
                $active_procs[] = [
                    'proc' => $proc,
                    'pipes' => $pipes,
                    'range' => $range
                ];
                write_log("Started worker for IDs {$range['start']} - {$range['end']} ({$range['count']} emails)");
            }
            $processed += $range['count'];
            $batch_idx++;
        }

        // Check for finished workers
        foreach ($active_procs as $key => $worker) {
            $status = proc_get_status($worker['proc']);
            if (!$status['running']) {
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
                foreach ($worker['pipes'] as $pipe)
                    fclose($pipe);
                proc_close($worker['proc']);
                unset($active_procs[$key]);
            }
        }
        // Prevent busy waiting
        usleep(100000); // 0.1s
        $active_procs = array_values($active_procs); // reindex
    }

    // Log how many emails remain to process
    global $conn;
    $result = $conn->query("SELECT COUNT(*) as remaining FROM emails WHERE domain_verified = 0");
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
    $verified = $conn->query("SELECT COUNT(*) as verified FROM emails WHERE domain_verified = 1")->fetch_row()[0];

    echo json_encode([
        "status" => "success",
        "processed" => (int) $processed,
        "total_domains" => (int) $total,
        "verified_domains" => (int) $verified,
        "time_seconds" => round($total_time, 2),
        "rate_per_second" => round($processed / $total_time, 2),
        "message" => "Parallel processing completed"
    ]);

    // Start SMTP verification in background
    // exec('php includes/verify_smtp.php > /dev/null 2>&1 &');

} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
} finally {
    $conn->close();
}