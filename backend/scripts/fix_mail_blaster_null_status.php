<?php
/**
 * Fix NULL/Empty Status in mail_blaster Table
 * 
 * This script fixes existing records in mail_blaster table where:
 * - status is NULL
 * - status is empty string ''
 * 
 * Sets them to 'pending' so they can be processed properly.
 * 
 * Run this once to clean up existing data:
 * php fix_mail_blaster_null_status.php
 */

require_once __DIR__ . '/../config/db.php';

echo "=== Mail Blaster Status Fix Script ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// Check for NULL status records
$nullCheck = $conn->query("SELECT COUNT(*) as cnt FROM mail_blaster WHERE status IS NULL");
$nullCount = $nullCheck ? $nullCheck->fetch_assoc()['cnt'] : 0;

// Check for empty string status records
$emptyCheck = $conn->query("SELECT COUNT(*) as cnt FROM mail_blaster WHERE status = ''");
$emptyCount = $emptyCheck ? $emptyCheck->fetch_assoc()['cnt'] : 0;

echo "Records with NULL status: $nullCount\n";
echo "Records with empty status: $emptyCount\n";
echo "Total records to fix: " . ($nullCount + $emptyCount) . "\n\n";

if (($nullCount + $emptyCount) == 0) {
    echo "✓ No records need fixing. All statuses are properly set.\n";
    exit(0);
}

// Ask for confirmation
echo "This will set all NULL/empty statuses to 'pending'.\n";
echo "Continue? [yes/no]: ";
$handle = fopen("php://stdin", "r");
$line = trim(fgets($handle));
fclose($handle);

if (strtolower($line) !== 'yes') {
    echo "Aborted.\n";
    exit(0);
}

echo "\nStarting fix...\n";

try {
    $conn->begin_transaction();
    
    // Fix NULL statuses
    if ($nullCount > 0) {
        echo "Fixing NULL statuses...\n";
        $result = $conn->query("UPDATE mail_blaster SET status = 'pending', smtpid = 0 WHERE status IS NULL");
        if ($result) {
            echo "✓ Fixed $nullCount records with NULL status\n";
        } else {
            throw new Exception("Failed to fix NULL statuses: " . $conn->error);
        }
    }
    
    // Fix empty string statuses
    if ($emptyCount > 0) {
        echo "Fixing empty string statuses...\n";
        $result = $conn->query("UPDATE mail_blaster SET status = 'pending', smtpid = 0 WHERE status = ''");
        if ($result) {
            echo "✓ Fixed $emptyCount records with empty status\n";
        } else {
            throw new Exception("Failed to fix empty statuses: " . $conn->error);
        }
    }
    
    // Verify the fix
    $verifyNull = $conn->query("SELECT COUNT(*) as cnt FROM mail_blaster WHERE status IS NULL")->fetch_assoc()['cnt'];
    $verifyEmpty = $conn->query("SELECT COUNT(*) as cnt FROM mail_blaster WHERE status = ''")->fetch_assoc()['cnt'];
    
    if ($verifyNull > 0 || $verifyEmpty > 0) {
        throw new Exception("Verification failed: Still have $verifyNull NULL and $verifyEmpty empty statuses");
    }
    
    $conn->commit();
    
    echo "\n=== Fix Completed Successfully ===\n";
    echo "All mail_blaster records now have proper status values.\n";
    
    // Show status distribution
    echo "\n=== Status Distribution ===\n";
    $statusDist = $conn->query("
        SELECT status, COUNT(*) as cnt 
        FROM mail_blaster 
        GROUP BY status 
        ORDER BY cnt DESC
    ");
    
    while ($row = $statusDist->fetch_assoc()) {
        echo sprintf("  %-20s: %d\n", $row['status'], $row['cnt']);
    }
    
} catch (Exception $e) {
    $conn->rollback();
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n✓ Done!\n";
exit(0);
