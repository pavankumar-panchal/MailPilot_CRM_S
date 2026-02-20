<?php
/**
 * Web-Accessible Database Connection Test
 * Access via: http://localhost/verify_emails/MailPilot_CRM_S/backend/test_db.php
 * 
 * Tests both Server 1 and Server 2 database connections
 */

// Set content type to HTML for better browser display
header('Content-Type: text/html; charset=utf-8');

error_reporting(E_ALL);
ini_set('display_errors', 1);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Connection Test</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: #252526;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.3);
        }
        h1 {
            color: #4ec9b0;
            border-bottom: 2px solid #4ec9b0;
            padding-bottom: 10px;
        }
        h2 {
            color: #569cd6;
            margin-top: 30px;
        }
        .success {
            color: #4ec9b0;
            font-weight: bold;
        }
        .error {
            color: #f48771;
            font-weight: bold;
        }
        .warning {
            color: #dcdcaa;
            font-weight: bold;
        }
        .info {
            color: #9cdcfe;
        }
        .indent {
            margin-left: 30px;
        }
        .code {
            background: #1e1e1e;
            padding: 15px;
            border-left: 3px solid #569cd6;
            margin: 10px 0;
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th {
            background: #1e1e1e;
            color: #4ec9b0;
            padding: 10px;
            text-align: left;
            border-bottom: 2px solid #4ec9b0;
        }
        td {
            padding: 8px 10px;
            border-bottom: 1px solid #3e3e42;
        }
        tr:hover {
            background: #2d2d30;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>========== DATABASE CONNECTION TEST ==========</h1>
        
        <?php
        // Track test status
        $hasErrors = false;
        $hasWarnings = false;
        
        // Load database configurations
        require_once __DIR__ . '/config/db.php';
        require_once __DIR__ . '/config/db_campaign.php';
        
        echo "<h2>1. Testing Server 1 Connection (Main DB)</h2>";
        echo "<div class='indent'>";
        echo "<p><span class='info'>Host:</span> " . htmlspecialchars($dbConfig['host']) . "</p>";
        echo "<p><span class='info'>Database:</span> " . htmlspecialchars($dbConfig['name']) . "</p>";
        
        if (!isset($conn) || !($conn instanceof mysqli)) {
            echo "<p class='error'>❌ FAILED: Connection object not initialized</p>";
            $hasErrors = true;
        } elseif ($conn->connect_error) {
            echo "<p class='error'>❌ FAILED: " . htmlspecialchars($conn->connect_error) . "</p>";
            $hasErrors = true;
        } else {
            echo "<p class='success'>✅ CONNECTED</p>";
            
            // Test a query
            $result = $conn->query("SELECT COUNT(*) as cnt FROM campaign_master");
            if ($result) {
                $row = $result->fetch_assoc();
                echo "<p class='success'>✅ Query OK - Found {$row['cnt']} campaigns</p>";
            } else {
                echo "<p class='error'>❌ Query FAILED: " . htmlspecialchars($conn->error) . "</p>";
                $hasErrors = true;
            }
        }
        echo "</div>";
        
        echo "<h2>2. Testing Server 2 Connection (Campaign DB)</h2>";
        echo "<div class='indent'>";
        echo "<p><span class='info'>Host:</span> " . htmlspecialchars($heavy_host) . "</p>";
        echo "<p><span class='info'>Database:</span> " . htmlspecialchars($heavy_database) . "</p>";
        
        if (!isset($conn_heavy) || !($conn_heavy instanceof mysqli)) {
            echo "<p class='error'>❌ FAILED: Connection object not initialized</p>";
            $hasErrors = true;
        } elseif ($conn_heavy->connect_error) {
            echo "<p class='error'>❌ FAILED: " . htmlspecialchars($conn_heavy->connect_error) . "</p>";
            $hasErrors = true;
        } else {
            echo "<p class='success'>✅ CONNECTED</p>";
            
            // Test if mail_blaster table exists
            $result = $conn_heavy->query("SHOW TABLES LIKE 'mail_blaster'");
            if ($result && $result->num_rows > 0) {
                echo "<p class='success'>✅ Table 'mail_blaster' exists</p>";
                
                // Count records
                $countResult = $conn_heavy->query("SELECT COUNT(*) as cnt FROM mail_blaster");
                if ($countResult) {
                    $row = $countResult->fetch_assoc();
                    echo "<p class='success'>✅ Query OK - Found {$row['cnt']} queue entries</p>";
                }
            } else {
                echo "<p class='warning'>⚠️  WARNING: Table 'mail_blaster' not found!</p>";
                echo "<div class='code'>Run: mysql -h $heavy_host -u $heavy_username -p $heavy_database &lt; campaign_heavy_tables.sql</div>";
                $hasWarnings = true;
            }
            
            // Test SMTP tables
            $result = $conn_heavy->query("SHOW TABLES LIKE 'smtp_accounts'");
            if ($result && $result->num_rows > 0) {
                echo "<p class='success'>✅ Table 'smtp_accounts' exists</p>";
                
                $countResult = $conn_heavy->query("SELECT COUNT(*) as cnt FROM smtp_accounts WHERE is_active = 1");
                if ($countResult) {
                    $row = $countResult->fetch_assoc();
                    echo "<p class='success'>✅ Found {$row['cnt']} active SMTP accounts</p>";
                    
                    // Show SMTP accounts details
                    if ($row['cnt'] > 0) {
                        $smtpResult = $conn_heavy->query("SELECT account_id, smtp_username, smtp_server, smtp_port, is_active, daily_limit FROM smtp_accounts WHERE is_active = 1 LIMIT 10");
                        if ($smtpResult) {
                            echo "<table>";
                            echo "<tr><th>ID</th><th>Username</th><th>Server</th><th>Port</th><th>Daily Limit</th><th>Status</th></tr>";
                            while ($smtp = $smtpResult->fetch_assoc()) {
                                echo "<tr>";
                                echo "<td>{$smtp['account_id']}</td>";
                                echo "<td>" . htmlspecialchars($smtp['smtp_username']) . "</td>";
                                echo "<td>" . htmlspecialchars($smtp['smtp_server']) . "</td>";
                                echo "<td>{$smtp['smtp_port']}</td>";
                                echo "<td>{$smtp['daily_limit']}</td>";
                                echo "<td><span class='success'>Active</span></td>";
                                echo "</tr>";
                            }
                            echo "</table>";
                        }
                    }
                }
            } else {
                echo "<p class='warning'>⚠️  WARNING: Table 'smtp_accounts' not found!</p>";
                $hasWarnings = true;
            }
        }
        echo "</div>";
        
        // Test recent campaigns
        if (!$hasErrors) {
            echo "<h2>3. Recent Campaigns</h2>";
            echo "<div class='indent'>";
            
            $recentResult = $conn->query("
                SELECT campaign_id, mail_subject, import_batch_id, csv_list_id, created_at 
                FROM campaign_master 
                ORDER BY campaign_id DESC 
                LIMIT 10
            ");
            
            if ($recentResult && $recentResult->num_rows > 0) {
                echo "<table>";
                echo "<tr><th>ID</th><th>Subject</th><th>Import Batch</th><th>CSV List</th><th>Created</th></tr>";
                while ($campaign = $recentResult->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>{$campaign['campaign_id']}</td>";
                    echo "<td>" . htmlspecialchars(substr($campaign['mail_subject'], 0, 50)) . "</td>";
                    echo "<td>" . ($campaign['import_batch_id'] ?: 'NULL') . "</td>";
                    echo "<td>" . ($campaign['csv_list_id'] ?: 'NULL') . "</td>";
                    echo "<td>" . htmlspecialchars($campaign['created_at']) . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<p class='warning'>⚠️  No campaigns found</p>";
            }
            
            echo "</div>";
        }
        
        echo "<h2>========== TEST COMPLETE ==========</h2>";
        
        // Display final status
        if ($hasErrors) {
            echo "<p class='error'>❌ Test completed with ERRORS!</p>";
            echo "<p>Please fix the errors above before proceeding.</p>";
        } elseif ($hasWarnings) {
            echo "<p class='warning'>⚠️  Test completed with WARNINGS!</p>";
            echo "<p>Connections are working but some issues were found.</p>";
        } else {
            echo "<p class='success'>✅ All connections working perfectly!</p>";
        }
        
        echo "<h2>Log Files to Check:</h2>";
        echo "<ul>";
        echo "<li>backend/logs/queue_init.log (queue initialization)</li>";
        echo "<li>PHP error log (campaign start errors)</li>";
        echo "<li>backend/logs/campaign_cron.log (cron monitoring)</li>";
        echo "</ul>";
        ?>
        
        <p style="margin-top: 30px; color: #858585; font-size: 12px;">
            Generated: <?php echo date('Y-m-d H:i:s'); ?>
        </p>
    </div>
</body>
</html>
