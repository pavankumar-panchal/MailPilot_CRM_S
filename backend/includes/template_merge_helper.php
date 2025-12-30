<?php
/**
 * Template Merge Helper
 * Functions to merge mail templates with CSV data during campaign sending
 */

/**
 * Load template for campaign
 * 
 * @param mysqli $conn Database connection
 * @param int $template_id Template ID
 * @return array|null Template data or null if not found
 */
function loadMailTemplate($conn, $template_id) {
    $template_id = intval($template_id);
    if ($template_id === 0) return null;
    
    $stmt = $conn->prepare("SELECT template_html, merge_fields FROM mail_templates WHERE template_id = ? AND is_active = 1");
    $stmt->bind_param("i", $template_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) return null;
    
    $template = $result->fetch_assoc();
    $template['merge_fields'] = json_decode($template['merge_fields'], true);
    
    return $template;
}

/**
 * Get CSV row data for email address
 * Supports both csv_emails table and imported_recipients table
 * 
 * @param mysqli $conn Database connection
 * @param string $email Email address
 * @param int $csv_list_id CSV list ID
 * @param string $import_batch_id Import batch ID (for imported_recipients)
 * @return array Email row data with all columns
 */
function getEmailRowData($conn, $email, $csv_list_id = null, $import_batch_id = null) {
    $email_escaped = $conn->real_escape_string($email);
    
    // First, try imported_recipients if batch_id is provided
    if ($import_batch_id) {
        $batch_escaped = $conn->real_escape_string($import_batch_id);
        $query = "SELECT * FROM imported_recipients 
                  WHERE Emails = '$email_escaped' 
                  AND import_batch_id = '$batch_escaped' 
                  AND is_active = 1 
                  LIMIT 1";
        
        $result = $conn->query($query);
        
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            
            // Merge extra_data JSON into main array
            if (isset($row['extra_data']) && $row['extra_data']) {
                $extraData = json_decode($row['extra_data'], true);
                if (is_array($extraData)) {
                    $row = array_merge($row, $extraData);
                }
            }
            
            // Add common aliases for backward compatibility
            if (!isset($row['Email']) && isset($row['Emails'])) {
                $row['Email'] = $row['Emails'];
            }
            if (!isset($row['Name']) && isset($row['BilledName'])) {
                $row['Name'] = $row['BilledName'];
            }
            if (!isset($row['Company']) && isset($row['Group Name'])) {
                $row['Company'] = $row['Group Name'];
            }
            
            return $row;
        }
    }
    
    // Fall back to emails table (CSV upload)
    $csv_list_id = intval($csv_list_id);
    $query = "SELECT * FROM emails WHERE raw_emailid = '$email_escaped'";
    if ($csv_list_id > 0) {
        $query .= " AND csv_list_id = $csv_list_id";
    }
    $query .= " LIMIT 1";
    
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return [];
}

/**
 * Merge template with email data
 * Replaces [[FieldName]] placeholders with actual CSV column values
 * 
 * @param string $template_html HTML template with [[placeholders]]
 * @param array $email_data Row data from emails table
 * @return string Merged HTML with replaced values
 */
function mergeTemplateWithData($template_html, $email_data) {
    // Add current date if not in data
    if (!isset($email_data['CurrentDate'])) {
        $email_data['CurrentDate'] = date('F jS, Y');
    }
    
    // Replace all [[FieldName]] placeholders
    foreach ($email_data as $key => $value) {
        // Skip system fields
        if (in_array($key, ['id', 'domain_verified', 'domain_status', 'validation_response', 'domain_processed', 'validation_status', 'worker_id'])) {
            continue;
        }
        
        $placeholder = '[[' . $key . ']]';
        $safe_value = htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
        $template_html = str_replace($placeholder, $safe_value, $template_html);
    }
    
    // Replace any remaining unfilled placeholders with empty string
    $template_html = preg_replace('/\[\[[^\]]+\]\]/', '', $template_html);
    
    return $template_html;
}

/**
 * Process campaign body - use template if available, otherwise use regular mail_body
 * This function integrates template merging into the email sending workflow
 * 
 * @param mysqli $conn Database connection
 * @param array $campaign Campaign data
 * @param string $to_email Recipient email address
 * @param int|null $csv_list_id CSV list ID
 * @return string Final email body (HTML or text)
 */
function processCampaignBody($conn, $campaign, $to_email, $csv_list_id = null) {
    $template_id = isset($campaign['template_id']) ? intval($campaign['template_id']) : 0;
    $import_batch_id = isset($campaign['import_batch_id']) ? $campaign['import_batch_id'] : null;
    
    // If no template, return regular mail_body
    if ($template_id === 0) {
        return $campaign['mail_body'];
    }
    
    // Load template
    $template = loadMailTemplate($conn, $template_id);
    if (!$template) {
        // Template not found or inactive, fall back to mail_body
        return $campaign['mail_body'];
    }
    
    // Get data for this email (from imported_recipients or emails table)
    $email_data = getEmailRowData($conn, $to_email, $csv_list_id, $import_batch_id);
    
    // Merge template with data
    $merged_html = mergeTemplateWithData($template['template_html'], $email_data);
    
    return $merged_html;
}
