<?php
/**
 * Test Server 2 Connection
 * Run this to verify $conn_heavy is connecting to the correct server
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Testing Server 2 Connection</h2>";

require_once __DIR__ . '/config/db_campaign.php';

echo "<h3>Connection Details:</h3>";
echo "<pre>";
echo "Expected Host: 207.244.80.245 (Server 2)\n";
echo "Expected Database: CRM\n";
echo "Expected User: CRM\n";
echo "\n";

// Verify actual connection
$actual_db = $conn_heavy->query("SELECT DATABASE() as db")->fetch_assoc()['db'];
$actual_host = $conn_heavy->query("SELECT @@hostname as host")->fetch_assoc()['host'];
$actual_user = $conn_heavy->query("SELECT USER() as user")->fetch_assoc()['user'];

echo "✓ Actual Database: $actual_db\n";
echo "✓ Actual Host: $actual_host\n";
echo "✓ Actual User: $actual_user\n";
echo "</pre>";

// Check if mail_blaster table exists
$tableCheck = $conn_heavy->query("SHOW TABLES LIKE 'mail_blaster'");
if ($tableCheck && $tableCheck->num_rows > 0) {
    echo "<h3>✓ mail_blaster table EXISTS in database: $actual_db</h3>";
    
    // Count rows
    $count = $conn_heavy->query("SELECT COUNT(*) as cnt FROM mail_blaster")->fetch_assoc()['cnt'];
    echo "<p>Total emails in mail_blaster: <strong>$count</strong></p>";
    
    // Show sample data
    $sample = $conn_heavy->query("SELECT * FROM mail_blaster LIMIT 5");
    echo "<h3>Sample Data (first 5 rows):</h3>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Campaign ID</th><th>To Email</th><th>Status</th><th>Delivery Time</th></tr>";
    while ($row = $sample->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['campaign_id']}</td>";
        echo "<td>{$row['to_mail']}</td>";
        echo "<td>{$row['status']}</td>";
        echo "<td>{$row['delivery_time']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<h3>❌ mail_blaster table NOT FOUND in database: $actual_db</h3>";
    echo "<p>This is WRONG! mail_blaster should exist on Server 2.</p>";
}

// Check if we're on the right server
echo "<h3>Server Detection:</h3>";
echo "<pre>";
$currentServerIp = gethostbyname(gethostname());
echo "Current Server IP: $currentServerIp\n";
echo "Server 1 IP: 174.141.233.174\n";
echo "Server 2 IP: 207.244.80.245\n";

if ($currentServerIp === '174.141.233.174') {
    echo "\n⚠️ You are on SERVER 1 - should connect remotely to Server 2\n";
} elseif ($currentServerIp === '207.244.80.245') {
    echo "\n✓ You are on SERVER 2 - should use localhost\n";
} else {
    echo "\n⚠️ You are on LOCAL DEV - should connect remotely to Server 2\n";
}
echo "</pre>";

echo "<h3>Summary:</h3>";
if ($actual_db === 'CRM' && strpos($actual_user, 'CRM') !== false) {
    echo "<p style='color: green; font-size: 18px;'><strong>✓ CORRECT: Connected to Server 2's CRM database!</strong></p>";
} else {
    echo "<p style='color: red; font-size: 18px;'><strong>❌ WRONG: Not connected to Server 2's CRM database!</strong></p>";
    echo "<p>Current database: $actual_db (should be: CRM)</p>";
}

?>
