<?php
// Logout endpoint for Relyon CRM
require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../includes/security_helpers.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

// Handle CORS
handleCors();

// Set security headers
setSecurityHeaders();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Get token from session or Authorization header
$token = $_SESSION['token'] ?? null;
if (!$token) {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    if (preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
        $token = $matches[1];
    }
}

// Invalidate token in database
if ($token) {
    try {
        $stmt = $conn->prepare('DELETE FROM user_tokens WHERE token = ?');
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error deleting token: " . $e->getMessage());
    }
}

// Clear all session data
$_SESSION = array();

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"] ?? '',
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

echo json_encode([
    'success' => true,
    'message' => 'Logged out successfully'
]);
?>
