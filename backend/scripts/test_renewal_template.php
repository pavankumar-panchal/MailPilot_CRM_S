<?php
/**
 * Test renewal_D-new.html Template with Real Data
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/template_merge_helper.php';

echo "=== Testing renewal_D-new.html Template ===\n\n";

// Get a real import batch
$batch_query = "SELECT import_batch_id FROM imported_recipients WHERE is_active = 1 LIMIT 1";
$batch_result = $conn->query($batch_query);

if (!$batch_result || $batch_result->num_rows === 0) {
    die("No import batches found!\n");
}

$batch_row = $batch_result->fetch_assoc();
$batch_id = $batch_row['import_batch_id'];

echo "Using batch: $batch_id\n\n";

// Get first email from batch
$email_query = "SELECT Emails FROM imported_recipients WHERE import_batch_id = ? AND is_active = 1 LIMIT 1";
$stmt = $conn->prepare($email_query);
$stmt->bind_param("s", $batch_id);
$stmt->execute();
$email_result = $stmt->get_result();

if (!$email_result || $email_result->num_rows === 0) {
    die("No emails found in batch!\n");
}

$email_row = $email_result->fetch_assoc();
$test_email = $email_row['Emails'];

echo "Testing with email: $test_email\n\n";

// Get ALL data for this email
$email_data = getEmailRowData($conn, $test_email, null, $batch_id);

echo "=== Required Template Fields ===\n";
$required_fields = [
    'Company', 'CustomerID', 'DealerCell', 'DealerEmail', 'DealerName',
    'DISTRICT', 'Edition', 'Email', 'LastProduct', 'NetPrice', 'Price', 'Tax', 'UsageType'
];

foreach ($required_fields as $field) {
    $lower_field = strtolower($field);
    $found = false;
    $value = '';
    $actual_key = '';
    
    // Check all case variations in data
    foreach ($email_data as $key => $val) {
        if (strtolower($key) === $lower_field) {
            $found = true;
            $value = $val;
            $actual_key = $key;
            break;
        }
    }
    
    if ($found) {
        $display_value = $value ? (strlen($value) > 40 ? substr($value, 0, 40) . '...' : $value) : '(EMPTY)';
        $status = $value ? '✓ HAS DATA' : '⚠ EMPTY';
        echo "$status - [[$field]] (DB: $actual_key) = $display_value\n";
    } else {
        echo "✗ NOT FOUND - [[$field]]\n";
    }
}

// Load the template
$template_file = '/opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/renewal_D-new.html';
if (!file_exists($template_file)) {
    die("\nTemplate file not found: $template_file\n");
}

$template_html = file_get_contents($template_file);

echo "\n=== Merging Template ===\n";
$merged_html = mergeTemplateWithData($template_html, $email_data);

// Check which placeholders were NOT filled
preg_match_all('/\[\[([^\]]+)\]\]/', $template_html, $original_matches);
preg_match_all('/\[\[([^\]]+)\]\]/', $merged_html, $remaining_matches);

$original_placeholders = array_unique($original_matches[1]);
$remaining_placeholders = $remaining_matches[1];

echo "Original placeholders: " . count($original_placeholders) . "\n";
echo "Remaining unfilled: " . count($remaining_placeholders) . "\n\n";

if (count($remaining_placeholders) > 0) {
    echo "❌ UNFILLED PLACEHOLDERS (not replaced):\n";
    $unique_remaining = array_unique($remaining_placeholders);
    foreach ($unique_remaining as $field) {
        echo "  - [[$field]]\n";
    }
    echo "\nThis is EXPECTED if the database field is empty or doesn't exist.\n";
} else {
    echo "✓ All placeholders were processed (replaced or removed)!\n";
}

// Show a snippet of merged result with customer info
echo "\n=== Merged Result (Customer Info Section) ===\n";
$snippet_start = strpos($merged_html, '<table width="450"');
if ($snippet_start !== false) {
    $snippet_end = strpos($merged_html, '</table>', $snippet_start) + 8;
    $snippet = substr($merged_html, $snippet_start, $snippet_end - $snippet_start);
    
    // Clean up for display
    $snippet = preg_replace('/\s+/', ' ', $snippet);
    $snippet = str_replace('><', ">\n<", $snippet);
    
    echo substr($snippet, 0, 800) . "\n...\n";
}

echo "\n=== Checking If Fields Are Actually Being Filled ===\n";

// Extract actual content between tags
if (preg_match('/<div>\[\[Company\]\], \[\[DISTRICT\]\]<\/div>/', $merged_html)) {
    echo "❌ PROBLEM: [[Company]] and [[DISTRICT]] NOT replaced in merged HTML!\n";
} else if (preg_match('/<div>([^<]+), ([^<]+)<\/div>/', $merged_html, $matches)) {
    echo "✓ Company and District section filled: '{$matches[1]}, {$matches[2]}'\n";
} else {
    echo "⚠ Could not find company/district section in output\n";
}

if (preg_match('/Email: <a[^>]*>\[\[Email\]\]<\/a>/', $merged_html)) {
    echo "❌ PROBLEM: [[Email]] NOT replaced in merged HTML!\n";
} else if (preg_match('/Email: <a[^>]*>([^<]+)<\/a>/', $merged_html, $matches)) {
    echo "✓ Email filled: '{$matches[1]}'\n";
} else {
    echo "⚠ Could not find email section in output\n";
}

echo "\n=== Test Complete ===\n";
