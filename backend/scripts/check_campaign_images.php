<?php
require_once __DIR__ . '/../config/db.php';

echo "\n╔══════════════════════════════════════════════════════════╗\n";
echo "║  CAMPAIGN IMAGE CONFIGURATION CHECK                      ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n\n";

// Get the most recent campaign
$stmt = $conn->prepare("SELECT id, campaign_name, images_paths, body FROM campaign_master ORDER BY id DESC LIMIT 1");
$stmt->execute();
$campaign = $stmt->get_result()->fetch_assoc();

if (!$campaign) {
    die("❌ No campaigns found\n\n");
}

echo "Campaign ID: " . $campaign['id'] . "\n";
echo "Campaign Name: " . $campaign['campaign_name'] . "\n";
echo "─────────────────────────────────────────────────────────\n\n";

echo "📋 Images Paths Field:\n";
if (empty($campaign['images_paths'])) {
    echo "  ❌ NULL or EMPTY\n";
} else {
    echo "  ✓ Value: " . $campaign['images_paths'] . "\n";
    
    // Try to decode if it's JSON
    $images = json_decode($campaign['images_paths'], true);
    if (is_array($images)) {
        echo "  ✓ JSON decoded successfully: " . count($images) . " images\n";
        foreach ($images as $idx => $path) {
            echo "    [$idx] $path\n";
            
            // Check if file exists
            $fullPath = __DIR__ . '/../' . $path;
            if (file_exists($fullPath)) {
                echo "        ✓ File exists (" . filesize($fullPath) . " bytes)\n";
            } else {
                echo "        ❌ File NOT found at: $fullPath\n";
            }
        }
    } else {
        echo "  ⚠️  Not valid JSON array\n";
    }
}

echo "\n─────────────────────────────────────────────────────────\n\n";

echo "📋 Images in Body HTML:\n";
if (preg_match_all('/<img[^>]+src=["\']([^"\'>]+)["\']/', $campaign['body'], $matches)) {
    echo "  ✓ Found " . count($matches[1]) . " <img> tag(s):\n";
    foreach ($matches[1] as $idx => $imgSrc) {
        echo "    [$idx] $imgSrc\n";
        
        // Check if it's already a CID
        if (strpos($imgSrc, 'cid:') === 0) {
            echo "        ⚠️  Already has CID reference!\n";
        } elseif (strpos($imgSrc, 'http://') === 0 || strpos($imgSrc, 'https://') === 0) {
            echo "        ⚠️  External URL (won't be embedded)\n";
        } elseif (strpos($imgSrc, 'localhost') !== false) {
            echo "        ❌ LOCALHOST URL - This is the problem!\n";
        } else {
            echo "        ✓ Relative path (should be embedded)\n";
        }
    }
} else {
    echo "  ❌ No <img> tags found in body\n";
}

echo "\n─────────────────────────────────────────────────────────\n\n";

echo "🔍 DIAGNOSIS:\n";

if (empty($campaign['images_paths'])) {
    echo "  ❌ PROBLEM: images_paths is empty!\n";
    echo "     The campaign doesn't have images_paths configured.\n";
    echo "     This means the image embedding code never runs.\n\n";
    echo "  💡 SOLUTION: When creating campaign, make sure to save\n";
    echo "     image paths to the images_paths column.\n";
} elseif (preg_match('/localhost/', $campaign['body'])) {
    echo "  ❌ PROBLEM: Body contains localhost URLs!\n";
    echo "     Images are being saved with full localhost URLs\n";
    echo "     instead of relative paths.\n\n";
    echo "  💡 SOLUTION: Save images with relative paths like:\n";
    echo "     storage/images/filename.jpg\n";
    echo "     NOT: http://localhost/path/to/image.jpg\n";
}

echo "\n═══════════════════════════════════════════════════════════\n\n";

$conn->close();
?>
