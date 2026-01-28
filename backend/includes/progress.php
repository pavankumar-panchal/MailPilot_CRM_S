<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

// CRITICAL: Load security helpers FIRST (before session starts) to handle CORS
require_once __DIR__ . '/security_helpers.php';

// Handle CORS BEFORE session starts
handleCors();

// Set security headers
setSecurityHeaders();

// Now start session and load other dependencies
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/session_config.php';
require_once __DIR__ . '/user_filtering.php';
require_once __DIR__ . '/auth_helper.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Get current user for filtering (don't require auth for progress monitoring)
$currentUser = getAuthenticatedUser();
$userFilterEmails = '';
$userFilterCsvList = '';
if ($currentUser && !isAdmin()) {
    $userId = intval($currentUser['id']);
    $userFilterEmails = " AND e.user_id = $userId";
    $userFilterCsvList = " AND user_id = $userId";
}

try {
    // Check if any emails are in progress (not fully processed)
    $inProgressResult = $conn->query("
        SELECT COUNT(*) as cnt 
        FROM emails e
        JOIN csv_list c ON e.csv_list_id = c.id
        WHERE c.status != 'completed' 
        $userFilterEmails
        AND e.domain_processed = 0
    ");
    $inProgress = $inProgressResult->fetch_assoc()['cnt'] ?? 0;

    if ($inProgress == 0) {
        // Check if ALL csv_lists are marked as completed
        $allCompletedResult = $conn->query("SELECT COUNT(*) as pending FROM csv_list WHERE status != 'completed' $userFilterCsvList");
        $pendingLists = $allCompletedResult->fetch_assoc()['pending'] ?? 0;

        if ($pendingLists == 0) {
            echo json_encode([
                "stage" => "completed",
                "total" => 0,
                "processed" => 0,
                "percent" => 100,
                "message" => "All validation completed"
            ]);
        } else {
            echo json_encode([
                "stage" => "idle",
                "message" => "No validation in progress"
            ]);
        }
        exit;
    }

    // Get total emails and processed count (domain + SMTP validation in single pass)
    $totalResult = $conn->query("
        SELECT COUNT(*) as total 
        FROM emails e
        JOIN csv_list c ON e.csv_list_id = c.id
        WHERE c.status != 'completed'
        $userFilterEmails
    ");
    $total = $totalResult->fetch_assoc()['total'] ?? 0;

    $processedResult = $conn->query("
        SELECT COUNT(*) as processed 
        FROM emails e
        JOIN csv_list c ON e.csv_list_id = c.id
        WHERE e.domain_processed = 1 AND c.status != 'completed'
        $userFilterEmails
    ");
    $processed = $processedResult->fetch_assoc()['processed'] ?? 0;

    $percent = $total > 0 ? round(($processed / $total) * 100, 1) : 0;

    // Check for completion
    $allCompletedResult = $conn->query("SELECT COUNT(*) as pending FROM csv_list WHERE status != 'completed' $userFilterCsvList");
    $pendingLists = $allCompletedResult->fetch_assoc()['pending'] ?? 0;

    if ($pendingLists == 0 && $processed >= $total && $total > 0) {
        echo json_encode([
            "stage" => "completed",
            "total" => (int) $total,
            "processed" => (int) $processed,
            "percent" => 100,
            "message" => "All validation completed"
        ]);
        exit;
    }

    echo json_encode([
        "stage" => "validation",
        "total" => (int) $total,
        "processed" => (int) $processed,
        "percent" => $percent,
        "message" => "Email validation in progress ($processed/$total)"
    ]);
} catch (Exception $e) {
    echo json_encode([
        "error" => "Query failed",
        "details" => $e->getMessage()
    ]);
}
