<?php
require_once __DIR__ . '/../config/db.php';

// Function to insert SMTP server
function insertSmtpServer($conn, $host, $reply_to) {
    $stmt = $conn->prepare("INSERT INTO smtp_servers (host, reply_to, is_active) VALUES (?, ?, 1)");
    $stmt->bind_param("ss", $host, $reply_to);
    $stmt->execute();
    return $conn->insert_id;
}

// Function to insert SMTP account
function insertSmtpAccount($conn, $smtp_server_id, $email, $password) {
    $stmt = $conn->prepare("INSERT INTO smtp_accounts (smtp_server_id, email, password, daily_limit, hourly_limit, is_active) 
                           VALUES (?, ?, ?, 500, 100, 1)");
    $stmt->bind_param("iss", $smtp_server_id, $email, $password);
    return $stmt->execute();
}

// Configuration
$host = "relyonsoft.info";
$reply_to = "reply@relyonsoft.com";

// First, insert the SMTP server
$smtp_server_id = insertSmtpServer($conn, $host, $reply_to);

if ($smtp_server_id) {
    echo "SMTP Server created successfully with ID: $smtp_server_id\n";
    
    // Now you can read your Excel file and insert accounts
    // For demonstration, I'll show how to insert a few example accounts
    $accounts = [
        ['email' => 'praveen@relyonsoft.info', 'password' => 'praveen_password'],
        ['email' => 'chethan@relyonsoft.info', 'password' => 'chethan_password'],
        ['email' => 'divya@relyonsoft.info', 'password' => 'divya_password']
    ];
    
    foreach ($accounts as $account) {
        if (insertSmtpAccount($conn, $smtp_server_id, $account['email'], $account['password'])) {
            echo "Successfully inserted account: {$account['email']}\n";
        } else {
            echo "Error inserting account: {$account['email']}\n";
        }
    }
} else {
    echo "Error creating SMTP server\n";
}

$conn->close();
?>