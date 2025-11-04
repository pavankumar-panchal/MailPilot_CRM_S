<?php
/**
 * Fix Existing Campaigns - Replace localhost URLs with relative paths
 * and populate images_paths column
 */

require_once __DIR__ . '/../config/db.php';

echo "\n╔══════════════════════════════════════════════════════════╗\n";
echo "║  FIX EXISTING CAMPAIGNS - LOCALHOST TO RELATIVE PATHS   ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n\n";

// Get all campaigns
$stmt = $conn->prepare("SELECT campaign_id, description, mail_body, images_paths FROM campaign_master");
$stmt->execute();
$campaigns = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if (empty($campaigns)) {
    echo "❌ No campaigns found\n\n";
    exit;
}

echo "Found " . count($campaigns) . " campaigns to check\n\n";

$fixed = 0;
$skipped = 0;

foreach ($campaigns as $campaign) {
    $campaignId = $campaign['campaign_id'];
    $description = $campaign['description'];
    $body = $campaign['mail_body'];
    $imagesPaths = $campaign['images_paths'];
    
    echo "─────────────────────────────────────────────────────────\n";
    echo "Campaign #$campaignId: $description\n";
    
    $needsUpdate = false;
    $updatedBody = $body;
    $extractedPaths = [];
    
    // Check if body contains localhost URLs
    if (preg_match('/localhost/', $body)) {
        echo "  ⚠️  Found localhost URLs in body\n";
        
        // Extract all image paths from localhost URLs
        if (preg_match_all('/http:\/\/localhost\/verify_emails\/MailPilot_CRM\/backend\/(storage\/images\/[^"\'>\s]+)/i', $body, $matches)) {
            $extractedPaths = array_unique($matches[1]);
            echo "  ✓ Extracted " . count($extractedPaths) . " image path(s):\n";
            foreach ($extractedPaths as $path) {
                echo "    - $path\n";
                
                // Check if file exists
                $fullPath = __DIR__ . '/../' . $path;
                if (file_exists($fullPath)) {
                    echo "      ✓ File exists (" . filesize($fullPath) . " bytes)\n";
                } else {
                    echo "      ⚠️  File NOT found at: $fullPath\n";
                }
            }
        }
        
        // Replace localhost URLs with relative paths
        $updatedBody = preg_replace(
            '/http:\/\/localhost\/verify_emails\/MailPilot_CRM\/backend\/(storage\/images\/[^"\'>\s]+)/i',
            '$1',
            $body
        );
        
        $needsUpdate = true;
        echo "  ✓ Replaced localhost URLs with relative paths\n";
    }
    
    // Check if images_paths is empty but we have images in body
    if (empty($imagesPaths) && !empty($extractedPaths)) {
        $needsUpdate = true;
        echo "  ✓ Will populate images_paths column\n";
    }
    
    if ($needsUpdate) {
        // Update the campaign
        $imagesJson = !empty($extractedPaths) ? json_encode($extractedPaths) : $imagesPaths;
        
        $updateStmt = $conn->prepare("UPDATE campaign_master SET mail_body = ?, images_paths = ? WHERE campaign_id = ?");
        $updateStmt->bind_param("ssi", $updatedBody, $imagesJson, $campaignId);
        
        if ($updateStmt->execute()) {
            echo "  ✅ FIXED successfully!\n";
            $fixed++;
        } else {
            echo "  ❌ Failed to update: " . $updateStmt->error . "\n";
        }
        $updateStmt->close();
    } else {
        echo "  ✓ No changes needed\n";
        $skipped++;
    }
    
    echo "\n";
}

echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║  SUMMARY                                                 ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n\n";
echo "Total campaigns: " . count($campaigns) . "\n";
echo "Fixed: $fixed\n";
echo "Skipped (no changes needed): $skipped\n\n";

if ($fixed > 0) {
    echo "✅ SUCCESS: $fixed campaign(s) have been fixed!\n";
    echo "\n";
    echo "WHAT WAS FIXED:\n";
    echo "1. Replaced localhost URLs with relative paths in mail_body\n";
    echo "2. Populated images_paths column with extracted image paths\n";
    echo "\n";
    echo "NOW:\n";
    echo "- Images will be properly embedded when sending emails\n";
    echo "- CID references will be generated correctly\n";
    echo "- Images will display in ALL email clients\n";
} else {
    echo "ℹ️  No campaigns needed fixing\n";
}

echo "\n═══════════════════════════════════════════════════════════\n\n";

$conn->close();
?>
