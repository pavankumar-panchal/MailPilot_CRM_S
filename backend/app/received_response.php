<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
require 'email_responce.php';

// DB Connection
error_reporting(0);
$db = new mysqli("localhost", "root", "", "CRM");
if ($db->connect_error) {
    die(json_encode([
        "success" => false,
        "emails" => [],
        "message" => "Database connection failed: " . $db->connect_error
    ]));
}

$account_id = intval($_GET['account_id'] ?? 1);
$type = $_GET['type'] ?? 'regular';
$page = intval($_GET['page'] ?? 1);
$pageSize = 20;
$offset = ($page - 1) * $pageSize;

// Fetch SMTP server info for this account
$smtp = $db->query("SELECT * FROM smtp_servers WHERE id = $account_id AND is_active = 1")->fetch_assoc();
if (!$smtp) {
    echo json_encode([
        "success" => false,
        "emails" => [],
        "message" => "SMTP account not found or inactive."
    ]);
    exit;
}

// Fetch only new emails for this SMTP account
fetchReplies($smtp, $db);

// Build WHERE clause for filtering
$where = "smtp_server_id = $account_id";
if ($type === 'unsubscribes') $where .= " AND is_unsubscribe = 1";
elseif ($type === 'bounces') $where .= " AND is_bounce = 1";
else $where .= " AND is_unsubscribe = 0 AND is_bounce = 0";

// Query emails from DB for frontend
$query = "SELECT * FROM processed_emails WHERE $where ORDER BY date_received DESC LIMIT $pageSize OFFSET $offset";
$result = $db->query($query);

$emails = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $emails[] = $row;
    }
    if (count($emails) > 0) {
        $success = true;
        $message = "Fetched " . count($emails) . " emails successfully.";
    } else {
        $success = false;
        $message = "No emails found.";
    }
} else {
    $success = false;
    $message = "Database query failed.";
}

echo json_encode([
    "success" => $success,
    "emails" => $emails,
    "page" => $page,
    "pageSize" => $pageSize,
    "message" => $message
]);
?>