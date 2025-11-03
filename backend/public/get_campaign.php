<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';


$campaign_id = intval($_GET['id']);

// Safely detect if the send_as_html column exists. If it does, include it
// in the SELECT so the client can toggle between literal/plain and HTML
// rendering modes. If not present, return a default of 0.
$dbNameRes = $conn->query("SELECT DATABASE() AS db");
$dbName = $dbNameRes ? $dbNameRes->fetch_assoc()['db'] : '';
$sendColExists = false;
if ($dbName) {
    $colCheck = $conn->query("SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '" . $conn->real_escape_string($dbName) . "' AND TABLE_NAME = 'campaign_master' AND COLUMN_NAME = 'send_as_html'");
    if ($colCheck) {
        $sendColExists = (int)$colCheck->fetch_assoc()['cnt'] > 0;
    }
}

if ($sendColExists) {
    $sql = "SELECT description, mail_subject, mail_body, send_as_html FROM campaign_master WHERE campaign_id = $campaign_id";
} else {
    $sql = "SELECT description, mail_subject, mail_body FROM campaign_master WHERE campaign_id = $campaign_id";
}
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    // Normalize escaped sequences (e.g. "\r\n") to real newlines so
    // the admin editor/textarea shows proper line breaks and structure.
    if (isset($row['mail_body'])) {
        $row['mail_body'] = stripcslashes($row['mail_body']);
    }
    if (!isset($row['send_as_html'])) {
        $row['send_as_html'] = 0;
    }
    echo json_encode($row);
} else {
    echo json_encode(['error' => 'Campaign not found']);
}

$conn->close();
?>