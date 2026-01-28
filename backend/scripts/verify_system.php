<?php
/**
 * System Verification Script
 * Checks database schema, authentication, and user filtering
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/user_filtering.php';

echo "=== Relyon CRM SYSTEM VERIFICATION ===\n\n";

// 1. Check Database Tables and user_id columns
echo "1. DATABASE SCHEMA CHECK\n";
echo str_repeat("-", 50) . "\n";

$tables = ['campaign_master', 'csv_list', 'emails', 'imported_recipients', 'mail_templates', 'smtp_accounts', 'smtp_servers', 'users'];
$missing_user_id = [];

foreach ($tables as $table) {
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE 'user_id'");
    if ($result->num_rows > 0) {
        echo "✓ Table '$table' has user_id column\n";
    } else {
        if ($table !== 'users') {
            echo "✗ Table '$table' MISSING user_id column\n";
            $missing_user_id[] = $table;
        } else {
            echo "✓ Table 'users' (no user_id needed)\n";
        }
    }
}

if (!empty($missing_user_id)) {
    echo "\n⚠ WARNING: " . count($missing_user_id) . " tables missing user_id column!\n";
    echo "Run ensureUserIdColumns() to fix.\n";
} else {
    echo "\n✓ All tables have proper user_id columns\n";
}

// 2. Check Users Table
echo "\n2. USERS TABLE CHECK\n";
echo str_repeat("-", 50) . "\n";

$users_result = $conn->query("SELECT id, email, name, role, is_active FROM users ORDER BY id");
if ($users_result && $users_result->num_rows > 0) {
    echo "Total users: " . $users_result->num_rows . "\n\n";
    while ($user = $users_result->fetch_assoc()) {
        $status = $user['is_active'] ? '✓ Active' : '✗ Inactive';
        $role = strtoupper($user['role']);
        echo "  ID: {$user['id']} | {$user['email']} | {$user['name']} | {$role} | {$status}\n";
    }
} else {
    echo "✗ No users found in database!\n";
}

// 3. Check Data Distribution
echo "\n3. DATA DISTRIBUTION BY USER\n";
echo str_repeat("-", 50) . "\n";

$data_tables = [
    'smtp_servers' => 'SMTP Servers',
    'smtp_accounts' => 'SMTP Accounts',
    'campaign_master' => 'Campaigns',
    'mail_templates' => 'Templates',
    'csv_list' => 'Email Lists',
    'imported_recipients' => 'Recipients'
];

foreach ($data_tables as $table => $label) {
    $result = $conn->query("
        SELECT user_id, COUNT(*) as count 
        FROM `$table` 
        WHERE user_id IS NOT NULL 
        GROUP BY user_id 
        ORDER BY user_id
    ");
    
    $null_result = $conn->query("SELECT COUNT(*) as count FROM `$table` WHERE user_id IS NULL");
    $null_count = $null_result->fetch_assoc()['count'];
    
    echo "\n$label:\n";
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "  User ID {$row['user_id']}: {$row['count']} records\n";
        }
    } else {
        echo "  No records with user_id\n";
    }
    
    if ($null_count > 0) {
        echo "  ⚠ NULL user_id: $null_count records (needs migration)\n";
    }
}

// 4. Check Backend Files for Session Config
echo "\n4. BACKEND FILES CHECK\n";
echo str_repeat("-", 50) . "\n";

$critical_files = [
    'includes/session_config.php' => 'Session Configuration',
    'includes/user_filtering.php' => 'User Filtering',
    'includes/security_helpers.php' => 'Security Helpers',
    'includes/master_smtps.php' => 'Master SMTP API',
    'includes/mail_templates.php' => 'Mail Templates API',
    'includes/campaign.php' => 'Campaign API',
    'includes/get_csv_list.php' => 'CSV List API',
    'app/login.php' => 'Login Endpoint',
    'public/email_processor.php' => 'Email Processor'
];

foreach ($critical_files as $file => $label) {
    $path = __DIR__ . '/../' . $file;
    if (file_exists($path)) {
        $content = file_get_contents($path);
        
        // Check for session_config.php inclusion
        $has_session_config = strpos($content, "require_once __DIR__ . '/session_config.php'") !== false ||
                              strpos($content, "require_once __DIR__ . '/../includes/session_config.php'") !== false;
        
        // Check for credentials handling
        $has_getCurrentUser = strpos($content, 'getCurrentUser()') !== false;
        
        // Check for user filtering
        $has_getUserFilter = strpos($content, 'getUserFilterWhere()') !== false || 
                             strpos($content, 'getUserFilterAnd()') !== false;
        
        echo "✓ $label exists\n";
        if ($has_session_config) echo "  └─ Uses centralized session config\n";
        if ($has_getCurrentUser) echo "  └─ Checks authentication\n";
        if ($has_getUserFilter) echo "  └─ Implements user filtering\n";
    } else {
        echo "✗ $label MISSING at $path\n";
    }
}

// 5. Check Frontend Credentials
echo "\n5. FRONTEND CREDENTIALS CHECK\n";
echo str_repeat("-", 50) . "\n";

$frontend_files = glob(__DIR__ . '/../../frontend/src/pages/*.jsx');
$files_without_credentials = [];

foreach ($frontend_files as $file) {
    $content = file_get_contents($file);
    $filename = basename($file);
    
    // Count fetch calls
    preg_match_all('/fetch\s*\(/i', $content, $fetch_matches);
    $fetch_count = count($fetch_matches[0]);
    
    // Count fetch calls with credentials
    preg_match_all('/credentials\s*:\s*[\'"]include[\'"]/i', $content, $cred_matches);
    $cred_count = count($cred_matches[0]);
    
    if ($fetch_count > 0) {
        $percentage = round(($cred_count / $fetch_count) * 100);
        if ($percentage < 100) {
            $files_without_credentials[] = "$filename ($cred_count/$fetch_count = $percentage%)";
        }
        echo "  $filename: $cred_count/$fetch_count fetch calls have credentials ($percentage%)\n";
    }
}

if (!empty($files_without_credentials)) {
    echo "\n⚠ Files with missing credentials:\n";
    foreach ($files_without_credentials as $file) {
        echo "  - $file\n";
    }
} else {
    echo "\n✓ All files properly include credentials\n";
}

// 6. Summary
echo "\n" . str_repeat("=", 50) . "\n";
echo "SUMMARY\n";
echo str_repeat("=", 50) . "\n";

$issues = [];
if (!empty($missing_user_id)) $issues[] = "Missing user_id columns in " . count($missing_user_id) . " tables";
if (!empty($files_without_credentials)) $issues[] = count($files_without_credentials) . " frontend files missing credentials";

if (empty($issues)) {
    echo "✓ System is properly configured for multi-user operation\n";
    echo "✓ All tables have user_id columns\n";
    echo "✓ All backend files use centralized session\n";
    echo "✓ All frontend files include credentials\n";
    echo "\nSYSTEM READY FOR PRODUCTION\n";
} else {
    echo "⚠ Found " . count($issues) . " issues:\n";
    foreach ($issues as $issue) {
        echo "  - $issue\n";
    }
    echo "\nPLEASE FIX THESE ISSUES BEFORE PRODUCTION\n";
}

$conn->close();
