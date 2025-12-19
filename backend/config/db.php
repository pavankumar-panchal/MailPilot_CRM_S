<?php
error_reporting(0);

// Use Indian Standard Time across all PHP processes
date_default_timezone_set('Asia/Kolkata');

$dbConfig = [
    // 'host' => '127.0.0.1',
    // 'username' => 'email_id',
    // 'password' => '55y60jgW*',
    // 'name' => 'email_id',
    // 'port' => 3306


    'host' => '127.0.0.1',
    'username' => 'root',
    'password' => '',
    'name' => 'CRM',
    'port' => 3306

];

$conn = new mysqli($dbConfig['host'], $dbConfig['username'], $dbConfig['password'], $dbConfig['name'], $dbConfig['port']);

// Set connection timeout to prevent hanging connections
$conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);

// Check connection with detailed error logging
if ($conn->connect_error) {
    $error_msg = date('[Y-m-d H:i:s] ') . "Database Connection Failed\n";
    $error_msg .= "Error: " . $conn->connect_error . "\n";
    $error_msg .= "Host: " . $dbConfig['host'] . "\n";
    $error_msg .= "Username: " . $dbConfig['username'] . "\n";
    $error_msg .= "Database: " . $dbConfig['name'] . "\n";
    $error_msg .= str_repeat('-', 80) . "\n";

    // Log to file
    @error_log($error_msg, 3, __DIR__ . '/../logs/db_error.log');

    // Return JSON error for API calls
    $requestUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    if (strpos($requestUri, '/api/') !== false) {
        header('Content-Type: application/json');
        die(json_encode([
            'success' => false,
            'message' => 'Database connection failed',
            'error' => $conn->connect_error
        ]));
    } else {
        die("Database connection failed: " . $conn->connect_error);
    }
}

// Set proper character set for utf8mb4 support
$conn->set_charset("utf8mb4");

// Set wait_timeout and interactive_timeout to prevent stale connections
$conn->query("SET SESSION wait_timeout = 600");
$conn->query("SET SESSION interactive_timeout = 600");

// Ensure MySQL uses IST for CURDATE()/NOW()/CURTIME()
$conn->query("SET time_zone = '+05:30'");
