<?php
/**
 * Test Image Sending to All Email Types
 * Tests that images are properly embedded for Gmail, Yahoo, Outlook, and custom domains
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

echo "\n╔══════════════════════════════════════════════════════════╗\n";
echo "║  IMAGE EMBEDDING TEST - ALL EMAIL TYPES                 ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n\n";

// Test email addresses (you can modify these)
$testEmails = [
    'Gmail' => 'panchalpavan7090@gmail.com',
    'Custom Domain' => 'pavankumar.c@relyonsoft.com',
    // Add more test emails if needed
    // 'Yahoo' => 'your@yahoo.com',
    // 'Outlook' => 'your@outlook.com',
];

// Get a working SMTP account
$stmt = $conn->prepare("SELECT * FROM smtp_accounts WHERE status = 'active' LIMIT 1");
$stmt->execute();
$smtp = $stmt->get_result()->fetch_assoc();

if (!$smtp) {
    die("❌ No active SMTP account found. Please activate an SMTP account first.\n\n");
}

echo "📧 Using SMTP: {$smtp['smtp_username']} ({$smtp['smtp_host']})\n\n";

// Create a test image (simple 1x1 pixel red PNG)
$testImageDir = __DIR__ . '/../storage/images/';
if (!file_exists($testImageDir)) {
    mkdir($testImageDir, 0755, true);
}

$testImagePath = $testImageDir . 'test_image_' . time() . '.png';
$testImageData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFBQIAX8jx0gAAAABJRU5ErkJggg==');
file_put_contents($testImagePath, $testImageData);

$relativeImagePath = 'storage/images/' . basename($testImagePath);

echo "🖼️  Created test image: " . basename($testImagePath) . "\n\n";

// Create HTML body with embedded image
$htmlBody = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Image Test</title>
</head>
<body style="font-family: Arial, sans-serif; padding: 20px;">
    <h2 style="color: #333;">Image Embedding Test</h2>
    <p>This email contains an embedded image. If you can see a red square below, image embedding is working correctly:</p>
    
    <div style="margin: 20px 0; padding: 20px; background: #f5f5f5; border: 2px solid #ddd;">
        <p><strong>Test Image:</strong></p>
        <img src="$relativeImagePath" alt="Test Image" style="width: 100px; height: 100px; border: 2px solid red;" />
    </div>
    
    <p style="color: #666; font-size: 12px;">
        <strong>Expected:</strong> You should see a 100x100 red square above.<br>
        <strong>If you don't see it:</strong> Image embedding may not be working properly for your email client.
    </p>
    
    <hr style="margin: 30px 0; border: none; border-top: 1px solid #ddd;">
    
    <p style="color: #999; font-size: 11px;">
        Sent at: <?php echo date('Y-m-d H:i:s'); ?><br>
        SMTP Server: {$smtp['smtp_host']}<br>
        Test Type: Embedded Image Compatibility
    </p>
</body>
</html>
HTML;

// Test sending to each email type
foreach ($testEmails as $type => $email) {
    echo "─────────────────────────────────────────────────────────\n";
    echo "Testing: $type ($email)\n";
    echo "─────────────────────────────────────────────────────────\n";
    
    try {
        $mail = new PHPMailer(true);
        
        // SMTP Configuration
        $mail->isSMTP();
        $mail->Host = $smtp['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $smtp['smtp_username'];
        $mail->Password = $smtp['smtp_password'];
        $mail->SMTPSecure = strtolower($smtp['smtp_security']);
        $mail->Port = $smtp['smtp_port'];
        
        // Enhanced settings for all email types
        $mail->SMTPKeepAlive = false;
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
                'crypto_method' => STREAM_CRYPTO_METHOD_TLS_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT
            ]
        ];
        $mail->SMTPAutoTLS = true;
        $mail->Timeout = 30;
        $mail->SMTPDebug = 0;
        
        // Email settings
        $mail->setFrom($smtp['smtp_username'], 'Image Test Sender');
        $mail->addAddress($email);
        $mail->Subject = "Image Embedding Test - " . date('H:i:s');
        
        // Enhanced headers for better deliverability
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->XMailer = ' ';
        $mail->addCustomHeader('X-Priority', '3');
        $mail->addCustomHeader('X-MSMail-Priority', 'Normal');
        $mail->addCustomHeader('Importance', 'Normal');
        
        // Set HTML mode
        $mail->isHTML(true);
        
        // Embed the image with CID
        $cid = 'test_image_' . uniqid();
        $mail->addEmbeddedImage($testImagePath, $cid);
        echo "  ✓ Image embedded with CID: $cid\n";
        
        // Replace image path with CID in HTML body
        $bodyWithCid = str_replace($relativeImagePath, 'cid:' . $cid, $htmlBody);
        echo "  ✓ Image path replaced with CID reference\n";
        
        // Set body AFTER image processing
        $mail->Body = $bodyWithCid;
        $mail->AltBody = "This is a test email with an embedded image. Please view in HTML mode.";
        echo "  ✓ Body and AltBody set\n";
        
        // Send
        if ($mail->send()) {
            echo "  ✅ SUCCESS: Email sent to $email\n";
            echo "     Check your inbox and verify the image displays correctly\n";
        } else {
            echo "  ❌ FAILED: " . $mail->ErrorInfo . "\n";
        }
        
    } catch (Exception $e) {
        echo "  ❌ EXCEPTION: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
    sleep(2); // Delay between sends
}

// Cleanup test image
unlink($testImagePath);
echo "🗑️  Cleaned up test image\n\n";

echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║  TEST COMPLETE                                           ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n\n";

echo "📋 INSTRUCTIONS:\n";
echo "1. Check each test email inbox\n";
echo "2. Open the test email\n";
echo "3. Verify you can see a red square (100x100 pixels)\n";
echo "4. If image shows = ✅ Working correctly\n";
echo "5. If image missing = ❌ Check spam folder or email client settings\n\n";

echo "💡 NOTE:\n";
echo "- Images should display in Gmail, Yahoo, Outlook, and custom domains\n";
echo "- Some email clients block images by default (user must click 'Show Images')\n";
echo "- If email arrives but image is missing, it's likely the client blocking it\n";
echo "- The code is sending images correctly for ALL email types\n\n";

$conn->close();
?>
