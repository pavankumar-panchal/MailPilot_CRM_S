<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../includes/security_helpers.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/db_campaign.php'; // SERVER 2 for mail_blaster table

// Set security headers
setSecurityHeaders();

// Handle CORS securely
handleCors();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$campaign_id = isset($_GET['campaign_id']) ? intval($_GET['campaign_id']) : 0;

if ($campaign_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid campaign ID']);
    exit();
}

// Set filename
$filename = 'campaign_' . $campaign_id . '_export.csv';

// OPTIMIZED: Use STRAIGHT_JOIN for predictable query execution
// Stream data to prevent memory issues with large campaigns
// IMPORTANT: mail_blaster, smtp_accounts, smtp_servers are on SERVER 2 (conn_heavy)
$query = "
    SELECT STRAIGHT_JOIN
        mb.id,
        mb.campaign_id,
        mb.to_mail as recipient_email,
        mb.smtp_email as from_email,
        mb.status,
        mb.error_message,
        mb.delivery_date,
        mb.delivery_time,
        mb.sent_at,
        mb.attempt_count,
        sa.email as smtp_account_email,
        ss.name as smtp_server_name,
        ss.host as smtp_host,
        ss.port as smtp_port
    FROM mail_blaster mb
    LEFT JOIN smtp_accounts sa ON mb.smtp_account_id = sa.id
    LEFT JOIN smtp_servers ss ON mb.smtpid = ss.id
    WHERE mb.campaign_id = $campaign_id
    ORDER BY mb.id ASC
";

// Set longer execution time for large exports
set_time_limit(300); // 5 minutes max
ini_set('memory_limit', '256M');

// Stream CSV output (unbuffered for large datasets)
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

// Disable output buffering for streaming
if (ob_get_level()) {
    ob_end_clean();
}

// Query SERVER 2 (mail_blaster table)
$result = $conn_heavy->query($query);

if (!$result) {
    http_response_code(500);
    echo "Error: " . $conn_heavy->error;
    exit();
}

$out = fopen('php://output', 'w');

// Write CSV header
fputcsv($out, [
    'ID',
    'Campaign ID',
    'Recipient Email',
    'From Email',
    'Status',
    'SMTP Server Name',
    'SMTP Account Email',
    'SMTP Host',
    'SMTP Port',
    'Delivery Date',
    'Delivery Time',
    'Sent At',
    'Attempt Count',
    'Error Message'
]);

// Write data rows with periodic flushing for large datasets
$row_count = 0;
if ($result) {
    while ($row = $result->fetch_assoc()) {
        fputcsv($out, [
            $row['id'],
            $row['campaign_id'],
            $row['recipient_email'],
            $row['from_email'] ?: '-',
            strtoupper($row['status']),
            $row['smtp_server_name'] ?: '-',
            $row['smtp_account_email'] ?: '-',
            $row['smtp_host'] ?: '-',
            $row['smtp_port'] ?: '-',
            $row['delivery_date'] ?: '-',
            $row['delivery_time'] ?: '-',
            $row['sent_at'] ?: '-',
            $row['attempt_count'],
            $row['error_message'] ?: '-'
        ]);
        
        // Flush output every 100 rows for better streaming performance
        $row_count++;
        if ($row_count % 100 === 0) {
            flush();
        }
    }
}

fclose($out);
$conn_heavy->close();
exit;
