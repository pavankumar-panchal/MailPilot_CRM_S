<?php
ini_set('memory_limit', '1024M'); // or more
ini_set('max_execution_time', 300); // seconds

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

    $excludedAccounts = getExcludedAccounts();
    $excludedDomains = getExcludedDomainsWithIPs();

    $batchSize = 100;
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
    $insertStmt = $conn->prepare("INSERT INTO emails (raw_emailid, sp_account, sp_domain, domain_verified, domain_status, validation_response, domain_processed, csv_list_id) VALUES (?, ?, ?, ?, ?, ?, 0, ?)");

    if (($handle = fopen($file, "r")) === false) {
        return ["status" => "error", "message" => "Failed to read CSV file"];
    }

    $conn->begin_transaction();

    while (($data = fgetcsv($handle, 1000, ",")) !== false) {
        if (empty($data[0]))
            continue;

        if (stripos(trim($data[0]), 'email') === 0)
            continue;
        $email = normalizeGmail(trim($data[0]));
        $email = preg_replace('/[^\x20-\x7E]/', '', $email);

        $checkStmt->bind_param("s", $email);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        if ($checkResult->num_rows > 0) {
            $skipped_count++;
            continue;
        }
        if (isset($uniqueEmails[$email])) {
            $skipped_count++;
            continue;
        }
        $uniqueEmails[$email] = true;

        $emailParts = explode("@", $email);
        if (count($emailParts) != 2) {
            $sp_account = '';
            $sp_domain = '';
            $domain_verified = 1;
            $domain_status = 0;
            $validation_response = "Invalid email format";
            $insertStmt->bind_param("ssssisi", $email, $sp_account, $sp_domain, $domain_verified, $domain_status, $validation_response, $campaignListId);
            $insertStmt->execute();
            $invalid_account_count++;
            continue;
        }

        [$sp_account, $sp_domain] = $emailParts;
        $domain_verified = 0;
        $domain_status = 0;
        $validation_response = "Not Verified Yet";

        // Check for existing duplicate in database
        $checkStmt->bind_param("s", $email);
        $checkStmt->execute();

        if ($checkStmt->get_result()->num_rows > 0) {
            $domain_verified = 1;
            $domain_status = 0;
            $validation_response = "Duplicate in database";
            $insertStmt->bind_param("ssssisi", $email, $sp_account, $sp_domain, $domain_verified, $domain_status, $validation_response, $campaignListId);
            $insertStmt->execute();
            $invalid_account_count++;
            continue;
        }

        // Validate account name
        if (!isValidAccountName($sp_account)) {
            $domain_verified = 1;
            $domain_status = 0;
            $validation_response = "Invalid account name";
            $invalid_account_count++;

            $insertStmt->bind_param("ssssisi", $email, $sp_account, $sp_domain, $domain_verified, $domain_status, $validation_response, $campaignListId);
            $insertStmt->execute();
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

        // Insert into emails
        $insertStmt->bind_param("ssssisi", $email, $sp_account, $sp_domain, $domain_verified, $domain_status, $validation_response, $campaignListId);
        $insertStmt->execute();
        $inserted_count++;

        if ($inserted_count % $batchSize === 0) {
            $conn->commit();
            $conn->begin_transaction();
        }
    }

    $conn->commit();
    fclose($handle);

    // Update csv_list with totals
    $updateListStmt = $conn->prepare("UPDATE csv_list SET 
                                    total_emails = (SELECT COUNT(*) FROM emails WHERE csv_list_id = ?),
                                    valid_count = (SELECT COUNT(*) FROM emails WHERE csv_list_id = ? AND domain_status = 1),
                                    invalid_count = (SELECT COUNT(*) FROM emails WHERE csv_list_id = ? AND domain_status = 0)
                                    WHERE id = ?");
    $updateListStmt->bind_param("iiii", $campaignListId, $campaignListId, $campaignListId, $campaignListId);
    $updateListStmt->execute();

    return [
        "status" => "success",
        "message" => "CSV processed successfully",
        "inserted" => $inserted_count,
        "excluded" => $excluded_count,
        "invalid_accounts" => $invalid_account_count,
        "csv_list_id" => $campaignListId,
        "total_emails" => $inserted_count + $invalid_account_count + $excluded_count,
        "valid" => $excluded_count, // Excluded are considered valid in this context
        "invalid" => $invalid_account_count
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