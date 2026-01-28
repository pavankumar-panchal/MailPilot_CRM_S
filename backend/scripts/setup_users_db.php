<?php
// Script to properly create/update users table with role-based access control
require_once __DIR__ . '/../config/db.php';

echo "=== Updating Users Table Schema ===\n\n";

// Create table if not exists
$createTable = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(254) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(100) DEFAULT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    permissions JSON DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME DEFAULT NULL,
    INDEX idx_email (email),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($createTable)) {
    echo "✓ Users table structure ready\n";
} else {
    echo "✗ Error creating table: " . $conn->error . "\n";
}

// Check and add missing columns
$columns = [];
$result = $conn->query("DESCRIBE users");
while ($row = $result->fetch_assoc()) {
    $columns[] = $row['Field'];
}

// Add role column if missing
if (!in_array('role', $columns)) {
    $conn->query("ALTER TABLE users ADD COLUMN role ENUM('admin', 'user') DEFAULT 'user' AFTER password_hash");
    echo "✓ Added 'role' column\n";
}

// Add permissions column if missing
if (!in_array('permissions', $columns)) {
    $conn->query("ALTER TABLE users ADD COLUMN permissions JSON DEFAULT NULL AFTER role");
    echo "✓ Added 'permissions' column\n";
}

// Add last_login column if missing
if (!in_array('last_login', $columns)) {
    $conn->query("ALTER TABLE users ADD COLUMN last_login DATETIME DEFAULT NULL");
    echo "✓ Added 'last_login' column\n";
}

// Ensure is_active and created_at exist
if (!in_array('is_active', $columns)) {
    $conn->query("ALTER TABLE users ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1");
    echo "✓ Added 'is_active' column\n";
}

if (!in_array('created_at', $columns)) {
    $conn->query("ALTER TABLE users ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP");
    echo "✓ Added 'created_at' column\n";
}

// Update existing users to have default role
$conn->query("UPDATE users SET role = 'user' WHERE role IS NULL");
$conn->query("UPDATE users SET is_active = 1 WHERE is_active IS NULL OR is_active = 0");

echo "\n=== Creating Default Users ===\n\n";

// Create default admin user (password: admin123)
$adminEmail = 'admin@mailpilot.local';
$adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
$adminName = 'System Admin';

$checkAdmin = $conn->prepare("SELECT id FROM users WHERE email = ?");
$checkAdmin->bind_param('s', $adminEmail);
$checkAdmin->execute();
$result = $checkAdmin->get_result();

if ($result->num_rows == 0) {
    $stmt = $conn->prepare("INSERT INTO users (name, email, password_hash, role, is_active) VALUES (?, ?, ?, 'admin', 1)");
    $stmt->bind_param('sss', $adminName, $adminEmail, $adminPassword);
    if ($stmt->execute()) {
        echo "✓ Admin user created: $adminEmail / admin123\n";
    } else {
        echo "✗ Error creating admin: " . $conn->error . "\n";
    }
} else {
    $conn->query("UPDATE users SET role = 'admin', is_active = 1 WHERE email = '$adminEmail'");
    echo "✓ Admin user already exists: $adminEmail\n";
}

// Update the existing admin@mailpilot.com user
$existingAdmin = 'admin@mailpilot.com';
$conn->query("UPDATE users SET role = 'admin' WHERE email = '$existingAdmin'");

echo "\n=== Summary ===\n";
$result = $conn->query("SELECT id, email, name, role, is_active FROM users ORDER BY role DESC, id ASC");
echo "\nRegistered Users:\n";
echo str_repeat('-', 80) . "\n";
printf("%-5s %-30s %-20s %-10s %-10s\n", "ID", "Email", "Name", "Role", "Active");
echo str_repeat('-', 80) . "\n";
while ($row = $result->fetch_assoc()) {
    printf("%-5s %-30s %-20s %-10s %-10s\n", 
        $row['id'], 
        $row['email'], 
        $row['name'] ?: 'N/A', 
        strtoupper($row['role']), 
        $row['is_active'] ? 'Yes' : 'No'
    );
}
echo str_repeat('-', 80) . "\n";

echo "\n✅ Database update complete!\n";
echo "\nLogin Credentials:\n";
echo "  Admin: admin@mailpilot.local / admin123\n";
echo "  User:  admin@mailpilot.com / password123\n\n";

$conn->close();
?>