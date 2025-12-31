<?php
/**
 * Test Second Template (Final-Naveen.html) Merge
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/template_merge_helper.php';

echo "=== Testing Final-Naveen.html Template ===\n\n";

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

echo "=== Available Database Fields ===\n";
$required_fields = ['Amount', 'Days', 'BilledName', 'BillNumber', 'BillDate', 'ExecutiveName', 'ExecutiveContact'];

foreach ($required_fields as $field) {
    $lower_field = strtolower($field);
    $found = false;
    $value = '';
    
    // Check all case variations in data
    foreach ($email_data as $key => $val) {
        if (strtolower($key) === $lower_field) {
            $found = true;
            $value = $val;
            break;
        }
    }
    
    $status = $found ? '✓ FOUND' : '✗ NOT FOUND';
    $display_value = $value ? (strlen($value) > 50 ? substr($value, 0, 50) . '...' : $value) : '(empty)';
    echo "$status - [[$field]]: $display_value\n";
}

echo "\n=== ALL Available Fields (first 50) ===\n";
$count = 0;
foreach ($email_data as $key => $value) {
    if ($count++ < 50) {
        $display_value = $value ? (strlen($value) > 40 ? substr($value, 0, 40) . '...' : $value) : '(empty)';
        echo "  $key = $display_value\n";
    }
}

// Load the template
$template_file = '/opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/Final -Naveen.html';
if (!file_exists($template_file)) {
    die("\nTemplate file not found: $template_file\n");
}

$template_html = file_get_contents($template_file);

echo "\n=== Template Placeholders ===\n";
preg_match_all('/\[\[([^\]]+)\]\]/', $template_html, $matches);
$placeholders = array_unique($matches[1]);
sort($placeholders);

foreach ($placeholders as $placeholder) {
    echo "  - [[$placeholder]]\n";
}

echo "\n=== Merging Template ===\n";
$merged_html = mergeTemplateWithData($template_html, $email_data);

// Check which placeholders were NOT filled
preg_match_all('/\[\[([^\]]+)\]\]/', $merged_html, $remaining_matches);
$remaining = $remaining_matches[1];

echo "Original placeholders: " . count($placeholders) . "\n";
echo "Remaining unfilled: " . count($remaining) . "\n";

if (count($remaining) > 0) {
    echo "\n❌ UNFILLED PLACEHOLDERS:\n";
    foreach ($remaining as $field) {
        echo "  - [[$field]]\n";
        
        // Try to find similar fields in data
        $lower_field = strtolower($field);
        echo "    Looking for case-insensitive match for '$field'...\n";
        $found = false;
        foreach ($email_data as $key => $val) {
            if (strtolower($key) === $lower_field) {
                $found = true;
                echo "    → Found as '$key' with value: " . ($val ? $val : '(empty)') . "\n";
                break;
            }
        }
        if (!$found) {
            echo "    → NOT FOUND in database\n";
            
            // Suggest similar fields
            $similar = [];
            foreach ($email_data as $key => $val) {
                if (stripos($key, substr($field, 0, 4)) !== false) {
                    $similar[] = $key;
                }
            }
            if (!empty($similar)) {
                echo "    → Similar fields: " . implode(', ', $similar) . "\n";
            }
        }
    }
} else {
    echo "\n✓ All placeholders filled successfully!\n";
}

// Show a snippet of merged result
echo "\n=== Merged Result (snippet) ===\n";
$snippet_start = strpos($merged_html, '<strong>The outstanding details');
if ($snippet_start !== false) {
    $snippet = substr($merged_html, $snippet_start, 500);
    echo $snippet . "\n...\n";
}

echo "\n=== Test Complete ===\n";
