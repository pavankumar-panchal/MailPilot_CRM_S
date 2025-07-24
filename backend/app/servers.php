<?php

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
$db = new mysqli("localhost", "root", "", "CRM");
$smtps = $db->query("SELECT id, name, email FROM smtp_servers WHERE is_active = 1")->fetch_all(MYSQLI_ASSOC);
echo json_encode(["servers" => $smtps]);
?>