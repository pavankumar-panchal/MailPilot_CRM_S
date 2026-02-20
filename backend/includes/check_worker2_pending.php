<?php
// Quick check for WORKER_ID=2 pending emails
require_once __DIR__ . '/../config/db.php';

echo "=== WORKER 2 PENDING EMAILS CHECK ===\n";
echo "Database: " . $dbConfig['name'] . " @ " . $dbConfig['host'] . "\n\n";

// Check pending emails for worker_id = 2
$query = "
    SELECT 
        COUNT(*) as total_pending,
        SUM(CASE WHEN domain_processed = 0 THEN 1 ELSE 0 END) as domain_pending,
        SUM(CASE WHEN domain_processed = 1 AND validation_status IS NULL THEN 1 ELSE 0 END) as smtp_pending
    FROM emails 
    WHERE worker_id = 2
";

$result = $conn->query($query);
if ($result) {
    $row = $result->fetch_assoc();
    echo "Total emails for worker_id=2: {$row['total_pending']}\n";
    echo "Domain validation pending: {$row['domain_pending']}\n";
    echo "SMTP validation pending: {$row['smtp_pending']}\n\n";
    $result->close();
}

// Check by user
$userQuery = "
    SELECT 
        user_id,
        COUNT(*) as total,
        SUM(CASE WHEN domain_processed = 0 THEN 1 ELSE 0 END) as pending
    FROM emails 
    WHERE worker_id = 2
    GROUP BY user_id
    ORDER BY pending DESC
";

$result = $conn->query($userQuery);
if ($result) {
    echo "Breakdown by user:\n";
    while ($row = $result->fetch_assoc()) {
        echo "  User {$row['user_id']}: {$row['pending']} pending / {$row['total']} total\n";
    }
    $result->close();
}

$conn->close();
echo "\n=== CHECK COMPLETE ===\n";
