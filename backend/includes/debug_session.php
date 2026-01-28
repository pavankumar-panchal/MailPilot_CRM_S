<?php
/**
 * Debug endpoint to check session status
 */

require_once __DIR__ . '/session_config.php';
require_once __DIR__ . '/user_filtering.php';
require_once __DIR__ . '/security_helpers.php';

// Set security headers
setSecurityHeaders();

// Handle CORS securely
handleCors();

header('Content-Type: application/json');

$currentUser = getCurrentUser();

$sessionData = [];
foreach ($_SESSION as $key => $value) {
    if ($key !== 'password_hash') { // Don't expose sensitive data
        $sessionData[$key] = $value;
    }
}

$response = [
    'session_id' => session_id(),
    'session_name' => session_name(),
    'has_user' => $currentUser !== null,
    'user' => $currentUser,
    'session_data' => $sessionData,
    'cookies_received' => isset($_COOKIE[session_name()]),
    'cookie_value' => isset($_COOKIE[session_name()]) ? substr($_COOKIE[session_name()], 0, 10) . '...' : null
];

echo json_encode($response, JSON_PRETTY_PRINT);
