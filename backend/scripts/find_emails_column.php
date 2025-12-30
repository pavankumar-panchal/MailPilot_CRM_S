<?php
$file = '/opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/Final -naveen.xlsx';

$zip = new ZipArchive();
if ($zip->open($file) === TRUE) {
    $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
    $worksheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    
    // Parse shared strings
    preg_match_all('/<t[^>]*>(.*?)<\/t>/s', $sharedStringsXml, $matches);
    $sharedStrings = array_map('html_entity_decode', $matches[1]);
    
    // Extract FULL first row
    preg_match('/<row[^>]*r="1"[^>]*>(.*?)<\/row>/s', $worksheetXml, $firstRow);
    
    if (!empty($firstRow[1])) {
        preg_match_all('/<c r="([A-Z]+1)"[^>]*t="s"[^>]*><v>(.*?)<\/v>/s', $firstRow[1], $cellMatches);
        
        echo "ALL Headers in First Row (with string type):\n";
        echo "=============================================\n";
        
        for ($i = 0; $i < count($cellMatches[1]); $i++) {
            $col = $cellMatches[1][$i];
            $strIndex = (int)$cellMatches[2][$i];
            $headerValue = $sharedStrings[$strIndex] ?? "MISSING[$strIndex]";
            
            echo "Cell $col: Index $strIndex → '$headerValue'\n";
            
            $normalized = strtolower(trim($headerValue));
            if (strpos($normalized, 'email') !== false || strpos($normalized, 'mail') !== false) {
                echo "  ✓✓✓ THIS IS AN EMAIL COLUMN! ✓✓✓\n";
            }
        }
    }
}
