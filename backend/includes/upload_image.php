<?php
header('Content-Type: application/json');

// Get the origin from the request
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';

// Allow specific origins (localhost on any port for development)
$allowed_origins = [
    'http://localhost:5173',
    'http://localhost:5174',
    'http://localhost:3000',
    'http://127.0.0.1:5173',
    'http://127.0.0.1:5174',
    'https://payrollsoft.in'
];

// Check if the origin is allowed
if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
} else {
    // For other localhost ports, allow them too
    if (strpos($origin, 'http://localhost:') === 0 || strpos($origin, 'http://127.0.0.1:') === 0) {
        header("Access-Control-Allow-Origin: $origin");
        header("Access-Control-Allow-Credentials: true");
    }
}

header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Load database connection
require_once __DIR__ . '/../config/db.php';

// Define constants for image upload
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

// Auto-detect BASE_URL
if ($host === 'localhost' || strpos($host, '127.0.0.1') !== false || strpos($host, 'lampp') !== false) {
    // Local development
    $BASE_URL = $protocol . '://' . $host . '/verify_emails/MailPilot_CRM';
} elseif ($host === 'payrollsoft.in' || strpos($host, 'payrollsoft.in') !== false) {
    // Production server: payrollsoft.in
    $BASE_URL = 'https://payrollsoft.in/emailvalidation';
} else {
    // Other servers - automatically uses actual domain
    $BASE_URL = $protocol . '://' . $host;
}

// Upload limits
$MAX_UPLOAD_SIZE = 5 * 1024 * 1024; // 5MB
$ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];

// Handle preflight (CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    if (!isset($_FILES['image'])) {
        throw new Exception('No image file in request');
    }
    
    if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize in php.ini',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE in HTML form',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        $errorMsg = $errorMessages[$_FILES['image']['error']] ?? 'Unknown upload error: ' . $_FILES['image']['error'];
        throw new Exception($errorMsg);
    }

    $file = $_FILES['image'];
    
    // Validate file type (images only)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $ALLOWED_IMAGE_TYPES)) {
        throw new Exception('Invalid file type. Only images are allowed.');
    }
    
    // Validate file size
    if ($file['size'] > $MAX_UPLOAD_SIZE) {
        throw new Exception('File too large. Maximum size is ' . ($MAX_UPLOAD_SIZE / 1024 / 1024) . 'MB.');
    }
    
    // Create upload directory if it doesn't exist
    $uploadDir = __DIR__ . '/../storage/images/';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true)) {
            throw new Exception('Failed to create upload directory');
        }
    }
    
    // Check if directory is writable
    if (!is_writable($uploadDir)) {
        throw new Exception('Upload directory is not writable: ' . $uploadDir);
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    if (empty($extension)) {
        $extension = 'jpg'; // Default extension
    }
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $targetPath = $uploadDir . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        $error = error_get_last();
        throw new Exception('Failed to save uploaded file. Target: ' . $targetPath . ' Error: ' . ($error ? $error['message'] : 'Unknown'));
    }
    
    // Set file permissions
    chmod($targetPath, 0644);
    
    // Build the full URL
    $imageUrl = $BASE_URL . '/backend/storage/images/' . $filename;
    
    echo json_encode([
        'success' => true,
        'url' => $imageUrl,
        'path' => 'storage/images/' . $filename
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
