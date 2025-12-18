<?php
/**
 * Fix Campaign Duplicates - Add UNIQUE constraint and cleanup
 * 
 * This script:
 * 1. Removes duplicate campaign_status rows (keeps the latest)
 * 2. Adds UNIQUE constraint on campaign_id to prevent future duplicates
 * 
 * Run this once to fix the database
 */

require_once __DIR__ . '/../config/db.php';

echo "=== Campaign Duplicates Cleanup ===\n\n";

try {
    // Step 1: Check for duplicates
    echo "Step 1: Checking for duplicate campaign_status rows...\n";
    $result = $conn->query("
        SELECT campaign_id, COUNT(*) as count 
        FROM campaign_status 
        GROUP BY campaign_id 
        HAVING count > 1
    ");
    
    $duplicates = $result->fetch_all(MYSQLI_ASSOC);
    
    if (empty($duplicates)) {
        echo "✓ No duplicates found!\n\n";
    } else {
        echo "Found " . count($duplicates) . " campaigns with duplicate status rows:\n";
        foreach ($duplicates as $dup) {
            echo "  - Campaign ID {$dup['campaign_id']}: {$dup['count']} rows\n";
        }
        echo "\n";
        
        // Step 2: Remove duplicates (keep the latest one)
        echo "Step 2: Removing duplicate rows (keeping latest)...\n";
        
        foreach ($duplicates as $dup) {
            $campaign_id = $dup['campaign_id'];
            
            // Keep only the row with the highest ID (latest)
            $conn->query("
                DELETE FROM campaign_status 
                WHERE campaign_id = $campaign_id 
                AND id NOT IN (
                    SELECT * FROM (
                        SELECT MAX(id) FROM campaign_status WHERE campaign_id = $campaign_id
                    ) AS temp
                )
            ");
            
            echo "  ✓ Cleaned campaign_id $campaign_id\n";
        }
        echo "\n";
    }
    
    // Step 3: Add UNIQUE constraint if it doesn't exist
    echo "Step 3: Adding UNIQUE constraint on campaign_id...\n";
    
    // Check if constraint already exists
    $check = $conn->query("
        SHOW INDEXES FROM campaign_status 
        WHERE Column_name = 'campaign_id' 
        AND Non_unique = 0
    ");
    
    if ($check->num_rows > 0) {
        echo "✓ UNIQUE constraint already exists!\n\n";
    } else {
        // Add UNIQUE constraint
        $conn->query("ALTER TABLE campaign_status ADD UNIQUE KEY unique_campaign_id (campaign_id)");
        echo "✓ UNIQUE constraint added successfully!\n\n";
    }
    
    // Step 4: Verify
    echo "Step 4: Final verification...\n";
    $result = $conn->query("
        SELECT 
            COUNT(DISTINCT campaign_id) as unique_campaigns,
            COUNT(*) as total_rows
        FROM campaign_status
    ");
    $stats = $result->fetch_assoc();
    
    echo "  - Unique campaigns: {$stats['unique_campaigns']}\n";
    echo "  - Total status rows: {$stats['total_rows']}\n";
    
    if ($stats['unique_campaigns'] == $stats['total_rows']) {
        echo "\n✓✓✓ SUCCESS! No duplicates remaining! ✓✓✓\n";
    } else {
        echo "\n⚠ Warning: Still have duplicates. Manual intervention needed.\n";
    }
    
} catch (Exception $e) {
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

$conn->close();
echo "\n=== Cleanup Complete ===\n";
?>
