<?php
// Login endpoint for Relyon CRM with 24-hour session management
error_log("login.php execution started");

// Only start session and handle CORS if not called through router
if (!defined('ROUTER_HANDLED')) {
    error_log("login.php: ROUTER_HANDLED not defined, loading dependencies");
    try {
        require_once __DIR__ . '/../includes/session_config.php';
        require_once __DIR__ . '/../includes/security_helpers.php';
        
        // Set security headers
        setSecurityHeaders();
        
        // Handle CORS securely
        handleCors();
    } catch (Exception $e) {
        error_log("login.php: Failed to load dependencies: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Configuration error', 'details' => $e->getMessage()]);
        exit;
    }
} else {
    error_log("login.php: ROUTER_HANDLED is defined, skipping session/CORS");
}

// Always load database config if $conn is not already defined
if (!isset($conn)) {
    try {
        require_once __DIR__ . '/../config/db.php';
        error_log("login.php: Database config loaded");
    } catch (Exception $e) {
        error_log("login.php: Failed to load database config: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Database error', 'details' => $e->getMessage()]);
        exit;
    }
}

$rawInput = file_get_contents('php://input');
error_log("login.php: Raw input length: " . strlen($rawInput));

$input = json_decode($rawInput, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("login.php: JSON decode error: " . json_last_error_msg());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
    exit();
}

$email = $input['email'] ?? '';
$password = $input['password'] ?? '';
error_log("login.php: Email provided: " . ($email ? 'yes' : 'no'));
error_log("login.php: Password provided: " . ($password ? 'yes' : 'no'));

try {
    if (!function_exists('validateEmail')) {
        error_log("login.php: validateEmail function not found, loading security_helpers");
        require_once __DIR__ . '/../includes/security_helpers.php';
    }
    $email = validateEmail($email);
    error_log("login.php: Email validated: $email");
    if (strlen($password) < 6) throw new Exception('Password must be at least 6 characters');
} catch (Exception $e) {
    error_log("login.php: Validation error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit();
}

$stmt = $conn->prepare('SELECT id, email, password_hash, name, role, permissions, is_active FROM users WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    if (!$row['is_active']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Account is disabled. Contact administrator.']);
        exit();
    }
    if (password_verify($password, $row['password_hash'])) {
        // Update last login timestamp
        $updateStmt = $conn->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
        $updateStmt->bind_param('i', $row['id']);
        $updateStmt->execute();
        
        // Generate secure session token with 24-hour expiry
        $token = bin2hex(random_bytes(32));
        $expiryTime = time() + (24 * 60 * 60); // 24 hours from now
        $expiryDateTime = date('Y-m-d H:i:s', $expiryTime);
        
        // Store token in database
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $tokenStmt = $conn->prepare('INSERT INTO user_tokens (user_id, token, expires_at, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)');
        $tokenStmt->bind_param('issss', $row['id'], $token, $expiryDateTime, $ipAddress, $userAgent);
        $tokenStmt->execute();
        $tokenStmt->close();
        
        // Get session ID BEFORE regenerating or closing
        $originalSessionId = session_id();
        
        // Regenerate session ID for security
        session_regenerate_id(true);
        $newSessionId = session_id();
        
        // Store session in PHP session
        $_SESSION['user_id'] = $row['id'];
        $_SESSION['user_email'] = $row['email'];
        $_SESSION['user_name'] = $row['name'];
        $_SESSION['user_role'] = $row['role'];
        $_SESSION['token'] = $token;
        $_SESSION['token_expiry'] = $expiryTime;

        // Detect HTTPS for secure cookie flag
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
                    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
                    || (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

        // Ensure session cookie is set explicitly (helps with both localhost and production)
        $cookieParams = session_get_cookie_params();
        setcookie(session_name(), session_id(), [
            'expires' => time() + 86400,
            'path' => $cookieParams['path'] ?? '/',
            // 'domain' omitted intentionally (host-only cookie works everywhere)
            'secure' => $isHttps,
            'httponly' => $cookieParams['httponly'] ?? false,
            'samesite' => $cookieParams['samesite'] ?? 'Lax'
        ]);
        
        // Parse permissions JSON
        $permissions = $row['permissions'] ? json_decode($row['permissions'], true) : [];
        
        // Log session info and cookie params
        error_log("Login successful - Session ID: " . $newSessionId . " - User ID: " . $row['id']);
        error_log("Session cookie params: " . print_r(session_get_cookie_params(), true));
        error_log("Headers sent: " . (headers_sent() ? 'YES' : 'NO'));
        
        // Keep session open; cookie has been set above
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'token' => $token,
            'expiresAt' => date('Y-m-d H:i:s', $expiryTime),
            'sessionId' => $newSessionId,
            'sessionName' => session_name(),
            'user' => [
                'id' => $row['id'],
                'email' => $row['email'],
                'name' => $row['name'],
                'role' => $row['role'],
                'permissions' => $permissions
            ]
        ]);
        exit();
    }
}
http_response_code(401);
echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
