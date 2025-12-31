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
} catch (Exception $e) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$action = $_GET['action'] ?? 'list';

// List all import batches
if ($action === 'list') {
    $sql = "SELECT 
        import_batch_id,
        import_filename,
        COUNT(*) as record_count,
        MIN(imported_at) as imported_at,
        GROUP_CONCAT(DISTINCT Emails ORDER BY Emails SEPARATOR ', ') as sample_emails
    FROM imported_recipients 
    WHERE is_active = 1
    GROUP BY import_batch_id, import_filename
    ORDER BY imported_at DESC";
    
    $result = $conn->query($sql);
    $batches = [];
    
    while ($row = $result->fetch_assoc()) {
        // Limit sample emails to first 3
        $emails = explode(', ', $row['sample_emails']);
        $row['sample_emails'] = implode(', ', array_slice($emails, 0, 3));
        if (count($emails) > 3) {
            $row['sample_emails'] .= '...';
        }
        $batches[] = $row;
    }
    
    echo json_encode(['success' => true, 'batches' => $batches]);
    exit;
}

// Get recipients for a specific batch
if ($action === 'get_batch') {
    $batchId = $_GET['batch_id'] ?? '';
    
    if (!$batchId) {
        echo json_encode(['success' => false, 'error' => 'Batch ID is required']);
        exit;
    }
    
    $stmt = $conn->prepare("SELECT * FROM imported_recipients WHERE import_batch_id = ? AND is_active = 1");
    $stmt->bind_param("s", $batchId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $recipients = [];
    while ($row = $result->fetch_assoc()) {
        // Parse extra_data JSON
        if ($row['extra_data']) {
            $row['extra_data'] = json_decode($row['extra_data'], true);
        }
        $recipients[] = $row;
    }
    
    echo json_encode(['success' => true, 'recipients' => $recipients]);
    exit;
}

// Import CSV data
if ($action === 'import' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if file was uploaded
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error']);
        exit;
    }
    
    $file = $_FILES['file'];
    $filename = $file['name'];
    $tmpPath = $file['tmp_name'];
    
    // Check file extension
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (!in_array($ext, ['csv', 'xlsx', 'xls'])) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Only CSV and Excel (.xlsx, .xls) files are supported']);
        exit;
    }
    
    // Read file based on extension
    $rows = [];
    
    if ($ext === 'csv') {
        // Read CSV file
        if (($handle = fopen($tmpPath, "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 10000, ",")) !== FALSE) {
                $rows[] = $data;
            }
            fclose($handle);
        }
    } elseif ($ext === 'xlsx') {
        // Read Excel file using ZipArchive (no XML dependencies)
        $zip = new ZipArchive;
        if ($zip->open($tmpPath) === TRUE) {
            // Read shared strings using basic XML parsing
            $sharedStrings = [];
            $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
            if ($sharedStringsXml) {
                // Extract text values using regex (no SimpleXML needed)
                preg_match_all('/<t[^>]*>(.*?)<\/t>/s', $sharedStringsXml, $matches);
                $sharedStrings = $matches[1];
            }
            
            // Read first worksheet
            $worksheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
            $zip->close();
            
            if ($worksheetXml) {
                // Parse rows using regex (basic XML parsing)
                preg_match_all('/<row[^>]*>(.*?)<\/row>/s', $worksheetXml, $rowMatches);
                
                foreach ($rowMatches[1] as $rowXml) {
                    $rowData = [];
                    
                    // Find all cells in this row (including self-closing empty cells)
                    preg_match_all('/<c r="([A-Z]+\d+)"([^>\/]*)(?:\/>|>(.*?)<\/c>)/s', $rowXml, $cellMatches, PREG_SET_ORDER);
                    
                    $lastCol = 0;
                    foreach ($cellMatches as $cellMatch) {
                        $cellRef = $cellMatch[1];
                        $cellAttributes = $cellMatch[2]; // Attributes like s="8" t="s"
                        $cellContent = isset($cellMatch[3]) ? $cellMatch[3] : ''; // Empty for self-closing tags
                        
                        // Extract column letter
                        preg_match('/([A-Z]+)/', $cellRef, $colMatch);
                        $colLetter = $colMatch[1];
                        
                        // Convert column letter to number
                        $colNum = 0;
                        for ($i = 0; $i < strlen($colLetter); $i++) {
                            $colNum = $colNum * 26 + (ord($colLetter[$i]) - ord('A') + 1);
                        }
                        $colNum--;
                        
                        // Fill empty cells
                        while ($lastCol < $colNum) {
                            $rowData[] = '';
                            $lastCol++;
                        }
                        
                // Get cell value
                $cellValue = '';
                if (preg_match('/<v>(.*?)<\/v>/', $cellContent, $valueMatch)) {
                    $cellValue = $valueMatch[1];
                    
                    // Check attributes for type and style  
                    if (preg_match('/t="s"/', $cellAttributes)) {
                        // Explicitly marked as shared string
                        $index = (int)$cellValue;
                        $cellValue = isset($sharedStrings[$index]) ? $sharedStrings[$index] : '';
                    } else if (preg_match('/s="(\d+)"/', $cellAttributes, $styleMatch)) {
                        // Has style attribute - keep as numeric value
                        // Don't convert to shared string index
                        // $cellValue stays as-is
                    } else if (is_numeric($cellValue) && !preg_match('/\./', $cellValue)) {
                        // No type or style attribute - might be shared string
                        $index = (int)$cellValue;
                        if (isset($sharedStrings[$index]) && $index < 10000) {
                            $cellValue = $sharedStrings[$index];
                        }
                    }
                }                        $rowData[] = $cellValue;
                        $lastCol++;
                    }
                    
                    $rows[] = $rowData;
                }
            }
        }
        
        if (empty($rows)) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Failed to read Excel file']);
            exit;
        }
    } else {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Unsupported file format']);
        exit;
    }
    
    if (empty($rows)) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'File is empty or could not be read']);
        exit;
    }
    
    // First row is headers
    $headers = array_map('trim', $rows[0]);
    $normalizedHeaders = array_map('strtolower', $headers);
    
    // Detect file type based on headers
    $fileType = 'other';
    $hasCustomerID = in_array('customerid', $normalizedHeaders);
    $hasBillNumber = in_array('billnumber', $normalizedHeaders);
    $hasCompanyCol = in_array('company', $normalizedHeaders);
    
    if ($hasCustomerID || ($hasCompanyCol && in_array('address', $normalizedHeaders))) {
        $fileType = 'customer'; // TDS Report format
    } elseif ($hasBillNumber || in_array('billdate', $normalizedHeaders)) {
        $fileType = 'invoice'; // Final -naveen format
    }
    
    // Find email column - check multiple variations
    $emailCol = false;
    $emailVariations = ['email', 'e-mail', 'emailid', 'email_id', 'email id', 'e_mail', 'emails', 'emailaddress', 'email_address', 'email address', 'mail', 'e mail'];
    
    foreach ($emailVariations as $variation) {
        $emailCol = array_search($variation, $normalizedHeaders);
        if ($emailCol !== false) {
            break;
        }
    }
    
    // If still not found, try partial matches (contains 'email' or 'mail')
    if ($emailCol === false) {
        foreach ($normalizedHeaders as $idx => $header) {
            if (strpos($header, 'email') !== false || strpos($header, 'mail') !== false) {
                $emailCol = $idx;
                break;
            }
        }
    }
    
    if ($emailCol === false) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Email column not found. Please ensure your file has a column with email addresses (e.g., "Email", "Emails", "E-mail", etc.)']);
        exit;
    }
    
    // Generate unique batch ID
    $batchId = 'BATCH_' . date('Ymd_His') . '_' . uniqid();
    
    // Create a mapping of Excel column names to database fields
    // This allows ANY Excel structure to work dynamically
    $knownFields = [
        // Common fields (multiple variations)
        'email' => 'Emails', 'emails' => 'Emails', 'e-mail' => 'Emails', 'emailid' => 'Emails',
        'email id' => 'Emails', 'email_id' => 'Emails', 'emailaddress' => 'Emails',
        
        // Invoice fields
        'billdate' => 'BillDate', 'bill date' => 'BillDate', 'bill_date' => 'BillDate',
        'billnumber' => 'BillNumber', 'bill number' => 'BillNumber', 'bill_number' => 'BillNumber',
        'billedname' => 'BilledName', 'billed name' => 'BilledName', 'billed_name' => 'BilledName',
        'groupname' => 'Group Name', 'group name' => 'Group Name', 'group_name' => 'Group Name',
        'executivename' => 'ExecutiveName', 'executive name' => 'ExecutiveName', 'executive_name' => 'ExecutiveName',
        'executivecontact' => 'ExecutiveContact', 'executive contact' => 'ExecutiveContact', 'executive_contact' => 'ExecutiveContact',
        'amount' => 'Amount',
        'days' => 'Days',
        
        // Customer/TDS fields
        'slno' => 'SlNo', 'sl no' => 'SlNo', 'sl_no' => 'SlNo', 'sno' => 'SlNo',
        'customerid' => 'CustomerID', 'customer id' => 'CustomerID', 'customer_id' => 'CustomerID',
        'company' => 'Company',
        'contactperson' => 'ContactPerson', 'contact person' => 'ContactPerson', 'contact_person' => 'ContactPerson',
        'address' => 'Address',
        'place' => 'Place',
        'pincode' => 'Pincode', 'pin code' => 'Pincode', 'pin_code' => 'Pincode',
        'district' => 'District',
        'state' => 'State',
        'cell' => 'Cell', 'cellno' => 'Cell', 'cell no' => 'Cell',
        'phone' => 'Phone', 'phoneno' => 'Phone', 'phone no' => 'Phone',
        'region' => 'Region',
        'branch' => 'Branch',
        'type' => 'Type',
        'category' => 'Category',
        'productgroup' => 'ProductGroup', 'product group' => 'ProductGroup', 'product_group' => 'ProductGroup',
        'lastproduct' => 'LastProduct', 'last product' => 'LastProduct', 'last_product' => 'LastProduct',
        'usagetype' => 'UsageType', 'usage type' => 'UsageType', 'usage_type' => 'UsageType',
        'lastyear' => 'LastYear', 'last year' => 'LastYear', 'last_year' => 'LastYear',
        'lastlicenses' => 'LastLicenses', 'last licenses' => 'LastLicenses', 'last_licenses' => 'LastLicenses',
        'lastregdate' => 'LastRegDate', 'last reg date' => 'LastRegDate', 'last_reg_date' => 'LastRegDate',
        'dealername' => 'DealerName', 'dealer name' => 'DealerName', 'dealer_name' => 'DealerName',
        'dealeremail' => 'DealerEmail', 'dealer email' => 'DealerEmail', 'dealer_email' => 'DealerEmail',
        'dealercell' => 'DealerCell', 'dealer cell' => 'DealerCell', 'dealer_cell' => 'DealerCell',
        'price' => 'Price',
        'tax' => 'Tax',
        'netprice' => 'NetPrice', 'net price' => 'NetPrice', 'net_price' => 'NetPrice',
        'edition' => 'Edition',
    ];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Prepare statement with all possible columns
        $stmt = $conn->prepare("INSERT INTO imported_recipients (
            import_batch_id, source_file_type, Emails, 
            BilledName, `Group Name`, Phone, Amount, Days, BillNumber, BillDate, 
            ExecutiveName, ExecutiveContact,
            CustomerID, Company, ContactPerson, Address, Place, Pincode, District, State,
            Cell, Region, Branch, Type, Category, ProductGroup, LastProduct, UsageType,
            LastYear, LastLicenses, LastRegDate, DealerName, DealerEmail, DealerCell,
            Price, Tax, NetPrice, Edition, SlNo,
            extra_data, import_filename
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $imported = 0;
        $skipped = 0;
        $errors = [];
        
        // Process data rows
        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            
            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }
            
            // Initialize all fields to null
            $data = [
                'Emails' => null, 'Phone' => null, 'BillDate' => null, 'BillNumber' => null,
                'BilledName' => null, 'Group Name' => null, 'ExecutiveName' => null,
                'ExecutiveContact' => null, 'Amount' => null, 'Days' => null,
                'CustomerID' => null, 'Company' => null, 'ContactPerson' => null,
                'Address' => null, 'Place' => null, 'Pincode' => null, 'District' => null,
                'State' => null, 'Cell' => null, 'Region' => null, 'Branch' => null,
                'Type' => null, 'Category' => null, 'ProductGroup' => null,
                'LastProduct' => null, 'UsageType' => null, 'LastYear' => null,
                'LastLicenses' => null, 'LastRegDate' => null, 'DealerName' => null,
                'DealerEmail' => null, 'DealerCell' => null, 'Price' => null,
                'Tax' => null, 'NetPrice' => null, 'Edition' => null, 'SlNo' => null,
            ];
            
            $extraData = []; // Store unmapped columns
            
            // DYNAMIC MAPPING: Map Excel columns to database fields
            foreach ($headers as $colIndex => $headerName) {
                $value = isset($row[$colIndex]) ? trim($row[$colIndex]) : null;
                
                // Skip empty values
                if ($value === '' || $value === null) {
                    continue;
                }
                
                // Normalize header for matching
                $normalizedHeader = strtolower(trim(str_replace([' ', '_', '-'], '', $headerName)));
                
                // Check if this column maps to a known database field
                if (isset($knownFields[$normalizedHeader])) {
                    $dbField = $knownFields[$normalizedHeader];
                    $data[$dbField] = $value;
                } else {
                    // Unknown column - store in extra_data
                    $extraData[$headerName] = $value;
                }
            }
            
            // Empty strings to null
            foreach ($data as $key => $value) {
                if ($value === '') {
                    $data[$key] = null;
                }
            }
            
            // Validate email
            if (!$data['Emails'] || !filter_var($data['Emails'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Row " . ($i + 1) . ": Invalid email (" . ($data['Emails'] ?: 'empty') . ")";
                $skipped++;
                continue;
            }
            
            // Store all data as-is without cleaning or validation
            // Convert date format from d/m/Y to Y-m-d only if it looks like a date
            if ($data['BillDate'] && preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $data['BillDate'])) {
                $dateObj = DateTime::createFromFormat('d/m/Y', $data['BillDate']);
                if ($dateObj) {
                    $data['BillDate'] = $dateObj->format('Y-m-d');
                }
            }
            
            // Store extra_data as JSON (unmapped columns)
            $extraDataJson = !empty($extraData) ? json_encode($extraData, JSON_UNESCAPED_UNICODE) : null;
            
            // Extract to variables for bind_param (41 parameters total)
            $stmt->bind_param(
                "sssssssssssssssssssssssssssssssssssssssss", // 41 's' for strings
                $batchId,
                $fileType,
                $data['Emails'],
                $data['BilledName'],
                $data['Group Name'],
                $data['Phone'],
                $data['Amount'],
                $data['Days'],
                $data['BillNumber'],
                $data['BillDate'],
                $data['ExecutiveName'],
                $data['ExecutiveContact'],
                $data['CustomerID'],
                $data['Company'],
                $data['ContactPerson'],
                $data['Address'],
                $data['Place'],
                $data['Pincode'],
                $data['District'],
                $data['State'],
                $data['Cell'],
                $data['Region'],
                $data['Branch'],
                $data['Type'],
                $data['Category'],
                $data['ProductGroup'],
                $data['LastProduct'],
                $data['UsageType'],
                $data['LastYear'],
                $data['LastLicenses'],
                $data['LastRegDate'],
                $data['DealerName'],
                $data['DealerEmail'],
                $data['DealerCell'],
                $data['Price'],
                $data['Tax'],
                $data['NetPrice'],
                $data['Edition'],
                $data['SlNo'],
                $extraDataJson,
                $filename
            );
            
            if ($stmt->execute()) {
                $imported++;
            } else {
                $errors[] = "Row " . ($i + 1) . ": " . $stmt->error;
                $skipped++;
            }
        }
        
        $conn->commit();
        
        ob_clean();
        $response = json_encode([
            'success' => true,
            'message' => "Import completed successfully!",
            'batch_id' => $batchId,
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => array_slice($errors, 0, 10), // Only first 10 errors
            'columns_found' => $headers
        ]);
        echo $response;
        if (ob_get_level() > 0) ob_end_flush();
        exit;
        
    } catch (Exception $e) {
        $conn->rollback();
        ob_clean();
        $response = json_encode(['success' => false, 'error' => 'Import failed: ' . $e->getMessage()]);
        echo $response;
        if (ob_get_level() > 0) ob_end_flush();
        exit;
    }
    
    exit;
}

// Delete batch
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $batchId = $_POST['batch_id'] ?? '';
    
    if (!$batchId) {
        echo json_encode(['success' => false, 'error' => 'Batch ID is required']);
        exit;
    }
    
    $stmt = $conn->prepare("UPDATE imported_recipients SET is_active = 0 WHERE import_batch_id = ?");
    $stmt->bind_param("s", $batchId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Batch deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to delete batch']);
    }
    
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
