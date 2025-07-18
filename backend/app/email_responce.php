<?php
// DB Connection
error_reporting(0);
$db = new mysqli("localhost", "root", "", "CRM");
if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

// Get active SMTP servers
$smtps = $db->query("SELECT * FROM smtp_servers WHERE is_active = 1")->fetch_all(MYSQLI_ASSOC);

// Stats for dashboard
$totalStats = [
    'total_emails' => 0,
    'unsubscribes' => 0,
    'bounces' => 0,
    'replies' => 0
];

function fetchReplies($smtp, $db)
{
    global $totalStats;
    $host = trim($smtp['host']);
    $port = 995; // Secure POP3
    $protocol = 'pop3';
    $encryption = 'ssl';

    // Mailbox connection string
    $mailbox = "{" . $host . ":" . $port . "/" . $protocol . "/" . $encryption . "/novalidate-cert}INBOX";

    // Open mailbox

    $inbox = @imap_open($mailbox, $smtp['email'], $smtp['password']);
    if (!$inbox) {
        return ["error" => imap_last_error()];
    }


    $emails = @imap_search($inbox, 'UNSEEN', SE_UID);
    if (!$emails) {
        imap_close($inbox);
        return ["error" => imap_last_error()];
    }

    // Process in batches
    $batchSize = 20;
    $messages = [
        'regular' => [],
        'unsubscribes' => [],
        'bounces' => []
    ];

    if ($emails) {
        rsort($emails);
        $emails = array_slice($emails, 0, 50); // Increased limit

        foreach ($emails as $email_number) {
            $overview = imap_fetch_overview($inbox, $email_number, 0)[0];
            $body = imap_fetchbody($inbox, $email_number, 1);
            $headers = imap_headerinfo($inbox, $email_number);
            $headers_raw = imap_fetchheader($inbox, $email_number);
            $body_text = quoted_printable_decode($body);

            // Extract from email
            $from_email = '';
            $from_name = '';
            if (isset($headers->from[0]->mailbox) && isset($headers->from[0]->host)) {
                $from_email = $headers->from[0]->mailbox . '@' . $headers->from[0]->host;
            }
            if (isset($headers->from[0]->personal)) {
                $from_name = $headers->from[0]->personal;
            }

            // Check for bounced emails
            $is_bounce = false;
            $bounce_reason = '';
            if (
                stripos($overview->subject ?? '', 'undeliverable') !== false ||
                stripos($overview->subject ?? '', 'returned') !== false ||
                stripos($overview->subject ?? '', 'failure') !== false ||
                stripos($overview->subject ?? '', 'bounce') !== false
            ) {
                $is_bounce = true;
                $bounce_reason = 'Bounced email detected by subject';
            } elseif (preg_match('/X-Failed-Recipients:\s*(.*)/i', $headers_raw, $matches)) {
                $is_bounce = true;
                $bounce_reason = 'Bounced email detected by headers';
            }

            // Check for unsubscribe requests
            $is_unsubscribe = false;
            $unsubscribe_method = '';
            if ($is_bounce) {
                // Skip unsubscribe checks for bounced emails
            } elseif (
                stripos($body_text, 'unsubscribe') !== false ||
                stripos($overview->subject ?? '', 'unsubscribe') !== false
            ) {
                $is_unsubscribe = true;
                $unsubscribe_method = 'Email content';
            } elseif (preg_match('/List-Unsubscribe:\s*(.*)/i', $headers_raw, $matches)) {
                $is_unsubscribe = true;
                $unsubscribe_method = 'List-Unsubscribe header';
            }

            $message = [
                "from" => $from_name,
                "from_email" => $from_email,
                "subject" => $overview->subject ?? '(No Subject)',
                "date" => $overview->date ?? '',
                "body" => $body_text,
                "headers" => $headers_raw,
                "uid" => $overview->uid ?? $email_number,
                "seen" => $overview->seen ?? false,
                "is_unsubscribe" => $is_unsubscribe,
                "unsubscribe_method" => $unsubscribe_method,
                "is_bounce" => $is_bounce,
                "bounce_reason" => $bounce_reason,
                "account_id" => $smtp['id']
            ];

            // Store email in database
            storeEmail($message, $db);

            if ($is_bounce) {
                $messages['bounces'][] = $message;
                $totalStats['bounces']++;
            } elseif ($is_unsubscribe) {
                $messages['unsubscribes'][] = $message;
                $totalStats['unsubscribes']++;
            } else {
                $messages['regular'][] = $message;
                $totalStats['replies']++;
            }
            $totalStats['total_emails']++;
        }
    }

    imap_close($inbox);
    return $messages;
}

function storeEmail($email, $db)
{
    // First store in processed_emails
    $from = $db->real_escape_string($email['from']);
    $from_email = $db->real_escape_string($email['from_email']);
    $subject = $db->real_escape_string($email['subject']);
    $body = $db->real_escape_string($email['body']);
    $headers = $db->real_escape_string($email['headers']);
    $is_unsubscribe = $email['is_unsubscribe'] ? 1 : 0;
    $unsubscribe_method = $db->real_escape_string($email['unsubscribe_method']);
    $is_bounce = $email['is_bounce'] ? 1 : 0;
    $bounce_reason = $db->real_escape_string($email['bounce_reason']);
    $account_id = intval($email['account_id']);
    $uid = $db->real_escape_string($email['uid']);

    // Convert date to MySQL format
    $date_received = date('Y-m-d H:i:s', strtotime($email['date']));

    $query = "INSERT INTO processed_emails 
              (smtp_server_id, from_email, from_name, subject, body, headers, 
               is_unsubscribe, is_bounce, bounce_reason, unsubscribe_method, 
               date_received, uid)
              VALUES 
              ($account_id, '$from_email', '$from', '$subject', '$body', '$headers',
               $is_unsubscribe, $is_bounce, '$bounce_reason', '$unsubscribe_method',
               '$date_received', '$uid')";

    $db->query($query);

    // If unsubscribe, add to unsubscribers table
    if ($is_unsubscribe) {
        $query = "INSERT IGNORE INTO unsubscribers 
                  (email, source, reason)
                  VALUES 
                  ('$from_email', '$unsubscribe_method', 'Email unsubscribe request')";
        $db->query($query);
    }

    // If bounce, add to bounced_emails table
    if ($is_bounce) {
        $query = "INSERT IGNORE INTO bounced_emails 
                  (email, reason, source)
                  VALUES 
                  ('$from_email', '$bounce_reason', 'Email bounce')";
        $db->query($query);
    }

    // Log processing stats
    $processed_count = 1;
    $unsubscribes_count = $is_unsubscribe ? 1 : 0;
    $bounces_count = $is_bounce ? 1 : 0;

    $query = "INSERT INTO email_processing_logs 
              (smtp_server_id, processed_count, unsubscribes_count, bounces_count)
              VALUES 
              ($account_id, $processed_count, $unsubscribes_count, $bounces_count)";
    $db->query($query);
}

// Replace multiple COUNT queries with a single query
function getDashboardStats($db)
{
    $stats = [
        'total_emails' => 0,
        'unsubscribes' => 0,
        'bounces' => 0,
        'replies' => 0
    ];

    // Single query to get all counts
    $query = "SELECT 
        COUNT(*) as total,
        SUM(is_unsubscribe) as unsubscribes,
        SUM(is_bounce) as bounces,
        SUM(IF(is_unsubscribe = 0 AND is_bounce = 0, 1, 0)) as replies
        FROM processed_emails";

    $result = $db->query($query);
    if ($result && $row = $result->fetch_assoc()) {
        $stats['total_emails'] = $row['total'];
        $stats['unsubscribes'] = $row['unsubscribes'];
        $stats['bounces'] = $row['bounces'];
        $stats['replies'] = $row['replies'];
    }

    return $stats;
}

function getInitials($name)
{
    $parts = explode(' ', $name);
    $initials = '';

    foreach ($parts as $part) {
        if (preg_match('/[a-zA-Z]/', substr($part, 0, 1))) {
            $initials .= strtoupper(substr($part, 0, 1));
            if (strlen($initials) >= 2)
                break;
        }
    }

    return $initials ?: '?';
}

function formatDate($dateString, $full = false)
{
    if (empty($dateString))
        return '';

    try {
        $date = new DateTime($dateString);
        $now = new DateTime();

        if ($full) {
            return $date->format('M j, Y g:i A');
        }

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

$dbStats = getDashboardStats($db);
?>