<?php
/**
 * Test Template Merge with Case-Insensitive Matching
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/template_merge_helper.php';

echo "=== Template Merge Case-Insensitive Test ===\n\n";

// Test 1: Case-insensitive matching
$template_html = <<<HTML
<html>
<body>
    <h1>Hello [[BilledName]]</h1>
    <p>Company: [[COMPANY]]</p>
    <p>District: [[district]]</p>
    <p>Email: [[Email]]</p>
    <p>Customer ID: [[CustomerID]]</p>
    <p>Amount: [[Amount]]</p>
    <p>Price: [[PRICE]]</p>
    <p>Last Product: [[lastproduct]]</p>
</body>
</html>
HTML;

$test_data = [
    'BilledName' => 'Test Company Pvt Ltd',
    'Company' => 'Test Corp',
    'District' => 'Bangalore',
    'Emails' => 'test@example.com',
    'CustomerID' => 'CUST123',
    'Amount' => '5000',
    'Price' => '4237',
    'LastProduct' => 'Saral TDS Pro'
];

echo "Template placeholders:\n";
preg_match_all('/\[\[([^\]]+)\]\]/', $template_html, $matches);
foreach ($matches[1] as $field) {
    echo "  - [[$field]]\n";
}

echo "\nAvailable data fields:\n";
foreach ($test_data as $key => $value) {
    echo "  - $key = $value\n";
}

echo "\n--- Merging Template ---\n";
$merged = mergeTemplateWithData($template_html, $test_data);

echo "\n=== MERGED RESULT ===\n";
echo $merged;
echo "\n\n";

// Test 2: Real database test (if import batch exists)
echo "=== Database Test ===\n";
$batch_query = "SELECT import_batch_id FROM imported_recipients WHERE is_active = 1 LIMIT 1";
$batch_result = $conn->query($batch_query);

if ($batch_result && $batch_result->num_rows > 0) {
    $batch_row = $batch_result->fetch_assoc();
    $batch_id = $batch_row['import_batch_id'];
    
    echo "Found batch: $batch_id\n";
    
    // Get first email
    $email_query = "SELECT Emails FROM imported_recipients WHERE import_batch_id = ? AND is_active = 1 LIMIT 1";
    $stmt = $conn->prepare($email_query);
    $stmt->bind_param("s", $batch_id);
    $stmt->execute();
    $email_result = $stmt->get_result();
    
    if ($email_result && $email_result->num_rows > 0) {
        $email_row = $email_result->fetch_assoc();
        $test_email = $email_row['Emails'];
        
        echo "Testing with email: $test_email\n\n";
        
        $email_data = getEmailRowData($conn, $test_email, null, $batch_id);
        
        echo "Retrieved " . count($email_data) . " fields from database\n";
        echo "Sample fields:\n";
        $count = 0;
        foreach ($email_data as $key => $value) {
            if ($count++ < 10) {
                $display_value = strlen($value) > 50 ? substr($value, 0, 50) . '...' : $value;
                echo "  - $key = $display_value\n";
            }
        }
        
        echo "\n--- Testing Case Variations ---\n";
        $test_placeholders = [
            '[[Email]]' => isset($email_data['Email']) || isset($email_data['email']) || isset($email_data['EMAIL']),
            '[[DISTRICT]]' => isset($email_data['District']) || isset($email_data['district']) || isset($email_data['DISTRICT']),
            '[[company]]' => isset($email_data['Company']) || isset($email_data['company']) || isset($email_data['COMPANY']),
        ];
        
        foreach ($test_placeholders as $placeholder => $found) {
            $status = $found ? '✓ FOUND' : '✗ NOT FOUND';
            echo "  $placeholder: $status\n";
        }
        
        echo "\n--- Merging with Real Data ---\n";
        $real_merged = mergeTemplateWithData($template_html, $email_data);
        
        // Check if placeholders were replaced
        preg_match_all('/\[\[([^\]]+)\]\]/', $real_merged, $remaining);
        $remaining_count = count($remaining[1]);
        
        echo "Remaining unfilled placeholders: $remaining_count\n";
        if ($remaining_count > 0) {
            echo "Unfilled fields:\n";
            foreach ($remaining[1] as $field) {
                echo "  - [[$field]]\n";
            }
        } else {
            echo "✓ All placeholders filled successfully!\n";
        }
    } else {
        echo "No emails found in batch\n";
    }
} else {
    echo "No import batches found in database\n";
}

echo "\n=== Test Complete ===\n";
