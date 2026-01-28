<?php
/**
 * Migration script to add existing session tokens to user_tokens table
 * Run this once to migrate users who are currently logged in
 */

require_once __DIR__ . '/../config/db.php';

// Check for any localStorage tokens that might be in use
// This script should be run after users login

echo "Migrating existing session tokens..." . PHP_EOL;

// Get all active users who logged in recently
$result = $conn->query("
    SELECT id, email, name, role 
    FROM users 
    WHERE is_active = 1 
    AND last_login >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
");

$migrated = 0;
while ($user = $result->fetch_assoc()) {
    // Check if user already has a valid token
    $checkStmt = $conn->prepare("
        SELECT COUNT(*) as cnt 
        FROM user_tokens 
        WHERE user_id = ? AND expires_at > NOW()
    ");
    $checkStmt->bind_param('i', $user['id']);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $row = $checkResult->fetch_assoc();
    
    if ($row['cnt'] == 0) {
        // Create a new token for this user
        $token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', time() + 86400);
        
        $stmt = $conn->prepare('INSERT INTO user_tokens (user_id, token, expires_at) VALUES (?, ?, ?)');
        $stmt->bind_param('iss', $user['id'], $token, $expires_at);
        $stmt->execute();
        
        echo "Created token for user: {$user['email']} (ID: {$user['id']})" . PHP_EOL;
        echo "  Token: $token" . PHP_EOL;
        $migrated++;
    } else {
        echo "User {$user['email']} already has a valid token" . PHP_EOL;
    }
}

echo PHP_EOL . "Migration complete. Created $migrated new tokens." . PHP_EOL;
echo "Users should logout and login again to use the new authentication system." . PHP_EOL;
?>
