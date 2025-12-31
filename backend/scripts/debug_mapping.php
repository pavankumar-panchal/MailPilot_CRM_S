<?php
require_once __DIR__ . '/../includes/template_merge_helper.php';
require_once __DIR__ . '/../config/db.php';

// Get data
$batch = $conn->query('SELECT import_batch_id FROM imported_recipients WHERE is_active=1 LIMIT 1')->fetch_assoc();
$email_row = $conn->query('SELECT Emails FROM imported_recipients WHERE import_batch_id="' . $batch['import_batch_id'] . '" LIMIT 1')->fetch_assoc();
$data = getEmailRowData($conn, $email_row['Emails'], null, $batch['import_batch_id']);

// Get available fields
$available = [];
foreach ($data as $k => $v) {
    if (!in_array(strtolower($k), ['id', 'import_batch_id', 'is_active', 'imported_at', 'slno'])) {
        $available[] = strtolower($k);
    }
}

echo "Available fields:\n";
foreach ($available as $f) {
    $val = isset($data[$f]) ? $data[$f] : $data[array_search($f, array_map('strtolower', array_keys($data)))];
    $display = ($val && strlen($val) > 30) ? substr($val, 0, 30) . '...' : $val;
    echo "  - $f = " . ($display ? $display : '(empty)') . "\n";
}

echo "\n=== Testing Mappings ===\n";
$tests = ['price', 'netprice', 'dealername', 'dealercell', 'name', 'email'];
foreach ($tests as $test) {
    $mapped = getIntelligentFieldMapping($test, $available);
    echo "[[$test]] -> " . ($mapped ? "'$mapped'" : 'NULL') . "\n";
}
