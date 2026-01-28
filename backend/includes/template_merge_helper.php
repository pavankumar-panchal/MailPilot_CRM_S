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
function loadMailTemplate($conn, $template_id, $user_id = null) {
    $template_id = intval($template_id);
    if ($template_id === 0) return null;
    
    // Add user filtering if user_id provided
    $userFilter = '';
    if ($user_id !== null) {
        $userFilter = " AND user_id = " . intval($user_id);
    }
    
    $stmt = $conn->prepare("SELECT template_html, merge_fields FROM mail_templates WHERE template_id = ? AND is_active = 1" . $userFilter);
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
            
            // Add common aliases for backward compatibility BEFORE case variants
            // This ensures aliases also get case-insensitive variants
            $row['Email'] = $row['Emails'] ?? '';  // Singular alias for Emails
            $row['Name'] = $row['BilledName'] ?? '';  // Short alias
            $row['Company'] = $row['Company'] ?? $row['Group Name'] ?? $row['BilledName'] ?? '';  // Multiple fallbacks
            
            // ============================================
            // CALCULATED FIELDS FOR MISSING DATA
            // ============================================
            
            // Calculate Price, Tax, NetPrice from Amount if missing
            if (empty($row['Price']) && !empty($row['Amount'])) {
                $row['Price'] = $row['Amount'];
            }
            
            if (!empty($row['Price']) && empty($row['Tax'])) {
                $row['Tax'] = round($row['Price'] * 0.18, 2); // 18% GST
            }
            
            if (!empty($row['Price']) && empty($row['NetPrice'])) {
                $tax = !empty($row['Tax']) ? $row['Tax'] : round($row['Price'] * 0.18, 2);
                $row['NetPrice'] = $row['Price'] + $tax;
            }
            
            // Default Edition if missing
            if (empty($row['Edition'])) {
                $row['Edition'] = 'Professional'; // Default edition
            }
            
            // Default UsageType if missing
            if (empty($row['UsageType'])) {
                $row['UsageType'] = 'Single User'; // Default usage
            }
            
            // Use Region/Place for District if District is empty
            if (empty($row['District'])) {
                $row['District'] = $row['Region'] ?? $row['Place'] ?? '';
            }
            
            // Use ExecutiveName for DealerName if empty
            if (empty($row['DealerName']) && !empty($row['ExecutiveName'])) {
                $row['DealerName'] = $row['ExecutiveName'];
            }
            
            // Use ExecutiveContact for DealerCell if empty
            if (empty($row['DealerCell']) && !empty($row['ExecutiveContact'])) {
                $row['DealerCell'] = $row['ExecutiveContact'];
            }
            
            // Generate DealerEmail from ExecutiveName if missing
            if (empty($row['DealerEmail'])) {
                if (!empty($row['ExecutiveName'])) {
                    // Create email from name: "Subramani M" -> "subramani.m@relyonsoft.com"
                    $name = strtolower(trim($row['ExecutiveName']));
                    $name = preg_replace('/\s+/', '.', $name); // Replace spaces with dots
                    $row['DealerEmail'] = $name . '@relyonsoft.com';
                } else {
                    $row['DealerEmail'] = 'sales@relyonsoft.com'; // Default fallback
                }
            }
            
            // Generate CustomerID if missing (use import batch + id)
            if (empty($row['CustomerID']) && !empty($row['id'])) {
                $row['CustomerID'] = 'CUST' . str_pad($row['id'], 6, '0', STR_PAD_LEFT);
            }
            
            // Set default LastProduct if empty
            if (empty($row['LastProduct'])) {
                $row['LastProduct'] = 'Saral TDS'; // Default product
            }
            
            // ============================================
            // END CALCULATED FIELDS
            // ============================================
            
            // Now add case variants for ALL fields including aliases
            // This allows [[Email]], [[EMAIL]], [[email]] to all work
            return $row;  // mergeTemplateWithData handles case-insensitivity
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
 * Get intelligent field mapping with fallbacks
 * Maps requested field to best available field in data
 * 
 * @param string $requested_field Field name from template placeholder
 * @param array $available_fields All available field names from database (lowercase)
 * @return string|null Best matching field name or null if not found
 */
function getIntelligentFieldMapping($requested_field, $available_fields) {
    $lower_requested = strtolower($requested_field);
    
    // Direct match
    if (in_array($lower_requested, $available_fields)) {
        return $lower_requested;
    }
    
    // Define intelligent field mappings and fallbacks
    $field_mappings = [
        // Email variations
        'email' => ['emails', 'email', 'emailid', 'email_address', 'billedemail'],
        'emails' => ['emails', 'email', 'emailid'],
        
        // Name variations
        'name' => ['billedname', 'name', 'customername', 'contactperson', 'company'],
        'customername' => ['billedname', 'customername', 'name', 'company'],
        'billedname' => ['billedname', 'name', 'company'],
        
        // Company variations
        'company' => ['company', 'group name', 'billedname', 'customername'],
        'companyname' => ['company', 'group name', 'billedname'],
        
        // Location fields
        'district' => ['district', 'place', 'city', 'region'],
        'city' => ['city', 'place', 'district'],
        'state' => ['state', 'region'],
        'address' => ['address', 'place', 'district'],
        
        // Customer ID
        'customerid' => ['customerid', 'customer_id', 'id', 'slno'],
        
        // Product fields
        'product' => ['lastproduct', 'product', 'productgroup', 'category'],
        'lastproduct' => ['lastproduct', 'product', 'productgroup'],
        'productname' => ['lastproduct', 'product', 'productgroup'],
        
        // Edition/Version
        'edition' => ['edition', 'version', 'type', 'category'],
        'version' => ['edition', 'version', 'type'],
        
        // Usage type
        'usagetype' => ['usagetype', 'type', 'category'],
        'type' => ['type', 'usagetype', 'category'],
        
        // Price fields
        'price' => ['price', 'amount', 'netprice'],
        'amount' => ['amount', 'price', 'netprice'],
        'netprice' => ['netprice', 'amount', 'price'],
        'tax' => ['tax', 'gst', 'taxamount'],
        
        // Invoice fields
        'billnumber' => ['billnumber', 'bill_number', 'invoicenumber', 'invoice_number'],
        'invoicenumber' => ['billnumber', 'invoicenumber', 'invoice_number'],
        'billdate' => ['billdate', 'bill_date', 'invoicedate', 'invoice_date'],
        'invoicedate' => ['billdate', 'invoicedate', 'invoice_date'],
        
        // Date fields
        'date' => ['billdate', 'date', 'lastregdate', 'currentdate'],
        'registrationdate' => ['lastregdate', 'billdate', 'date'],
        
        // Days
        'days' => ['days', 'daysoverdue', 'outstanding_days'],
        
        // Executive/Contact
        'executivename' => ['executivename', 'executive', 'salesname', 'dealername'],
        'executive' => ['executivename', 'executive', 'dealername'],
        'executivecontact' => ['executivecontact', 'executivecell', 'executivephone', 'executivemobile', 'dealercell'],
        'contactperson' => ['contactperson', 'executivename', 'dealername'],
        
        // Dealer fields
        'dealername' => ['dealername', 'dealer', 'executivename', 'salesname'],
        'dealeremail' => ['dealeremail', 'dealer_email', 'executiveemail'],
        'dealercell' => ['dealercell', 'dealerphone', 'executivecontact', 'executivecell'],
        'dealerphone' => ['dealercell', 'dealerphone', 'executivecontact', 'phone'],
        
        // Phone fields
        'phone' => ['phone', 'cell', 'mobile', 'contact'],
        'cell' => ['cell', 'phone', 'mobile'],
        'mobile' => ['cell', 'mobile', 'phone'],
        'contact' => ['executivecontact', 'contact', 'phone', 'cell'],
        
        // License fields
        'licenses' => ['lastlicenses', 'licenses', 'licensecount'],
        'year' => ['lastyear', 'year'],
    ];
    
    // Check if we have a mapping for this field
    if (isset($field_mappings[$lower_requested])) {
        foreach ($field_mappings[$lower_requested] as $fallback) {
            if (in_array($fallback, $available_fields)) {
                return $fallback;
            }
        }
    }
    
    // Try partial matching (contains)
    foreach ($available_fields as $available) {
        // Check if requested field is contained in available field
        if (strpos($available, $lower_requested) !== false) {
            return $available;
        }
        // Check if available field is contained in requested field
        if (strpos($lower_requested, $available) !== false && strlen($available) > 3) {
            return $available;
        }
    }
    
    return null;
}

/**
 * Merge template with email data
 * Replaces [[FieldName]] placeholders with actual CSV column values
 * Case-insensitive matching with intelligent field mapping and fallbacks
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
    
    // Create a case-insensitive lookup map - ONLY include fields with actual data
    $lookupMap = [];
    $availableFieldsLower = [];  // Fields that have data
    
    foreach ($email_data as $key => $value) {
        // Skip system fields
        if (in_array(strtolower($key), ['id', 'domain_verified', 'domain_status', 'validation_response', 'domain_processed', 'validation_status', 'worker_id', 'slno', 'import_batch_id', 'import_filename', 'source_file_type', 'imported_at', 'is_active', 'extra_data'])) {
            continue;
        }
        
        $lowerKey = strtolower($key);
        // Store with lowercase key for case-insensitive lookup
        $lookupMap[$lowerKey] = $value;
        
        // Only add to available list if it has data
        if ($value !== null && $value !== '') {
            $availableFieldsLower[] = $lowerKey;
        }
    }
    
    // Find all placeholders and replace them with intelligent mapping
    $template_html = preg_replace_callback(
        '/\[\[([^\]]+)\]\]/',
        function($matches) use ($lookupMap, $availableFieldsLower) {
            $fieldName = $matches[1];
            $lowerFieldName = strtolower($fieldName);
            
            // Try direct match first - but only if it has actual data
            $directValue = $lookupMap[$lowerFieldName] ?? null;
            if ($directValue !== null && $directValue !== '') {
                return htmlspecialchars($directValue, ENT_QUOTES, 'UTF-8');
            }
            
            // Direct match was empty or doesn't exist - try intelligent fallbacks
            $mappedField = getIntelligentFieldMapping($fieldName, $availableFieldsLower);
            
            // Make sure mapped field is different from original (avoid infinite loop)
            // and has actual data
            if ($mappedField && $mappedField !== $lowerFieldName) {
                $fallbackValue = $lookupMap[$mappedField] ?? null;
                if ($fallbackValue !== null && $fallbackValue !== '') {
                    return htmlspecialchars($fallbackValue, ENT_QUOTES, 'UTF-8');
                }
            }
            
            // No data found in direct match or fallbacks - remove placeholder
            return '';
        },
        $template_html
    );
    
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
