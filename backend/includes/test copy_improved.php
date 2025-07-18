<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

$servername = "127.0.0.1";
$username = "root";
$password = "";
$dbname = "CRM";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "Connection failed: " . $conn->connect_error]));
}

// Configuration
define('MAX_WORKERS', 100);
define('DOMAINS_PER_WORKER', 1000);
define('WORKER_SCRIPT', __DIR__ . '/domain_worker.php');

set_time_limit(0);
ini_set('memory_limit', '512M');

if (!file_exists(WORKER_SCRIPT)) {
    $worker_code = '<?php
    $conn = new mysqli("127.0.0.1", "root", "", "CRM");
    if ($conn->connect_error) exit(1);

    $start_id = $argv[1] ?? 0;
    $end_id = $argv[2] ?? 0;

    $query = "SELECT id, sp_domain FROM emails WHERE id BETWEEN $start_id AND $end_id AND domain_verified = 0";
    $result = $conn->query($query);

    while ($row = $result->fetch_assoc()) {
        $domain = $row["sp_domain"];
        $ip = false;

        if (getmxrr($domain, $mxhosts)) {
            $mxIp = gethostbyname($mxhosts[0]);
            if (filter_var($mxIp, FILTER_VALIDATE_IP)) $ip = $mxIp;
        }

        if (!$ip) {
            $aRecord = gethostbyname($domain);
            if (filter_var($aRecord, FILTER_VALIDATE_IP)) $ip = $aRecord;
        }

        $status = $ip ? 1 : 0;
        $response = $ip ? $ip : "Invalid domain";

        $update = $conn->prepare("UPDATE emails SET domain_verified = 1, domain_status = ?, validation_response = ? WHERE id = ?");
        $update->bind_param("isi", $status, $response, $row["id"]);
        $update->execute();
        $update->close();
    }
    $conn->close();
    ?>';

    file_put_contents(WORKER_SCRIPT, $worker_code);
}

function get_id_ranges($conn, $batch_size)
{
    $ranges = [];
    $result = $conn->query("SELECT MIN(id) as min_id, MAX(id) as max_id FROM emails WHERE domain_verified = 0");
    $row = $result->fetch_assoc();
    if (!$row || $row['min_id'] === null || $row['max_id'] === null)
        return $ranges;

    for ($i = $row['min_id']; $i <= $row['max_id']; $i += $batch_size) {
        $end = min($i + $batch_size - 1, $row['max_id']);
        $ranges[] = ['start' => $i, 'end' => $end];
    }
    return $ranges;
}

function process_in_parallel($conn)
{
    $batch_size = DOMAINS_PER_WORKER;
    $ranges = get_id_ranges($conn, $batch_size);
    $processed = 0;
    $active_procs = [];
    $batch_idx = 0;

    while ($batch_idx < count($ranges) || count($active_procs) > 0) {
        while (count($active_procs) < MAX_WORKERS && $batch_idx < count($ranges)) {
            $range = $ranges[$batch_idx];
            $cmd = "php " . escapeshellarg(WORKER_SCRIPT) . " {$range['start']} {$range['end']}";
            $descriptorspec = [0 => ["pipe", "r"], 1 => ["pipe", "w"], 2 => ["pipe", "w"]];
            $proc = proc_open($cmd, $descriptorspec, $pipes);
            if (is_resource($proc)) {
                $active_procs[] = ['proc' => $proc, 'pipes' => $pipes];
                $processed += ($range['end'] - $range['start'] + 1);
            }
            $batch_idx++;
        }

        foreach ($active_procs as $key => $worker) {
            $status = proc_get_status($worker['proc']);
            if (!$status['running']) {
                foreach ($worker['pipes'] as $pipe)
                    fclose($pipe);
                proc_close($worker['proc']);
                unset($active_procs[$key]);
            }
        }

        usleep(100000);
        $active_procs = array_values($active_procs);
    }

    return $processed;
}

try {
    $conn->query("UPDATE csv_list SET status = 'running' WHERE status = 'pending'");

    $start_time = microtime(true);
    $processed = process_in_parallel($conn);
    $total_time = microtime(true) - $start_time;

    $total = $conn->query("SELECT COUNT(*) FROM emails")->fetch_row()[0];
    $verified = $conn->query("SELECT COUNT(*) FROM emails WHERE domain_verified = 1")->fetch_row()[0];

    echo json_encode([
        "status" => "success",
        "processed" => (int) $processed,
        "total_domains" => (int) $total,
        "verified_domains" => (int) $verified,
        "time_seconds" => round($total_time, 2),
        "rate_per_second" => round($processed / $total_time, 2),
        "message" => "Parallel processing completed"
    ]);

} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
} finally {
    $conn->close();
}
