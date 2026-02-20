<?php
// Load authentication and configuration
require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../includes/security_helpers.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/db_campaign.php'; // Server 2 DB for mail_blaster, smtp_* tables
require_once __DIR__ . '/../includes/user_filtering.php';
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/api_optimization.php';
require_once __DIR__ . '/../includes/campaign_email_verification.php';
require_once __DIR__ . '/../includes/campaign_cache.php';

// Start performance tracking
$startTime = microtime(true);

// Skip headers and CORS if already handled by router
if (!defined('ROUTER_HANDLED')) {
    // Enable response compression
    enableCompression();
    
    // Set security headers
    setSecurityHeaders();
    handleCors();
    
    header('Content-Type: application/json');
}

// Require authentication (supports both session and token)
$currentUser = requireAuth();

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../includes/ProcessManager.php';

// Ensure lock directory exists
$lock_dir = __DIR__ . '/../tmp/cron_locks';
if (!is_dir($lock_dir)) {
    mkdir($lock_dir, 0775, true);
    chmod($lock_dir, 0775);
}

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
        } elseif ($action === 'list') {
            // Set cache headers for list action (5 seconds cache)
            setCacheHeaders(5);
            
            // Check if client provided last_update timestamp for change detection
            $lastUpdate = isset($input['last_update']) ? (int)$input['last_update'] : 0;
            $campaigns = getCampaignsWithStatsOptimized($lastUpdate);
            
            $response['success'] = true;
            $response['data'] = [
                'campaigns' => $campaigns['data'],
                'has_changes' => $campaigns['has_changes'],
                'timestamp' => time()
            ];
        } elseif ($action === 'email_counts') {
            // Set cache headers for email counts (3 seconds cache)
            setCacheHeaders(3);
            $campaign_id = (int)($input['campaign_id'] ?? 0);
            $user = getAuthenticatedUser();
            $userId = $user['id'] ?? null;
            $isAdmin = $userId ? isAuthenticatedAdmin() : false;
            $response['success'] = true;
            $response['data'] = getEmailCounts($conn, $campaign_id, $userId, $isAdmin);
        } elseif ($action === 'get_campaign_emails') {
            $campaign_id = (int)($input['campaign_id'] ?? 0);
            $page = (int)($input['page'] ?? 1);
            $limit = (int)($input['limit'] ?? 50);
            $response['success'] = true;
            $response['data'] = getCampaignEmails($conn, $campaign_id, $page, $limit);
        } elseif ($action === 'get_template_preview') {
            $campaign_id = (int)($input['campaign_id'] ?? 0);
            $email_index = (int)($input['email_index'] ?? 0);
            $response['success'] = true;
            $response['data'] = getTemplatePreview($conn, $campaign_id, $email_index);
        } elseif ($action === 'update_campaign_status') {
            $campaign_id = (int)($input['campaign_id'] ?? 0);
            updateCampaignCompletionStatus($conn, $campaign_id);
            $response['success'] = true;
            $response['message'] = "Campaign status updated successfully";
        } else {
            throw new Exception('Invalid action');
        }
    } elseif ($method === 'GET') {
        // Set cache headers for GET requests (5 seconds)
        setCacheHeaders(5);
        $lastUpdate = isset($_GET['last_update']) ? (int)$_GET['last_update'] : 0;
        $campaigns = getCampaignsWithStatsOptimized($lastUpdate);
        $response['success'] = true;
        $response['data'] = [
            'campaigns' => $campaigns['data'],
            'has_changes' => $campaigns['has_changes'],
            'timestamp' => time()
        ];
    } else {
        throw new Exception('Invalid request method');
    }
} catch (mysqli_sql_exception $e) {
    // Handle database lock timeout specifically
    $errorCode = $e->getCode();
    $errorMsg = $e->getMessage();
    
    if ($errorCode == 1205 || strpos($errorMsg, 'Lock wait timeout') !== false) {
        // Lock timeout - workers are busy, return friendly message
        $response['success'] = false;
        $response['message'] = 'Campaign workers are processing emails. Please refresh in a moment.';
        $response['error_code'] = 'LOCK_TIMEOUT';
        
        // Log for debugging
        $uri = $_SERVER['REQUEST_URI'] ?? 'unknown';
        $log = date('[Y-m-d H:i:s]') . " LOCK TIMEOUT on $uri: " . $errorMsg . "\n";
        // @file_put_contents(__DIR__ . '/../logs/api_lock_timeouts.log', $log, FILE_APPEND); // Disabled
    } else {
        // Other database errors
        $response['success'] = false;
        $response['message'] = 'Database error occurred: ' . $errorMsg; // Show actual error for debugging
        // error_log("campaigns_master.php DB Error: " . $errorMsg); // Disabled
        // error_log("campaigns_master.php DB Error Code: " . $errorCode); // Disabled
    }
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    // error_log("campaigns_master.php Error: " . $e->getMessage()); // Disabled
    // error_log("campaigns_master.php Stack: " . $e->getTraceAsString()); // Disabled
}

// Add performance headers
addPerformanceHeaders($startTime);

echo json_encode($response);

// --- Helper Functions ---

/**
 * OPTIMIZED: Get campaigns with stats using caching and minimal DB queries
 * Implements change detection to avoid sending unchanged data
 */
function getCampaignsWithStatsOptimized($lastClientUpdate = 0) {
    global $conn;
    
    // Get auth filter
    $user = getAuthenticatedUser();
    $userId = $user ? $user['id'] : null;
    $isAdmin = $userId ? isAuthenticatedAdmin() : false;
    
    // Check cache first
    $cacheKey = "campaign_list_" . ($userId ?? 'admin');
    $cached = CampaignCache::get($cacheKey, CampaignCache::TTL_CAMPAIGN_LIST);
    
    if ($cached !== null) {
        // Check if data has changed since client's last update
        $hasChanges = $cached['timestamp'] > $lastClientUpdate;
        
        return [
            'data' => $hasChanges ? $cached['campaigns'] : [],
            'has_changes' => $hasChanges,
            'from_cache' => true
        ];
    }
    
    // Not in cache, fetch from database
    $campaigns = getCampaignsWithStats(); // Call original function
    
    // Store in cache with timestamp
    $timestamp = time();
    CampaignCache::set($cacheKey, [
        'campaigns' => $campaigns,
        'timestamp' => $timestamp
    ]);
    
    // Always return full data on first fetch
    return [
        'data' => $campaigns,
        'has_changes' => true,
        'from_cache' => false
    ];
}

function getCampaignsWithStats()
{
    global $conn;
    
    // Get auth filter for campaign_master table
    $user = getAuthenticatedUser();
    $userId = $user ? $user['id'] : null;
    $isAdmin = $userId ? isAuthenticatedAdmin() : false;
    
    // DEFENSIVE: Check if validation_status column exists once at the beginning
    $hasValidationStatus = $conn->query("SHOW COLUMNS FROM emails LIKE 'validation_status'");
    $validationFilter = ($hasValidationStatus && $hasValidationStatus->num_rows > 0) ? "AND validation_status = 'valid'" : "";
    
    // error_log("getCampaignsWithStats - userId: $userId, isAdmin: " . ($isAdmin ? 'YES' : 'NO')); // Disabled
    
    // For single table queries
    $userFilter = $isAdmin ? "" : "WHERE user_id = $userId";
    $userFilterAnd = $isAdmin ? "" : "AND user_id = $userId";
    
    // For campaign_master table with alias
    $userFilterCm = $isAdmin ? "" : "WHERE cm.user_id = $userId";
    $userFilterCmAnd = $isAdmin ? "" : "AND cm.user_id = $userId";
    
    // ===== OPTIMIZATION: Use aggregator for pre-computed counts (reduces queries dramatically) =====
    $aggregator = new CampaignAggregator($conn);
    
    // ULTRA-FAST: Read from campaign_status table instead of counting mail_blaster  
    // This is orders of magnitude faster for large campaigns (lakhs of emails)
    $aggregatedCounts = $aggregator->getAggregatedCounts($userId, $isAdmin);
    $csvListCounts = $aggregator->getCsvListCounts($userId, $isAdmin);
    // ✅ FIXED: Pass userId and isAdmin to filter Excel import counts by user
    $importBatchCounts = $aggregator->getImportBatchCounts($userId, $isAdmin);
    $runningCampaignIds = $aggregator->getRunningCampaignIds($userId, $isAdmin);
    
    // ===== OPTIMIZATION: Skip heavy initialization and completion checks =====
    // Campaign initialization is handled by campaign_cron.php every 2 minutes
    // Workers update campaign_status in real-time via batch updates (every 500 emails)
    // This prevents loading delays and database overload for lakhs of emails
    
    // Get total valid emails from emails table
    $valid_emails_total = 0;
    $emailUserFilter = $isAdmin ? "" : "AND user_id = $userId";
    $valid_res = $conn->query("SELECT COUNT(*) as cnt FROM emails WHERE domain_status = 1 $validationFilter $emailUserFilter");
    if ($valid_res) {
        $valid_emails_total = (int)$valid_res->fetch_assoc()['cnt'];
    }
    
    // ===== OPTIMIZATION: No need to pre-aggregate failed counts - we have them from aggregator =====
    // They are already computed in $aggregatedCounts
    
    // ===== CRITICAL: Use READ UNCOMMITTED to avoid being blocked by worker locks =====
    // This allows frontend to read campaign_status even when workers hold FOR UPDATE locks
    // Slightly stale data (milliseconds) is acceptable for UI display
    $conn->query("SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED");
    
    $query = "SELECT 
                cm.campaign_id, 
                cm.description, 
                cm.mail_subject,
                cm.attachment_path,
                cm.csv_list_id,
                cm.import_batch_id,
                cm.template_id,
                cl.list_name as csv_list_name,
                cs.status as campaign_status,
                COALESCE(cs.total_emails, 0) as total_emails,
                COALESCE(cs.pending_emails, 0) as pending_emails,
                COALESCE(cs.sent_emails, 0) as sent_emails,
                COALESCE(cs.failed_emails, 0) as failed_emails,
                cs.start_time,
                cs.end_time
              FROM campaign_master cm
              LEFT JOIN csv_list cl ON cm.csv_list_id = cl.id
              LEFT JOIN (
                  SELECT cs1.campaign_id, cs1.status, cs1.total_emails, cs1.pending_emails, 
                         cs1.sent_emails, cs1.failed_emails, cs1.start_time, cs1.end_time
                  FROM campaign_status cs1
                  INNER JOIN (
                      SELECT campaign_id, MAX(id) as max_id
                      FROM campaign_status
                      GROUP BY campaign_id
                  ) cs2 ON cs1.campaign_id = cs2.campaign_id AND cs1.id = cs2.max_id
              ) cs ON cm.campaign_id = cs.campaign_id
              $userFilterCm
              ORDER BY cm.campaign_id DESC";
    $result = $conn->query($query);
    
    // Reset to default isolation level
    $conn->query("SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED");
    
    $campaigns = $result->fetch_all(MYSQLI_ASSOC);

    foreach ($campaigns as &$campaign) {
        $campaign['valid_emails'] = $valid_emails_total; // Total valid emails in system
        
        // Determine email source type
        if ($campaign['import_batch_id']) {
            $campaign['email_source'] = 'imported_recipients';
            $campaign['email_source_label'] = 'Excel Import';
            
            // Use pre-aggregated count from aggregator
            $batch_id = $campaign['import_batch_id'];
            $campaign['csv_list_valid_count'] = $importBatchCounts[$batch_id] ?? 0;
        } elseif ($campaign['csv_list_id']) {
            $campaign['email_source'] = 'csv_upload';
            $campaign['email_source_label'] = 'CSV Upload';
            
            // Use pre-aggregated count from aggregator
            $csvListId = (int)$campaign['csv_list_id'];
            $campaign['csv_list_valid_count'] = $csvListCounts[$csvListId] ?? 0;
        } else {
            $campaign['email_source'] = 'all_emails';
            $campaign['email_source_label'] = 'All Valid Emails';
            
            // If no CSV list or import batch selected, show total valid emails
            $campaign['csv_list_valid_count'] = $valid_emails_total;
        }
        
        $campaign['total_emails'] = (int)($campaign['total_emails'] ?? 0);
        $campaign['pending_emails'] = (int)($campaign['pending_emails'] ?? 0);
        $campaign['sent_emails'] = (int)($campaign['sent_emails'] ?? 0);
        
        // ===== OPTIMIZATION: Use pre-aggregated counts from aggregator =====
        $campaignId = $campaign['campaign_id'];
        $aggregatedData = $aggregatedCounts[$campaignId] ?? ['sent' => 0, 'failed' => 0, 'retryable' => 0, 'pending' => 0];
        
        // Override with accurate counts from aggregator
        $campaign['failed_emails'] = $aggregatedData['failed'];
        $campaign['retryable_count'] = $aggregatedData['retryable'];
        
        $total = max($campaign['total_emails'], 1);
        $sent = min($campaign['sent_emails'], $total);
        $campaign['progress'] = round(($sent / $total) * 100);
    }
    return $campaigns;
}

function startCampaign($conn, $campaign_id)
{
    global $conn_heavy; // Access Server 2 connection for smtp_* tables
    
    // Get user context for permission check
    $userId = getCurrentUserId();
    $isAdmin = isAuthenticatedAdmin();
    
    $max_retries = 5;
    $retry_count = 0;
    $success = false;
    while ($retry_count < $max_retries && !$success) {
        try {
            $conn->query("SET SESSION innodb_lock_wait_timeout = 10");
            $conn->begin_transaction();
            
            // Check if campaign exists and user has permission (campaign_master is on Server 1)
            $userCheck = $isAdmin ? "" : " AND user_id = $userId";
            $check = $conn->query("SELECT cm.campaign_id, cm.user_id FROM campaign_master cm WHERE cm.campaign_id = $campaign_id $userCheck");
            if ($check->num_rows == 0) {
                $conn->commit();
                throw new Exception($isAdmin ? "Campaign #$campaign_id does not exist" : "Campaign #$campaign_id not found or you don't have permission");
            }
            
            $campaignData = $check->fetch_assoc();
            $campaignUserId = $campaignData['user_id'];
            
            // Verify user has active SMTP accounts before starting (smtp_* tables are on Server 2)
            $smtpCheck = $conn_heavy->query("
                SELECT COUNT(*) as smtp_count 
                FROM smtp_accounts sa
                JOIN smtp_servers ss ON sa.smtp_server_id = ss.id
                WHERE sa.user_id = $campaignUserId
                AND sa.is_active = 1 
                AND ss.is_active = 1
            ");
            
            if ($smtpCheck) {
                $smtpRow = $smtpCheck->fetch_assoc();
                if ($smtpRow['smtp_count'] == 0) {
                    $conn->commit();
                    throw new Exception("Cannot start campaign: No active SMTP accounts found for this user");
                }
            }
            
            $status_check = $conn->query("SELECT status FROM campaign_status WHERE campaign_id = $campaign_id");
            if ($status_check->num_rows > 0) {
                $currentStatus = $status_check->fetch_assoc()['status'];
                if ($currentStatus === 'completed') {
                    $conn->commit();
                    throw new Exception("Campaign #$campaign_id is already completed");
                }
                if ($currentStatus === 'running') {
                    $conn->commit();
                    throw new Exception("Campaign #$campaign_id is already running");
                }
            }
            
            // Offload heavy initialization to a background worker to avoid blocking the HTTP request.
            // The background runner will initialize the email queue and launch the blaster process.
            // error_log("Spawning async start worker for campaign #$campaign_id..."); // Disabled

            // Commit any open transaction before spawning background job
            $conn->commit();



            // Robust PHP binary detection for spawning background worker
            $php_cli_candidates = [
                '/opt/plesk/php/8.1/bin/php',   // Plesk PHP 8.1 (Production Preferred)
                '/usr/bin/php8.1',              // Standard PHP 8.1
                '/usr/local/bin/php',
                '/usr/bin/php',
                '/opt/lampp/bin/php'            // XAMPP
            ];
            
            $php_bin = null;
            $open_basedir = ini_get('open_basedir');
            
            foreach ($php_cli_candidates as $candidate) {
                // If open_basedir is set, file_exists might return false for valid system binaries
                // So requires a tri-state check: Exists+Executable OR open_basedir restricted
                if (file_exists($candidate) && is_executable($candidate)) {
                    $php_bin = $candidate;
                    break;
                }
            }
            
            // Fallback: If no specific binary found
            if (!$php_bin) {
                // On this server, open_basedir prevents verifying the Plesk binary, but we know it's there from logs.
                // Force try the Plesk binary if we couldn't verify anything else.
                $php_bin = '/opt/plesk/php/8.1/bin/php';
            }
            
            // Check if exec is enabled
            if (!function_exists('exec')) {
                throw new Exception("Server Error: exec() function is disabled. Cannot start background campaign.");
            }

            $runner = escapeshellarg(__DIR__ . "/../scripts/async_start_campaign.php");
            $logfile = __DIR__ . "/../logs/async_launch_output.log";
            
            // Log output to file instead of /dev/null to debug startup errors
            $cmd = "nohup $php_bin -f $runner " . intval($campaign_id) . " > " . escapeshellarg($logfile) . " 2>&1 &";
            // error_log("Launching Async Campaign: $cmd"); // Disabled
            
            $output = [];
            $ret = -1;
            exec($cmd, $output, $ret);
            
            if ($ret !== 0) {
                // Only throw if we get a non-zero exit code (0 usually means success for background &)
                throw new Exception("Failed to spawn background process (Exit Code: $ret). Command: $cmd");
            }
            // error_log("Async process spawned. Exit code: $ret"); // Disabled
            if ($ret !== 0) {
                // Background spawn failed — surface an error so caller can see it
                throw new Exception("Failed to spawn async campaign starter (exit $ret)");
            }

            $success = true;
            // error_log("Campaign #$campaign_id: async starter spawned successfully"); // Disabled
            
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
    if ($conn && !$conn->connect_error) {
        $conn->query("SET SESSION innodb_lock_wait_timeout = 50");
    }
}

function getEmailCounts($conn, $campaign_id, $userId = null, $isAdmin = false)
{
    global $conn_heavy; // Access Server 2 connection for mail_blaster
    
    // ===== PERFORMANCE OPTIMIZATION: Add in-memory caching =====
    static $cache = [];
    static $cacheTimestamps = [];
    $cacheTTL = 3; // 3 second cache to prevent duplicate queries
    $cacheKey = "{$campaign_id}:{$userId}:" . ($isAdmin ? '1' : '0');
    
    // DEFENSIVE: Check if validation_status column exists
    $hasValidationStatus = $conn->query("SHOW COLUMNS FROM emails LIKE 'validation_status'");
    $validationFilter = ($hasValidationStatus && $hasValidationStatus->num_rows > 0) ? "AND validation_status = 'valid'" : "";
    
    // Check if cache is still valid
    if (isset($cache[$cacheKey]) && isset($cacheTimestamps[$cacheKey])) {
        $age = time() - $cacheTimestamps[$cacheKey];
        if ($age < $cacheTTL) {
            // error_log("getEmailCounts - Cache HIT for campaign $campaign_id (age: {$age}s)"); // Disabled
            return $cache[$cacheKey];
        }
    }
    
    // First, verify the campaign belongs to the user (if not admin)
    if ($userId && !$isAdmin) {
        $campaignCheck = $conn->query("SELECT campaign_id FROM campaign_master WHERE campaign_id = $campaign_id AND user_id = $userId");
        if (!$campaignCheck || $campaignCheck->num_rows == 0) {
            // User doesn't have access to this campaign
            $emptyResult = [
                'total_valid' => 0,
                'sent' => 0,
                'failed' => 0,
                'pending' => 0,
                'retryable' => 0
            ];
            // Cache the empty result too (prevent repeated unauthorized access queries)
            $cache[$cacheKey] = $emptyResult;
            $cacheTimestamps[$cacheKey] = time();
            return $emptyResult;
        }
    }
    
    // Get csv_list_id and import_batch_id for this campaign
    $campaignResult = $conn->query("SELECT csv_list_id, import_batch_id, user_id FROM campaign_master WHERE campaign_id = $campaign_id");
    $csvListId = null;
    $importBatchId = null;
    $campaignUserId = null;
    
    if ($campaignResult && $campaignResult->num_rows > 0) {
        $campaignRow = $campaignResult->fetch_assoc();
        $csvListId = $campaignRow['csv_list_id'];
        $importBatchId = $campaignRow['import_batch_id'];
        $campaignUserId = $campaignRow['user_id'];
    }
    
    // error_log("getEmailCounts - campaign_id: $campaign_id, csv_list_id: " . ($csvListId ?? 'NULL') . ", import_batch_id: " . ($importBatchId ?? 'NULL')); // Disabled
    
    // Count total valid emails based on source
    if ($importBatchId) {
        // For import batch campaigns, count directly from mail_blaster on Server 2 (SOURCE OF TRUTH)
        // The import_batch_id is stored in campaign_master, and all queued emails are in mail_blaster
        $batch_escaped = $conn->real_escape_string($importBatchId);
        
        $countQuery = "SELECT 
                    COUNT(*) as total_valid,
                    COALESCE(SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END), 0) as sent,
                    COALESCE(SUM(CASE WHEN status = 'failed' AND attempt_count >= 5 THEN 1 ELSE 0 END), 0) as failed,
                    COALESCE(SUM(CASE WHEN status = 'failed' AND attempt_count < 5 THEN 1 ELSE 0 END), 0) as retryable,
                    COALESCE(SUM(CASE WHEN status = 'pending' OR status IS NULL THEN 1 ELSE 0 END), 0) as pending
                FROM mail_blaster
                WHERE campaign_id = $campaign_id";
        
        $result = $conn_heavy->query($countQuery);
        if (!$result) {
            error_log("getEmailCounts - Query error on Server 2 for import batch: " . $conn_heavy->error);
            return [
                'total_valid' => 0,
                'pending' => 0,
                'sent' => 0,
                'failed' => 0,
                'retryable' => 0
            ];
        }
        
        $counts = $result->fetch_assoc();
        $totalValid = (int)$counts['total_valid'];
        $sent = (int)$counts['sent'];
        $failed = (int)$counts['failed'];
        $retryable = (int)$counts['retryable'];
        $pending = (int)$counts['pending'];
        
        $result = [
            'total_valid' => $totalValid,
            'pending' => $pending,
            'sent' => $sent,
            'failed' => $failed,
            'retryable' => $retryable
        ];
        $cache[$cacheKey] = $result;
        $cacheTimestamps[$cacheKey] = time();
        return $result;
        
    } elseif ($csvListId) {
        // Count from mail_blaster for this CSV list (on Server 2) - SOURCE OF TRUTH
        $countQuery = "SELECT 
                    COUNT(*) as total_valid,
                    COALESCE(SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END), 0) as sent,
                    COALESCE(SUM(CASE WHEN status = 'failed' AND attempt_count >= 5 THEN 1 ELSE 0 END), 0) as failed,
                    COALESCE(SUM(CASE WHEN status = 'failed' AND attempt_count < 5 THEN 1 ELSE 0 END), 0) as retryable,
                    COALESCE(SUM(CASE WHEN status = 'pending' OR status IS NULL THEN 1 ELSE 0 END), 0) as pending
                FROM mail_blaster
                WHERE campaign_id = $campaign_id
                AND csv_list_id = $csvListId";
        
    } else {
        // No CSV list or import batch - count all from mail_blaster (Server 2) - SOURCE OF TRUTH
        $countQuery = "SELECT 
                    COUNT(*) as total_valid,
                    COALESCE(SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END), 0) as sent,
                    COALESCE(SUM(CASE WHEN status = 'failed' AND attempt_count >= 5 THEN 1 ELSE 0 END), 0) as failed,
                    COALESCE(SUM(CASE WHEN status = 'failed' AND attempt_count < 5 THEN 1 ELSE 0 END), 0) as retryable,
                    COALESCE(SUM(CASE WHEN status = 'pending' OR status IS NULL THEN 1 ELSE 0 END), 0) as pending
                FROM mail_blaster
                WHERE campaign_id = $campaign_id";
    }
    
    $result = $conn_heavy->query($countQuery);
    if (!$result) {
        error_log("getEmailCounts - Query error on Server 2: " . $conn_heavy->error);
        // Return empty counts on error
        return [
            'total_valid' => 0,
            'pending' => 0,
            'sent' => 0,
            'failed' => 0,
            'retryable' => 0
        ];
    }
    
    $counts = $result->fetch_assoc();
    
    $totalValid = (int)$counts['total_valid'];
    $sent = (int)$counts['sent'];
    $failed = (int)$counts['failed'];
    $retryable = (int)$counts['retryable'];
    $pending = (int)$counts['pending'];
    
    // error_log("getEmailCounts - Results: total=$totalValid, sent=$sent, failed=$failed, retryable=$retryable, pending=$pending"); // Disabled
    
    $result = [
        'total_valid' => $totalValid,
        'pending' => $pending,
        'sent' => $sent,
        'failed' => $failed,
        'retryable' => $retryable
    ];
    
    // ===== Store in cache before returning =====
    $cache[$cacheKey] = $result;
    $cacheTimestamps[$cacheKey] = time();
    
    return $result;
}

function startEmailBlasterProcess($campaign_id)
{
    global $conn;
    
    // Ensure tmp directory exists
    $tmp_dir = __DIR__ . "/../tmp";
    if (!is_dir($tmp_dir)) {
        @mkdir($tmp_dir, 0777, true);
    }
    
    // Use a pid file inside the project's tmp directory
    $pid_file = $tmp_dir . "/email_blaster_{$campaign_id}.pid";

    // If pid file exists, check whether process is still running
    if (file_exists($pid_file)) {
        $pid = trim(file_get_contents($pid_file));
        if (is_numeric($pid)) {
            // posix_kill may not be available on some systems; check /proc as fallback
            $isRunning = false;
            if (function_exists('posix_kill')) {
                $isRunning = @posix_kill((int)$pid, 0);
            } else {
                $isRunning = file_exists("/proc/" . (int)$pid);
            }

            if ($isRunning) {
                // Process is running, do not start another
                // error_log("Campaign #$campaign_id already running with PID $pid"); // Disabled
                return;
            }
        }
        // Stale pid file - remove it
        @unlink($pid_file);
    }

    // SIMPLIFIED APPROACH: Match email validation pattern (proc_open, immediate spawn, no complex ProcessManager)
    $script_path = __DIR__ . '/../includes/email_blast_parallel.php';
    
    // Use same PHP binary detection as email validation
    // Use robust PHP binary detection
    $php_cli_candidates = [
        '/opt/plesk/php/8.1/bin/php',   // Plesk PHP 8.1
        '/usr/bin/php8.1',              // Standard PHP 8.1
        '/usr/local/bin/php',
        '/usr/bin/php',
        '/opt/lampp/bin/php'            // XAMPP/LAMPP
    ];
    
    $php_path = 'php';
    foreach ($php_cli_candidates as $candidate) {
        if (file_exists($candidate) && is_executable($candidate)) {
            $php_path = $candidate;
            break;
        }
    }
    
    // Build command - Simple approach like email validation
    $cmd = sprintf(
        '%s %s %d > /dev/null 2>&1 & echo $!',
        escapeshellarg($php_path),
        escapeshellarg($script_path),
        intval($campaign_id)
    );
    
    // error_log("Starting campaign #$campaign_id with command: $cmd"); // Disabled
    
    // Execute and capture PID (same as email validation)  
    $output = [];
    exec($cmd, $output, $ret);
    $pid = isset($output[0]) ? intval($output[0]) : 0;
    
    if ($pid > 0) {
        file_put_contents($pid_file, $pid);
        // error_log("Campaign #$campaign_id started with PID $pid"); // Disabled
    } else {
        // error_log("ERROR: Failed to start campaign #$campaign_id (exec returned $ret)"); // Disabled
    }
}

function pauseCampaign($conn, $campaign_id)
{
    // Get user context for permission check
    $userId = getCurrentUserId();
    $isAdmin = isAuthenticatedAdmin();
    
    // Check if user has permission to pause this campaign
    $userCheck = $isAdmin ? "" : " AND user_id = $userId";
    $check = $conn->query("SELECT 1 FROM campaign_master WHERE campaign_id = $campaign_id $userCheck");
    if ($check->num_rows == 0) {
        throw new Exception($isAdmin ? "Campaign #$campaign_id does not exist" : "Campaign #$campaign_id not found or you don't have permission");
    }
    
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
    // Try graceful stop: read pid file and kill the process, then remove pid file
    $pid_file = __DIR__ . "/../tmp/email_blaster_{$campaign_id}.pid";
    if (file_exists($pid_file)) {
        $pid = (int)trim(file_get_contents($pid_file));
        if ($pid > 0) {
            // Send SIGTERM (15)
            @posix_kill($pid, 15);
            // Wait briefly for process to exit
            usleep(200000);
            // If still running, send SIGKILL (9)
            if (function_exists('posix_kill') && @posix_kill($pid, 0)) {
                @posix_kill($pid, 9);
            }
        }
        @unlink($pid_file);
    } else {
        // Fallback to pkill by pattern if pid file missing
        exec("pkill -f 'email_blaster.php $campaign_id'");
    }
}

function retryFailedEmails($conn, $campaign_id)
{
    global $conn_heavy; // Access Server 2 connection for mail_blaster
    
    // Get user context for permission check
    $userId = getCurrentUserId();
    $isAdmin = isAuthenticatedAdmin();
    
    // Check if user has permission to retry this campaign (campaign_master is on Server 1)
    $userCheck = $isAdmin ? "" : " AND user_id = $userId";
    $check = $conn->query("SELECT 1 FROM campaign_master WHERE campaign_id = $campaign_id $userCheck");
    if ($check->num_rows == 0) {
        throw new Exception($isAdmin ? "Campaign #$campaign_id does not exist" : "Campaign #$campaign_id not found or you don't have permission");
    }
    
    // Only retry emails that haven't exceeded 5 attempts (mail_blaster is on Server 2)
    $result = $conn_heavy->query("
            SELECT COUNT(*) as failed_count 
            FROM mail_blaster 
            WHERE campaign_id = $campaign_id 
            AND status = 'failed'
            AND attempt_count < 5
        ");
    $failed_count = $result->fetch_assoc()['failed_count'];
    
    if ($failed_count > 0) {
        // Reset failed emails back to pending for retry (don't increment attempt_count here, worker will do it)
        $conn_heavy->query("
                UPDATE mail_blaster 
                SET status = 'pending',     
                    error_message = NULL
                WHERE campaign_id = $campaign_id 
                AND status = 'failed'
                AND attempt_count < 5
            ");
        
        // Update campaign status (campaign_status is on Server 1)
        $conn->query("
                UPDATE campaign_status 
                SET status = 'running'
                WHERE campaign_id = $campaign_id
            ");
        
        startEmailBlasterProcess($campaign_id);
        return "Retrying $failed_count failed emails for campaign #$campaign_id (max 5 attempts per email)";
    } else {
        return "No emails available for retry. All failed emails have reached maximum attempts (5).";
    }
}

/**
 * Get list of emails for a specific campaign
 * Shows emails from imported_recipients if campaign uses Excel import
 * Shows emails from emails table if campaign uses CSV upload
 */
function getCampaignEmails($conn, $campaign_id, $page = 1, $limit = 50)
{
    global $conn_heavy; // Access Server 2 connection for mail_blaster
    
    // Get campaign details to determine source
    $campaignQuery = "SELECT csv_list_id, import_batch_id, template_id, description 
                      FROM campaign_master 
                      WHERE campaign_id = $campaign_id";
    $campaignResult = $conn->query($campaignQuery);
    
    if (!$campaignResult || $campaignResult->num_rows === 0) {
        return [
            'error' => 'Campaign not found',
            'campaign_id' => $campaign_id
        ];
    }
    
    $campaign = $campaignResult->fetch_assoc();
    $csvListId = $campaign['csv_list_id'];
    $importBatchId = $campaign['import_batch_id'];
    $templateId = $campaign['template_id'];
    
    $offset = ($page - 1) * $limit;
    $emails = [];
    $total = 0;
    $source = '';
    
    // Determine source and fetch emails
    if ($importBatchId) {
        // Fetch from imported_recipients (Excel import)
        $source = 'imported_recipients';
        $batch_escaped = $conn->real_escape_string($importBatchId);
        
        // Get total count
        $countQuery = "SELECT COUNT(*) as total 
                       FROM imported_recipients 
                       WHERE import_batch_id = '$batch_escaped' 
                       AND is_active = 1 
                       AND Emails IS NOT NULL 
                       AND Emails <> ''";
        $countResult = $conn->query($countQuery);
        $total = ($countResult && $countResult->num_rows > 0) ? (int)$countResult->fetch_assoc()['total'] : 0;
        
        // Get paginated emails with their data
        $emailQuery = "SELECT 
                        id,
                        Emails as email,
                        BilledName as name,
                        Company,
                        Amount,
                        Days,
                        BillNumber,
                        CustomerID,
                        Phone,
                        source_file_type
                       FROM imported_recipients 
                       WHERE import_batch_id = '$batch_escaped' 
                       AND is_active = 1 
                       AND Emails IS NOT NULL 
                       AND Emails <> ''
                       ORDER BY id ASC
                       LIMIT $limit OFFSET $offset";
        
        $emailResult = $conn->query($emailQuery);
        
        if ($emailResult) {
            while ($row = $emailResult->fetch_assoc()) {
                // Get send status from mail_blaster if exists (mail_blaster is on Server 2)
                $email_escaped = $conn_heavy->real_escape_string($row['email']);
                $statusQuery = "SELECT status, attempt_count, delivery_date, delivery_time, error_message 
                                FROM mail_blaster 
                                WHERE campaign_id = $campaign_id 
                                AND to_mail = '$email_escaped' 
                                LIMIT 1";
                $statusResult = $conn_heavy->query($statusQuery);
                
                $send_status = 'not_sent';
                $attempt_count = 0;
                $delivery_info = null;
                $error_message = null;
                
                if ($statusResult && $statusResult->num_rows > 0) {
                    $status = $statusResult->fetch_assoc();
                    $send_status = $status['status'] ?? 'pending';
                    $attempt_count = (int)($status['attempt_count'] ?? 0);
                    $delivery_info = $status['delivery_date'] . ' ' . $status['delivery_time'];
                    $error_message = $status['error_message'];
                }
                
                $emails[] = [
                    'id' => $row['id'],
                    'email' => $row['email'],
                    'name' => $row['name'] ?: $row['Company'] ?: 'N/A',
                    'company' => $row['Company'] ?: $row['name'] ?: '',
                    'amount' => $row['Amount'] ?: '',
                    'days' => $row['Days'] ?: '',
                    'bill_number' => $row['BillNumber'] ?: '',
                    'customer_id' => $row['CustomerID'] ?: '',
                    'phone' => $row['Phone'] ?: '',
                    'file_type' => $row['source_file_type'] ?: 'unknown',
                    'send_status' => $send_status,
                    'attempt_count' => $attempt_count,
                    'delivery_info' => $delivery_info,
                    'error_message' => $error_message
                ];
            }
        }
        
    } elseif ($csvListId) {
        // Fetch from emails table (CSV upload)
        $source = 'csv_upload';
        
        // Get total count
        $countQuery = "SELECT COUNT(*) as total 
                       FROM emails 
                       WHERE csv_list_id = $csvListId 
                       AND domain_status = 1 
                       AND validation_status = 'valid'";
        $countResult = $conn->query($countQuery);
        $total = ($countResult && $countResult->num_rows > 0) ? (int)$countResult->fetch_assoc()['total'] : 0;
        
        // Get paginated emails
        $emailQuery = "SELECT 
                        id,
                        raw_emailid as email,
                        name,
                        company,
                        phone
                       FROM emails 
                       WHERE csv_list_id = $csvListId 
                       AND domain_status = 1 
                       AND validation_status = 'valid'
                       ORDER BY id ASC
                       LIMIT $limit OFFSET $offset";
        
        $emailResult = $conn->query($emailQuery);
        
        if ($emailResult) {
            while ($row = $emailResult->fetch_assoc()) {
                // Get send status from mail_blaster if exists (mail_blaster is on Server 2)
                $email_escaped = $conn_heavy->real_escape_string($row['email']);
                $statusQuery = "SELECT status, attempt_count, delivery_date, delivery_time, error_message 
                                FROM mail_blaster 
                                WHERE campaign_id = $campaign_id 
                                AND to_mail = '$email_escaped' 
                                LIMIT 1";
                $statusResult = $conn_heavy->query($statusQuery);
                
                $send_status = 'not_sent';
                $attempt_count = 0;
                $delivery_info = null;
                $error_message = null;
                
                if ($statusResult && $statusResult->num_rows > 0) {
                    $status = $statusResult->fetch_assoc();
                    $send_status = $status['status'] ?? 'pending';
                    $attempt_count = (int)($status['attempt_count'] ?? 0);
                    $delivery_info = $status['delivery_date'] . ' ' . $status['delivery_time'];
                    $error_message = $status['error_message'];
                }
                
                $emails[] = [
                    'id' => $row['id'],
                    'email' => $row['email'],
                    'name' => $row['name'] ?: 'N/A',
                    'company' => $row['company'] ?: '',
                    'phone' => $row['phone'] ?: '',
                    'file_type' => 'csv',
                    'send_status' => $send_status,
                    'attempt_count' => $attempt_count,
                    'delivery_info' => $delivery_info,
                    'error_message' => $error_message
                ];
            }
        }
        
    } else {
        // No specific source - use all valid emails
        $source = 'all_valid_emails';
        
        // Get total count
        $countQuery = "SELECT COUNT(*) as total 
                       FROM emails 
                       WHERE domain_status = 1 
                       AND validation_status = 'valid'";
        $countResult = $conn->query($countQuery);
        $total = ($countResult && $countResult->num_rows > 0) ? (int)$countResult->fetch_assoc()['total'] : 0;
        
        // Get paginated emails
        $emailQuery = "SELECT 
                        id,
                        raw_emailid as email,
                        name,
                        company,
                        phone
                       FROM emails 
                       WHERE domain_status = 1 
                       AND validation_status = 'valid'
                       ORDER BY id ASC
                       LIMIT $limit OFFSET $offset";
        
        $emailResult = $conn->query($emailQuery);
        
        if ($emailResult) {
            while ($row = $emailResult->fetch_assoc()) {
                // Get send status from mail_blaster if exists (mail_blaster is on Server 2)
                $email_escaped = $conn_heavy->real_escape_string($row['email']);
                $statusQuery = "SELECT status, attempt_count, delivery_date, delivery_time, error_message 
                                FROM mail_blaster 
                                WHERE campaign_id = $campaign_id 
                                AND to_mail = '$email_escaped' 
                                LIMIT 1";
                $statusResult = $conn_heavy->query($statusQuery);
                
                $send_status = 'not_sent';
                $attempt_count = 0;
                $delivery_info = null;
                $error_message = null;
                
                if ($statusResult && $statusResult->num_rows > 0) {
                    $status = $statusResult->fetch_assoc();
                    $send_status = $status['status'] ?? 'pending';
                    $attempt_count = (int)($status['attempt_count'] ?? 0);
                    $delivery_info = $status['delivery_date'] . ' ' . $status['delivery_time'];
                    $error_message = $status['error_message'];
                }
                
                $emails[] = [
                    'id' => $row['id'],
                    'email' => $row['email'],
                    'name' => $row['name'] ?: 'N/A',
                    'company' => $row['company'] ?: '',
                    'phone' => $row['phone'] ?: '',
                    'file_type' => 'system',
                    'send_status' => $send_status,
                    'attempt_count' => $attempt_count,
                    'delivery_info' => $delivery_info,
                    'error_message' => $error_message
                ];
            }
        }
    }
    
    return [
        'campaign_id' => $campaign_id,
        'campaign_description' => $campaign['description'],
        'email_source' => $source,
        'uses_template' => !empty($templateId),
        'template_id' => $templateId,
        'total' => $total,
        'page' => $page,
        'limit' => $limit,
        'total_pages' => ceil($total / $limit),
        'emails' => $emails
    ];
}

function getTemplatePreview($conn, $campaign_id, $email_index = 0) {
    require_once __DIR__ . '/../includes/template_merge_helper.php';
    
    $campaign_id = intval($campaign_id);
    $email_index = intval($email_index);
    
    // Get campaign details
    $query = "SELECT cm.template_id, cm.import_batch_id, cm.description, cm.mail_subject,
                     mt.template_name, mt.template_html, mt.merge_fields
              FROM campaign_master cm
              LEFT JOIN mail_templates mt ON cm.template_id = mt.template_id
              WHERE cm.campaign_id = $campaign_id";
    
    $result = $conn->query($query);
    if (!$result || $result->num_rows === 0) {
        return ['error' => 'Campaign not found'];
    }
    
    $campaign = $result->fetch_assoc();
    
    // Check if campaign uses template
    if (!$campaign['template_id']) {
        return ['error' => 'Campaign does not use a template'];
    }
    
    // Get all sample emails from imported_recipients
    $sampleEmails = [];
    $totalCount = 0;
    $selectedEmail = null;
    
    if ($campaign['import_batch_id']) {
        $batch_escaped = $conn->real_escape_string($campaign['import_batch_id']);
        
        // Get total count
        $countQuery = "SELECT COUNT(*) as total FROM imported_recipients 
                      WHERE import_batch_id = '$batch_escaped' 
                      AND is_active = 1 
                      AND Emails IS NOT NULL 
                      AND Emails <> ''";
        $countResult = $conn->query($countQuery);
        if ($countResult) {
            $totalCount = (int)$countResult->fetch_assoc()['total'];
        }
        
        // Get list of sample emails (first 10 for dropdown)
        $listQuery = "SELECT * FROM imported_recipients 
                     WHERE import_batch_id = '$batch_escaped' 
                     AND is_active = 1 
                     AND Emails IS NOT NULL 
                     AND Emails <> ''
                     LIMIT 10";
        $listResult = $conn->query($listQuery);
        if ($listResult) {
            while ($row = $listResult->fetch_assoc()) {
                $sampleEmails[] = [
                    'email' => $row['Emails'] ?? '',
                    'name' => $row['BilledName'] ?? $row['Name'] ?? '',
                    'preview_label' => ($row['BilledName'] ?? $row['Name'] ?? $row['Emails']) . 
                                      ($row['Amount'] ? ' - ₹' . $row['Amount'] : '') .
                                      ($row['CustomerID'] ? ' (ID: ' . $row['CustomerID'] . ')' : '')
                ];
            }
        }
        
        // Get selected email data
        $offset = max(0, $email_index);
        $selectedQuery = "SELECT * FROM imported_recipients 
                         WHERE import_batch_id = '$batch_escaped' 
                         AND is_active = 1 
                         AND Emails IS NOT NULL 
                         AND Emails <> ''
                         LIMIT 1 OFFSET $offset";
        $selectedResult = $conn->query($selectedQuery);
        if ($selectedResult && $selectedResult->num_rows > 0) {
            $selectedEmail = $selectedResult->fetch_assoc();
            
            // Merge extra_data JSON into main array for preview display
            if (isset($selectedEmail['extra_data']) && $selectedEmail['extra_data']) {
                $extraData = json_decode($selectedEmail['extra_data'], true);
                if (is_array($extraData)) {
                    // Merge extra data into the main array
                    $selectedEmail = array_merge($selectedEmail, $extraData);
                }
            }
        }
    }
    
    // Prepare template HTML
    $templateHtml = $campaign['template_html'] ?? '';
    $mergeFields = json_decode($campaign['merge_fields'] ?? '[]', true);
    
    // If we have sample data, merge it
    if ($selectedEmail) {
        $mergedHtml = mergeTemplateWithData($templateHtml, $selectedEmail);
        // Also merge the email subject with recipient data
        $mergedSubject = mergeTemplateWithData($campaign['mail_subject'] ?? '', $selectedEmail);
    } else {
        $mergedHtml = $templateHtml;
        $mergedSubject = $campaign['mail_subject'] ?? '';
    }
    
    // Prepare current email data for display (excluding internal fields)
    $currentEmailData = null;
    if ($selectedEmail) {
        $currentEmailData = $selectedEmail;
        // Remove internal database fields
        unset($currentEmailData['id']);
        unset($currentEmailData['import_batch_id']);
        unset($currentEmailData['is_active']);
        unset($currentEmailData['created_at']);
        unset($currentEmailData['extra_data']); // Remove the JSON field itself after merging
    }
    
    return [
        'campaign_id' => $campaign_id,
        'campaign_name' => $campaign['description'],
        'template_id' => $campaign['template_id'],
        'template_name' => $campaign['template_name'],
        'template_html' => $mergedHtml,
        'mail_subject' => $mergedSubject, // Send merged subject instead of raw subject
        'has_sample_data' => $selectedEmail ? true : false,
        'current_index' => $email_index,
        'total_emails' => $totalCount,
        'sample_emails' => $sampleEmails,
        'current_email' => $currentEmailData,
        'merge_fields' => $mergeFields
    ];
}

/**
 * Update campaign completion status based on actual progress
 * Handles both Excel import and CSV list sources
 */
function updateCampaignCompletionStatus($conn, $campaign_id) {
    global $conn_heavy; // Access Server 2 connection for mail_blaster
    
    // Get campaign details to check source (campaign_master is on Server 1)
    $campaignResult = $conn->query("SELECT import_batch_id, csv_list_id FROM campaign_master WHERE campaign_id = $campaign_id");
    if (!$campaignResult || $campaignResult->num_rows === 0) {
        return false;
    }
    
    $campaignData = $campaignResult->fetch_assoc();
    $import_batch_id = $campaignData['import_batch_id'];
    $csv_list_id = intval($campaignData['csv_list_id']);
    
    // Get sent and failed counts from mail_blaster (mail_blaster is on Server 2)
    $stats = $conn_heavy->query("
        SELECT 
            COUNT(DISTINCT CASE WHEN mb.status = 'success' THEN mb.to_mail END) as sent_count,
            COUNT(DISTINCT CASE WHEN mb.status = 'failed' AND mb.attempt_count >= 5 THEN mb.to_mail END) as failed_count
        FROM mail_blaster mb
        WHERE mb.campaign_id = $campaign_id
    ")->fetch_assoc();
    
    $sent_emails = intval($stats['sent_count']);
    $failed_emails = intval($stats['failed_count']);
    
    // Get total emails based on campaign source
    if ($import_batch_id) {
        // Excel import source
        $batch_escaped = $conn->real_escape_string($import_batch_id);
        $total_result = $conn->query("
            SELECT COUNT(*) as total
            FROM imported_recipients
            WHERE import_batch_id = '$batch_escaped'
            AND is_active = 1
            AND Emails IS NOT NULL
            AND Emails <> ''
        ");
        $total_emails = intval($total_result->fetch_assoc()['total']);
    } elseif ($csv_list_id > 0) {
        // CSV list source
        $total_result = $conn->query("
            SELECT COUNT(*) as total
            FROM emails
            WHERE csv_list_id = $csv_list_id
            AND domain_status = 1
            AND validation_status = 'valid'
            AND raw_emailid IS NOT NULL
            AND raw_emailid <> ''
        ");
        $total_emails = intval($total_result->fetch_assoc()['total']);
    } else {
        // All valid emails source
        $total_result = $conn->query("
            SELECT COUNT(DISTINCT raw_emailid) as total
            FROM emails
            WHERE domain_status = 1
            AND validation_status = 'valid'
            AND raw_emailid IS NOT NULL
            AND raw_emailid <> ''
        ");
        $total_emails = intval($total_result->fetch_assoc()['total']);
    }
    
    // Calculate pending emails
    $pending_emails = max(0, $total_emails - $sent_emails - $failed_emails);
    
    // Determine campaign status
    $campaign_status = 'running';
    if ($pending_emails == 0 && $total_emails > 0) {
        $campaign_status = 'completed';
    } elseif ($total_emails == 0) {
        $campaign_status = 'completed';
    }
    
    // Update campaign_status table
    $conn->query("
        UPDATE campaign_status 
        SET 
            sent_emails = $sent_emails,
            failed_emails = $failed_emails,
            pending_emails = $pending_emails,
            total_emails = $total_emails,
            status = '$campaign_status',
            end_time = CASE WHEN '$campaign_status' = 'completed' THEN NOW() ELSE end_time END
        WHERE campaign_id = $campaign_id
    ");
    
    return true;
}