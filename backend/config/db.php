<?php
error_reporting(0);

// Use Indian Standard Time across all PHP processes
date_default_timezone_set('Asia/Kolkata');

// Auto-detect environment based on server name or hostname
$isProduction = false;

// Check SERVER_NAME (for web requests)
if (isset($_SERVER['SERVER_NAME']) && strpos($_SERVER['SERVER_NAME'], 'payrollsoft.in') !== false) {
    $isProduction = true;
}

// Check hostname (for CLI/cron jobs)
if (!$isProduction && php_sapi_name() === 'cli') {
    $hostname = gethostname();
    if ($hostname && strpos($hostname, 'payrollsoft') !== false) {
        $isProduction = true;
    }
    // Also check if we can detect production by file path
    if (!$isProduction && strpos(__DIR__, 'httpdocs') !== false) {
        $isProduction = true;
    }
}

if ($isProduction) {
    // Production server (payrollsoft.in)
    $dbConfig = [
        'host' => '127.0.0.1',
        'username' => 'email_id',
        'password' => '55y60jgW*',
        'name' => 'email_id',
        'port' => 3306
    ];
} else {
    // Local development (XAMPP)
    $dbConfig = [
        'host' => '127.0.0.1',
        'username' => 'root',
        'password' => '',
        'name' => 'CRM',
        'port' => 3306
    ];
}

error_log("Database config loaded for: " . ($isProduction ? 'PRODUCTION' : 'LOCALHOST') . " - DB: " . $dbConfig['name'] . " (CLI: " . (php_sapi_name() === 'cli' ? 'YES' : 'NO') . ")");

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
