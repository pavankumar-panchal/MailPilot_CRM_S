<?php
// Verify session endpoint - checks if user session is valid
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:5174');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Load database connection if needed for token validation
require_once __DIR__ . '/../config/db.php';

// Check if session exists and is valid
if (isset($_SESSION['user_id']) && isset($_SESSION['token_expiry']) && isset($_SESSION['token'])) {
    $currentTime = time();
    $expiryTime = $_SESSION['token_expiry'];
    $userId = $_SESSION['user_id'];
    $token = $_SESSION['token'];
    
    // Check if token has expired (24 hours)
    if ($currentTime < $expiryTime) {
        // Verify token still exists in database (handles logout from other devices)
        $stmt = $conn->prepare('SELECT id FROM user_tokens WHERE user_id = ? AND token = ? AND expires_at > NOW() LIMIT 1');
        $stmt->bind_param('is', $userId, $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Session is valid
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
            // Token not found - session invalidated
            session_destroy();
            echo json_encode([
                'success' => false,
                'valid' => false,
                'message' => 'Session invalidated. Please login again.'
            ]);
        }
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
