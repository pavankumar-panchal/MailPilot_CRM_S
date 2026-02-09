<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../includes/security_helpers.php';

// Set security headers
setSecurityHeaders();

// Handle CORS securely
handleCors();
require_once __DIR__ . '/../includes/user_filtering.php';

// Ensure user_id columns exist
ensureUserIdColumns($conn);

// Get current user
$currentUser = getCurrentUser();
$user_id = $currentUser ? $currentUser['id'] : null;

error_log("email_processor.php - User: " . ($currentUser ? json_encode($currentUser) : 'NOT LOGGED IN'));

// Require authentication
if (!$user_id) {
    error_log("email_processor.php - Authentication failed, no user_id");
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized. Please log in.']);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

try {
    // Validate required fields
    if (!isset($_FILES['csv_file']) || !isset($_POST['list_name']) || !isset($_POST['file_name'])) {
        throw new Exception('Missing required fields: csv_file, list_name, or file_name');
    }

    // Validate file upload
    if ($_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File was partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'PHP extension stopped upload',
        ];
        $errorMsg = $errorMessages[$_FILES['csv_file']['error']] ?? 'Unknown upload error';
        throw new Exception($errorMsg);
    }

    // Validate file type
    $fileType = mime_content_type($_FILES['csv_file']['tmp_name']);
    $allowedTypes = ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel'];
    if (!in_array($fileType, $allowedTypes)) {
        throw new Exception('Invalid file type. Only CSV files are allowed.');
    }

    // Validate file size (5MB max)
    $maxSize = 5 * 1024 * 1024;
    if ($_FILES['csv_file']['size'] > $maxSize) {
        throw new Exception('File size exceeds 5MB limit');
    }

    $listName = trim($_POST['list_name']);
    $fileName = trim($_POST['file_name']);

    if (empty($listName) || empty($fileName)) {
        throw new Exception('List name and file name cannot be empty');
    }

    // Read CSV file
    $csvFile = $_FILES['csv_file']['tmp_name'];
    $handle = fopen($csvFile, 'r');
    
    if (!$handle) {
        throw new Exception('Failed to open CSV file');
    }

    // Skip header row
    $header = fgetcsv($handle);
    
    // Load excluded domains and accounts
    $excludedDomains = [];
    $excludedAccounts = [];
    
    $result = $conn->query("SELECT domain FROM exclude_domains");
    while ($row = $result->fetch_assoc()) {
        $excludedDomains[] = strtolower(trim($row['domain']));
    }
    
    $result = $conn->query("SELECT account FROM exclude_accounts");
    while ($row = $result->fetch_assoc()) {
        $excludedAccounts[] = strtolower(trim($row['account']));
    }
    
    // Get only active workers for distribution
    $result = $conn->query("SELECT id FROM workers WHERE is_active = 1 ORDER BY id");
    $workerIds = [];
    while ($row = $result->fetch_assoc()) {
        $workerIds[] = (int)$row['id'];
    }
    $workerCount = count($workerIds);
    
    if ($workerCount === 0) {
        throw new Exception('No active workers available. Please activate at least one worker first.');
    }
    
    // Parse emails
    $emails = [];
    $validCount = 0;
    $invalidCount = 0;
    $duplicateCount = 0;
    $lineNumber = 1;
    $emailIndex = 0; // For worker distribution
    $seen = [];
    $rejectedEmails = []; // Track rejected emails with reasons

    while (($data = fgetcsv($handle)) !== false) {
        $lineNumber++;
        
        if (empty($data) || !isset($data[0])) {
            continue;
        }

        $email = trim($data[0]);
        
        // Skip empty lines
        if (empty($email)) {
            continue;
        }

        // Basic email validation
        $originalEmail = $email;
        $email = strtolower($email);
        $email = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $email); // Remove non-printable characters
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $invalidCount++;
            $rejectedEmails[] = [
                'email' => $originalEmail,
                'reason' => 'Invalid email format',
                'line' => $lineNumber
            ];
            continue;
        }

        // Extract domain
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            $invalidCount++;
            $rejectedEmails[] = [
                'email' => $originalEmail,
                'reason' => 'Invalid email structure',
                'line' => $lineNumber
            ];
            continue;
        }

        $account = $parts[0];
        $domain = $parts[1];

        // Check if domain is excluded
        if (in_array($domain, $excludedDomains)) {
            $invalidCount++;
            $rejectedEmails[] = [
                'email' => $email,
                'reason' => 'Excluded domain',
                'line' => $lineNumber
            ];
            continue;
        }
        
        // Check if account is excluded
        if (in_array($account, $excludedAccounts)) {
            $invalidCount++;
            $rejectedEmails[] = [
                'email' => $email,
                'reason' => 'Excluded account',
                'line' => $lineNumber
            ];
            continue;
        }

        // De-duplicate within the uploaded file only (case-insensitive)
        // This ensures uniqueness per file, not across the entire database
        if (isset($seen[$email])) {
            $duplicateCount++;
            $rejectedEmails[] = [
                'email' => $email,
                'reason' => 'Duplicate in file',
                'line' => $lineNumber
            ];
            continue;
        }
        $seen[$email] = true;

        // Assign worker ID using round-robin distribution
        $workerId = $workerIds[$emailIndex % $workerCount];
        $emailIndex++;

        $emails[] = [
            'raw_emailid' => $email,
            'sp_account' => $account,
            'sp_domain' => $domain,
            'worker_id' => $workerId,
        ];
    }

    fclose($handle);

    if (empty($emails)) {
        throw new Exception('No valid emails found in CSV file');
    }

    // No database duplicate checking - only in-file duplicates are filtered
    // This allows the same email to be uploaded in different CSV files for different campaigns
    $validCount = count($emails);

    if ($validCount === 0) {
        throw new Exception('All emails are duplicates or invalid; nothing to insert.');
    }

    // Begin transaction
    $conn->begin_transaction();

    try {
        error_log("email_processor.php - Inserting data for user_id: $user_id, valid emails: $validCount");
        
        // Insert into csv_list table
        $stmt = $conn->prepare("
            INSERT INTO csv_list (list_name, file_name, total_emails, valid_count, invalid_count, status, created_at, user_id)
            VALUES (?, ?, ?, ?, ?, 'pending', NOW(), ?)
        ");
        
        // total_emails = only emails actually inserted (after removing duplicates)
        // This ensures accurate valid/invalid counts during verification
        $totalEmailsInserted = $validCount;
        $stmt->bind_param('ssiiii', $listName, $fileName, $totalEmailsInserted, $validCount, $invalidCount, $user_id);
        $stmt->execute();
        $csvListId = $conn->insert_id;
        $stmt->close();
        
        error_log("email_processor.php - Created csv_list with ID: $csvListId for user_id: $user_id");

        // Prepare bulk insert for emails
        $batchSize = 1000;
        $batches = array_chunk($emails, $batchSize);

        foreach ($batches as $batch) {
            $values = [];
            $params = [];
            
            foreach ($batch as $email) {
                $values[] = "(?, ?, ?, 0, 0, NULL, 0, ?, 'pending', ?, ?)";
                $params[] = $email['raw_emailid'];
                $params[] = $email['sp_account'];
                $params[] = $email['sp_domain'];
                $params[] = $csvListId;
                $params[] = $email['worker_id'];
                $params[] = $user_id;
            }

            $sql = "INSERT INTO emails 
                (raw_emailid, sp_account, sp_domain, domain_verified, domain_status, validation_response, domain_processed, csv_list_id, validation_status, worker_id, user_id)
                VALUES " . implode(', ', $values);

            $stmt = $conn->prepare($sql);
            
            // Create type string (sss = 3 strings, ii = 2 ints for csv_list_id and worker_id, i = 1 int for user_id)
            $types = str_repeat('sssiii', count($batch));
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $stmt->close();
        }

        // Update csv_list status to running
        $stmt = $conn->prepare("UPDATE csv_list SET status = 'running' WHERE id = ?");
        $stmt->bind_param('i', $csvListId);
        $stmt->execute();
        $stmt->close();

        // Commit transaction
        $conn->commit();

        // Get server IP address
        $serverIp = $_SERVER['SERVER_ADDR'] ?? ($_SERVER['LOCAL_ADDR'] ?? gethostbyname(gethostname()));

        // Get newly created csv_list details
        $listStmt = $conn->prepare("SELECT id, list_name, file_name, total_emails, valid_count, invalid_count, status, created_at FROM csv_list WHERE id = ?");
        $listStmt->bind_param('i', $csvListId);
        $listStmt->execute();
        $listResult = $listStmt->get_result();
        $listData = $listResult->fetch_assoc();
        $listStmt->close();

        $response = [
            'status' => 'success',
            'success' => true,
            'message' => "Successfully uploaded {$validCount} valid emails.",
            'data' => [
                'csv_list_id' => $csvListId,
                'valid_count' => $validCount,
                'invalid_count' => $invalidCount,
                'duplicate_count' => $duplicateCount,
                'rejected_count' => count($rejectedEmails),
                'total_emails' => $validCount,  // Only emails actually inserted
                'total_in_file' => $validCount + $invalidCount + $duplicateCount,  // Original file count
                'server_ip' => $serverIp,
                'list_details' => $listData
            ]
        ];
        
        // Include rejected emails data if exists (don't save to file)
        if (!empty($rejectedEmails)) {
            $response['data']['rejected_emails'] = $rejectedEmails;
        }
        
        echo json_encode($response);

    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

$conn->close();
