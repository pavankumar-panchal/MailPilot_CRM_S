<?php
/**
 * Import Recipients API
 * Handles importing Excel/CSV files to imported_recipients table
 * This is specifically for campaign recipient imports with data merging
 */

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
set_time_limit(300);
ini_set('memory_limit', '512M');

// Start output buffering to catch any errors
ob_start();

try {
    require_once __DIR__ . '/../includes/session_config.php';
    require_once __DIR__ . '/../includes/security_helpers.php';
    require_once __DIR__ . '/../config/db.php';
    require_once __DIR__ . '/../includes/auth_helper.php';
} catch (Exception $e) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Failed to load dependencies: ' . $e->getMessage()]);
    exit;
}

// Set security headers
setSecurityHeaders();
handleCors();

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Require authentication
$currentUser = requireAuth();
$user_id = $currentUser['id'];

// Clear output buffer and set JSON header
ob_clean();
header('Content-Type: application/json');

// Only accept POST for import
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

// Check if file was uploaded
if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error']);
    exit();
}

$file = $_FILES['csv_file'];
$filename = basename($file['name']);
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

// Validate file extension
if (!in_array($ext, ['csv', 'xlsx', 'xls'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid file format. Only CSV and Excel files are allowed.']);
    exit();
}

// Generate unique batch ID
$batchId = 'BATCH_' . date('Ymd_His') . '_' . uniqid();

/**
 * Read CSV file
 */
function readCsv($filepath) {
    $rows = [];
    if (($handle = fopen($filepath, "r")) !== FALSE) {
        while (($data = fgetcsv($handle, 10000, ",")) !== FALSE) {
            $rows[] = $data;
        }
        fclose($handle);
    }
    return $rows;
}

/**
 * Simple XLSX reader
 */
function readXlsx($filepath) {
    $zip = new ZipArchive;
    if ($zip->open($filepath) !== TRUE) {
        return false;
    }
    
    // Read shared strings
    $sharedStrings = [];
    $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedStringsXml) {
        $xml = @simplexml_load_string($sharedStringsXml);
        if ($xml) {
            foreach ($xml->si as $val) {
                $sharedStrings[] = (string)$val->t;
            }
        }
    }
    
    // Read first worksheet
    $worksheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    
    if (!$worksheetXml) {
        return false;
    }
    
    $xml = @simplexml_load_string($worksheetXml);
    if (!$xml) {
        return false;
    }
    
    $rows = [];
    foreach ($xml->sheetData->row as $row) {
        $rowData = [];
        $colNum = 0;
        
        foreach ($row->c as $cell) {
            $cellValue = '';
            $cellRef = (string)$cell['r'];
            preg_match('/([A-Z]+)/', $cellRef, $matches);
            $expectedCol = $matches[1] ?? '';
            
            $currentColNum = 0;
            for ($i = 0; $i < strlen($expectedCol); $i++) {
                $currentColNum = $currentColNum * 26 + (ord($expectedCol[$i]) - ord('A') + 1);
            }
            $currentColNum--;
            
            while ($colNum < $currentColNum) {
                $rowData[] = '';
                $colNum++;
            }
            
            if (isset($cell->v)) {
                $cellValue = (string)$cell->v;
                if (isset($cell['t']) && (string)$cell['t'] === 's') {
                    $cellValue = $sharedStrings[(int)$cellValue] ?? '';
                }
            }
            
            $rowData[] = $cellValue;
            $colNum++;
        }
        
        $rows[] = $rowData;
    }
    
    return $rows;
}

try {
    // Read file
    if ($ext === 'xlsx' || $ext === 'xls') {
        $rows = readXlsx($file['tmp_name']);
    } else {
        $rows = readCsv($file['tmp_name']);
    }
    
    if (empty($rows)) {
        throw new Exception('File is empty or could not be read');
    }
    
    // First row is headers
    $headers = array_map('trim', $rows[0]);
    $normalizedHeaders = array_map('strtolower', $headers);
    
    // Find email column (required)
    $emailCol = array_search('email', $normalizedHeaders);
    if ($emailCol === false) {
        $emailCol = array_search('emails', $normalizedHeaders);
    }
    
    if ($emailCol === false) {
        throw new Exception('Email column not found. Please ensure your file has an "Email" or "Emails" column.');
    }
    
    // Column mapping - normalize column names to database fields
    $columnMap = [
        'slno' => 'SlNo',
        'sl.no' => 'SlNo',
        'sl no' => 'SlNo',
        'customerid' => 'CustomerID',
        'customer id' => 'CustomerID',
        'email' => 'Emails',
        'emails' => 'Emails',
        'billedname' => 'BilledName',
        'billed name' => 'BilledName',
        'company' => 'Company',
        'contactperson' => 'ContactPerson',
        'contact person' => 'ContactPerson',
        'groupname' => 'Group Name',
        'group name' => 'Group Name',
        'phone' => 'Phone',
        'cell' => 'Cell',
        'region' => 'Region',
        'branch' => 'Branch',
        'type' => 'Type',
        'category' => 'Category',
        'productgroup' => 'ProductGroup',
        'product group' => 'ProductGroup',
        'lastproduct' => 'LastProduct',
        'last product' => 'LastProduct',
        'usagetype' => 'UsageType',
        'usage type' => 'UsageType',
        'lastyear' => 'LastYear',
        'last year' => 'LastYear',
        'lastlicenses' => 'LastLicenses',
        'last licenses' => 'LastLicenses',
        'lastregdate' => 'LastRegDate',
        'last reg date' => 'LastRegDate',
        'dealername' => 'DealerName',
        'dealer name' => 'DealerName',
        'dealeremail' => 'DealerEmail',
        'dealer email' => 'DealerEmail',
        'dealercell' => 'DealerCell',
        'dealer cell' => 'DealerCell',
        'amount' => 'Amount',
        'price' => 'Price',
        'tax' => 'Tax',
        'netprice' => 'NetPrice',
        'net price' => 'NetPrice',
        'edition' => 'Edition',
        'days' => 'Days',
        'billnumber' => 'BillNumber',
        'bill number' => 'BillNumber',
        'bill_number' => 'BillNumber',
        'billdate' => 'BillDate',
        'bill date' => 'BillDate',
        'bill_date' => 'BillDate',
        'executivename' => 'ExecutiveName',
        'executive name' => 'ExecutiveName',
        'executivecontact' => 'ExecutiveContact',
        'executive contact' => 'ExecutiveContact',
        'address' => 'Address',
        'place' => 'Place',
        'pincode' => 'Pincode',
        'district' => 'District',
        'state' => 'State'
    ];
    
    // Generate unique batch ID
    $batchId = 'BATCH_' . date('Ymd_His') . '_' . substr(md5(uniqid(rand(), true)), 0, 8);
    
    // Prepare insert statement
    $sql = "INSERT INTO imported_recipients (
        import_batch_id, Emails, SlNo, CustomerID, BilledName, Company, ContactPerson, 
        `Group Name`, Phone, Cell, Region, Branch, Type, Category, ProductGroup, LastProduct, 
        UsageType, LastYear, LastLicenses, LastRegDate, DealerName, DealerEmail, DealerCell, 
        Amount, Price, Tax, NetPrice, Edition, Days, BillNumber, BillDate, ExecutiveName, 
        ExecutiveContact, Address, Place, Pincode, District, State, extra_data, import_filename, user_id
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $conn->error);
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    // Process each row
    $imported = 0;
    $skipped = 0;
    $errors = [];
    
    for ($i = 1; $i < count($rows); $i++) {
        $row = $rows[$i];
        
        // Skip empty rows
        if (empty(array_filter($row))) {
            continue;
        }
        
        // Initialize all fields
        $fields = [
            'SlNo' => null,
            'CustomerID' => null,
            'Emails' => null,
            'BilledName' => null,
            'Company' => null,
            'ContactPerson' => null,
            'Group Name' => null,
            'Phone' => null,
            'Cell' => null,
            'Region' => null,
            'Branch' => null,
            'Type' => null,
            'Category' => null,
            'ProductGroup' => null,
            'LastProduct' => null,
            'UsageType' => null,
            'LastYear' => null,
            'LastLicenses' => null,
            'LastRegDate' => null,
            'DealerName' => null,
            'DealerEmail' => null,
            'DealerCell' => null,
            'Amount' => null,
            'Price' => null,
            'Tax' => null,
            'NetPrice' => null,
            'Edition' => null,
            'Days' => null,
            'BillNumber' => null,
            'BillDate' => null,
            'ExecutiveName' => null,
            'ExecutiveContact' => null,
            'Address' => null,
            'Place' => null,
            'Pincode' => null,
            'District' => null,
            'State' => null,
        ];
        
        $extraData = [];
        
        // Map columns to fields
        foreach ($headers as $colIdx => $header) {
            $value = isset($row[$colIdx]) ? trim($row[$colIdx]) : '';
            if ($value === '') continue;
            
            // Normalize header
            $headerNormalized = strtolower(str_replace([' ', '_', '-', '.'], '', $header));
            
            // Try to map to known field
            $mapped = false;
            foreach ($columnMap as $pattern => $dbField) {
                $patternNormalized = strtolower(str_replace([' ', '_', '-', '.'], '', $pattern));
                if ($headerNormalized === $patternNormalized) {
                    $fields[$dbField] = $value;
                    $mapped = true;
                    break;
                }
            }
            
            // If not mapped, store in extra_data
            if (!$mapped) {
                $extraData[$header] = $value;
            }
        }
        
        // Get email
        $email = $fields['Emails'];
        
        // Validate email
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $skipped++;
            $errors[] = "Row " . ($i + 1) . ": Invalid email - " . ($email ?: 'empty');
            continue;
        }
        
        // Convert extra data to JSON
        $extraDataJson = !empty($extraData) ? json_encode($extraData, JSON_UNESCAPED_UNICODE) : null;
        
        // Bind all parameters (41 parameters: 1 batch_id + 38 fields + 1 extra_data + 1 filename + 1 user_id)
        $stmt->bind_param("ssisssssssssssssssssssssssssssssssssssssi",
            $batchId,
            $fields['Emails'],
            $fields['SlNo'],
            $fields['CustomerID'],
            $fields['BilledName'],
            $fields['Company'],
            $fields['ContactPerson'],
            $fields['Group Name'],
            $fields['Phone'],
            $fields['Cell'],
            $fields['Region'],
            $fields['Branch'],
            $fields['Type'],
            $fields['Category'],
            $fields['ProductGroup'],
            $fields['LastProduct'],
            $fields['UsageType'],
            $fields['LastYear'],
            $fields['LastLicenses'],
            $fields['LastRegDate'],
            $fields['DealerName'],
            $fields['DealerEmail'],
            $fields['DealerCell'],
            $fields['Amount'],
            $fields['Price'],
            $fields['Tax'],
            $fields['NetPrice'],
            $fields['Edition'],
            $fields['Days'],
            $fields['BillNumber'],
            $fields['BillDate'],
            $fields['ExecutiveName'],
            $fields['ExecutiveContact'],
            $fields['Address'],
            $fields['Place'],
            $fields['Pincode'],
            $fields['District'],
            $fields['State'],
            $extraDataJson,
            $filename,
            $user_id
        );
        
        if ($stmt->execute()) {
            $imported++;
        } else {
            $skipped++;
            $errors[] = "Row " . ($i + 1) . ": Database error - " . $stmt->error;
            error_log("Import error on row " . ($i + 1) . ": " . $stmt->error);
        }
    }
    
    $stmt->close();
    $conn->commit();
    
    // Return success
    echo json_encode([
        'success' => true,
        'status' => 'success',
        'message' => "Successfully imported $imported records",
        'data' => [
            'import_batch_id' => $batchId,
            'imported' => $imported,
            'skipped' => $skipped,
            'total' => $imported + $skipped,
            'filename' => $filename,
            'errors' => array_slice($errors, 0, 10) // Return first 10 errors
        ]
    ]);
    
} catch (Exception $e) {
    // Attempt rollback (might fail if no transaction was started)
    try {
        $conn->rollback();
    } catch (Exception $rollbackError) {
        // Ignore rollback errors
    }
    error_log("Import error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
