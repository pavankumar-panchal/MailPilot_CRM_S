<?php
ini_set('memory_limit', '2048M');
ini_set('max_execution_time', 900);

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, DELETE");
header("Access-Control-Allow-Headers: Content-Type");

require_once __DIR__ . '/../config/db.php';

// Clear any previous output
if (ob_get_level() > 0) {
    ob_end_clean();
}
ob_start();

// Set error reporting to avoid warnings in output
error_reporting(0);

if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "Database connection failed: " . $conn->connect_error]));
}

// Get the request method
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'POST':
            $response = handlePostRequest();
            break;
        case 'GET':
            $response = handleGetRequest();
            break;
        case 'DELETE':
            $response = handleDeleteRequest();
            break;
        default:
            $response = ["status" => "error", "message" => "Method not allowed"];
    }

    // Ensure no output has been sent before this
    if (ob_get_length() > 0) {
        ob_clean();
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Exception $e) {
    // Clean any output buffer
    ob_clean();
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}

// Close connection and flush buffer
$conn->close();
ob_end_flush();
exit;

function getExcludedAccounts()
{
    global $conn;
    $result = $conn->query("SELECT account FROM exclude_accounts");
    $excludedAccounts = [];
    while ($row = $result->fetch_assoc()) {
        $excludedAccounts[] = strtolower(trim($row['account']));
    }
    return $excludedAccounts;
}

function getExcludedDomainsWithIPs()
{
    global $conn;
    $result = $conn->query("SELECT domain, ip_address FROM exclude_domains");
    $excludedDomains = [];
    while ($row = $result->fetch_assoc()) {
        $domain = strtolower(trim($row['domain']));
        $ip = trim($row['ip_address']);
        if (!empty($domain)) {
            $excludedDomains[$domain] = $ip;
        }
    }
    return $excludedDomains;
}

function isValidAccountName($account)
{
    // 1. Basic pattern match
    if (!preg_match('/^[a-z0-9](?!.*[._-]{2})[a-z0-9._-]*[a-z0-9]$/i', $account)) {
        return false;
    }

    // 2. Length check
    if (strlen($account) < 1 || strlen($account) > 64) {
        return false;
    }

    // 3. Not all digits
    if (preg_match('/^[0-9]+$/', $account)) {
        return false;
    }

    return true;
}

function normalizeGmail($email)
{
    $parts = explode('@', strtolower(trim($email)));
    if (count($parts) !== 2 || $parts[1] !== 'gmail.com') {
        return $email;
    }

    $account = $parts[0];
    // Remove dots and anything after +
    $account = str_replace('.', '', $account);
    $account = explode('+', $account)[0];

    return $account . '@gmail.com';
}

function handlePostRequest()
{
    global $conn;

    if (!isset($_FILES['csv_file'])) {
        return ["status" => "error", "message" => "No file uploaded"];
    }

    $file = $_FILES['csv_file']['tmp_name'];
    if (!file_exists($file)) {
        return ["status" => "error", "message" => "File upload failed"];
    }

    // Load all excluded data once
    $excludedAccounts = getExcludedAccounts();
    $excludedDomains = getExcludedDomainsWithIPs();

    $batchSize = 5000; // Increased batch size
    $skipped_count = 0;
    $inserted_count = 0;
    $excluded_count = 0;
    $invalid_account_count = 0;
    $uniqueEmails = [];

    $listName = $_POST['list_name'];
    $fileName = $_POST['file_name'];

    // Insert a new csv_list row
    $insertListStmt = $conn->prepare("INSERT INTO csv_list (list_name, file_name) VALUES (?, ?)");
    $insertListStmt->bind_param("ss", $listName, $fileName);
    $insertListStmt->execute();
    $campaignListId = $conn->insert_id;

    // Prepare statements
    $checkStmt = $conn->prepare("SELECT id FROM emails WHERE raw_emailid = ? LIMIT 1");

    // Disable autocommit for bulk insert
    $conn->autocommit(FALSE);

    // Get all existing emails in one query (for small-medium datasets)
    $existingEmails = [];
    $result = $conn->query("SELECT raw_emailid FROM emails");
    while ($row = $result->fetch_assoc()) {
        $existingEmails[strtolower($row['raw_emailid'])] = true;
    }

    // Prepare bulk insert statement
    $bulkInsertValues = [];
    $bulkInsertParams = [];
    $bulkInsertQuery = "INSERT INTO emails (raw_emailid, sp_account, sp_domain, domain_verified, domain_status, validation_response, domain_processed, csv_list_id) VALUES ";

    if (($handle = fopen($file, "r")) === false) {
        return ["status" => "error", "message" => "Failed to read CSV file"];
    }

    // Read and process the file in chunks
    while (($data = fgetcsv($handle, 1000, ",")) !== false) {
        if (empty($data[0])) {
            continue;
        }

        if (stripos(trim($data[0]), 'email') === 0) {
            continue;
        }

        $email = normalizeGmail(trim($data[0]));
        $email = preg_replace('/[^\x20-\x7E]/', '', $email);
        $emailKey = strtolower($email);

        // Skip duplicates
        if (isset($uniqueEmails[$emailKey]) || isset($existingEmails[$emailKey])) {
            $skipped_count++;
            continue;
        }
        $uniqueEmails[$emailKey] = true;

        $emailParts = explode("@", $email);
        if (count($emailParts) != 2) {
            $bulkInsertValues[] = "(" . implode(",", array_fill(0, 8, "?")) . ")";
            $bulkInsertParams[] = $email;
            $bulkInsertParams[] = '';
            $bulkInsertParams[] = '';
            $bulkInsertParams[] = 1; // domain_verified
            $bulkInsertParams[] = 0; // domain_status
            $bulkInsertParams[] = "Invalid email format";
            $bulkInsertParams[] = 0; // domain_processed
            $bulkInsertParams[] = $campaignListId;
            $invalid_account_count++;
            continue;
        }

        [$sp_account, $sp_domain] = $emailParts;
        $domain_verified = 0;
        $domain_status = 0;
        $validation_response = "Not Verified Yet";

        // Validate account name
        if (!isValidAccountName($sp_account)) {
            $bulkInsertValues[] = "(" . implode(",", array_fill(0, 8, "?")) . ")";
            $bulkInsertParams[] = $email;
            $bulkInsertParams[] = $sp_account;
            $bulkInsertParams[] = $sp_domain;
            $bulkInsertParams[] = 1; // domain_verified
            $bulkInsertParams[] = 0; // domain_status
            $bulkInsertParams[] = "Invalid account name";
            $bulkInsertParams[] = 0; // domain_processed
            $bulkInsertParams[] = $campaignListId;
            $invalid_account_count++;
            continue;
        }

        // Exclusion check
        if (in_array(strtolower($sp_account), $excludedAccounts)) {
            $domain_verified = 1;
            $domain_status = 1;
            $validation_response = "Excluded: Account";
            $excluded_count++;
        } elseif (array_key_exists(strtolower($sp_domain), $excludedDomains)) {
            $domain_verified = 1;
            $domain_status = 1;
            $validation_response = $excludedDomains[strtolower($sp_domain)];
            $excluded_count++;
        }

        $bulkInsertValues[] = "(" . implode(",", array_fill(0, 8, "?")) . ")";
        $bulkInsertParams[] = $email;
        $bulkInsertParams[] = $sp_account;
        $bulkInsertParams[] = $sp_domain;
        $bulkInsertParams[] = $domain_verified;
        $bulkInsertParams[] = $domain_status;
        $bulkInsertParams[] = $validation_response;
        $bulkInsertParams[] = 0; // domain_processed
        $bulkInsertParams[] = $campaignListId;
        $inserted_count++;

        // Execute batch when reached batch size
        if (count($bulkInsertValues) >= $batchSize) {
            $query = $bulkInsertQuery . implode(",", $bulkInsertValues);
            $stmt = $conn->prepare($query);

            // Dynamically bind parameters
            $types = str_repeat("ssssisii", count($bulkInsertValues));
            $stmt->bind_param($types, ...$bulkInsertParams);

            $stmt->execute();
            $stmt->close();

            // Reset for next batch
            $bulkInsertValues = [];
            $bulkInsertParams = [];
        }
    }

    // Insert remaining records
    if (!empty($bulkInsertValues)) {
        $query = $bulkInsertQuery . implode(",", $bulkInsertValues);
        $stmt = $conn->prepare($query);

        $types = str_repeat("ssssisii", count($bulkInsertValues));
        $stmt->bind_param($types, ...$bulkInsertParams);

        $stmt->execute();
        $stmt->close();
    }

    // Commit transaction
    $conn->commit();
    $conn->autocommit(TRUE);
    fclose($handle);

    // Update csv_list with totals using direct counts rather than subqueries
    $total = $inserted_count + $invalid_account_count + $excluded_count;
    $valid = $excluded_count; // Excluded are considered valid in this context
    $invalid = $invalid_account_count;

    $updateListStmt = $conn->prepare("UPDATE csv_list SET 
                                    total_emails = ?,
                                    valid_count = ?,
                                    invalid_count = ?
                                    WHERE id = ?");
    $updateListStmt->bind_param("iiii", $total, $valid, $invalid, $campaignListId);
    $updateListStmt->execute();

    // Assign emails to workers in equal batches
    $workers = getWorkers($conn);
    $workerCount = count($workers);

    if ($workerCount > 0) {
        $result = $conn->query("SELECT id FROM emails WHERE csv_list_id = $campaignListId AND worker_id IS NULL");
        $emails = [];
        while ($row = $result->fetch_assoc()) {
            $emails[] = $row['id'];
        }

        $totalEmails = count($emails);
        $batchSize = ceil($totalEmails / $workerCount);

        $emailIndex = 0;
        foreach ($workers as $worker) {
            $assignedEmails = array_slice($emails, $emailIndex, $batchSize);
            if (count($assignedEmails) > 0) {
                $ids = implode(',', $assignedEmails);
                $conn->query("UPDATE emails SET worker_id = {$worker['id']} WHERE id IN ($ids)");
            }
            $emailIndex += $batchSize;
        }
    }

    return [
        "status" => "success",
        "message" => "CSV processed successfully",
        "inserted" => $inserted_count,
        "excluded" => $excluded_count,
        "invalid_accounts" => $invalid_account_count,
        "csv_list_id" => $campaignListId,
        "total_emails" => $total,
        "valid" => $valid,
        "invalid" => $invalid
    ];
}

function handleGetRequest()
{
    global $conn;

    $stmt = $conn->prepare("SELECT id, raw_emailid, sp_account, sp_domain, 
                            COALESCE(domain_verified, 0) AS domain_verified, 
                            COALESCE(domain_status, 0) AS domain_status, 
                            COALESCE(validation_response, 'Not Verified Yet') AS validation_response
                            FROM emails");
    $stmt->execute();
    $result = $stmt->get_result();

    $emails = [];
    while ($row = $result->fetch_assoc()) {
        $emails[] = $row;
    }

    return $emails;
}

function handleDeleteRequest()
{
    global $conn;

    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) {
        return ["status" => "error", "message" => "Invalid ID"];
    }

    $stmt = $conn->prepare("DELETE FROM emails WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        return ["status" => "success", "message" => "Email deleted"];
    } else {
        return ["status" => "error", "message" => "Deletion failed"];
    }
}

function getWorkers($conn)
{
    $result = $conn->query("SELECT id, workername, ip FROM workers");
    $workers = [];
    while ($row = $result->fetch_assoc()) {
        $workers[] = $row;
    }
    return $workers;
}
