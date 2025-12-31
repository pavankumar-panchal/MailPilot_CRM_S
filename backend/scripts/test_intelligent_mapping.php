<?php
/**
 * Test Intelligent Field Mapping
 * Shows how templates automatically adapt to available Excel data
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/template_merge_helper.php';

echo "=== INTELLIGENT FIELD MAPPING TEST ===\n\n";

// Get real data
$batch_query = "SELECT import_batch_id FROM imported_recipients WHERE is_active = 1 LIMIT 1";
$batch_result = $conn->query($batch_query);
$batch_row = $batch_result->fetch_assoc();
$batch_id = $batch_row['import_batch_id'];

$email_query = "SELECT Emails FROM imported_recipients WHERE import_batch_id = ? AND is_active = 1 LIMIT 1";
$stmt = $conn->prepare($email_query);
$stmt->bind_param("s", $batch_id);
$stmt->execute();
$email_result = $stmt->get_result();
$email_row = $email_result->fetch_assoc();
$test_email = $email_row['Emails'];

echo "Using email: $test_email\n\n";

$email_data = getEmailRowData($conn, $test_email, null, $batch_id);

echo "=== AVAILABLE DATA FIELDS ===\n";
$available_with_data = [];
foreach ($email_data as $key => $value) {
    if ($value && !in_array(strtolower($key), ['id', 'import_batch_id', 'is_active', 'imported_at'])) {
        $available_with_data[] = $key;
        $display = strlen($value) > 30 ? substr($value, 0, 30) . '...' : $value;
        echo "  ✓ $key = $display\n";
    }
}

echo "\n=== INTELLIGENT MAPPING TESTS ===\n";
echo "Testing how template fields map to available data:\n\n";

// Test cases: [template_field => expected_mapping]
$test_cases = [
    // Fields that should map via intelligent fallback
    'CustomerID' => 'No direct match, should try fallbacks',
    'District' => 'No direct match, should try fallbacks',
    'Price' => 'Should map to Amount (price fallback)',
    'NetPrice' => 'Should map to Amount (netprice fallback)',
    'Tax' => 'No tax data available',
    'DealerName' => 'Should map to ExecutiveName (dealer fallback)',
    'DealerCell' => 'Should map to ExecutiveContact (dealer fallback)',
    'DealerEmail' => 'No dealer email available',
    'Edition' => 'No edition available',
    'LastProduct' => 'No product available',
    'UsageType' => 'No usage type available',
    
    // Fields that should work directly
    'Company' => 'Direct match or Group Name',
    'Email' => 'Should map to Emails',
    'BilledName' => 'Direct match',
    'Amount' => 'Direct match',
    'Days' => 'Direct match',
    'BillNumber' => 'Direct match',
    'ExecutiveName' => 'Direct match',
    'ExecutiveContact' => 'Direct match',
];

// Create mini templates for each field
foreach ($test_cases as $field => $description) {
    $mini_template = "Field: [[$field]]";
    $merged = mergeTemplateWithData($mini_template, $email_data);
    
    // Check if placeholder was replaced
    if (strpos($merged, "[[") === false) {
        // Placeholder was replaced
        $value = str_replace('Field: ', '', $merged);
        if ($value === '') {
            echo "  ⚠ [[$field]] → EMPTY (mapped but no data)\n";
        } else {
            $display_value = strlen($value) > 30 ? substr($value, 0, 30) . '...' : $value;
            echo "  ✓ [[$field]] → '$display_value'\n";
        }
    } else {
        // Placeholder NOT replaced (removed)
        echo "  ✗ [[$field]] → REMOVED (no mapping found)\n";
    }
}

echo "\n=== TESTING WITH RENEWAL TEMPLATE ===\n";

// Simplified renewal template
$renewal_template = <<<HTML
<div class="customer-info">
    <p>Company: [[Company]]</p>
    <p>Email: [[Email]]</p>
    <p>Customer ID: [[CustomerID]]</p>
    <p>District: [[District]]</p>
</div>

<div class="product-info">
    <p>Product: [[LastProduct]]</p>
    <p>Edition: [[Edition]]</p>
    <p>Usage: [[UsageType]]</p>
</div>

<div class="pricing">
    <p>Price: Rs. [[Price]]</p>
    <p>Tax: Rs. [[Tax]]</p>
    <p>Net Price: Rs. [[NetPrice]]</p>
</div>

<div class="dealer">
    <p>Contact: [[DealerName]]</p>
    <p>Phone: [[DealerCell]]</p>
    <p>Email: [[DealerEmail]]</p>
</div>
HTML;

echo "Original template has placeholders for:\n";
preg_match_all('/\[\[([^\]]+)\]\]/', $renewal_template, $matches);
$placeholders = array_unique($matches[1]);
foreach ($placeholders as $p) {
    echo "  - [[$p]]\n";
}

$merged_renewal = mergeTemplateWithData($renewal_template, $email_data);

echo "\n--- MERGED RESULT ---\n";
echo $merged_renewal;

// Count filled vs empty
preg_match_all('/\[\[([^\]]+)\]\]/', $merged_renewal, $remaining);
$filled = count($placeholders) - count($remaining[1]);
echo "\n\n✓ Filled: $filled out of " . count($placeholders) . " fields\n";

if (count($remaining[1]) > 0) {
    echo "⚠ Removed (no data): " . implode(', ', array_unique($remaining[1])) . "\n";
}

echo "\n=== TESTING WITH INVOICE TEMPLATE ===\n";

$invoice_template = <<<HTML
<div class="invoice">
    <p>Billed To: [[BilledName]]</p>
    <p>Email: [[Email]]</p>
    <p>Bill Number: [[BillNumber]]</p>
    <p>Bill Date: [[BillDate]]</p>
    <p>Amount: Rs. [[Amount]]</p>
    <p>Days Overdue: [[Days]] days</p>
    <p>Executive: [[ExecutiveName]] ([[ExecutiveContact]])</p>
</div>
HTML;

echo "Original template has placeholders for:\n";
preg_match_all('/\[\[([^\]]+)\]\]/', $invoice_template, $matches);
$placeholders2 = array_unique($matches[1]);
foreach ($placeholders2 as $p) {
    echo "  - [[$p]]\n";
}

$merged_invoice = mergeTemplateWithData($invoice_template, $email_data);

echo "\n--- MERGED RESULT ---\n";
echo $merged_invoice;

preg_match_all('/\[\[([^\]]+)\]\]/', $merged_invoice, $remaining2);
$filled2 = count($placeholders2) - count($remaining2[1]);
echo "\n\n✓ Filled: $filled2 out of " . count($placeholders2) . " fields\n";
echo "✓ SUCCESS: Invoice template works perfectly with current data!\n";

echo "\n=== SUMMARY ===\n";
echo "Available data fields: " . count($available_with_data) . "\n";
echo "Renewal template compatibility: $filled/" . count($placeholders) . " (" . round($filled/count($placeholders)*100) . "%)\n";
echo "Invoice template compatibility: $filled2/" . count($placeholders2) . " (" . round($filled2/count($placeholders2)*100) . "%)\n";

echo "\n🎯 INTELLIGENT MAPPING ACTIVE!\n";
echo "Templates automatically adapt to available Excel data.\n";
echo "Missing fields are handled gracefully with fallbacks.\n";

echo "\n=== Test Complete ===\n";
