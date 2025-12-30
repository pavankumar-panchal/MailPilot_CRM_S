<?php
/**
 * Import Excel file to database
 * This script imports Excel data into imported_recipients table
 * 
 * Usage: php import_excel_to_db.php <excel_file_path>
 */

require_once __DIR__ . '/../config/db.php';

// Get Excel file path from command line
$excelFile = $argv[1] ?? __DIR__ . '/../../Final -naveen.xlsx';

if (!file_exists($excelFile)) {
    die("Error: Excel file not found: $excelFile\n");
}

echo "Importing Excel file: " . basename($excelFile) . "\n\n";

// Generate unique batch ID for this import
$batchId = 'BATCH_' . date('Ymd_His') . '_' . uniqid();
$filename = basename($excelFile);

/**
 * Simple XLSX reader without dependencies
 * Reads .xlsx files by extracting XML from ZIP
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
    $rowNum = 0;
    
    foreach ($xml->sheetData->row as $row) {
        $rowData = [];
        $colNum = 0;
        
        foreach ($row->c as $cell) {
            $cellValue = '';
            
            // Get cell reference (e.g., A1, B2)
            $cellRef = (string)$cell['r'];
            preg_match('/([A-Z]+)/', $cellRef, $matches);
            $expectedCol = $matches[1] ?? '';
            
            // Calculate column number
            $currentColNum = 0;
            for ($i = 0; $i < strlen($expectedCol); $i++) {
                $currentColNum = $currentColNum * 26 + (ord($expectedCol[$i]) - ord('A') + 1);
            }
            $currentColNum--;
            
            // Fill empty cells
            while ($colNum < $currentColNum) {
                $rowData[] = '';
                $colNum++;
            }
            
            // Get cell value
            if (isset($cell->v)) {
                $cellValue = (string)$cell->v;
                
                // Check if it's a shared string
                if (isset($cell['t']) && (string)$cell['t'] === 's') {
                    $cellValue = $sharedStrings[(int)$cellValue] ?? '';
                }
            }
            
            $rowData[] = $cellValue;
            $colNum++;
        }
        
        $rows[] = $rowData;
        $rowNum++;
    }
    
    return $rows;
}

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

// Read file based on extension
$ext = strtolower(pathinfo($excelFile, PATHINFO_EXTENSION));
if ($ext === 'xlsx') {
    $rows = readXlsx($excelFile);
} elseif ($ext === 'csv') {
    $rows = readCsv($excelFile);
} else {
    die("Error: Unsupported file format. Use .xlsx or .csv\n");
}

if ($rows === false || empty($rows)) {
    die("Error: Failed to read Excel file or file is empty\n");
}

// First row is headers
$headers = array_map('trim', $rows[0]);
echo "Found columns: " . implode(', ', $headers) . "\n\n";

// Normalize headers to lowercase for matching
$normalizedHeaders = array_map('strtolower', $headers);

// Find email column
$emailCol = array_search('email', $normalizedHeaders);
if ($emailCol === false) {
    die("Error: 'email' column not found in Excel file\n");
}

echo "Email column found at position: " . ($emailCol + 1) . "\n\n";

// Prepare column mapping for known fields
$columnMap = [
    'email' => 'email',
    'name' => 'name',
    'company' => 'company',
    'phone' => 'phone',
    'amount' => 'amount',
    'days' => 'days',
    'bill_number' => 'bill_number',
    'billnumber' => 'bill_number',
    'bill number' => 'bill_number',
    'bill_date' => 'bill_date',
    'billdate' => 'bill_date',
    'bill date' => 'bill_date',
    'executive_name' => 'executive_name',
    'executivename' => 'executive_name',
    'executive name' => 'executive_name',
    'executive_contact' => 'executive_contact',
    'executivecontact' => 'executive_contact',
    'executive contact' => 'executive_contact',
];

// Start transaction
$conn->begin_transaction();

try {
    $stmt = $conn->prepare("INSERT INTO imported_recipients (
        import_batch_id, email, name, company, phone, amount, days,
        bill_number, bill_date, executive_name, executive_contact,
        extra_data, import_filename
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $imported = 0;
    $skipped = 0;
    
    // Process data rows
    for ($i = 1; $i < count($rows); $i++) {
        $row = $rows[$i];
        
        // Skip empty rows
        if (empty(array_filter($row))) {
            continue;
        }
        
        // Extract known fields
        $data = [
            'email' => null,
            'name' => null,
            'company' => null,
            'phone' => null,
            'amount' => null,
            'days' => null,
            'bill_number' => null,
            'bill_date' => null,
            'executive_name' => null,
            'executive_contact' => null,
        ];
        
        $extraData = [];
        
        // Map columns to fields
        foreach ($headers as $colIdx => $header) {
            $value = isset($row[$colIdx]) ? trim($row[$colIdx]) : '';
            if ($value === '') continue;
            
            $headerLower = strtolower(str_replace([' ', '_', '-'], '', $header));
            $mappedField = null;
            
            // Find mapped field
            foreach ($columnMap as $pattern => $field) {
                $patternClean = str_replace([' ', '_', '-'], '', $pattern);
                if ($headerLower === $patternClean) {
                    $mappedField = $field;
                    break;
                }
            }
            
            if ($mappedField) {
                $data[$mappedField] = $value;
            } else {
                // Store in extra_data
                $extraData[$header] = $value;
            }
        }
        
        // Validate email
        if (!$data['email'] || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            echo "Row " . ($i + 1) . ": Skipped (invalid email: " . ($data['email'] ?: 'empty') . ")\n";
            $skipped++;
            continue;
        }
        
        // Prepare values
        $extraDataJson = !empty($extraData) ? json_encode($extraData) : null;
        
        $stmt->bind_param(
            "ssssssisssss",
            $batchId,
            $data['email'],
            $data['name'],
            $data['company'],
            $data['phone'],
            $data['amount'],
            $data['days'],
            $data['bill_number'],
            $data['bill_date'],
            $data['executive_name'],
            $data['executive_contact'],
            $extraDataJson,
            $filename
        );
        
        if ($stmt->execute()) {
            $imported++;
            if ($imported % 100 == 0) {
                echo "Imported $imported rows...\n";
            }
        } else {
            echo "Row " . ($i + 1) . ": Error - " . $stmt->error . "\n";
            $skipped++;
        }
    }
    
    $conn->commit();
    
    echo "\n✓ Import completed successfully!\n";
    echo "Batch ID: $batchId\n";
    echo "Total imported: $imported\n";
    echo "Total skipped: $skipped\n";
    echo "\nUse this Batch ID when creating campaigns to link with this data.\n";
    
} catch (Exception $e) {
    $conn->rollback();
    die("Error during import: " . $e->getMessage() . "\n");
}

$conn->close();
