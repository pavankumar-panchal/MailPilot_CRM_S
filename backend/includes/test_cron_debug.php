<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Kolkata');

echo "Step 1: Starting script\n";

if (php_sapi_name() !== 'cli') {
    die("CLI only\n");
}

echo "Step 2: CLI check passed\n";

require_once __DIR__ . '/../config/db.php';

echo "Step 3: Config loaded\n";
echo "Database: " . $dbConfig['name'] . " @ " . $dbConfig['host'] . "\n";

if (!isset($conn) || $conn->connect_error) {
    die("Step 4: Connection failed: " . ($conn->connect_error ?? 'No connection') . "\n");
}

echo "Step 4: Database connected\n";

// Test query
$result = $conn->query("SELECT COUNT(*) as count FROM emails WHERE worker_id = 2 AND domain_processed = 0");
if ($result) {
    $row = $result->fetch_assoc();
    echo "Step 5: Found {$row['count']} pending emails for worker_id=2\n";
    $result->close();
} else {
    echo "Step 5: Query failed: " . $conn->error . "\n";
}

echo "Step 6: Testing lock file\n";
$lockFile = __DIR__ . '/../storage/cron.lock';
echo "Lock file path: $lockFile\n";
$lock = fopen($lockFile, 'c');
if (!$lock) {
    die("Step 7: Failed to open lock file\n");
}
echo "Step 7: Lock file opened\n";

if (!flock($lock, LOCK_EX | LOCK_NB)) {
    echo "Step 8: Lock acquired by another process\n";
    fclose($lock);
    exit(0);
}

echo "Step 8: Lock acquired successfully\n";
echo "All steps completed successfully!\n";

flock($lock, LOCK_UN);
fclose($lock);
