<?php
/**
 * Test script to verify image embedding works correctly
 * Usage: php test_image_embed.php <campaign_id> <recipient_email>
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/db.php';

$campaignId = isset($argv[1]) ? intval($argv[1]) : die("Usage: php test_image_embed.php <campaign_id> <recipient_email>\n");
$recipientEmail = $argv[2] ?? die("Usage: php test_image_embed.php <campaign_id> <recipient_email>\n");

// Get campaign details
$result = $db->query("SELECT mail_subject, mail_body, send_as_html, images_paths, attachment_path, reply_to 
                       FROM campaign_master WHERE campaign_id = $campaignId");
if (!$result || $result->num_rows === 0) {
    die("Campaign #$campaignId not found\n");
}

$campaign = $result->fetch_assoc();

// Get first active SMTP
$smtpResult = $db->query("
    SELECT sa.email AS smtp_email, sa.password AS smtp_password, 
           ss.host, ss.port, ss.encryption, ss.received_email
    FROM smtp_accounts sa
    JOIN smtp_servers ss ON sa.smtp_server_id = ss.id
    WHERE sa.is_active = 1 AND ss.is_active = 1
    LIMIT 1
");

if (!$smtpResult || $smtpResult->num_rows === 0) {
    die("No active SMTP accounts found\n");
}

$smtp = $smtpResult->fetch_assoc();

echo "==============================================\n";
echo "Testing Image Embedding for Campaign #$campaignId\n";
echo "==============================================\n\n";

echo "Mail Subject: {$campaign['mail_subject']}\n";
echo "Send as HTML: " . ($campaign['send_as_html'] ? 'Yes' : 'No') . "\n";
echo "Images: " . ($campaign['images_paths'] ?: 'None') . "\n";
echo "To: $recipientEmail\n";
echo "SMTP: {$smtp['smtp_email']} ({$smtp['host']}:{$smtp['port']})\n\n";

// Process body and images like email_blaster.php does
$body = $campaign['mail_body'];
$isHtml = !empty($campaign['send_as_html']);

$mail = new PHPMailer(true);

try {
    // Setup SMTP
    $mail->isSMTP();
    $mail->Host = $smtp['host'];
    $mail->Port = $smtp['port'];
    $mail->SMTPAuth = true;
    $mail->Username = $smtp['smtp_email'];
    $mail->Password = $smtp['smtp_password'];
    $mail->Timeout = 30;
    $mail->SMTPDebug = 0;

    if ($smtp['encryption'] === 'ssl') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } elseif ($smtp['encryption'] === 'tls') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    }

    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );

    $mail->setFrom($smtp['smtp_email']);
    $mail->addAddress($recipientEmail);
    $mail->Subject = $campaign['mail_subject'];
    $mail->CharSet = 'UTF-8';

    if ($isHtml) {
        $mail->isHTML(true);
        $mail->Body = $body;
        $mail->AltBody = trim(strip_tags($body));
    } else {
        $mail->isHTML(false);
        $mail->Body = $body;
        $mail->AltBody = $body;
    }

    // Set Reply-To
    if (!empty($campaign['reply_to'])) {
        $mail->addReplyTo($campaign['reply_to']);
    } elseif (!empty($smtp['received_email'])) {
        $mail->addReplyTo($smtp['received_email']);
    }

    // Add attachment if present
    if (!empty($campaign['attachment_path'])) {
        $attachmentPath = __DIR__ . '/../' . $campaign['attachment_path'];
        if (file_exists($attachmentPath)) {
            $mail->addAttachment($attachmentPath);
            echo "✓ Attachment added: " . basename($attachmentPath) . "\n";
        }
    }

    // Add embedded images if present
    if (!empty($campaign['images_paths'])) {
        $images = is_string($campaign['images_paths']) 
            ? json_decode($campaign['images_paths'], true) 
            : $campaign['images_paths'];
        
        if (is_array($images)) {
            echo "\n--- Processing Embedded Images ---\n";
            echo "Found " . count($images) . " image(s)\n\n";
            
            foreach ($images as $index => $imagePath) {
                $fullPath = __DIR__ . '/../' . $imagePath;
                echo "Image $index: $imagePath\n";
                echo "  Full path: $fullPath\n";
                echo "  Exists: " . (file_exists($fullPath) ? 'Yes' : 'No') . "\n";
                
                if (file_exists($fullPath)) {
                    $cid = 'image_' . $index . '_' . uniqid();
                    $mail->addEmbeddedImage($fullPath, $cid);
                    echo "  CID: $cid\n";
                    
                    if ($isHtml) {
                        $filename = basename($imagePath);
                        $escapedPath = preg_quote($imagePath, '/');
                        $escapedFilename = preg_quote($filename, '/');
                        
                        // Show body before replacement
                        echo "  Body before: " . substr($body, 0, 200) . "...\n";
                        
                        // Try pattern 1: full path
                        $count = 0;
                        $body = preg_replace(
                            '/(<img[^>]+src=["\'])[^"\']*' . $escapedPath . '(["\'])/i',
                            '${1}cid:' . $cid . '${2}',
                            $body,
                            -1,
                            $count
                        );
                        
                        if ($count > 0) {
                            echo "  ✓ Replaced $count occurrence(s) using full path pattern\n";
                        } else {
                            // Try pattern 2: filename only
                            $body = preg_replace(
                                '/(<img[^>]+src=["\'])[^"\']*' . $escapedFilename . '(["\'])/i',
                                '${1}cid:' . $cid . '${2}',
                                $body,
                                -1,
                                $count
                            );
                            
                            if ($count > 0) {
                                echo "  ✓ Replaced $count occurrence(s) using filename pattern\n";
                            } else {
                                echo "  ⚠ WARNING: No matches found!\n";
                            }
                        }
                        
                        echo "  Body after: " . substr($body, 0, 200) . "...\n";
                    }
                }
                echo "\n";
            }
            
            // Update mail body with CID references
            if ($isHtml) {
                $mail->Body = $body;
                echo "✓ Mail body updated with CID references\n\n";
            }
        }
    }

    echo "--- Sending Email ---\n";
    if ($mail->send()) {
        echo "✅ SUCCESS! Email sent to $recipientEmail\n";
        echo "\nCheck your inbox to verify images display correctly.\n";
    } else {
        echo "❌ FAILED: " . $mail->ErrorInfo . "\n";
    }

} catch (Exception $e) {
    echo "❌ EXCEPTION: " . $e->getMessage() . "\n";
}

echo "\n==============================================\n";
?>
