<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';


$campaign_id = intval($_GET['id']);
$sql = "SELECT description, mail_subject, mail_body FROM campaign_master WHERE campaign_id = $campaign_id";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    // Normalize escaped sequences (e.g. "\r\n") to real newlines so
    // the admin editor/textarea shows proper line breaks and structure.
    if (isset($row['mail_body'])) {
        $row['mail_body'] = stripcslashes($row['mail_body']);
    }
    echo json_encode($row);
} else {
    echo json_encode(['error' => 'Campaign not found']);
}

$conn->close();
?>