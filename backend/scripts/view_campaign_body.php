<?php
require __DIR__ . '/../config/db.php';
$cid = isset($argv[1]) ? intval($argv[1]) : 23;
$result = $db->query("SELECT mail_body, images_paths FROM campaign_master WHERE campaign_id = $cid");
if ($result && $row = $result->fetch_assoc()) {
    echo "=== Campaign $cid Body ===\n";
    echo $row['mail_body'] . "\n";
    echo "\n=== Images Paths ===\n";
    echo ($row['images_paths'] ?: 'None') . "\n";
} else {
    echo "Campaign $cid not found\n";
}
