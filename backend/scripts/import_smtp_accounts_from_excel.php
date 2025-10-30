<?php
require_once __DIR__ . '/../../config/db.php';
require 'vendor/autoload.php'; // Make sure you have PhpSpreadsheet installed

use PhpOffice\PhpSpreadsheet\IOFactory;

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

try {
    // Load Excel file
    $inputFileName = __DIR__ . '/../../email_id_server_active.xlsx';
    $spreadsheet = IOFactory::load($inputFileName);
    $worksheet = $spreadsheet->getActiveSheet();
    
    // Get the highest row number
    $highestRow = $worksheet->getHighestRow();
    
    // Configuration
    $host = "relyonsoft.info";
    $reply_to = "reply@relyonsoft.com";
    
    // First, insert the SMTP server
    $smtp_server_id = insertSmtpServer($conn, $host, $reply_to);
    
    if ($smtp_server_id) {
        echo "SMTP Server created successfully with ID: $smtp_server_id\n";
        
        // Read data from Excel
        // Assuming first row has names, second row has emails, and last row has passwords
        $names = $worksheet->getRowIterator(1)->current();
        $emails = $worksheet->getRowIterator(2)->current();
        $passwords = $worksheet->getRowIterator($highestRow)->current();
        
        // Convert cell iterators to arrays
        $namesArray = [];
        $emailsArray = [];
        $passwordsArray = [];
        
        foreach ($names->getCellIterator() as $cell) {
            $namesArray[] = $cell->getValue();
        }
        
        foreach ($emails->getCellIterator() as $cell) {
            $emailsArray[] = $cell->getValue();
        }
        
        foreach ($passwords->getCellIterator() as $cell) {
            $passwordsArray[] = $cell->getValue();
        }
        
        // Insert accounts
        for ($i = 0; $i < count($emailsArray); $i++) {
            if (!empty($emailsArray[$i])) {
                if (insertSmtpAccount($conn, $smtp_server_id, $emailsArray[$i], $passwordsArray[$i])) {
                    echo "Successfully inserted account for {$namesArray[$i]}: {$emailsArray[$i]}\n";
                } else {
                    echo "Error inserting account: {$emailsArray[$i]}\n";
                }
            }
        }
    } else {
        echo "Error creating SMTP server\n";
    }
    
} catch (Exception $e) {
    echo "Error loading file: " . $e->getMessage() . "\n";
}

$conn->close();
?>