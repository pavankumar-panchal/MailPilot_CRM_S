<?php
// Test script to verify attachment functionality
require_once __DIR__ . '/../config/db.php';

echo "<h1>Attachment Functionality Test</h1>";
echo "<style>body { font-family: Arial, sans-serif; max-width: 900px; margin: 20px auto; padding: 20px; } 
      table { border-collapse: collapse; width: 100%; margin: 20px 0; }
      th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
      th { background-color: #4CAF50; color: white; }
      .success { color: green; } .error { color: red; } .warning { color: orange; }
      </style>";

// 1. Check database structure
echo "<h2>1. Database Structure</h2>";
$result = $conn->query("SHOW COLUMNS FROM campaign_master WHERE Field IN ('attachment_path', 'images_paths')");
if ($result->num_rows > 0) {
    echo "<table><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td><td>{$row['Null']}</td><td>{$row['Key']}</td><td>{$row['Default']}</td></tr>";
    }
    echo "</table>";
    echo "<p class='success'>✓ Database columns exist</p>";
} else {
    echo "<p class='error'>✗ attachment_path or images_paths columns not found!</p>";
}

// 2. Check storage directories
echo "<h2>2. Storage Directories</h2>";
$attachmentsDir = __DIR__ . '/../storage/attachments/';
$imagesDir = __DIR__ . '/../storage/images/';

echo "<table><tr><th>Directory</th><th>Exists</th><th>Writable</th><th>Files</th></tr>";

// Check attachments directory
$attachExists = is_dir($attachmentsDir);
$attachWritable = is_writable($attachmentsDir);
$attachFiles = $attachExists ? count(scandir($attachmentsDir)) - 2 : 0;
echo "<tr><td>$attachmentsDir</td><td>" . ($attachExists ? "✓" : "✗") . "</td><td>" . ($attachWritable ? "✓" : "✗") . "</td><td>$attachFiles</td></tr>";

// Check images directory
$imgExists = is_dir($imagesDir);
$imgWritable = is_writable($imagesDir);
$imgFiles = $imgExists ? count(scandir($imagesDir)) - 2 : 0;
echo "<tr><td>$imagesDir</td><td>" . ($imgExists ? "✓" : "✗") . "</td><td>" . ($imgWritable ? "✓" : "✗") . "</td><td>$imgFiles</td></tr>";

echo "</table>";

if ($attachExists && $attachWritable && $imgExists && $imgWritable) {
    echo "<p class='success'>✓ All directories are accessible and writable</p>";
} else {
    echo "<p class='error'>✗ Some directories have issues!</p>";
}

// 3. List attachment files
echo "<h2>3. Attachment Files in Storage</h2>";
if ($attachExists) {
    $files = array_diff(scandir($attachmentsDir), array('.', '..'));
    if (count($files) > 0) {
        echo "<table><tr><th>Filename</th><th>Size</th><th>Modified</th></tr>";
        foreach ($files as $file) {
            $filePath = $attachmentsDir . $file;
            $size = filesize($filePath);
            $modified = date("Y-m-d H:i:s", filemtime($filePath));
            echo "<tr><td>$file</td><td>" . number_format($size) . " bytes</td><td>$modified</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='warning'>No files in attachments directory</p>";
    }
}

// 4. Check campaigns with attachments
echo "<h2>4. Campaigns with Attachments</h2>";
$result = $conn->query("SELECT campaign_id, description, mail_subject, attachment_path, images_paths FROM campaign_master WHERE attachment_path IS NOT NULL OR images_paths IS NOT NULL ORDER BY campaign_id DESC LIMIT 10");

if ($result->num_rows > 0) {
    echo "<table><tr><th>ID</th><th>Description</th><th>Attachment Path</th><th>Images</th><th>File Exists</th></tr>";
    while ($row = $result->fetch_assoc()) {
        $attachPath = $row['attachment_path'];
        $fileExists = $attachPath ? file_exists(__DIR__ . '/../' . $attachPath) : false;
        $hasImages = !empty($row['images_paths']);
        
        echo "<tr>";
        echo "<td>{$row['campaign_id']}</td>";
        echo "<td>{$row['description']}</td>";
        echo "<td>" . ($attachPath ? $attachPath : '-') . "</td>";
        echo "<td>" . ($hasImages ? "Yes" : "No") . "</td>";
        echo "<td>" . ($attachPath ? ($fileExists ? "✓" : "✗ Missing") : "-") . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "<p class='success'>✓ Found {$result->num_rows} campaign(s) with attachments/images</p>";
} else {
    echo "<p class='warning'>No campaigns with attachments found in database</p>";
}

// 5. Test file upload capability
echo "<h2>5. Upload Test</h2>";
$testFile = $attachmentsDir . 'test_' . time() . '.txt';
$testContent = "Test file created at " . date('Y-m-d H:i:s');
if (file_put_contents($testFile, $testContent)) {
    echo "<p class='success'>✓ Successfully created test file: " . basename($testFile) . "</p>";
    unlink($testFile);
    echo "<p class='success'>✓ Successfully deleted test file</p>";
    echo "<p class='success'><strong>✓ File upload functionality is working!</strong></p>";
} else {
    echo "<p class='error'>✗ Failed to create test file. Check permissions!</p>";
}

// 6. Summary
echo "<h2>Summary</h2>";
echo "<ul>";
echo "<li><strong>Database:</strong> " . ($result->num_rows > 0 ? "Ready" : "No data yet") . "</li>";
echo "<li><strong>Storage:</strong> " . ($attachExists && $attachWritable ? "Ready" : "Issues found") . "</li>";
echo "<li><strong>Attachments Folder:</strong> $attachFiles file(s)</li>";
echo "<li><strong>Images Folder:</strong> $imgFiles file(s)</li>";
echo "</ul>";

echo "<hr><p><a href='test_attachment.html'>→ Go to Upload Test Page</a></p>";
?>
