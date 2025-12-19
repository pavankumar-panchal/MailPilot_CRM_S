<?php
// Test script to update csv_list_id
require_once __DIR__ . '/config/db.php';

$campaign_id = 19;
$csv_list_id = 2; // Change this to the CSV list ID you want to test

echo "Testing CSV list update for campaign #$campaign_id\n";
echo "Setting csv_list_id to: $csv_list_id\n\n";

$stmt = $conn->prepare("UPDATE campaign_master SET csv_list_id=? WHERE campaign_id=?");
$stmt->bind_param('ii', $csv_list_id, $campaign_id);

if ($stmt->execute()) {
    echo "✅ Success! Updated campaign #$campaign_id with csv_list_id=$csv_list_id\n";
    
    // Verify the update
    $verify = $conn->query("SELECT campaign_id, description, csv_list_id FROM campaign_master WHERE campaign_id=$campaign_id");
    $row = $verify->fetch_assoc();
    echo "\nVerification:\n";
    echo "Campaign ID: " . $row['campaign_id'] . "\n";
    echo "Description: " . $row['description'] . "\n";
    echo "CSV List ID: " . ($row['csv_list_id'] ?? 'NULL') . "\n";
} else {
    echo "❌ Error: " . $conn->error . "\n";
}
