<?php
/**
 * Fast, non-blocking progress endpoint
 * Returns pre-aggregated data only - NO JOINS, NO LOCKS
 * Optimized for <5ms response time even under load
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('max_execution_time', 3); // Hard 3-second timeout

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

// CRITICAL: Load security helpers FIRST
require_once __DIR__ . '/security_helpers.php';
handleCors();
setSecurityHeaders();

// Load dependencies
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/session_config.php';
require_once __DIR__ . '/user_filtering.php';
require_once __DIR__ . '/auth_helper.php';

// Disable query buffering for faster response
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->query("SET SESSION sql_mode='NO_ENGINE_SUBSTITUTION'");

// Get current user
$currentUser = getAuthenticatedUser();
$userId = $currentUser && !isAdmin() ? intval($currentUser['id']) : null;

try {
    // ===== EMAIL VALIDATION PROGRESS =====
    // Fast query on csv_list only (no joins)
    $validationQuery = "SELECT 
        COUNT(*) as pending_lists,
        SUM(CASE WHEN status != 'completed' THEN total_count ELSE 0 END) as total_emails,
        SUM(CASE WHEN status != 'completed' THEN processed_count ELSE 0 END) as processed_emails
    FROM csv_list 
    WHERE 1=1" . ($userId ? " AND user_id = $userId" : "");
    
    $validationResult = $conn->query($validationQuery);
    $validation = $validationResult->fetch_assoc();
    
    $pendingLists = intval($validation['pending_lists'] ?? 0);
    $totalEmails = intval($validation['total_emails'] ?? 0);
    $processedEmails = intval($validation['processed_emails'] ?? 0);
    
    // ===== CAMPAIGN PROGRESS =====
    // Fast query on campaign_status only (pre-aggregated by workers)
    $campaignQuery = "SELECT 
        cs.campaign_id,
        cs.status,
        cs.total_emails,
        cs.sent_emails,
        cs.failed_emails,
        cs.pending_emails,
        cm.description
    FROM campaign_status cs
    JOIN campaign_master cm ON cs.campaign_id = cm.campaign_id
    WHERE cs.status IN ('running', 'starting')
    " . ($userId ? " AND cm.user_id = $userId" : "") . "
    LIMIT 5";
    
    $campaignResult = $conn->query($campaignQuery);
    $runningCampaigns = [];
    
    while ($row = $campaignResult->fetch_assoc()) {
        $total = intval($row['total_emails']);
        $sent = intval($row['sent_emails']);
        $percent = $total > 0 ? round(($sent / $total) * 100, 1) : 0;
        
        $runningCampaigns[] = [
            'id' => intval($row['campaign_id']),
            'description' => $row['description'],
            'status' => $row['status'],
            'total' => $total,
            'sent' => $sent,
            'failed' => intval($row['failed_emails']),
            'pending' => intval($row['pending_emails']),
            'percent' => $percent
        ];
    }
    
    // ===== DETERMINE STAGE =====
    $hasValidation = ($pendingLists > 0 || $totalEmails > 0);
    $hasCampaigns = (count($runningCampaigns) > 0);
    
    if (!$hasValidation && !$hasCampaigns) {
        echo json_encode([
            "stage" => "idle",
            "message" => "No active operations",
            "validation" => null,
            "campaigns" => []
        ]);
        exit;
    }
    
    // Build response
    $response = [
        "stage" => "active",
        "validation" => null,
        "campaigns" => $runningCampaigns
    ];
    
    // Add validation if active
    if ($hasValidation && $totalEmails > 0) {
        $validationPercent = round(($processedEmails / $totalEmails) * 100, 1);
        $response["validation"] = [
            "total" => $totalEmails,
            "processed" => $processedEmails,
            "percent" => $validationPercent,
            "pending_lists" => $pendingLists
        ];
        
        // Legacy fields for TopProgressBar compatibility
        $response["total"] = $totalEmails;
        $response["percent"] = $validationPercent;
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "error" => "Server error",
        "stage" => "error"
    ]);
}
