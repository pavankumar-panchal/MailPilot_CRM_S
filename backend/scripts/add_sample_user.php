<?php
// Script to add a sample user to the CRM database
require_once __DIR__ . '/../config/db.php';

$name = 'Admin User';
$email = 'admin@mailpilot.com';
$password = 'password123'; // Change this in production

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name), password_hash = VALUES(password_hash)');
$stmt->bind_param('sss', $name, $email, $passwordHash);

if ($stmt->execute()) {
    echo "Sample user added/updated successfully.\n";
    echo "Email: $email\n";
    echo "Password: $password\n";
} else {
    echo "Error adding user: " . $conn->error . "\n";
}
?>