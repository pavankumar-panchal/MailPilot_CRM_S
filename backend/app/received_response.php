<?php
header('Content-Type: application/json');
require 'email_responce.php';

$account_id = intval($_GET['account_id'] ?? 1);
$type = $_GET['type'] ?? 'regular';
$page = intval($_GET['page'] ?? 1);
$pageSize = 20;
$offset = ($page - 1) * $pageSize;

$where = "smtp_server_id = $account_id";
if ($type === 'unsubscribes') $where .= " AND is_unsubscribe = 1";
elseif ($type === 'bounces') $where .= " AND is_bounce = 1";
else $where .= " AND is_unsubscribe = 0 AND is_bounce = 0";

$query = "SELECT * FROM processed_emails WHERE $where ORDER BY date_received DESC LIMIT $pageSize OFFSET $offset";
$result = $db->query($query);

$emails = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $emails[] = $row;
    }
    $message = "Fetched " . count($emails) . " emails successfully.";
} else {
    $message = "No emails found.";
}

echo json_encode([
    "success" => true,
    "emails" => $emails,
    "page" => $page,
    "pageSize" => $pageSize,
    "message" => $message
]);
?>