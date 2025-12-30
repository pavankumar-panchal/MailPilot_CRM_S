<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

require_once __DIR__ . '/../config/db.php';

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

        // De-duplicate within the uploaded file (case-insensitive)
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

    // Filter out emails that already exist in DB to avoid duplicates across uploads
    $existingSet = [];
    $chunkSize = 1000;
    $allRaw = array_column($emails, 'raw_emailid');
    $emailChunks = array_chunk($allRaw, $chunkSize);

    foreach ($emailChunks as $chunk) {
        $placeholders = implode(',', array_fill(0, count($chunk), '?'));
        $types = str_repeat('s', count($chunk));
        $stmt = $conn->prepare("SELECT raw_emailid FROM emails WHERE raw_emailid IN ($placeholders)");
        if ($stmt) {
            $stmt->bind_param($types, ...$chunk);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) { $existingSet[strtolower($r['raw_emailid'])] = true; }
            $stmt->close();
        }
    }

    $filtered = [];
    foreach ($emails as $row) {
        $e = strtolower($row['raw_emailid']);
        if (isset($existingSet[$e])) {
            $duplicateCount++;
            $rejectedEmails[] = [
                'email' => $row['raw_emailid'],
                'reason' => 'Already exists in database',
                'line' => 'N/A'
            ];
            continue;
        }
        $filtered[] = $row;
    }

    $emails = $filtered;
    $validCount = count($emails);

    if ($validCount === 0) {
        throw new Exception('All emails are duplicates or invalid; nothing to insert.');
    }

    // Begin transaction
    $conn->begin_transaction();

    try {
        // Insert into csv_list table
        $stmt = $conn->prepare("
            INSERT INTO csv_list (list_name, file_name, total_emails, valid_count, invalid_count, status, created_at)
            VALUES (?, ?, ?, ?, ?, 'pending', NOW())
        ");
        
    $totalEmails = $validCount + $invalidCount + $duplicateCount;
        $stmt->bind_param('ssiii', $listName, $fileName, $totalEmails, $validCount, $invalidCount);
        $stmt->execute();
        $csvListId = $conn->insert_id;
        $stmt->close();

        // Prepare bulk insert for emails
        $batchSize = 1000;
        $batches = array_chunk($emails, $batchSize);

        foreach ($batches as $batch) {
            $values = [];
            $params = [];
            
            foreach ($batch as $email) {
                $values[] = "(?, ?, ?, 0, 0, NULL, 0, ?, 'pending', ?)";
                $params[] = $email['raw_emailid'];
                $params[] = $email['sp_account'];
                $params[] = $email['sp_domain'];
                $params[] = $csvListId;
                $params[] = $email['worker_id'];
            }

            $sql = "INSERT INTO emails 
                (raw_emailid, sp_account, sp_domain, domain_verified, domain_status, validation_response, domain_processed, csv_list_id, validation_status, worker_id)
                VALUES " . implode(', ', $values);

            $stmt = $conn->prepare($sql);
            
            // Create type string (sssi = 3 strings + 1 int for csv_list_id + 1 int for worker_id)
            $types = str_repeat('sssii', count($batch));
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

        // Start domain verification in background
        $verifyScript = __DIR__ . '/../includes/verify_domain.php';
        if (file_exists($verifyScript)) {
            $cmd = 'php ' . escapeshellarg($verifyScript) . ' > /dev/null 2>&1 &';
            exec($cmd);
        }

        $response = [
            'status' => 'success',
            'message' => "Successfully uploaded {$validCount} valid emails. Domain verification started.",
            'data' => [
                'csv_list_id' => $csvListId,
                'valid_count' => $validCount,
                'invalid_count' => $invalidCount,
                'duplicate_count' => $duplicateCount,
                'rejected_count' => count($rejectedEmails),
                'total_emails' => $totalEmails,
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
