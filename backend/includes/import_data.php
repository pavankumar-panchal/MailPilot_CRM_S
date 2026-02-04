<?php
/**
 * Web interface to import Excel/CSV files
 * Upload Excel file and it will be imported to database
 */

// Register shutdown function to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (ob_get_level() > 0) ob_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Fatal error: ' . $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line']
        ]);
    }
});

// Error handling
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/import_errors.log');
error_reporting(E_ALL);
ini_set('display_errors', 0);
set_time_limit(300);
ini_set('memory_limit', '512M');

// Start output buffering
ob_start();

try {
    require_once __DIR__ . '/../config/db.php';
    require_once __DIR__ . '/session_config.php';
    require_once __DIR__ . '/security_helpers.php';
    require_once __DIR__ . '/user_filtering.php';
    require_once __DIR__ . '/auth_helper.php';
} catch (Exception $e) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Set security headers
setSecurityHeaders();

// Handle CORS securely
handleCors();

// Ensure user_id columns exist
ensureUserIdColumns($conn);

// Get current user (supports both session and token auth)
$currentUser = getAuthenticatedUser();

error_log("import_data.php - Current user: " . ($currentUser ? json_encode(['id' => $currentUser['id'], 'email' => $currentUser['email'], 'role' => $currentUser['role']]) : 'NULL'));

// Require authentication for all operations except OPTIONS
if ($_SERVER['REQUEST_METHOD'] !== 'OPTIONS' && !$currentUser) {
    ob_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Please log in.']);
    exit;
}
$user_id = $currentUser ? $currentUser['id'] : null;

$action = $_GET['action'] ?? 'list';

// List all import batches (campaigns only - imported_recipients table)
if ($action === 'list') {
    $userFilter = getUserFilterWhere('imported_recipients');
    $whereClause = $userFilter ? $userFilter : 'WHERE 1=1';
    
    error_log("Import list - User ID: " . ($user_id ?: 'NULL'));
    error_log("Import list - Filter: " . $userFilter);
    error_log("Import list - Where clause: " . $whereClause);
    
    // Fetch from imported_recipients table only (for campaign data with merge fields)
    $sql = "SELECT 
        import_batch_id,
        import_filename,
        COUNT(*) as record_count,
        COUNT(CASE WHEN is_active = 1 THEN 1 END) as valid_count,
        COUNT(CASE WHEN is_active = 0 THEN 1 END) as invalid_count,
        MIN(imported_at) as imported_at,
        user_id as batch_user_id,
        GROUP_CONCAT(DISTINCT Emails ORDER BY Emails SEPARATOR ', ') as sample_emails,
        'completed' as status
    FROM imported_recipients
    " . $whereClause . "
    GROUP BY import_batch_id, import_filename, user_id
    ORDER BY MIN(imported_at) DESC";
    
    error_log("Import list SQL: " . $sql);
    
    $result = $conn->query($sql);
    if (!$result) {
        error_log("Import list query error: " . $conn->error);
        echo json_encode(['success' => false, 'error' => 'Database query failed: ' . $conn->error]);
        exit;
    }
    
    $batches = [];
    
    while ($row = $result->fetch_assoc()) {
        // Limit sample emails to first 3
        if ($row['sample_emails']) {
            $emails = explode(', ', $row['sample_emails']);
            $row['sample_emails'] = implode(', ', array_slice($emails, 0, 3));
            if (count($emails) > 3) {
                $row['sample_emails'] .= '...';
            }
        }
        // Set total_emails for compatibility
        $row['total_emails'] = $row['record_count'];
        
        error_log("Batch found: " . $row['import_batch_id'] . " - File: " . $row['import_filename'] . " - User: " . $row['batch_user_id'] . " - Count: " . $row['record_count'] . " - Valid: " . $row['valid_count'] . " - Status: " . $row['status']);
        unset($row['batch_user_id']); // Don't send to frontend
        $batches[] = $row;
    }
    
    error_log("Import list - Total batches returned: " . count($batches));
    echo json_encode(['success' => true, 'batches' => $batches]);
    exit;
}

// Get recipients for a specific batch
if ($action === 'get_batch') {
    $batchId = isset($_GET['batch_id']) ? intval($_GET['batch_id']) : 0;
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 50;
    $offset = ($page - 1) * $limit;
    $filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
    
    if (!$batchId) {
        echo json_encode(['success' => false, 'error' => 'Batch ID is required']);
        exit;
    }
    
    // Build WHERE clause with user filtering
    $userFilterAnd = getUserFilterAnd('emails');
    $whereClause = "csv_list_id = ? $userFilterAnd";
    
    // Add filter conditions
    if ($filter === 'valid') {
        $whereClause .= " AND domain_status = 1";
    } elseif ($filter === 'invalid') {
        $whereClause .= " AND domain_status = 0 AND (validation_response IS NULL OR (validation_response NOT LIKE '%timeout%' AND validation_response NOT LIKE '%Connection refused%' AND validation_response NOT LIKE '%failed to connect%'))";
    } elseif ($filter === 'timeout') {
        $whereClause .= " AND (validation_response LIKE '%timeout%' OR validation_response LIKE '%Connection refused%' OR validation_response LIKE '%failed to connect%')";
    }
    
    // Get total count
    $countStmt = $conn->prepare("SELECT COUNT(*) as total FROM emails WHERE $whereClause");
    $countStmt->bind_param("i", $batchId);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $total = $countResult->fetch_assoc()['total'];
    $countStmt->close();
    
    // Get paginated data
    $stmt = $conn->prepare("
        SELECT id, raw_emailid as email, sp_account, sp_domain, 
               domain_verified, domain_status, validation_response, 
               validation_status, csv_list_id, user_id 
        FROM emails 
        WHERE $whereClause 
        ORDER BY id ASC 
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("iii", $batchId, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $recipients = [];
    while ($row = $result->fetch_assoc()) {
        $recipients[] = $row;
    }
    $stmt->close();
    
    echo json_encode([
        'success' => true, 
        'data' => $recipients,
        'total' => (int)$total,
        'page' => $page,
        'limit' => $limit
    ]);
    exit;
}

// Delete batch
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $batchId = $_POST['batch_id'] ?? '';
    
    if (!$batchId) {
        echo json_encode(['success' => false, 'error' => 'Batch ID is required']);
        exit;
    }
    
    // Check user access
    $userFilterAnd = getUserFilterAnd();
    
    // Delete emails first
    $stmt = $conn->prepare("DELETE FROM emails WHERE csv_list_id = ? " . $userFilterAnd);
    $stmt->bind_param("i", $batchId);
    $stmt->execute();
    
    // Then delete the csv_list entry
    $stmt = $conn->prepare("DELETE FROM csv_list WHERE id = ? " . $userFilterAnd);
    $stmt->bind_param("i", $batchId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Batch deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to delete batch']);
    }
    
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
