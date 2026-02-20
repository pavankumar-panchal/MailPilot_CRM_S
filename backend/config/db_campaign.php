<?php

// Detect which physical server we're on
$server1_ip = '174.141.233.174';
$server2_ip = '207.244.80.245';

// Check if we're running on Server 1 or Server 2
$currentServerIp = gethostbyname(gethostname());
$isServer1 = ($currentServerIp === $server1_ip || file_exists('/var/www/vhosts/payrollsoft.in'));
$isServer2 = ($currentServerIp === $server2_ip || file_exists('/var/www/vhosts/relyonmail.xyz'));

// Auto-detect: If running locally (XAMPP), default to Server 1 behavior
if (!$isServer1 && !$isServer2) {
    $isServer1 = true; // Local dev connects to remote Server 2
}

// Campaign DB Configuration (Server 2 - Campaign high-traffic tables)
if ($isServer2) {
    // Running ON Server 2 - use localhost for Server 2 DB
    $heavy_host = '127.0.0.1';           // Local Server 2 DB
    error_log("[DB_CAMPAIGN] SERVER 2 detected → Connecting to SERVER 2 locally at 127.0.0.1");
} else {
    // Running ON Server 1 or Local Dev - connect remotely to Server 2 DB
    $heavy_host = '207.244.80.245';      // Remote Server 2 DB
    error_log("[DB_CAMPAIGN] SERVER 1/Local detected → Connecting to SERVER 2 remotely at 207.244.80.245");
}

$heavy_username = 'CRM';                 // Database username
$heavy_password = '55y60jgW*';           // Database password
$heavy_database = 'CRM';                 // Database name: CRM (for campaigns)

// Create connection to Server 2's mail_blaster database
$conn_heavy = new mysqli($heavy_host, $heavy_username, $heavy_password, $heavy_database);

if ($conn_heavy->connect_error) {
    error_log("CRITICAL: Campaign DB Connection to $heavy_host FAILED: " . $conn_heavy->connect_error);
    error_log("CRITICAL: Attempted credentials - Host: $heavy_host, User: $heavy_username, DB: $heavy_database");
    die("Campaign database connection failed - mail_blaster must be on Server 2!");
}

// Log successful connection for verification
error_log("✓ Campaign DB Connected - Host: $heavy_host, Database: $heavy_database");

$conn_heavy->set_charset("utf8mb4");

// Set optimal settings for high-traffic database
$conn_heavy->query("SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'");
$conn_heavy->query("SET SESSION innodb_lock_wait_timeout = 3"); // Short lock timeout

// CRITICAL VERIFICATION: Confirm we're connected to the right database
$verify_db = $conn_heavy->query("SELECT DATABASE() as current_db");
if ($verify_db) {
    $db_info = $verify_db->fetch_assoc();
    $actual_db = $db_info['current_db'];
    error_log("✓ Verified connected to database: $actual_db");
    
    if ($actual_db !== $heavy_database) {
        error_log("CRITICAL ERROR: Connected to WRONG database! Expected: $heavy_database, Got: $actual_db");
        die("Database connection error - connected to wrong database!");
    }
} else {
    error_log("WARNING: Could not verify database connection");
}

?>
