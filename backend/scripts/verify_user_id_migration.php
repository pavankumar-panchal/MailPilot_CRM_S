<?php
/**
 * Verify User ID Migration
 * Checks that all relevant tables have user_id columns and indexes
 */

require_once __DIR__ . '/../config/db.php';

echo "=== User ID Migration Verification ===\n\n";

// List of tables that should have user_id column
$tables = [
    'bounced_emails',
    'campaign_master',
    'campaign_status',
    'csv_list',
    'emails',
    'email_processing_logs',
    'exclude_accounts',
    'exclude_domains',
    'imported_recipients',
    'mail_blaster',
    'mail_blaster_attempts',
    'mail_templates',
    'processed_emails',
    'smtp_accounts',
    'smtp_health',
    'smtp_rotation',
    'smtp_servers',
    'smtp_usage',
    'stats_cache',
    'unsubscribers',
    'workers'
];

$dbName = 'CRM';
$allGood = true;

foreach ($tables as $table) {
    // Check if user_id column exists
    $colCheck = $conn->query("
        SELECT COUNT(*) AS cnt 
        FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = '$dbName' 
        AND TABLE_NAME = '$table' 
        AND COLUMN_NAME = 'user_id'
    ");
    
    if ($colCheck) {
        $hasColumn = (int)$colCheck->fetch_assoc()['cnt'] > 0;
        
        // Check if index exists
        $idxCheck = $conn->query("
            SELECT COUNT(*) AS cnt 
            FROM information_schema.STATISTICS 
            WHERE TABLE_SCHEMA = '$dbName' 
            AND TABLE_NAME = '$table' 
            AND COLUMN_NAME = 'user_id'
        ");
        
        $hasIndex = false;
        if ($idxCheck) {
            $hasIndex = (int)$idxCheck->fetch_assoc()['cnt'] > 0;
        }
        
        $status = $hasColumn ? '✓' : '✗';
        $indexStatus = $hasIndex ? '✓' : '✗';
        
        echo sprintf("%-30s Column: %s  Index: %s\n", $table, $status, $indexStatus);
        
        if (!$hasColumn || !$hasIndex) {
            $allGood = false;
        }
    } else {
        echo sprintf("%-30s ERROR checking column\n", $table);
        $allGood = false;
    }
}

echo "\n";
if ($allGood) {
    echo "✓ All tables have user_id column and index!\n";
    echo "✓ Migration completed successfully.\n";
} else {
    echo "✗ Some tables are missing user_id column or index.\n";
    echo "  Run: backend/scripts/add_user_id_columns_all.sql\n";
}

echo "\n=== End of Verification ===\n";
