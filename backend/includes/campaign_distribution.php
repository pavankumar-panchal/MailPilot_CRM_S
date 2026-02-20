<?php
/**
 * Campaign Distribution API
 * 
 * Manages campaign distribution settings across SMTP servers.
 * 
 * DATABASE CONNECTIONS:
 * - $conn (Server 1): campaign_distribution table
 * - $conn_heavy (Server 2): smtp_servers, smtp_accounts tables
 */

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Load Server 1 connection (campaign_distribution table)
require_once __DIR__ . '/../config/db.php';

// Load Server 2 connection (smtp_servers, smtp_accounts tables)
require_once __DIR__ . '/../config/db_campaign.php';

require_once __DIR__ . '/session_config.php';
require_once __DIR__ . '/user_filtering.php';
require_once __DIR__ . '/auth_helper.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Require authentication
$currentUser = requireAuth();
$user_id = $currentUser['id'];

$method = $_SERVER['REQUEST_METHOD'];

function getInputData() {
    $input = file_get_contents('php://input');
    return json_decode($input, true) ?? [];
}

try {
    if ($method === 'GET') {
        if (!isset($_GET['campaign_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'campaign_id required']);
            exit;
        }
        $cid = intval($_GET['campaign_id']);
        
        // Step 1: Get distribution data from Server 1 (campaign_distribution table)
        $stmt = $conn->prepare("SELECT smtp_id, percentage FROM campaign_distribution WHERE campaign_id = ?");
        $stmt->bind_param("i", $cid);
        $stmt->execute();
        $res = $stmt->get_result();
        $distributions = $res->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        $rows = [];
        
        // Step 2: For each distribution, get SMTP server details from Server 2
        foreach ($distributions as $dist) {
            $smtp_id = intval($dist['smtp_id']);
            $percentage = $dist['percentage'];
            
            // Query Server 2 for smtp_server details and account limits
            $stmt2 = $conn_heavy->prepare("
                SELECT 
                    ss.id,
                    ss.name,
                    COALESCE((SELECT SUM(daily_limit) FROM smtp_accounts sa WHERE sa.smtp_server_id = ss.id AND sa.is_active = 1), 0) AS daily_limit,
                    COALESCE((SELECT SUM(hourly_limit) FROM smtp_accounts sa WHERE sa.smtp_server_id = ss.id AND sa.is_active = 1), 0) AS hourly_limit
                FROM smtp_servers ss
                WHERE ss.id = ?
            ");
            $stmt2->bind_param("i", $smtp_id);
            $stmt2->execute();
            $res2 = $stmt2->get_result();
            $server_data = $res2->fetch_assoc();
            $stmt2->close();
            
            if ($server_data) {
                $rows[] = [
                    'smtp_id' => $smtp_id,
                    'percentage' => $percentage,
                    'name' => $server_data['name'],
                    'daily_limit' => $server_data['daily_limit'],
                    'hourly_limit' => $server_data['hourly_limit']
                ];
            }
        }
        
        echo json_encode($rows);
        exit;
    }

    if ($method === 'POST') {
        // Save campaign distribution to Server 1 (campaign_distribution table)
        $data = getInputData();
        $cid = intval($data['campaign_id'] ?? 0);
        $distribution = $data['distribution'] ?? [];
        if (!$cid || !is_array($distribution)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid input']);
            exit;
        }
        $conn->begin_transaction();
        try {
            $conn->query("DELETE FROM campaign_distribution WHERE campaign_id = $cid");
            $stmt = $conn->prepare("INSERT INTO campaign_distribution (campaign_id, smtp_id, percentage) VALUES (?, ?, ?)");
            $total = 0;
            foreach ($distribution as $dist) {
                $smtp_id = intval($dist['smtp_id']);
                $perc = floatval($dist['percentage']);
                $total += $perc;
                $stmt->bind_param("iid", $cid, $smtp_id, $perc);
                $stmt->execute();
            }
            if ($total > 100) throw new Exception("Total percentage cannot exceed 100%");
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Distribution saved']);
        } catch (Exception $e) {
            $conn->rollback();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} finally {
    // Close both database connections
    if (isset($conn) && $conn) $conn->close();
    if (isset($conn_heavy) && $conn_heavy) $conn_heavy->close();
}