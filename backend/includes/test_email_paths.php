<?php
// Test attachment path resolution in email_blaster.php context

echo "<h1>Email Blaster - Attachment Path Test</h1>";
echo "<style>body { font-family: Arial, sans-serif; max-width: 900px; margin: 20px auto; padding: 20px; } 
      .success { color: green; font-weight: bold; } 
      .error { color: red; font-weight: bold; }
      .info { color: blue; }
      pre { background: #f4f4f4; padding: 10px; border-radius: 5px; }
      </style>";

// Simulate email_blaster.php directory
$emailBlasterDir = __DIR__ . '/../public';

echo "<h2>Path Testing (from email_blaster.php perspective)</h2>";

// Test attachment path
$storedPath = 'storage/attachments/69046b174391a_information.txt';
$resolvedPath = $emailBlasterDir . '/../' . $storedPath;
$exists = file_exists($resolvedPath);

echo "<div class='info'>";
echo "<p><strong>Stored in DB:</strong> $storedPath</p>";
echo "<p><strong>email_blaster.php location:</strong> backend/public/</p>";
echo "<p><strong>Resolution:</strong> __DIR__ . '/../' . \$storedPath</p>";
echo "<p><strong>Full resolved path:</strong> $resolvedPath</p>";
echo "<p><strong>File exists:</strong> " . ($exists ? "<span class='success'>YES ✓</span>" : "<span class='error'>NO ✗</span>") . "</p>";
echo "</div>";

if ($exists) {
    echo "<div class='success'>";
    echo "<h3>✓ Attachment path is correct!</h3>";
    echo "<p>File size: " . filesize($resolvedPath) . " bytes</p>";
    echo "<p>Content preview:</p>";
    echo "<pre>" . htmlspecialchars(file_get_contents($resolvedPath)) . "</pre>";
    echo "</div>";
} else {
    echo "<div class='error'>";
    echo "<h3>✗ Attachment file not found!</h3>";
    echo "</div>";
}

// Test image path
echo "<hr><h2>Image Path Test</h2>";
$imagePath = 'storage/images/690468b6479c9_1761896630.jpg';
$resolvedImagePath = $emailBlasterDir . '/../' . $imagePath;
$imageExists = file_exists($resolvedImagePath);

echo "<div class='info'>";
echo "<p><strong>Image path:</strong> $imagePath</p>";
echo "<p><strong>Resolved path:</strong> $resolvedImagePath</p>";
echo "<p><strong>File exists:</strong> " . ($imageExists ? "<span class='success'>YES ✓</span>" : "<span class='error'>NO ✗</span>") . "</p>";
echo "</div>";

if ($imageExists) {
    echo "<div class='success'>";
    echo "<h3>✓ Image path is correct!</h3>";
    echo "<p>File size: " . filesize($resolvedImagePath) . " bytes</p>";
    echo "</div>";
}

// Summary
echo "<hr><h2>Summary</h2>";
echo "<ul>";
echo "<li>Attachment path resolution: " . ($exists ? "<span class='success'>WORKING ✓</span>" : "<span class='error'>BROKEN ✗</span>") . "</li>";
echo "<li>Image path resolution: " . ($imageExists ? "<span class='success'>WORKING ✓</span>" : "<span class='error'>BROKEN ✗</span>") . "</li>";
echo "</ul>";

if ($exists && $imageExists) {
    echo "<div class='success'>";
    echo "<h3>🎉 All paths are working correctly!</h3>";
    echo "<p>Attachments and images will be properly included in emails.</p>";
    echo "</div>";
}
?>
