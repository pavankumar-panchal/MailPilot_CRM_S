<?php
/**
 * Read Excel file and convert to CSV
 * This script reads the Excel file and extracts data
 */

$excelFile = __DIR__ . '/../../Final -naveen.xlsx';

if (!file_exists($excelFile)) {
    die("Excel file not found: $excelFile\n");
}

// Try using SimpleXLSX library (lightweight, no dependencies)
// Download from: https://github.com/shuchkin/simplexlsx
class SimpleXLSX {
    public static function parse($filename) {
        if (!file_exists($filename)) {
            return false;
        }
        
        $zip = new ZipArchive();
        if ($zip->open($filename) !== true) {
            return false;
        }
        
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        $sharedStrings = [];
        if ($xml) {
            $xmlDoc = simplexml_load_string($xml);
            if ($xmlDoc) {
                foreach ($xmlDoc->si as $val) {
                    $sharedStrings[] = (string)$val->t;
                }
            }
        }
        
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        
        if (!$xml) {
            return false;
        }
        
        $xmlDoc = simplexml_load_string($xml);
        if (!$xmlDoc) {
            return false;
        }
        
        $rows = [];
        foreach ($xmlDoc->sheetData->row as $row) {
            $rowData = [];
            foreach ($row->c as $cell) {
                $value = '';
                if (isset($cell->v)) {
                    $value = (string)$cell->v;
                    // Check if it's a shared string
                    if (isset($cell['t']) && (string)$cell['t'] === 's' && isset($sharedStrings[$value])) {
                        $value = $sharedStrings[$value];
                    }
                }
                $rowData[] = $value;
            }
            $rows[] = $rowData;
        }
        
        return $rows;
    }
}

try {
    echo "Reading Excel file: $excelFile\n\n";
    
    $rows = SimpleXLSX::parse($excelFile);
    
    if ($rows === false) {
        die("Failed to parse Excel file\n");
    }
    
    if (empty($rows)) {
        die("No data found in Excel file\n");
    }
    
    // First row is headers
    $headers = $rows[0];
    echo "Column Headers:\n";
    echo json_encode($headers, JSON_PRETTY_PRINT) . "\n\n";
    
    // Show first 3 data rows
    echo "First 3 Sample Rows:\n";
    for ($i = 1; $i <= min(3, count($rows) - 1); $i++) {
        echo "\nRow $i:\n";
        $rowData = [];
        foreach ($headers as $colIdx => $header) {
            if (isset($rows[$i][$colIdx]) && $rows[$i][$colIdx] !== '') {
                $rowData[$header] = $rows[$i][$colIdx];
            }
        }
        echo json_encode($rowData, JSON_PRETTY_PRINT) . "\n";
    }
    
    echo "\n\nTotal rows: " . (count($rows) - 1) . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
