<?php
// Registration endpoint for Relyon CRM

// Only start session and handle CORS if not called through router
if (!defined('ROUTER_HANDLED')) {
    session_start();
    header('Content-Type: application/json');
    // Dynamic CORS for local dev ports and production
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (preg_match('/^https?:\/\/payrollsoft\.in/', $origin)) {
        header('Access-Control-Allow-Origin: ' . $origin);
    } elseif (preg_match('/^http:\/\/localhost:(5173|5174|5175|5176)$/', $origin)) {
        header('Access-Control-Allow-Origin: ' . $origin);
    } else {
        header('Access-Control-Allow-Origin: http://localhost');
    }
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/security_helpers.php';

$input = json_decode(file_get_contents('php://input'), true);
$name = $input['name'] ?? '';
$email = $input['email'] ?? '';
$password = $input['password'] ?? '';
$role = $input['role'] ?? 'user'; // Default role is user, can be 'admin'

// Validate input
try {
    if (empty($name)) throw new Exception('Name is required');
    $name = sanitizeString($name, 100);
    $email = validateEmail($email);
    
    if (strlen($password) < 6) {
        throw new Exception('Password must be at least 6 characters');
    }
    if (strlen($password) > 100) {
        throw new Exception('Password is too long');
    }
    
    // Validate role
    if (!in_array($role, ['user', 'admin'])) {
        $role = 'user';
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit();
}

// Check if user already exists
$stmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Email already registered']);
    exit();
}

// Hash password and insert user
$passwordHash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $conn->prepare('INSERT INTO users (name, email, password_hash, role, is_active, created_at) VALUES (?, ?, ?, ?, 1, NOW())');
$stmt->bind_param('ssss', $name, $email, $passwordHash, $role);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true, 
        'message' => "Registration successful! You can now login as {$role}."
    ]);
} else {
    error_log('Registration error: ' . $conn->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Registration failed. Please try again.']);
}
?>