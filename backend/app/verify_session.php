<?php
// Verify session endpoint - checks if user session is valid
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:5174');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Check if session exists and is valid
if (isset($_SESSION['user_id']) && isset($_SESSION['token_expiry'])) {
    $currentTime = time();
    $expiryTime = $_SESSION['token_expiry'];
    
    // Check if token has expired (24 hours)
    if ($currentTime < $expiryTime) {
        echo json_encode([
            'success' => true,
            'valid' => true,
            'user' => [
                'id' => $_SESSION['user_id'],
                'email' => $_SESSION['user_email'],
                'name' => $_SESSION['user_name'],
                'role' => $_SESSION['user_role']
            ],
            'expiresAt' => date('Y-m-d H:i:s', $expiryTime)
        ]);
    } else {
        // Session expired
        session_destroy();
        echo json_encode([
            'success' => false,
            'valid' => false,
            'message' => 'Session expired. Please login again.'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'valid' => false,
        'message' => 'No active session'
    ]);
}
?>
