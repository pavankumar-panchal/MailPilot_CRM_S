<?php
/**
 * Check Campaign Health - Validate campaigns before sending
 * Usage: php check_campaign.php [campaign_id]
 */

require_once __DIR__ . '/../config/db.php';

$campaign_id = isset($argv[1]) ? (int)$argv[1] : null;

if (!$campaign_id) {
    echo "Usage: php check_campaign.php [campaign_id]\n";
    echo "\nExample: php check_campaign.php 17\n";
    exit(1);
}

echo "========================================\n";
echo "Campaign Health Check - Campaign #$campaign_id\n";
echo "========================================\n\n";

// Get campaign details
$result = $conn->query("SELECT * FROM campaign_master WHERE campaign_id = $campaign_id");
$campaign = $result->fetch_assoc();

if (!$campaign) {
    echo "❌ Campaign #$campaign_id not found!\n";
    exit(1);
}

$issues = [];
$warnings = [];

// Check 1: Mail Subject
echo "✓ Checking mail subject... ";
if (empty(trim($campaign['mail_subject']))) {
    $issues[] = "Mail subject is empty";
    echo "❌ EMPTY\n";
} else {
    echo "✓ OK (" . strlen($campaign['mail_subject']) . " chars)\n";
}

// Check 2: Mail Body
echo "✓ Checking mail body... ";
if (empty(trim($campaign['mail_body']))) {
    $issues[] = "Mail body is empty";
    echo "❌ EMPTY\n";
} else {
    $bodyLength = strlen($campaign['mail_body']);
    echo "✓ OK ($bodyLength chars)\n";
    
    // Check if HTML body has actual content
    if (!empty($campaign['send_as_html'])) {
        $textOnly = trim(strip_tags($campaign['mail_body']));
        if (empty($textOnly)) {
            $issues[] = "HTML body has no text content (only tags/whitespace)";
            echo "  ⚠️  HTML body has no text content!\n";
        } else {
            echo "  📝 Text content: " . strlen($textOnly) . " chars\n";
        }
    }
}

// Check 3: Attachment
echo "✓ Checking attachment... ";
if (!empty($campaign['attachment_path'])) {
    $attachmentPath = __DIR__ . '/../' . $campaign['attachment_path'];
    if (file_exists($attachmentPath)) {
        $size = filesize($attachmentPath);
        echo "✓ OK (" . basename($campaign['attachment_path']) . ", " . number_format($size) . " bytes)\n";
    } else {
        $warnings[] = "Attachment file not found: " . $campaign['attachment_path'];
        echo "⚠️  NOT FOUND\n";
        echo "  Expected: $attachmentPath\n";
    }
} else {
    echo "No attachment\n";
}

// Check 4: Images
echo "✓ Checking images... ";
if (!empty($campaign['images_paths'])) {
    $images = json_decode($campaign['images_paths'], true);
    if (is_array($images)) {
        $missing = [];
        foreach ($images as $img) {
            $imgPath = __DIR__ . '/../' . $img;
            if (!file_exists($imgPath)) {
                $missing[] = basename($img);
            }
        }
        if (empty($missing)) {
            echo "✓ OK (" . count($images) . " images)\n";
        } else {
            $warnings[] = "Missing images: " . implode(', ', $missing);
            echo "⚠️  " . count($missing) . " missing\n";
        }
    } else {
        echo "No images\n";
    }
} else {
    echo "No images\n";
}

// Check 5: Active SMTP Accounts
echo "✓ Checking SMTP accounts... ";
$smtp_result = $conn->query("SELECT COUNT(*) as count FROM smtp_accounts sa 
                              JOIN smtp_servers ss ON sa.smtp_server_id = ss.id 
                              WHERE sa.is_active = 1 AND ss.is_active = 1");
$smtp_count = $smtp_result->fetch_assoc()['count'];
if ($smtp_count > 0) {
    echo "✓ OK ($smtp_count active)\n";
} else {
    $issues[] = "No active SMTP accounts available";
    echo "❌ NO ACTIVE SMTP\n";
}

// Check 6: Valid Emails
echo "✓ Checking valid emails... ";
$email_result = $conn->query("SELECT COUNT(*) as count FROM emails WHERE domain_status = 1");
$email_count = $email_result->fetch_assoc()['count'];
if ($email_count > 0) {
    echo "✓ OK ($email_count valid emails)\n";
} else {
    $issues[] = "No valid emails available to send";
    echo "❌ NO VALID EMAILS\n";
}

// Check 7: Campaign Status
echo "✓ Checking campaign status... ";
$status_result = $conn->query("SELECT * FROM campaign_status WHERE campaign_id = $campaign_id");
$status = $status_result->fetch_assoc();
if ($status) {
    echo "{$status['status']}\n";
    echo "  📊 Sent: {$status['sent_emails']}, Pending: {$status['pending_emails']}, Failed: {$status['failed_emails']}\n";
} else {
    echo "⚠️  No status record\n";
}

echo "\n========================================\n";
echo "Summary\n";
echo "========================================\n";

if (empty($issues) && empty($warnings)) {
    echo "✅ Campaign is ready to send!\n";
} else {
    if (!empty($issues)) {
        echo "❌ CRITICAL ISSUES (" . count($issues) . "):\n";
        foreach ($issues as $issue) {
            echo "   • $issue\n";
        }
        echo "\n⚠️  Fix these issues before sending!\n";
    }
    
    if (!empty($warnings)) {
        echo "\n⚠️  WARNINGS (" . count($warnings) . "):\n";
        foreach ($warnings as $warning) {
            echo "   • $warning\n";
        }
        echo "\n📝 These won't stop sending but should be reviewed.\n";
    }
}

echo "========================================\n";
