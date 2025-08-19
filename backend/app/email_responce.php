<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'CRM');

// Error reporting for development (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

class EmailProcessor {
    private $db;
    
    public function __construct() {
        $this->db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($this->db->connect_error) {
            throw new Exception("Database connection failed: " . $this->db->connect_error);
        }
        $this->db->set_charset("utf8mb4");
    }
    
    public function processAllServers() {
        $servers = $this->getActiveServers();
        foreach ($servers as $server) {
            try {
                $this->fetchReplies($server);
            } catch (Exception $e) {
                error_log("Error processing server {$server['id']}: " . $e->getMessage());
            }
        }
    }
    
    private function getActiveServers() {
        $stmt = $this->db->prepare("
            SELECT s.*, a.id AS smtp_account_id, a.email AS smtp_email, a.password AS smtp_password
            FROM smtp_servers s
            LEFT JOIN smtp_accounts a ON a.smtp_server_id = s.id AND a.is_active = 1
            WHERE s.is_active = 1
        ");
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    public function fetchReplies($smtp) {
        if (empty($smtp['smtp_email']) || empty($smtp['smtp_password'])) {
            throw new Exception("Missing email credentials for server {$smtp['id']}");
        }
        
        $host = trim($smtp['host']);
        $port = 995;
        $protocol = 'pop3';
        $encryption = 'ssl';
        
        $mailbox = "{" . $host . ":" . $port . "/" . $protocol . "/" . $encryption . "/novalidate-cert}INBOX";
        
        // Suppress errors and handle them properly
        $inbox = @imap_open($mailbox, $smtp['smtp_email'], $smtp['smtp_password'], OP_READONLY, 1);
        
        if (!$inbox) {
            $errors = imap_errors();
            throw new Exception("IMAP connection failed: " . implode(", ", $errors ?: ["Unknown error"]));
        }
        
        try {
            $last_uid = intval($smtp['last_uid'] ?? 0);
            
            // Get all emails
            $emails = imap_search($inbox, 'ALL', SE_UID);
            
            if ($emails) {
                sort($emails); // Sort ascending to process oldest first
                
                $processed = 0;
                $max_uid = $last_uid;
                
                foreach ($emails as $email_uid) {
                    if ($email_uid <= $last_uid) continue;
                    
                    try {
                        $this->processEmail($inbox, $email_uid, $smtp);
                        $max_uid = max($max_uid, $email_uid);
                        $processed++;
                    } catch (Exception $e) {
                        error_log("Error processing email UID $email_uid: " . $e->getMessage());
                    }
                }
                
                if ($processed > 0) {
                    $this->updateLastUid($smtp['id'], $max_uid);
                }
            }
        } finally {
            imap_close($inbox);
        }
    }
    
    private function processEmail($inbox, $email_uid, $smtp) {
        $overview = imap_fetch_overview($inbox, $email_uid, FT_UID)[0] ?? null;
        if (!$overview) return;
        
        $headers_raw = imap_fetchheader($inbox, $email_uid, FT_UID);
        $headers = imap_rfc822_parse_headers($headers_raw);
        $body = $this->getEmailBody($inbox, $email_uid);
        
        // Extract recipient email
        $to_email = $this->extractEmail($headers->to[0] ?? null);
        
        // Only process if email is addressed to this SMTP account
        if (strtolower($to_email) !== strtolower($smtp['smtp_email'])) {
            return;
        }
        
        $from_email = $this->extractEmail($headers->from[0] ?? null);
        $from_name = $this->decodeMimeHeader($headers->from[0]->personal ?? '');
        
        // Detect bounces and unsubscribes
        $analysis = $this->analyzeEmail($overview, $body, $headers_raw);
        
        $message = [
            "account_id" => $smtp['id'],
            "from_email" => $from_email,
            "from_name" => $from_name,
            "subject" => $this->decodeMimeHeader($overview->subject ?? '(No Subject)'),
            "date" => $overview->date ?? date('r'),
            "body" => $body,
            "headers" => $headers_raw,
            "uid" => $email_uid,
            "seen" => $overview->seen ?? false,
            "is_unsubscribe" => $analysis['is_unsubscribe'],
            "unsubscribe_method" => $analysis['unsubscribe_method'],
            "is_bounce" => $analysis['is_bounce'],
            "bounce_reason" => $analysis['bounce_reason']
        ];
        
        $this->storeEmail($message);
    }
    
    private function getEmailBody($inbox, $email_uid) {
        $body = '';
        
        $structure = imap_fetchstructure($inbox, $email_uid, FT_UID);
        
        if (!isset($structure->parts)) {
            $body = imap_body($inbox, $email_uid, FT_UID);
        } else {
            $body = $this->getPart($inbox, $email_uid, $structure, 1);
        }
        
        return quoted_printable_decode($body);
    }
    
    private function getPart($inbox, $email_uid, $structure, $part_number) {
        $data = imap_fetchbody($inbox, $email_uid, $part_number, FT_UID);
        
        if ($structure->encoding == 4) {
            $data = quoted_printable_decode($data);
        } elseif ($structure->encoding == 3) {
            $data = base64_decode($data);
        }
        
        return $data;
    }
    
    private function extractEmail($address) {
        if (!$address) return '';
        return strtolower($address->mailbox . '@' . $address->host);
    }
    
    private function decodeMimeHeader($text) {
        return mb_decode_mimeheader($text ?? '');
    }
    
    private function analyzeEmail($overview, $body, $headers_raw) {
        $subject = strtolower($overview->subject ?? '');
        $body = strtolower($body);
        
        $is_bounce = false;
        $bounce_reason = '';
        $is_unsubscribe = false;
        $unsubscribe_method = '';
        
        // Bounce detection
        $bounce_indicators = [
            'undelivered', 'undeliverable', 'returned', 'delivery failure',
            'permanent error', 'user unknown', 'quota exceeded'
        ];
        
        foreach ($bounce_indicators as $indicator) {
            if (strpos($subject, $indicator) !== false) {
                $is_bounce = true;
                $bounce_reason = "Subject indicates bounce: $indicator";
                break;
            }
        }
        
        if (!$is_bounce && preg_match('/X-Failed-Recipients:\s*([^\r\n]+)/i', $headers_raw, $matches)) {
            $is_bounce = true;
            $bounce_reason = 'Failed recipient: ' . trim($matches[1]);
        }
        
        // Unsubscribe detection
        if (!$is_bounce) {
            if (strpos($body, 'unsubscribe') !== false || strpos($subject, 'unsubscribe') !== false) {
                $is_unsubscribe = true;
                $unsubscribe_method = 'Content keyword';
            }
            
            if (preg_match('/List-Unsubscribe:\s*<([^>]+)>/i', $headers_raw, $matches)) {
                $is_unsubscribe = true;
                $unsubscribe_method = 'List-Unsubscribe header';
            }
        }
        
        return [
            'is_bounce' => $is_bounce,
            'bounce_reason' => $bounce_reason,
            'is_unsubscribe' => $is_unsubscribe,
            'unsubscribe_method' => $unsubscribe_method
        ];
    }
    
    private function storeEmail($email) {
        $stmt = $this->db->prepare("
            INSERT INTO processed_emails 
            (smtp_server_id, from_email, from_name, subject, body, headers, 
             is_unsubscribe, is_bounce, bounce_reason, unsubscribe_method, 
             date_received, uid, seen)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE seen = VALUES(seen)
        ");
        
        $date_received = date('Y-m-d H:i:s', strtotime($email['date']));
        $stmt->bind_param(
            "isssssisssssi",
            $email['account_id'],
            $email['from_email'],
            $email['from_name'],
            $email['subject'],
            $email['body'],
            $email['headers'],
            $email['is_unsubscribe'],
            $email['is_bounce'],
            $email['bounce_reason'],
            $email['unsubscribe_method'],
            $date_received,
            $email['uid'],
            $email['seen']
        );
        
        $stmt->execute();
        $stmt->close();
        
        // Handle unsubscribers
        if ($email['is_unsubscribe'] && !empty($email['from_email'])) {
            $stmt = $this->db->prepare("
                INSERT IGNORE INTO unsubscribers (email, source, reason) 
                VALUES (?, ?, ?)
            ");
            $reason = 'Email unsubscribe request';
            $stmt->bind_param("sss", $email['from_email'], $email['unsubscribe_method'], $reason);
            $stmt->execute();
            $stmt->close();
        }
        
        // Handle bounced emails
        if ($email['is_bounce'] && !empty($email['from_email'])) {
            $stmt = $this->db->prepare("
                INSERT IGNORE INTO bounced_emails (email, reason, source) 
                VALUES (?, ?, ?)
            ");
            $source = 'Email bounce';
            $stmt->bind_param("sss", $email['from_email'], $email['bounce_reason'], $source);
            $stmt->execute();
            $stmt->close();
        }
        
        // Log processing
        $stmt = $this->db->prepare("
            INSERT INTO email_processing_logs 
            (smtp_server_id, processed_count, unsubscribes_count, bounces_count) 
            VALUES (?, 1, ?, ?)
        ");
        $unsubscribes = $email['is_unsubscribe'] ? 1 : 0;
        $bounces = $email['is_bounce'] ? 1 : 0;
        $stmt->bind_param("iii", $email['account_id'], $unsubscribes, $bounces);
        $stmt->execute();
        $stmt->close();
    }
    
    private function updateLastUid($server_id, $uid) {
        $stmt = $this->db->prepare("UPDATE smtp_servers SET last_uid = ? WHERE id = ?");
        $stmt->bind_param("ii", $uid, $server_id);
        $stmt->execute();
        $stmt->close();
    }
    
    public function getDashboardStats() {
        $stats = [
            'total_emails' => 0,
            'unsubscribes' => 0,
            'bounces' => 0,
            'replies' => 0
        ];
        
        $query = "SELECT 
            COUNT(*) as total,
            SUM(is_unsubscribe) as unsubscribes,
            SUM(is_bounce) as bounces,
            SUM(CASE WHEN is_unsubscribe = 0 AND is_bounce = 0 THEN 1 ELSE 0 END) as replies
            FROM processed_emails";
        
        $result = $this->db->query($query);
        if ($result && $row = $result->fetch_assoc()) {
            $stats['total_emails'] = (int)$row['total'];
            $stats['unsubscribes'] = (int)$row['unsubscribes'];
            $stats['bounces'] = (int)$row['bounces'];
            $stats['replies'] = (int)$row['replies'];
        }
        
        return $stats;
    }
    
    public function close() {
        if ($this->db) {
            $this->db->close();
        }
    }
}

// Usage example
try {
    $processor = new EmailProcessor();
    $processor->processAllServers();
    $stats = $processor->getDashboardStats();
    echo json_encode(['success' => true, 'stats' => $stats]);
    $processor->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>