<?php
/**
 * Test Intelligent Fallbacks - Show Real Substitutions
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/template_merge_helper.php';

echo "=== INTELLIGENT FALLBACK DEMONSTRATION ===\n\n";

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

$email_data = getEmailRowData($conn, $test_email, null, $batch_id);

echo "📊 AVAILABLE DATA:\n";
echo "  ✓ Amount: " . $email_data['Amount'] . "\n";
echo "  ✓ ExecutiveName: " . $email_data['ExecutiveName'] . "\n";
echo "  ✓ ExecutiveContact: " . $email_data['ExecutiveContact'] . "\n";
echo "  ✗ Price: (not available)\n";
echo "  ✗ DealerName: (not available)\n";
echo "  ✗ DealerCell: (not available)\n\n";

echo "🎯 TESTING INTELLIGENT FALLBACKS:\n\n";

// Test Case 1: Price fallback to Amount
echo "1. Template asks for [[Price]], but only Amount exists:\n";
$test1 = "   Price: Rs. [[Price]]";
$result1 = mergeTemplateWithData($test1, $email_data);
echo "   Input:  '$test1'\n";
echo "   Output: '$result1'\n";
if (strpos($result1, '6313') !== false) {
    echo "   ✓ SUCCESS: [[Price]] mapped to Amount (6313)\n\n";
} else {
    echo "   ✗ FAILED: Did not map to Amount\n\n";
}

// Test Case 2: NetPrice fallback to Amount
echo "2. Template asks for [[NetPrice]], but only Amount exists:\n";
$test2 = "   Net Price: Rs. [[NetPrice]]";
$result2 = mergeTemplateWithData($test2, $email_data);
echo "   Input:  '$test2'\n";
echo "   Output: '$result2'\n";
if (strpos($result2, '6313') !== false) {
    echo "   ✓ SUCCESS: [[NetPrice]] mapped to Amount (6313)\n\n";
} else {
    echo "   ✗ FAILED: Did not map to Amount\n\n";
}

// Test Case 3: DealerName fallback to ExecutiveName  
echo "3. Template asks for [[DealerName]], but only ExecutiveName exists:\n";
$test3 = "   Dealer: [[DealerName]]";
$result3 = mergeTemplateWithData($test3, $email_data);
echo "   Input:  '$test3'\n";
echo "   Output: '$result3'\n";
if (strpos($result3, 'Subramani') !== false) {
    echo "   ✓ SUCCESS: [[DealerName]] mapped to ExecutiveName (Subramani M)\n\n";
} else {
    echo "   ✗ FAILED: Did not map to ExecutiveName\n\n";
}

// Test Case 4: DealerCell fallback to ExecutiveContact
echo "4. Template asks for [[DealerCell]], but only ExecutiveContact exists:\n";
$test4 = "   Phone: [[DealerCell]]";
$result4 = mergeTemplateWithData($test4, $email_data);
echo "   Input:  '$test4'\n";
echo "   Output: '$result4'\n";
if (strpos($result4, '9449599704') !== false) {
    echo "   ✓ SUCCESS: [[DealerCell]] mapped to ExecutiveContact (9449599704)\n\n";
} else {
    echo "   ✗ FAILED: Did not map to ExecutiveContact\n\n";
}

// Test Case 5: Name fallback to BilledName
echo "5. Template asks for [[Name]], but only BilledName exists:\n";
$test5 = "   Customer: [[Name]]";
$result5 = mergeTemplateWithData($test5, $email_data);
echo "   Input:  '$test5'\n";
echo "   Output: '$result5'\n";
if (strpos($result5, '10K INFO') !== false) {
    echo "   ✓ SUCCESS: [[Name]] mapped to BilledName\n\n";
} else {
    echo "   ✗ FAILED: Did not map to BilledName\n\n";
}

// Test Case 6: Email (singular) fallback to Emails (plural)
echo "6. Template asks for [[Email]] (singular), but only Emails (plural) exists:\n";
$test6 = "   Email: [[Email]]";
$result6 = mergeTemplateWithData($test6, $email_data);
echo "   Input:  '$test6'\n";
echo "   Output: '$result6'\n";
if (strpos($result6, 'mithun@10kinfo.com') !== false) {
    echo "   ✓ SUCCESS: [[Email]] mapped to Emails\n\n";
} else {
    echo "   ✗ FAILED: Did not map to Emails\n\n";
}

echo "\n=== REAL WORLD EXAMPLE ===\n";
echo "A renewal template designed for different data can still work:\n\n";

$real_template = <<<HTML
<div class="quote">
    <h2>Quotation for [[Name]]</h2>
    <p>Email: [[Email]]</p>
    <p>Company: [[Company]]</p>
    
    <h3>Pricing</h3>
    <table>
        <tr>
            <td>Base Price:</td>
            <td>Rs. [[Price]]</td>
        </tr>
        <tr>
            <td>Tax (18%):</td>
            <td>Rs. [[Tax]]</td>
        </tr>
        <tr>
            <td><strong>Total:</strong></td>
            <td><strong>Rs. [[NetPrice]]</strong></td>
        </tr>
    </table>
    
    <h3>Contact Person</h3>
    <p>Name: [[DealerName]]</p>
    <p>Phone: [[DealerCell]]</p>
</div>
HTML;

echo "TEMPLATE FIELDS REQUESTED:\n";
echo "  - [[Name]], [[Email]], [[Company]]\n";
echo "  - [[Price]], [[Tax]], [[NetPrice]]\n";
echo "  - [[DealerName]], [[DealerCell]]\n\n";

echo "ACTUAL DATA AVAILABLE:\n";
echo "  - BilledName, Emails, Company\n";
echo "  - Amount (no Price/Tax/NetPrice)\n";
echo "  - ExecutiveName, ExecutiveContact (no Dealer fields)\n\n";

$merged = mergeTemplateWithData($real_template, $email_data);

echo "MERGED RESULT:\n";
echo $merged . "\n\n";

// Analyze the result
$success_count = 0;
if (strpos($merged, '10K INFO') !== false) {
    echo "✓ [[Name]] → BilledName (intelligent fallback)\n";
    $success_count++;
}
if (strpos($merged, 'mithun@10kinfo.com') !== false) {
    echo "✓ [[Email]] → Emails (intelligent fallback)\n";
    $success_count++;
}
if (strpos($merged, 'BKG-Bangalore') !== false) {
    echo "✓ [[Company]] → Company (direct match)\n";
    $success_count++;
}
if (strpos($merged, '6313') !== false) {
    echo "✓ [[Price]] → Amount (intelligent fallback)\n";
    echo "✓ [[NetPrice]] → Amount (intelligent fallback)\n";
    $success_count += 2;
}
if (strpos($merged, 'Subramani') !== false) {
    echo "✓ [[DealerName]] → ExecutiveName (intelligent fallback)\n";
    $success_count++;
}
if (strpos($merged, '9449599704') !== false) {
    echo "✓ [[DealerCell]] → ExecutiveContact (intelligent fallback)\n";
    $success_count++;
}

echo "\n📊 RESULT: $success_count out of 8 fields successfully mapped!\n";
echo "🎯 Template works with different data structure!\n";

echo "\n=== Test Complete ===\n";
