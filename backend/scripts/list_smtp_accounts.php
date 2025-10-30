<?php
require_once __DIR__ . '/../config/db.php';

$result = $conn->query("SELECT ss.id AS server_id, ss.name AS server_name, ss.host, ss.port, ss.encryption, ss.received_email, ss.is_active AS server_active,
 sa.id AS account_id, sa.email AS account_email, sa.password AS account_password, sa.daily_limit, sa.hourly_limit, sa.is_active AS account_active
 FROM smtp_servers ss LEFT JOIN smtp_accounts sa ON sa.smtp_server_id = ss.id ORDER BY ss.id, sa.id");

$out = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $out[] = $row;
    }
}

if (empty($out)) {
    echo "No SMTP servers or accounts found in DB.\n";
    exit(0);
}

foreach ($out as $r) {
    echo "Server ID: {$r['server_id']} | Server: {$r['server_name']} | Host: {$r['host']}:{$r['port']} | Enc: {$r['encryption']} | Received: {$r['received_email']} | Active: {$r['server_active']}\n";
    if (!empty($r['account_id'])) {
        echo "  -> Account ID: {$r['account_id']} | Email: {$r['account_email']} | Active: {$r['account_active']} | daily: {$r['daily_limit']} hourly: {$r['hourly_limit']}\n";
    }
}

$conn->close();

?>