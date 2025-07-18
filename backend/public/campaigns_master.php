<?php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
error_reporting(E_ALL);
ini_set('display_errors', 1);

ini_set('memory_limit', '2048M');
set_time_limit(0);

$response = [
    'success' => false,
    'message' => '',
    'data' => null
];

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $action = $input['action'] ?? null;

        if ($action === 'start_campaign') {
            $campaign_id = intval($input['campaign_id']);
            startCampaign($conn, $campaign_id);
            $response['success'] = true;
            $response['message'] = "Campaign #$campaign_id started successfully!";
        } elseif ($action === 'pause_campaign') {
            $campaign_id = intval($input['campaign_id']);
            pauseCampaign($conn, $campaign_id);
            $response['success'] = true;
            $response['message'] = "Campaign #$campaign_id paused successfully!";
        } elseif ($action === 'retry_failed') {
            $campaign_id = intval($input['campaign_id']);
            $msg = retryFailedEmails($conn, $campaign_id);
            $response['success'] = true;
            $response['message'] = $msg;
        } elseif ($action === 'distribute') {
            $campaign_id = (int)$input['campaign_id'];
            $distributions = $input['distribution'] ?? [];
            $msg = saveDistribution($conn, $campaign_id, $distributions);
            $response['success'] = true;
            $response['message'] = $msg;
        } elseif ($action === 'auto_distribute') {
            $campaign_id = (int)$input['campaign_id'];
            $msg = autoDistribute($conn, $campaign_id);
            $response['success'] = true;
            $response['message'] = $msg;
        } elseif ($action === 'list') {
            $response['success'] = true;
            $response['data'] = [
                'campaigns' => getCampaignsWithStats(),
                'smtp_servers' => getSMTPServers()
            ];
        } elseif ($action === 'email_counts') {
            $campaign_id = (int)($input['campaign_id'] ?? 0);
            $response['success'] = true;
            $response['data'] = getEmailCounts($conn, $campaign_id);
        } elseif ($action === 'get_distribution') {
            $campaign_id = (int)$input['campaign_id'];
            $stmt = $conn->prepare("SELECT cd.smtp_id, cd.percentage, ss.name, ss.daily_limit, ss.hourly_limit
                                    FROM campaign_distribution cd
                                    JOIN smtp_servers ss ON cd.smtp_id = ss.id
                                    WHERE cd.campaign_id = ?");
            $stmt->bind_param("i", $campaign_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $response['success'] = true;
            $response['data'] = $result->fetch_all(MYSQLI_ASSOC);
        } else {
            throw new Exception('Invalid action');
        }
    } elseif ($method === 'GET') {
        $response['success'] = true;
        $response['data'] = [
            'campaigns' => getCampaignsWithStats(),
            'smtp_servers' => getSMTPServers()
        ];
    } else {
        throw new Exception('Invalid request method');
    }
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);

// --- Helper Functions ---

function saveDistribution($conn, $campaign_id, $distributions)
{
    $conn->begin_transaction();
    try {
        $delete_stmt = $conn->prepare("DELETE FROM campaign_distribution WHERE campaign_id = ?");
        $delete_stmt->bind_param("i", $campaign_id);
        $delete_stmt->execute();

        $insert_stmt = $conn->prepare("INSERT INTO campaign_distribution (campaign_id, smtp_id, percentage) VALUES (?, ?, ?)");
        $total_percentage = 0;
        foreach ($distributions as $dist) {
            if (!isset($dist['smtp_id']) || !isset($dist['percentage'])) {
                throw new Exception("Invalid distribution data");
            }
            $smtp_id = (int)$dist['smtp_id'];
            $percentage = (float)$dist['percentage'];
            $total_percentage += $percentage;
            $insert_stmt->bind_param("iid", $campaign_id, $smtp_id, $percentage);
            $insert_stmt->execute();
        }
        if ($total_percentage > 100) {
            throw new Exception("Total distribution cannot exceed 100%");
        }
        $conn->commit();
        return "Distribution saved successfully!";
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

function autoDistribute($conn, $campaign_id)
{
    $email_result = $conn->query("SELECT COUNT(*) AS total FROM emails WHERE domain_status = 1");
    $email_data = $email_result->fetch_assoc();
    $total_emails = $email_data['total'];
    $smtp_servers = getSMTPServers();
    $optimal_distribution = calculateOptimalDistribution($total_emails, $smtp_servers);

    $conn->begin_transaction();
    try {
        $delete_stmt = $conn->prepare("DELETE FROM campaign_distribution WHERE campaign_id = ?");
        $delete_stmt->bind_param("i", $campaign_id);
        $delete_stmt->execute();

        $insert_stmt = $conn->prepare("INSERT INTO campaign_distribution (campaign_id, smtp_id, percentage) VALUES (?, ?, ?)");
        foreach ($optimal_distribution as $dist) {
            $insert_stmt->bind_param("iid", $campaign_id, $dist['smtp_id'], $dist['percentage']);
            $insert_stmt->execute();
        }
        $conn->commit();
        return "Optimal distribution calculated and saved!";
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

function getCampaignsWithStats()
{
    global $conn;
    $query = "SELECT 
                cm.campaign_id, 
                cm.description, 
                cm.mail_subject,
                COALESCE((SELECT COUNT(*) FROM emails WHERE domain_status = 1 AND domain_processed = 1), 0) AS valid_emails,
                (SELECT SUM(percentage) FROM campaign_distribution WHERE campaign_id = cm.campaign_id) AS distributed_percentage,
                cs.status as campaign_status,
                COALESCE(cs.total_emails, 0) as total_emails,
                COALESCE(cs.pending_emails, 0) as pending_emails,
                COALESCE(cs.sent_emails, 0) as sent_emails,
                COALESCE(cs.failed_emails, 0) as failed_emails,
                cs.start_time,
                cs.end_time
              FROM campaign_master cm
              LEFT JOIN campaign_status cs ON cm.campaign_id = cs.campaign_id
              ORDER BY cm.campaign_id DESC";
    $result = $conn->query($query);
    $campaigns = $result->fetch_all(MYSQLI_ASSOC);

    foreach ($campaigns as &$campaign) {
        $campaign['valid_emails'] = (int)($campaign['valid_emails'] ?? 0);
        $campaign['distributed_percentage'] = (float)($campaign['distributed_percentage'] ?? 0);
        $campaign['total_emails'] = (int)($campaign['total_emails'] ?? 0);
        $campaign['pending_emails'] = (int)($campaign['pending_emails'] ?? 0);
        $campaign['sent_emails'] = (int)($campaign['sent_emails'] ?? 0);
        $campaign['failed_emails'] = (int)($campaign['failed_emails'] ?? 0);
        $campaign['remaining_percentage'] = 100 - ($campaign['distributed_percentage'] ?? 0);
        $total = max($campaign['total_emails'], 1);
        $sent = min($campaign['sent_emails'], $total);
        $campaign['progress'] = round(($sent / $total) * 100);

        $dist_stmt = $conn->prepare("SELECT 
                                    cd.smtp_id, 
                                    cd.percentage, 
                                    ss.name,
                                    ss.daily_limit,
                                    ss.hourly_limit
                                FROM campaign_distribution cd
                                JOIN smtp_servers ss ON cd.smtp_id = ss.id
                                WHERE cd.campaign_id = ?");
        $dist_stmt->bind_param("i", $campaign['campaign_id']);
        $dist_stmt->execute();
        $dist_result = $dist_stmt->get_result();
        $raw_distributions = $dist_result->fetch_all(MYSQLI_ASSOC);

        // Calculate email_count using the helper
        $campaign['current_distributions'] = calculateEmailDistribution(
            $campaign['valid_emails'],
            $raw_distributions
        );
    }
    return $campaigns;
}

function getSMTPServers()
{
    global $conn;
    $query = "SELECT id, name, host, email, daily_limit, hourly_limit FROM smtp_servers WHERE is_active = 1";
    $result = $conn->query($query);
    return $result->fetch_all(MYSQLI_ASSOC);
}

function calculateOptimalDistribution($total_emails, $smtp_servers)
{
    $distribution = [];
    $count = count($smtp_servers);
    if ($count > 0) {
        $equal_percentage = round(100 / $count, 2);
        foreach ($smtp_servers as $server) {
            $distribution[] = [
                'smtp_id' => $server['id'],
                'percentage' => $equal_percentage,
                'email_count' => floor($total_emails * $equal_percentage / 100)
            ];
        }
    }
    return $distribution;
}

function calculateEmailDistribution($total_emails, $distributions)
{
    $result = [];
    $assigned = 0;
    $remainders = [];

    foreach ($distributions as $i => $dist) {
        $raw = $total_emails * ($dist['percentage'] / 100);
        $floored = floor($raw);
        $assigned += $floored;
        $result[$i] = [
            'smtp_id' => $dist['smtp_id'],
            'percentage' => $dist['percentage'],
            'email_count' => $floored,
        ];
        $remainders[$i] = $raw - $floored;
    }

    // Distribute the remaining emails to the largest remainders
    $left = $total_emails - $assigned;
    arsort($remainders); // sort by largest remainder
    foreach (array_keys($remainders) as $i) {
        if ($left <= 0) break;
        $result[$i]['email_count'] += 1;
        $left--;
    }

    return $result;
}

function startCampaign($conn, $campaign_id)
{
    $max_retries = 5;
    $retry_count = 0;
    $success = false;
    while ($retry_count < $max_retries && !$success) {
        try {
            $conn->query("SET SESSION innodb_lock_wait_timeout = 10");
            $conn->begin_transaction();
            $check = $conn->query("SELECT 1 FROM campaign_master WHERE campaign_id = $campaign_id");
            if ($check->num_rows == 0) {
                $conn->commit();
                throw new Exception("Campaign #$campaign_id does not exist");
            }
            $status_check = $conn->query("SELECT status FROM campaign_status WHERE campaign_id = $campaign_id");
            if ($status_check->num_rows > 0 && $status_check->fetch_assoc()['status'] === 'completed') {
                $conn->commit();
                throw new Exception("Campaign #$campaign_id is already completed");
            }
            $counts = getEmailCounts($conn, $campaign_id);
            if ($status_check->num_rows > 0) {
                $conn->query("UPDATE campaign_status SET 
                        status = 'running',
                        total_emails = {$counts['total_valid']},
                        pending_emails = {$counts['pending']},
                        sent_emails = {$counts['sent']},
                        failed_emails = {$counts['failed']},
                        start_time = IFNULL(start_time, NOW()),
                        end_time = NULL
                        WHERE campaign_id = $campaign_id");
            } else {
                $conn->query("INSERT INTO campaign_status 
                        (campaign_id, total_emails, pending_emails, sent_emails, failed_emails, status, start_time)
                        VALUES ($campaign_id, {$counts['total_valid']}, {$counts['pending']}, {$counts['sent']}, {$counts['failed']}, 'running', NOW())");
            }
            $conn->commit();
            $success = true;
            startEmailBlasterProcess($campaign_id);
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            if (strpos($e->getMessage(), 'Lock wait timeout exceeded') !== false) {
                $retry_count++;
                sleep(1);
                if ($retry_count >= $max_retries) {
                    throw new Exception("Failed to start campaign #$campaign_id after $max_retries attempts due to lock timeout");
                }
            } else {
                throw new Exception("Database error starting campaign #$campaign_id: " . $e->getMessage());
            }
        }
    }
    $conn->query("SET SESSION innodb_lock_wait_timeout = 50");
}

function getEmailCounts($conn, $campaign_id)
{
    $result = $conn->query("
            SELECT 
                COUNT(*) as total_valid,
                SUM(CASE WHEN (mb.status IS NULL OR mb.status = 'failed' AND mb.attempt_count < 3) THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN mb.status = 'success' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN mb.status = 'failed' AND mb.attempt_count >= 3 THEN 1 ELSE 0 END) as failed
            FROM emails e
            LEFT JOIN mail_blaster mb ON mb.to_mail = e.raw_emailid AND mb.campaign_id = $campaign_id
            WHERE e.domain_status = 1
        ");
    return $result->fetch_assoc();
}

function startEmailBlasterProcess($campaign_id)
{
    $lock_file = "/tmp/email_blaster_{$campaign_id}.lock";
    if (file_exists($lock_file)) {
        $pid = file_get_contents($lock_file);
        // Check if process is running
        if (function_exists('posix_kill') && posix_kill((int) $pid, 0)) {
            // Process is running, do not start another
            return;
        } else {
            // Stale lock file, remove it
            unlink($lock_file);
        }
    }
    $php_path = '/opt/lampp/bin/php';
    $script_path = '/opt/lampp/htdocs/Verify_email/backend/public/email_blaster.php';
    $command = "nohup $php_path $script_path $campaign_id > /dev/null 2>&1 & echo $!";
    $pid = shell_exec($command);
    if ($pid) {
        file_put_contents($lock_file, trim($pid));
    }
}

function pauseCampaign($conn, $campaign_id)
{
    $max_retries = 3;
    $retry_count = 0;
    $success = false;
    while ($retry_count < $max_retries && !$success) {
        try {
            $conn->query("SET SESSION innodb_lock_wait_timeout = 10");
            $conn->begin_transaction();
            $result = $conn->query("UPDATE campaign_status SET status = 'paused' 
                    WHERE campaign_id = $campaign_id AND status = 'running'");
            if ($conn->affected_rows > 0) {
                stopEmailBlasterProcess($campaign_id);
                $success = true;
            } else {
                $success = true;
            }
            $conn->commit();
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            if (strpos($e->getMessage(), 'Lock wait timeout exceeded') !== false) {
                $retry_count++;
                sleep(1);
                if ($retry_count >= $max_retries) {
                    throw new Exception("Failed to pause campaign #$campaign_id after $max_retries attempts due to lock timeout");
                }
            } else {
                throw new Exception("Database error pausing campaign #$campaign_id: " . $e->getMessage());
            }
        }
    }
    $conn->query("SET SESSION innodb_lock_wait_timeout = 50");
}

function stopEmailBlasterProcess($campaign_id)
{
    exec("pkill -f 'email_blaster.php $campaign_id'");
}

function retryFailedEmails($conn, $campaign_id)
{
    $result = $conn->query("
            SELECT COUNT(*) as failed_count 
            FROM mail_blaster 
            WHERE campaign_id = $campaign_id 
            AND status = 'failed'
            AND attempt_count < 5
        ");
    $failed_count = $result->fetch_assoc()['failed_count'];
    if ($failed_count > 0) {
        $conn->query("
                UPDATE mail_blaster 
                SET status = 'pending', 
                    error_message = NULL,
                    attempt_count = attempt_count + 1
                WHERE campaign_id = $campaign_id 
                AND status = 'failed'
            ");
        $conn->query("
                UPDATE campaign_status 
                SET pending_emails = pending_emails + $failed_count,
                    failed_emails = GREATEST(0, failed_emails - $failed_count),
                    status = 'running'
                WHERE campaign_id = $campaign_id
            ");
        startEmailBlasterProcess($campaign_id);
        return "Retrying $failed_count failed emails for campaign #$campaign_id";
    } else {
        return "Retry campaign limit reached for campaign #$campaign_id";
    }
}
