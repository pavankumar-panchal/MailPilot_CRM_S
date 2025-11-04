<?php

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

// For localhost development
if ($host === 'localhost' || strpos($host, '127.0.0.1') !== false) {
    define('BASE_URL', $protocol . '://' . $host . '/verify_emails/MailPilot_CRM');
} else {
    // For production server - auto-detects domain
    define('BASE_URL', $protocol . '://' . $host);
   
}

// Storage paths (relative to backend root)
define('STORAGE_ATTACHMENTS', 'storage/attachments/');
define('STORAGE_IMAGES', 'storage/images/');

// Upload limits
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB

// Allowed image types
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp']);
