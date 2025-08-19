<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
require_once 'email_response.php';

class EmailAPI {
    private $db;
    
    public function __construct() {
        $this->db = new mysqli("localhost", "root", "", "CRM");
        if ($this->db->connect_error) {
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "emails" => [],
                "message" => "Database connection failed: " . $this->db->connect_error
            ]);
            exit;
        }
        $this->db->set_charset("utf8mb4");
    }
    
    public function handleRequest() {
        $account_id = intval($_GET['account_id'] ?? 1);
        $type = $_GET['type'] ?? 'regular';
        $page = max(1, intval($_GET['page'] ?? 1));
        $pageSize = min(100, max(1, intval($_GET['pageSize'] ?? 20)));
        $offset = ($page - 1) * $pageSize;
        
        try {
            // Fetch SMTP server info
            $smtp = $this->getSmtpServer($account_id);
            if (!$smtp) {
                echo json_encode([
                    "success" => false,
                    "emails" => [],
                    "message" => "SMTP account not found or inactive."
                ]);
                return;
            }
            
            // Process new emails
            $processor = new EmailProcessor();
            $processor->fetchReplies($smtp);
            
            // Get emails based on filters
            $emails = $this->getEmails($account_id, $type, $pageSize, $offset);
            
            echo json_encode([
                "success" => true,
                "emails" => $emails,
                "page" => $page,
                "pageSize" => $pageSize,
                "total" => $this->getTotalCount($account_id, $type),
                "smtp_info" => $smtp,
                "message" => count($emails) > 0 ? "Fetched " . count($emails) . " emails successfully." : "No emails found."
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "emails" => [],
                "message" => "Error: " . $e->getMessage()
            ]);
        }
    }
    
    private function getSmtpServer($account_id) {
        $stmt = $this->db->prepare("
            SELECT s.*, a.email AS smtp_email, a.daily_limit, a.hourly_limit
            FROM smtp_servers s
            LEFT JOIN smtp_accounts a ON a.smtp_server_id = s.id AND a.is_active = 1
            WHERE s.id = ? AND s.is_active = 1
            LIMIT 1
        ");
        $stmt->bind_param("i", $account_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result;
    }
    
    private function getEmails($account_id, $type, $limit, $offset) {
        $where = "smtp_server_id = ?";
        $params = [$account_id];
        $types = "i";
        
        switch ($type) {
            case 'unsubscribes':
                $where .= " AND is_unsubscribe = 1";
                break;
            case 'bounces':
                $where .= " AND is_bounce = 1";
                break;
            default:
                $where .= " AND is_unsubscribe = 0 AND is_bounce = 0";
                break;
        }
        
        $query = "SELECT * FROM processed_emails WHERE $where ORDER BY date_received DESC LIMIT ? OFFSET ?";
        $stmt = $this->db->prepare($query);
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
        
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $emails = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // Format dates for frontend
        foreach ($emails as &$email) {
            $email['date_formatted'] = $this->formatDate($email['date_received']);
        }
        
        return $emails;
    }
    
    private function getTotalCount($account_id, $type) {
        $where = "smtp_server_id = ?";
        $params = [$account_id];
        $types = "i";
        
        switch ($type) {
            case 'unsubscribes':
                $where .= " AND is_unsubscribe = 1";
                break;
            case 'bounces':
                $where .= " AND is_bounce = 1";
                break;
            default:
                $where .= " AND is_unsubscribe = 0 AND is_bounce = 0";
                break;
        }
        
        $query = "SELECT COUNT(*) as total FROM processed_emails WHERE $where";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        return (int)$result['total'];
    }
    
    private function formatDate($dateString) {
        if (empty($dateString)) return '';
        
        try {
            $date = new DateTime($dateString);
            $now = new DateTime();
            $diff = $now->diff($date);
            
            if ($diff->days === 0) {
                return $date->format('g:i A');
            } elseif ($diff->days === 1) {
                return 'Yesterday';
            } elseif ($diff->days < 7) {
                return $date->format('D');
            } else {
                return $date->format('M j');
            }
        } catch (Exception $e) {
            return $dateString;
        }
    }
}

// Handle the request
$api = new EmailAPI();
$api->handleRequest();
?>