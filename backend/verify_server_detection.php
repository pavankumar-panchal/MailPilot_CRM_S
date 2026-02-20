<?php
/**
 * Verify Server Detection and Database Configuration
 * Run this on both servers to confirm correct detection
 */

echo "========== SERVER DETECTION TEST ==========\n\n";

// Detect server
$server1_ip = '174.141.233.174';
$server2_ip = '207.244.80.245';
$currentServerIp = gethostbyname(gethostname());
$isServer1 = ($currentServerIp === $server1_ip || @file_exists('/var/www/vhosts/payrollsoft.in'));
$isServer2 = ($currentServerIp === $server2_ip || @file_exists('/var/www/vhosts/relyonmail.xyz'));

echo "Current hostname: " . gethostname() . "\n";
echo "Resolved IP: $currentServerIp\n\n";

echo "Detection Results:\n";
echo "  Is Server 1 (payrollsoft.in)? " . ($isServer1 ? 'YES ✓' : 'NO') . "\n";
echo "  Is Server 2 (relyonmail.xyz)? " . ($isServer2 ? 'YES ✓' : 'NO') . "\n";
echo "  Is Local Dev (XAMPP)?         " . (!$isServer1 && !$isServer2 ? 'YES ✓' : 'NO') . "\n\n";

// Load configs
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/db_campaign.php';

echo "========== DATABASE CONFIGURATION ==========\n\n";

echo "Server 1 DB (campaign_master, etc.):\n";
echo "  Host: " . $dbConfig['host'] . "\n";
echo "  Database: " . $dbConfig['name'] . "\n";
echo "  Type: " . ($dbConfig['host'] === '127.0.0.1' ? 'LOCAL' : 'REMOTE') . "\n\n";

echo "Server 2 DB (mail_blaster, smtp_*):\n";
echo "  Host: $heavy_host\n";
echo "  Database: $heavy_database\n";
echo "  Type: " . ($heavy_host === '127.0.0.1' ? 'LOCAL' : 'REMOTE') . "\n\n";

echo "========== EXPECTED CONFIGURATION ==========\n\n";

if ($isServer1) {
    echo "✓ On Server 1 - Expected:\n";
    echo "  - Server 1 DB: 127.0.0.1 (local)\n";
    echo "  - Server 2 DB: 207.244.80.245 (remote)\n\n";
    
    $correctS1 = ($dbConfig['host'] === '127.0.0.1');
    $correctS2 = ($heavy_host === '207.244.80.245');
    
    echo "Verification:\n";
    echo "  Server 1 DB: " . ($correctS1 ? '✓ CORRECT' : '✗ WRONG') . "\n";
    echo "  Server 2 DB: " . ($correctS2 ? '✓ CORRECT' : '✗ WRONG') . "\n";
    
} elseif ($isServer2) {
    echo "✓ On Server 2 - Expected:\n";
    echo "  - Server 1 DB: 174.141.233.174 (remote)\n";
    echo "  - Server 2 DB: 127.0.0.1 (local)\n\n";
    
    $correctS1 = ($dbConfig['host'] === '174.141.233.174');
    $correctS2 = ($heavy_host === '127.0.0.1');
    
    echo "Verification:\n";
    echo "  Server 1 DB: " . ($correctS1 ? '✓ CORRECT' : '✗ WRONG') . "\n";
    echo "  Server 2 DB: " . ($correctS2 ? '✓ CORRECT' : '✗ WRONG') . "\n";
    
} else {
    echo "✓ On Local Dev - Expected:\n";
    echo "  - Server 1 DB: 174.141.233.174 (remote)\n";
    echo "  - Server 2 DB: 207.244.80.245 (remote)\n\n";
    
    $correctS1 = ($dbConfig['host'] === '174.141.233.174');
    $correctS2 = ($heavy_host === '207.244.80.245');
    
    echo "Verification:\n";
    echo "  Server 1 DB: " . ($correctS1 ? '✓ CORRECT' : '✗ WRONG') . "\n";
    echo "  Server 2 DB: " . ($correctS2 ? '✓ CORRECT' : '✗ WRONG') . "\n";
}

echo "\n========== CONNECTION TEST ==========\n\n";

// Test Server 1 connection
echo "Testing Server 1 DB connection...\n";
if ($conn->connect_error) {
    echo "  ✗ FAILED: " . $conn->connect_error . "\n";
} else {
    echo "  ✓ CONNECTED\n";
    $result = $conn->query("SELECT COUNT(*) as cnt FROM campaign_master");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "  ✓ Query OK - {$row['cnt']} campaigns found\n";
    }
}

echo "\n";

// Test Server 2 connection
echo "Testing Server 2 DB connection...\n";
if ($conn_heavy->connect_error) {
    echo "  ✗ FAILED: " . $conn_heavy->connect_error . "\n";
} else {
    echo "  ✓ CONNECTED\n";
    $result = $conn_heavy->query("SELECT COUNT(*) as cnt FROM mail_blaster");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "  ✓ Query OK - {$row['cnt']} queue entries found\n";
    } else {
        echo "  ✗ Query FAILED: " . $conn_heavy->error . "\n";
    }
}

echo "\n========== TEST COMPLETE ==========\n";
