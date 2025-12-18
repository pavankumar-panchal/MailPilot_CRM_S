<?php
// Helper functions for tracking SMTP usage (hourly and daily limits)
// Note: Expects $conn (mysqli) to be already defined by caller
// Uses EXISTING smtp_usage table schema: smtp_id, date, hour, emails_sent

function ensureSmtpUsageSchema(mysqli $conn): void {
    // Check if table exists and has correct columns
    $check = $conn->query("SHOW TABLES LIKE 'smtp_usage'");
    if ($check && $check->num_rows > 0) {
        // Table exists, check if it has the correct columns
        $cols = [];
        $result = $conn->query("DESCRIBE smtp_usage");
        while ($row = $result->fetch_assoc()) {
            $cols[] = $row['Field'];
        }
        
        // Add missing columns if needed
        if (!in_array('account_id', $cols)) {
            // Old schema - use it as-is
            return;
        }
    }
    
    // Create new schema if table doesn't exist
    $conn->query("CREATE TABLE IF NOT EXISTS smtp_usage (
        id INT AUTO_INCREMENT PRIMARY KEY,
        smtp_id INT NOT NULL,
        date DATE NOT NULL,
        hour TINYINT NOT NULL,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        emails_sent INT NOT NULL DEFAULT 0,
        UNIQUE KEY uniq_usage (smtp_id, date, hour)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function getUsage(mysqli $conn, int $smtp_server_id, int $account_id): array {
    $date = date('Y-m-d');
    $hour = intval(date('G'));
    
    // Use account_id as smtp_id (since existing table uses smtp_id for account tracking)
    $smtp_id = $account_id;
    
    // Get current hour usage
    $stmt = $conn->prepare('SELECT emails_sent FROM smtp_usage WHERE smtp_id=? AND date=? AND hour=?');
    if (!$stmt) {
        error_log("getUsage ERROR: prepare failed: " . $conn->error);
        return ['sent_hour' => 0, 'sent_day' => 0];
    }
    $stmt->bind_param('isi', $smtp_id, $date, $hour);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    
    $sent_hour = $res ? (int)$res['emails_sent'] : 0;
    
    // Get daily total
    $dayStmt = $conn->prepare('SELECT COALESCE(SUM(emails_sent), 0) as sent_day FROM smtp_usage WHERE smtp_id=? AND date=?');
    $dayStmt->bind_param('is', $smtp_id, $date);
    $dayStmt->execute();
    $dayRes = $dayStmt->get_result()->fetch_assoc();
    $sent_day = (int)$dayRes['sent_day'];
    
    return ['sent_hour' => $sent_hour, 'sent_day' => $sent_day];
}

function incrementUsage(mysqli $conn, int $smtp_server_id, int $account_id, int $count = 1): void {
    $date = date('Y-m-d');
    $hour = intval(date('G'));
    $smtp_id = $account_id;
    
    // Insert or update current hour
    $stmt = $conn->prepare('INSERT INTO smtp_usage (smtp_id, date, hour, emails_sent, timestamp) VALUES (?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE emails_sent = emails_sent + ?, timestamp = NOW()');
    $stmt->bind_param('isiii', $smtp_id, $date, $hour, $count, $count);
    $stmt->execute();
}

?>