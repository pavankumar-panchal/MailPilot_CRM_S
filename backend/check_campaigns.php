<?php
require 'config/db.php';
$result = $conn->query('SELECT campaign_id, description, cs.status as campaign_status FROM campaign_master cm LEFT JOIN campaign_status cs ON cm.campaign_id = cs.campaign_id ORDER BY campaign_id DESC LIMIT 5');
echo "Campaign ID\tDescription\t\tStatus\n";
echo "------------------------------------------------------------\n";
while ($row = $result->fetch_assoc()) {
    $status = $row['campaign_status'] ?? 'pending';
    echo $row['campaign_id'] . "\t" . substr($row['description'], 0, 20) . "...\t" . $status . "\n";
}
