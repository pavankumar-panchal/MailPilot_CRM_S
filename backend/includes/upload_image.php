<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Load configuration
require_once __DIR__ . '/../config/config.php';

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
    $allowedTypes = ALLOWED_IMAGE_TYPES;
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        throw new Exception('Invalid file type. Only images are allowed.');
    }
    
    // Validate file size
    $maxSize = MAX_UPLOAD_SIZE;
    if ($file['size'] > $maxSize) {
        throw new Exception('File too large. Maximum size is ' . ($maxSize / 1024 / 1024) . 'MB.');
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
    
    // Build the full URL using BASE_URL from config
    $imageUrl = BASE_URL . '/backend/storage/images/' . $filename;
    
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
