<?php
/**
 * Test Database Connections
 * Run this script to verify both database connections are working
 * 
 * Usage: php backend/test_db_connections.php [campaign_id]
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "========== DATABASE CONNECTION TEST ==========\n\n";

// Track test status
$hasErrors = false;
$hasWarnings = false;

// Load database configurations
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/db_campaign.php';

// Test Server 1 Connection
echo "1. Testing Server 1 Connection (Main DB)...\n";
echo "   Host: " . ($dbConfig['host'] ?? 'Unknown') . "\n";
echo "   Database: " . ($dbConfig['name'] ?? 'Unknown') . "\n";

if (!isset($conn) || !($conn instanceof mysqli)) {
    echo "   ❌ FAILED: Connection object not initialized\n\n";
    $hasErrors = true;
    exit(1);
} elseif ($conn->connect_error) {
    echo "   ❌ FAILED: " . $conn->connect_error . "\n\n";
    $hasErrors = true;
    exit(1);
} else {
    echo "   ✅ CONNECTED\n";
    
    // Test a query
    $result = $conn->query("SELECT COUNT(*) as cnt FROM campaign_master");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "   ✅ Query OK - Found {$row['cnt']} campaigns\n";
    } else {
        echo "   ❌ Query FAILED: " . $conn->error . "\n";
        $hasErrors = true;
    }
}
echo "\n";

// Test Server 2 Connection
echo "2. Testing Server 2 Connection (Campaign DB)...\n";
echo "   Host: $heavy_host\n";
echo "   Database: $heavy_database\n";

if (!isset($conn_heavy) || !($conn_heavy instanceof mysqli)) {
    echo "   ❌ FAILED: Connection object not initialized\n\n";
    $hasErrors = true;
    exit(1);
} elseif ($conn_heavy->connect_error) {
    echo "   ❌ FAILED: " . $conn_heavy->connect_error . "\n\n";
    $hasErrors = true;
    exit(1);
} else {
    echo "   ✅ CONNECTED\n";
    
    // Test if mail_blaster table exists
    $result = $conn_heavy->query("SHOW TABLES LIKE 'mail_blaster'");
    if ($result && $result->num_rows > 0) {
        echo "   ✅ Table 'mail_blaster' exists\n";
        
        // Count records
        $countResult = $conn_heavy->query("SELECT COUNT(*) as cnt FROM mail_blaster");
        if ($countResult) {
            $row = $countResult->fetch_assoc();
            echo "   ✅ Query OK - Found {$row['cnt']} queue entries\n";
        }
    } else {
        echo "   ⚠️  WARNING: Table 'mail_blaster' not found!\n";
        echo "   Run: mysql -h $heavy_host -u $heavy_username -p $heavy_database < campaign_heavy_tables.sql\n";
        $hasWarnings = true;
    }
    
    // Test SMTP tables
    $result = $conn_heavy->query("SHOW TABLES LIKE 'smtp_accounts'");
    if ($result && $result->num_rows > 0) {
        echo "   ✅ Table 'smtp_accounts' exists\n";
        
        $countResult = $conn_heavy->query("SELECT COUNT(*) as cnt FROM smtp_accounts WHERE is_active = 1");
        if ($countResult) {
            $row = $countResult->fetch_assoc();
            echo "   ✅ Found {$row['cnt']} active SMTP accounts\n";
        }
    } else {
        echo "   ⚠️  WARNING: Table 'smtp_accounts' not found!\n";
        $hasWarnings = true;
    }
}
echo "\n";

// If campaign_id provided, test specific campaign
if (isset($argv[1])) {
    $campaign_id = intval($argv[1]);
    echo "3. Testing Campaign #$campaign_id...\n";
    
    // Check campaign exists on Server 1
    $result = $conn->query("SELECT campaign_id, import_batch_id, csv_list_id, mail_subject FROM campaign_master WHERE campaign_id = $campaign_id");
    if ($result && $result->num_rows > 0) {
        $campaign = $result->fetch_assoc();
        echo "   ✅ Campaign found on Server 1\n";
        echo "      Subject: {$campaign['mail_subject']}\n";
        echo "      Import Batch: " . ($campaign['import_batch_id'] ?: 'NULL') . "\n";
        echo "      CSV List: " . ($campaign['csv_list_id'] ?: 'NULL') . "\n";
        
        // Check if recipients exist
        if ($campaign['import_batch_id']) {
            $batch = $conn->real_escape_string($campaign['import_batch_id']);
            $recipResult = $conn->query("SELECT COUNT(*) as cnt FROM imported_recipients WHERE import_batch_id = '$batch' AND is_active = 1");
            if ($recipResult) {
                $row = $recipResult->fetch_assoc();
                echo "   ✅ Found {$row['cnt']} recipients in imported_recipients\n";
                if ($row['cnt'] == 0) {
                    echo "   ⚠️  WARNING: No active recipients found!\n";
                    $hasWarnings = true;
                }
            }
        } elseif ($campaign['csv_list_id']) {
            $csvId = intval($campaign['csv_list_id']);
            $recipResult = $conn->query("SELECT COUNT(*) as cnt FROM emails WHERE csv_list_id = $csvId AND domain_status = 1");
            if ($recipResult) {
                $row = $recipResult->fetch_assoc();
                echo "   ✅ Found {$row['cnt']} recipients in emails table\n";
                if ($row['cnt'] == 0) {
                    echo "   ⚠️  WARNING: No valid recipients found!\n";
                    $hasWarnings = true;
                }
            }
        } else {
            echo "   ❌ ERROR: No import_batch_id or csv_list_id found!\n";
            $hasErrors = true;
        }
        
        // Check queue on Server 2
        $queueResult = $conn_heavy->query("SELECT COUNT(*) as cnt, status FROM mail_blaster WHERE campaign_id = $campaign_id GROUP BY status");
        if ($queueResult && $queueResult->num_rows > 0) {
            echo "   Queue status on Server 2:\n";
            while ($row = $queueResult->fetch_assoc()) {
                echo "      - {$row['status']}: {$row['cnt']}\n";
            }
        } else {
            echo "   ⚠️  No queue entries found on Server 2\n";
            $hasWarnings = true;
        }
    } else {
        echo "   ❌ Campaign not found\n";
        $hasErrors = true;
    }
    echo "\n";
}

echo "========== CONNECTION TEST COMPLETE ==========\n\n";

// Display final status based on test results
if ($hasErrors) {
    echo "❌ Test completed with ERRORS!\n";
    echo "\nPlease fix the errors above before proceeding.\n";
    exit(1);
} elseif ($hasWarnings) {
    echo "⚠️  Test completed with WARNINGS!\n";
    echo "\nConnections are working but some issues were found.\n";
} else {
    echo "✅ All connections working perfectly!\n";
}

echo "\nLog files to check:\n";
echo "  - backend/logs/queue_init.log (queue initialization)\n";
echo "  - PHP error log (campaign start errors)\n";
echo "  - backend/logs/campaign_cron.log (cron monitoring)\n";
echo "\n";

exit($hasErrors ? 1 : 0);

