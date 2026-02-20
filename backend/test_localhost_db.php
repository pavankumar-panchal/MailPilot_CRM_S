<?php
/**
 * Localhost Database Connection Test
 * 
 * Run this file to verify your localhost database connections are working correctly.
 * Access via: http://localhost/verify_emails/MailPilot_CRM_S/backend/test_localhost_db.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Localhost Database Connection Test</h1>";
echo "<p>Testing database connections on localhost...</p>";

// Test Server 1 Database (email_id)
echo "<h2>1. Testing Server 1 Database (email_id)</h2>";
require_once __DIR__ . '/config/db.php';

if ($conn->connect_error) {
    echo "<p style='color: red;'>❌ <strong>FAILED:</strong> " . htmlspecialchars($conn->connect_error) . "</p>";
    echo "<p><strong>Troubleshooting:</strong></p>";
    echo "<ul>";
    echo "<li>Make sure MySQL is running in XAMPP Control Panel</li>";
    echo "<li>Verify database 'email_id' exists in phpMyAdmin</li>";
    echo "<li>Check username and password in backend/config/db.php</li>";
    echo "</ul>";
} else {
    echo "<p style='color: green;'>✅ <strong>SUCCESS:</strong> Connected to database</p>";
    
    // Get database info
    $result = $conn->query("SELECT DATABASE() as current_db, VERSION() as mysql_version");
    if ($result) {
        $info = $result->fetch_assoc();
        echo "<ul>";
        echo "<li><strong>Database:</strong> " . htmlspecialchars($info['current_db']) . "</li>";
        echo "<li><strong>MySQL Version:</strong> " . htmlspecialchars($info['mysql_version']) . "</li>";
        echo "<li><strong>Host:</strong> " . htmlspecialchars($conn->host_info) . "</li>";
        echo "</ul>";
        
        // Check for required tables
        echo "<h3>Checking Required Tables:</h3>";
        $tables = [
            'campaign_master',
            'campaign_status',
            'imported_recipients',
            'emails',
            'users',
            'mail_templates',
            'csv_list'
        ];
        
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr><th>Table Name</th><th>Status</th><th>Row Count</th></tr>";
        
        foreach ($tables as $table) {
            $check = $conn->query("SHOW TABLES LIKE '$table'");
            if ($check && $check->num_rows > 0) {
                $count_result = $conn->query("SELECT COUNT(*) as cnt FROM `$table`");
                $count = $count_result ? $count_result->fetch_assoc()['cnt'] : 0;
                echo "<tr>";
                echo "<td>$table</td>";
                echo "<td style='color: green;'>✅ Exists</td>";
                echo "<td>$count rows</td>";
                echo "</tr>";
            } else {
                echo "<tr>";
                echo "<td>$table</td>";
                echo "<td style='color: red;'>❌ Missing</td>";
                echo "<td>-</td>";
                echo "</tr>";
            }
        }
        echo "</table>";
    }
}

// Test Server 2 Database (CRM)
echo "<h2>2. Testing Server 2 Database (CRM)</h2>";
require_once __DIR__ . '/config/db_campaign.php';

if ($conn_heavy->connect_error) {
    echo "<p style='color: red;'>❌ <strong>FAILED:</strong> " . htmlspecialchars($conn_heavy->connect_error) . "</p>";
    echo "<p><strong>Troubleshooting:</strong></p>";
    echo "<ul>";
    echo "<li>Make sure MySQL is running in XAMPP Control Panel</li>";
    echo "<li>Verify database 'CRM' exists in phpMyAdmin</li>";
    echo "<li>Check username and password in backend/config/db_campaign.php</li>";
    echo "</ul>";
} else {
    echo "<p style='color: green;'>✅ <strong>SUCCESS:</strong> Connected to database</p>";
    
    // Get database info
    $result = $conn_heavy->query("SELECT DATABASE() as current_db, VERSION() as mysql_version");
    if ($result) {
        $info = $result->fetch_assoc();
        echo "<ul>";
        echo "<li><strong>Database:</strong> " . htmlspecialchars($info['current_db']) . "</li>";
        echo "<li><strong>MySQL Version:</strong> " . htmlspecialchars($info['mysql_version']) . "</li>";
        echo "<li><strong>Host:</strong> " . htmlspecialchars($conn_heavy->host_info) . "</li>";
        echo "</ul>";
        
        // Check for required tables
        echo "<h3>Checking Required Tables:</h3>";
        $tables = [
            'mail_blaster',
            'smtp_accounts',
            'smtp_servers',
            'smtp_health',
            'smtp_rotation',
            'smtp_usage'
        ];
        
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr><th>Table Name</th><th>Status</th><th>Row Count</th></tr>";
        
        foreach ($tables as $table) {
            $check = $conn_heavy->query("SHOW TABLES LIKE '$table'");
            if ($check && $check->num_rows > 0) {
                $count_result = $conn_heavy->query("SELECT COUNT(*) as cnt FROM `$table`");
                $count = $count_result ? $count_result->fetch_assoc()['cnt'] : 0;
                echo "<tr>";
                echo "<td>$table</td>";
                echo "<td style='color: green;'>✅ Exists</td>";
                echo "<td>$count rows</td>";
                echo "</tr>";
            } else {
                echo "<tr>";
                echo "<td>$table</td>";
                echo "<td style='color: red;'>❌ Missing</td>";
                echo "<td>-</td>";
                echo "</tr>";
            }
        }
        echo "</table>";
    }
}

// Final Summary
echo "<h2>Summary</h2>";
$server1_ok = !$conn->connect_error;
$server2_ok = !$conn_heavy->connect_error;

if ($server1_ok && $server2_ok) {
    echo "<p style='color: green; font-size: 18px; font-weight: bold;'>🎉 All database connections successful! Your localhost setup is ready.</p>";
    echo "<p><strong>Next Steps:</strong></p>";
    echo "<ol>";
    echo "<li>Create a test user in the 'users' table</li>";
    echo "<li>Add SMTP server configuration in 'smtp_servers' table</li>";
    echo "<li>Add SMTP account in 'smtp_accounts' table</li>";
    echo "<li>Try creating a campaign through the frontend</li>";
    echo "</ol>";
} else {
    echo "<p style='color: red; font-size: 18px; font-weight: bold;'>❌ Some database connections failed. Please fix the errors above.</p>";
    
    if (!$server1_ok) {
        echo "<h3>How to create 'email_id' database:</h3>";
        echo "<ol>";
        echo "<li>Open phpMyAdmin: <a href='http://localhost/phpmyadmin' target='_blank'>http://localhost/phpmyadmin</a></li>";
        echo "<li>Click 'New' to create a new database</li>";
        echo "<li>Name it 'email_id'</li>";
        echo "<li>Choose collation: utf8mb4_unicode_ci</li>";
        echo "<li>Import the SQL file from the first SQL dump you provided</li>";
        echo "</ol>";
    }
    
    if (!$server2_ok) {
        echo "<h3>How to create 'CRM' database:</h3>";
        echo "<ol>";
        echo "<li>Open phpMyAdmin: <a href='http://localhost/phpmyadmin' target='_blank'>http://localhost/phpmyadmin</a></li>";
        echo "<li>Click 'New' to create a new database</li>";
        echo "<li>Name it 'CRM'</li>";
        echo "<li>Choose collation: utf8mb4_general_ci</li>";
        echo "<li>Import the SQL file from the second SQL dump you provided</li>";
        echo "</ol>";
    }
}

echo "<hr>";
echo "<p><em>Generated: " . date('Y-m-d H:i:s') . "</em></p>";
?>
