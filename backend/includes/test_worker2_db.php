<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Kolkata');

echo "Testing database connection for WORKER_ID=2...\n";

require_once __DIR__ . '/../config/db.php';

echo "Database connected successfully!\n";
echo "Database: " . $dbConfig['name'] . " @ " . $dbConfig['host'] . "\n";

// Test a simple query
$result = $conn->query("SELECT DATABASE() as current_db, COUNT(*) as total FROM emails WHERE worker_id = 2");
if ($result) {
    $row = $result->fetch_assoc();
    echo "Current DB: " . $row['current_db'] . "\n";
    echo "Total emails for worker_id=2: " . $row['total'] . "\n";
} else {
    echo "Query failed: " . $conn->error . "\n";
}

$conn->close();
echo "Test complete!\n";
