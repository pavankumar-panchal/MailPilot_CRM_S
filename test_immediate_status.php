<?php
/**
 * Test script to verify immediate status updates
 * This tests that campaign status updates are visible immediately after execution
 */

date_default_timezone_set('Asia/Kolkata');
require_once __DIR__ . '/backend/config/db.php';

echo "=== Testing Immediate Status Updates ===\n\n";

// Test 1: Check autocommit is enabled
echo "Test 1: Checking autocommit status...\n";
$result = $conn->query("SELECT @@autocommit");
$row = $result->fetch_assoc();
$autocommit = $row['@@autocommit'];
echo "  Autocommit: " . ($autocommit ? "ENABLED ✓" : "DISABLED ✗") . "\n\n";

// Test 2: Create test campaign status entry
echo "Test 2: Testing immediate status update...\n";
$test_campaign_id = 999999; // Use a test ID that won't conflict

// Clean up any existing test data
$conn->query("DELETE FROM campaign_status WHERE campaign_id = $test_campaign_id");

// Insert test status
$start_time = date('Y-m-d H:i:s');
echo "  Inserting test status at $start_time...\n";
$conn->query("INSERT INTO campaign_status (campaign_id, status, total_emails) VALUES ($test_campaign_id, 'pending', 100)");

// Immediately query it back (simulating frontend polling)
$check = $conn->query("SELECT status, total_emails FROM campaign_status WHERE campaign_id = $test_campaign_id");
if ($check && $check->num_rows > 0) {
    $data = $check->fetch_assoc();
    echo "  ✓ Status immediately visible: " . $data['status'] . " (total: " . $data['total_emails'] . ")\n";
} else {
    echo "  ✗ ERROR: Status NOT visible immediately!\n";
}

// Update status to running
echo "\n  Updating status to 'running'...\n";
$conn->query("UPDATE campaign_status SET status = 'running', start_time = NOW() WHERE campaign_id = $test_campaign_id");

// Immediately query it back
$check = $conn->query("SELECT status, start_time FROM campaign_status WHERE campaign_id = $test_campaign_id");
if ($check && $check->num_rows > 0) {
    $data = $check->fetch_assoc();
    echo "  ✓ Status immediately updated: " . $data['status'] . " (started: " . $data['start_time'] . ")\n";
} else {
    echo "  ✗ ERROR: Status update NOT visible immediately!\n";
}

// Update to paused
echo "\n  Updating status to 'paused'...\n";
$conn->query("UPDATE campaign_status SET status = 'paused' WHERE campaign_id = $test_campaign_id");

// Immediately query it back
$check = $conn->query("SELECT status FROM campaign_status WHERE campaign_id = $test_campaign_id");
if ($check && $check->num_rows > 0) {
    $data = $check->fetch_assoc();
    echo "  ✓ Status immediately updated: " . $data['status'] . "\n";
} else {
    echo "  ✗ ERROR: Status update NOT visible immediately!\n";
}

// Clean up
echo "\n  Cleaning up test data...\n";
$conn->query("DELETE FROM campaign_status WHERE campaign_id = $test_campaign_id");
echo "  ✓ Test data removed\n";

echo "\n=== Test Complete ===\n";
echo "\nSummary:\n";
echo "- Autocommit is " . ($autocommit ? "ENABLED" : "DISABLED") . "\n";
echo "- Status updates are " . ($autocommit ? "immediate" : "may be delayed until commit") . "\n";

if (!$autocommit) {
    echo "\nWARNING: Autocommit is disabled! This can cause delays in status updates.\n";
    echo "Fix: Edit backend/config/db.php and ensure autocommit is enabled.\n";
}
?>
