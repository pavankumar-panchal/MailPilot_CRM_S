<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/session_config.php';
require_once __DIR__ . '/security_helpers.php';
require_once __DIR__ . '/user_filtering.php';
require_once __DIR__ . '/auth_helper.php';

// Production-safe error handling
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output
ini_set('log_errors', 1); // Log errors instead
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
set_time_limit(0);

header('Content-Type: application/json');

// Set security headers (includes no-cache)
setSecurityHeaders();

// Handle CORS securely
handleCors();

// Custom error handler to return JSON
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Server error: ' . $errstr,
        'file' => basename($errfile),
        'line' => $errline
    ]);
    exit;
});

// Custom exception handler
set_exception_handler(function($exception) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Exception: ' . $exception->getMessage()
    ]);
    exit;
});

try {
    // 1. Authenticate User
    $currentUser = requireAuth();
    $userId = $currentUser['id'];

    // 2. Validate Input
    $input = getValidatedJsonInput();
    if (!$input || !isset($input['list_id'])) {
        throw new Exception('Missing list_id');
    }
    
    $listId = validateInteger($input['list_id']);

    // 3. User Authorization for this List
    if (!isAdmin()) {
        $stmt = $conn->prepare("SELECT user_id FROM csv_list WHERE id = ?");
        $stmt->bind_param("i", $listId);
        $stmt->execute();
        $res = $stmt->get_result();
        $list = $res->fetch_assoc();
        
        if (!$list || !canAccessRecord($list['user_id'])) {
            throw new Exception("Access denied to list ID: $listId");
        }
    }

    // 4. NON-BLOCKING ACTION: Mark emails for retry
    // Instead of processing them here (which freezes the UI),
    // we just reset their status so the background worker picks them up.
    
    // Reset 'processing' flag and set validation_status to 'pending' 
    // for all emails in this list that are currently 'retryable' (domain_status=2)
    // or failed.
    
    // We only retry items that are effectively "failed" or "unknown" but not "valid" or hard "bounces" if desired.
    // Assuming domain_status=2 means "Soft Bounce / Retryable".
    
    $updateStmt = $conn->prepare("
        UPDATE emails 
        SET 
            domain_processed = 0, 
            validation_status = 'pending', 
            validation_response = NULL,
            smtp_log = NULL
        WHERE 
            csv_list_id = ? 
            AND domain_status = 2 -- Retryable status
    ");
    
    $updateStmt->bind_param("i", $listId);
    $updateStmt->execute();
    $affected = $updateStmt->affected_rows;
    
    // Also update the main list status to 'running' so the cron picks it up
    if ($affected > 0) {
        $stmtList = $conn->prepare("UPDATE csv_list SET status = 'running' WHERE id = ?");
        $stmtList->bind_param("i", $listId);
        $stmtList->execute();
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Retry process started in background.',
        'emails_queued' => $affected
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

$conn->close();
exit;