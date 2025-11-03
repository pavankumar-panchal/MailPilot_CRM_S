<?php
// Test script to verify image path saving

require_once __DIR__ . '/../config/db.php';

echo "Testing Image Path Saving\n";
echo "=========================\n\n";

// Test 1: Create a campaign with images
$description = 'Test Campaign ' . time();
$subject = 'Test Subject';
$body = '<p>Test body with image</p>';
$attachment = null;
$images = ['storage/images/test1.jpg', 'storage/images/test2.jpg'];
$images_json = json_encode($images);
$reply = '';
$html = 1;

echo "Step 1: Inserting test campaign...\n";
$stmt = $conn->prepare("INSERT INTO campaign_master (description, mail_subject, mail_body, attachment_path, images_paths, reply_to, send_as_html) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssssssi", $description, $subject, $body, $attachment, $images_json, $reply, $html);

if ($stmt->execute()) {
    $campaign_id = $stmt->insert_id;
    echo "✓ Campaign created with ID: $campaign_id\n";
    
    // Verify it was saved correctly
    $result = $conn->query("SELECT campaign_id, description, attachment_path, images_paths FROM campaign_master WHERE campaign_id = $campaign_id");
    $row = $result->fetch_assoc();
    
    echo "\nStep 2: Verifying saved data...\n";
    echo "Campaign ID: " . $row['campaign_id'] . "\n";
    echo "Description: " . $row['description'] . "\n";
    echo "Attachment: " . ($row['attachment_path'] ?? 'NULL') . "\n";
    echo "Images: " . ($row['images_paths'] ?? 'NULL') . "\n";
    
    if ($row['images_paths'] === $images_json) {
        echo "\n✓ SUCCESS: Images paths saved correctly!\n";
    } else {
        echo "\n✗ FAIL: Images paths not saved correctly\n";
        echo "Expected: $images_json\n";
        echo "Got: " . ($row['images_paths'] ?? 'NULL') . "\n";
    }
    
    // Clean up
    $conn->query("DELETE FROM campaign_master WHERE campaign_id = $campaign_id");
    echo "\nCleaned up test campaign\n";
} else {
    echo "✗ Failed to create campaign: " . $conn->error . "\n";
}

echo "\n\nTest 2: Simulating POST request\n";
echo "================================\n";

// Simulate what the frontend sends
$_POST = [
    'description' => 'Test Campaign POST',
    'mail_subject' => 'Test Subject',
    'mail_body' => '<p>Test</p>',
    'send_as_html' => '1',
    'images_json' => json_encode(['storage/images/test3.jpg'])
];

echo "POST data:\n";
print_r($_POST);

// Simulate the backend logic
$images_paths = [];
if (isset($_POST['images_json'])) {
    $quillImages = json_decode($_POST['images_json'], true);
    echo "\nDecoded images_json: " . print_r($quillImages, true);
    
    if (is_array($quillImages) && !empty($quillImages)) {
        $images_paths = array_merge($images_paths, $quillImages);
        echo "Merged into images_paths: " . print_r($images_paths, true);
    }
}

$images_json = !empty($images_paths) ? json_encode($images_paths) : null;
echo "\nFinal images_json for database: " . ($images_json ?? 'NULL') . "\n";

if ($images_json !== null) {
    echo "\n✓ SUCCESS: Images would be saved!\n";
} else {
    echo "\n✗ FAIL: Images would NOT be saved\n";
}
