#!/usr/bin/env php
<?php
/**
 * Dual Database Connection Verification Script
 * 
 * This script verifies that both databases are properly configured
 * and accessible for the MailPilot CRM dual database architecture.
 * 
 * Usage: php verify_dual_db.php
 */

echo "\n";
echo "========================================\n";
echo "  DUAL DATABASE VERIFICATION\n";
echo "========================================\n\n";

// Load database configurations
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/db_campaign.php';

$errors = [];
$warnings = [];
$success = [];

// Test Main Database Connection
echo "1. Testing Main Database Connection...\n";
echo "   Host: 174.141.233.174\n";
echo "   Database: email_id\n";

if ($conn->connect_error) {
    $errors[] = "Main DB connection FAILED: " . $conn->connect_error;
    echo "   ❌ FAILED: " . $conn->connect_error . "\n\n";
} else {
    $success[] = "Main DB connection successful";
    echo "   ✅ SUCCESS\n";
    
    // Test query
    $result = $conn->query("SELECT DATABASE() as db");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "   Connected to: " . $row['db'] . "\n";
    }
    
    // Check required tables
    echo "   Checking required tables...\n";
    $main_tables = ['campaign_master', 'campaign_status', 'users', 'imported_recipients'];
    foreach ($main_tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result && $result->num_rows > 0) {
            echo "   ✓ $table exists\n";
        } else {
            $errors[] = "Table $table not found in main database";
            echo "   ✗ $table NOT FOUND\n";
        }
    }
    echo "\n";
}

// Test Heavy Load Database Connection
echo "2. Testing Campaign Database Connection...\\n";
echo "   Host: 127.0.0.1\n";
echo "   Database: CRM\n";

if ($conn_heavy->connect_error) {
    $errors[] = "Campaign DB connection FAILED: " . $conn_heavy->connect_error;
    echo "   ❌ FAILED: " . $conn_heavy->connect_error . "\\n\\n";
} else {
    $success[] = "Campaign DB connection successful";
    echo "   ✅ SUCCESS\n";
    
    // Test query
    $result = $conn_heavy->query("SELECT DATABASE() as db");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "   Connected to: " . $row['db'] . "\n";
    }
    
    // Check required tables
    echo "   Checking required tables...\n";
    $heavy_tables = ['mail_blaster', 'smtp_servers', 'smtp_accounts', 'smtp_usage', 'smtp_health', 'smtp_rotation'];
    foreach ($heavy_tables as $table) {
        $result = $conn_heavy->query("SHOW TABLES LIKE '$table'");
        if ($result && $result->num_rows > 0) {
            echo "   ✓ $table exists\n";
            
            // Get row count
            $count_result = $conn_heavy->query("SELECT COUNT(*) as cnt FROM $table");
            if ($count_result) {
                $count = $count_result->fetch_assoc()['cnt'];
                echo "     ($count rows)\n";
            }
        } else {
            $errors[] = "Table $table not found in campaign database";
            echo "   ✗ $table NOT FOUND\n";
        }
    }
    echo "\n";
}

// Check Database Routing
echo "3. Testing Database Routing Logic...\n";
$routing_ok = true;

// Verify mail_blaster is ONLY in heavy DB
echo "   Checking mail_blaster routing...\n";
if (!$conn_heavy->connect_error) {
    $result = $conn_heavy->query("SHOW TABLES LIKE 'mail_blaster'");
    if ($result && $result->num_rows > 0) {
        echo "   ✓ mail_blaster found in Campaign DB (correct)\\n";
    } else {
        $errors[] = "mail_blaster not found in Campaign DB";
        echo "   ✗ mail_blaster NOT in Campaign DB\\n";
        $routing_ok = false;
    }
}

// Verify campaign_status is in main DB
echo "   Checking campaign_status routing...\n";
if (!$conn->connect_error) {
    $result = $conn->query("SHOW TABLES LIKE 'campaign_status'");
    if ($result && $result->num_rows > 0) {
        echo "   ✓ campaign_status found in Main DB (correct)\n";
    } else {
        $warnings[] = "campaign_status not found in Main DB";
        echo "   ⚠ campaign_status NOT in Main DB\n";
        $routing_ok = false;
    }
}
echo "\n";

// Test SMTP Configuration
echo "4. Testing SMTP Configuration...\n";
if (!$conn_heavy->connect_error) {
    $smtp_result = $conn_heavy->query("
        SELECT 
            (SELECT COUNT(*) FROM smtp_servers WHERE is_active = 1) as active_servers,
            (SELECT COUNT(*) FROM smtp_accounts WHERE is_active = 1) as active_accounts
    ");
    
    if ($smtp_result) {
        $smtp_data = $smtp_result->fetch_assoc();
        echo "   Active SMTP Servers: " . $smtp_data['active_servers'] . "\n";
        echo "   Active SMTP Accounts: " . $smtp_data['active_accounts'] . "\n";
        
        if ($smtp_data['active_servers'] == 0) {
            $warnings[] = "No active SMTP servers configured";
            echo "   ⚠ WARNING: No active SMTP servers\n";
        }
        if ($smtp_data['active_accounts'] == 0) {
            $warnings[] = "No active SMTP accounts configured";
            echo "   ⚠ WARNING: No active SMTP accounts\n";
        }
    }
}
echo "\n";

// Test Campaign Data
echo "5. Testing Campaign Data...\n";
if (!$conn->connect_error) {
    $campaigns = $conn->query("SELECT COUNT(*) as cnt FROM campaign_master");
    if ($campaigns) {
        $count = $campaigns->fetch_assoc()['cnt'];
        echo "   Total Campaigns: $count\n";
    }
    
    $running = $conn->query("SELECT COUNT(*) as cnt FROM campaign_status WHERE status = 'running'");
    if ($running) {
        $count = $running->fetch_assoc()['cnt'];
        echo "   Running Campaigns: $count\n";
        
        if ($count > 0) {
            echo "   ℹ Active campaigns detected\n";
        }
    }
}
echo "\n";

// Check Queue Statistics
echo "6. Testing Email Queue (mail_blaster)...\n";
if (!$conn_heavy->connect_error) {
    $queue_stats = $conn_heavy->query("
        SELECT 
            status,
            COUNT(*) as count
        FROM mail_blaster
        GROUP BY status
    ");
    
    if ($queue_stats && $queue_stats->num_rows > 0) {
        echo "   Queue Status:\n";
        while ($row = $queue_stats->fetch_assoc()) {
            echo "   - " . ucfirst($row['status']) . ": " . $row['count'] . "\n";
        }
    } else {
        echo "   Email queue is empty\n";
    }
}
echo "\n";

// Network Latency Test
echo "7. Testing Network Latency...\n";

// Main DB latency
if (!$conn->connect_error) {
    $start = microtime(true);
    $conn->query("SELECT 1");
    $latency = round((microtime(true) - $start) * 1000, 2);
    echo "   Main DB (174.141.233.174): {$latency}ms\n";
    
    if ($latency > 100) {
        $warnings[] = "Main DB latency is high ({$latency}ms)";
        echo "   ⚠ High latency detected\n";
    }
}

// Heavy DB latency
if (!$conn_heavy->connect_error) {
    $start = microtime(true);
    $conn_heavy->query("SELECT 1");
    $latency = round((microtime(true) - $start) * 1000, 2);
    echo "   Campaign DB (127.0.0.1): {$latency}ms\\n";
    
    if ($latency > 50) {
        $warnings[] = "Campaign DB latency is high ({$latency}ms)";
        echo "   ⚠ High latency detected\n";
    }
}
echo "\n";

// Summary
echo "========================================\n";
echo "  VERIFICATION SUMMARY\n";
echo "========================================\n\n";

if (count($errors) == 0 && count($warnings) == 0) {
    echo "✅ ALL CHECKS PASSED!\n\n";
    echo "Your dual database architecture is properly configured.\n";
    echo "You can now start campaigns and they will:\n";
    echo "- Store campaign data on Main DB (174.141.233.174)\n";
    echo "- Process email queue on Campaign DB (127.0.0.1/CRM)\\n";
    echo "- Update campaign status on Main DB\n\n";
} else {
    if (count($errors) > 0) {
        echo "❌ ERRORS FOUND:\n";
        foreach ($errors as $i => $error) {
            echo ($i + 1) . ". $error\n";
        }
        echo "\n";
    }
    
    if (count($warnings) > 0) {
        echo "⚠️  WARNINGS:\n";
        foreach ($warnings as $i => $warning) {
            echo ($i + 1) . ". $warning\n";
        }
        echo "\n";
    }
}

echo "Success Count: " . count($success) . "\n";
echo "Error Count: " . count($errors) . "\n";
echo "Warning Count: " . count($warnings) . "\n\n";

// Close connections
if ($conn) $conn->close();
if ($conn_heavy) $conn_heavy->close();

// Exit with proper code
exit(count($errors) > 0 ? 1 : 0);
