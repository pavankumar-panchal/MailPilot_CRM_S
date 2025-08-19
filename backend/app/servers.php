<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

$db = new mysqli("localhost", "root", "", "CRM");
if ($db->connect_error) {
    http_response_code(500);
    echo json_encode(["servers" => [], "error" => "Database connection failed"]);
    exit;
}

$db->set_charset("utf8mb4");

try {
    $query = "
        SELECT s.id, s.name, a.email, 
               COUNT(e.id) as total_emails,
               SUM(e.is_unsubscribe) as unsubscribes,
               SUM(e.is_bounce) as bounces,
               SUM(CASE WHEN e.is_unsubscribe = 0 AND e.is_bounce = 0 THEN 1 ELSE 0 END) as replies
        FROM smtp_servers s
        LEFT JOIN smtp_accounts a ON a.smtp_server_id = s.id AND a.is_active = 1
        LEFT JOIN processed_emails e ON e.smtp_server_id = s.id
        WHERE s.is_active = 1
        GROUP BY s.id, s.name, a.email
        ORDER BY s.name
    ";
    
    $result = $db->query($query);
    $servers = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    
    // Convert counts to integers
    foreach ($servers as &$server) {
        $server['total_emails'] = (int)($server['total_emails'] ?? 0);
        $server['unsubscribes'] = (int)($server['unsubscribes'] ?? 0);
        $server['bounces'] = (int)($server['bounces'] ?? 0);
        $server['replies'] = (int)($server['replies'] ?? 0);
    }
    
    echo json_encode(["servers" => $servers]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["servers" => [], "error" => $e->getMessage()]);
} finally {
    $db->close();
}
?>