<?php
/**
 * Data Migration Script
 * Assigns NULL user_id records to admin user
 */

require_once __DIR__ . '/../config/db.php';

echo "=== DATA MIGRATION: NULL user_id to Admin ===\n\n";

// Get admin user ID (first admin found)
$admin_result = $conn->query("SELECT id FROM users WHERE role='admin' AND is_active=1 LIMIT 1");
if ($admin_result->num_rows === 0) {
    echo "✗ No active admin user found! Please create an admin user first.\n";
    exit(1);
}
$admin_id = $admin_result->fetch_assoc()['id'];
echo "Found admin user ID: $admin_id\n\n";

// Tables to migrate
$tables = [
    'smtp_servers' => 'SMTP Servers',
    'smtp_accounts' => 'SMTP Accounts', 
    'campaign_master' => 'Campaigns',
    'mail_templates' => 'Mail Templates',
    'csv_list' => 'Email Lists',
    'imported_recipients' => 'Imported Recipients',
    'emails' => 'Emails'
];

$total_migrated = 0;

foreach ($tables as $table => $label) {
    // Count NULL records
    $count_result = $conn->query("SELECT COUNT(*) as count FROM `$table` WHERE user_id IS NULL");
    $null_count = $count_result->fetch_assoc()['count'];
    
    if ($null_count > 0) {
        echo "$label:\n";
        echo "  Found $null_count records with NULL user_id\n";
        
        // Update NULL records to admin
        $update_query = "UPDATE `$table` SET user_id = $admin_id WHERE user_id IS NULL";
        if ($conn->query($update_query)) {
            echo "  ✓ Migrated $null_count records to admin user\n\n";
            $total_migrated += $null_count;
        } else {
            echo "  ✗ Error migrating: " . $conn->error . "\n\n";
        }
    } else {
        echo "$label: ✓ No NULL user_id records\n\n";
    }
}

echo str_repeat("=", 50) . "\n";
echo "MIGRATION COMPLETE\n";
echo "Total records migrated: $total_migrated\n";
echo "All records now assigned to admin user ID: $admin_id\n";

$conn->close();
