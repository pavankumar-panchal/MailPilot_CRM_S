<?php
/**
 * Mail Templates API
 * Manages HTML email templates with merge fields
 */

// Use centralized session configuration
require_once __DIR__ . '/session_config.php';
require_once __DIR__ . '/security_helpers.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/user_filtering.php';
require_once __DIR__ . '/auth_helper.php';

// Set security headers
setSecurityHeaders();

// Handle CORS securely
handleCors();

// Ensure user_id columns exist
ensureUserIdColumns($conn);

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

try {
    switch ($method) {
        case 'GET':
            if ($action === 'list') {
                listTemplates($conn);
            } elseif ($action === 'get') {
                getTemplate($conn);
            } elseif ($action === 'merge_fields') {
                getMergeFields($conn);
            } else {
                throw new Exception('Invalid action');
            }
            break;
            
        case 'POST':
            if ($action === 'create') {
                createTemplate($conn);
            } elseif ($action === 'merge_preview') {
                mergePreview($conn);
            } else {
                throw new Exception('Invalid action');
            }
            break;
            
        case 'PUT':
            if ($action === 'update') {
                updateTemplate($conn);
            } else {
                throw new Exception('Invalid action');
            }
            break;
            
        case 'DELETE':
            if ($action === 'delete') {
                deleteTemplate($conn);
            } else {
                throw new Exception('Invalid action');
            }
            break;
            
        default:
            throw new Exception('Method not allowed');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();

/**
 * List all templates
 */
function listTemplates($conn) {
    // Use unified auth
    $currentUser = requireAuth();
    $userFilter = getAuthFilterWhere('mail_templates');
    
    $query = "SELECT template_id, template_name, template_description, merge_fields, 
              is_active, created_at, updated_at, 
              LENGTH(template_html) as html_length
              FROM mail_templates 
              $userFilter
              ORDER BY is_active DESC, template_name ASC";
    
    $result = $conn->query($query);
    
    if (!$result) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $templates = [];
    while ($row = $result->fetch_assoc()) {
        $row['merge_fields'] = json_decode($row['merge_fields'], true);
        $templates[] = $row;
    }
    
    echo json_encode(['success' => true, 'templates' => $templates]);
}

/**
 * Get single template
 */
function getTemplate($conn) {
    $template_id = isset($_GET['template_id']) ? intval($_GET['template_id']) : 0;
    
    if ($template_id === 0) {
        throw new Exception('Invalid template ID');
    }
    
    // Add user filtering for security
    $currentUser = requireAuth();
    $isAdmin = isAuthenticatedAdmin();
    $userFilter = $isAdmin ? '' : ' AND user_id = ' . intval($currentUser['id']);
    
    $stmt = $conn->prepare("SELECT * FROM mail_templates WHERE template_id = ?" . $userFilter);
    $stmt->bind_param("i", $template_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Template not found or access denied');
    }
    
    $template = $result->fetch_assoc();
    $template['merge_fields'] = json_decode($template['merge_fields'], true);
    
    echo json_encode(['success' => true, 'template' => $template]);
}

/**
 * Fix image paths - convert local image references to CDN URLs
 */
function fixImagePaths($html) {
    // Replace local image files with CDN URLs for social media icons
    $replacements = [
        'facebook-icon.jpg' => 'https://cdn-icons-png.flaticon.com/512/124/124010.png',
        'twitter-icon.jpg' => 'https://cdn-icons-png.flaticon.com/512/124/124021.png',
        'linkedin-icon.jpg' => 'https://cdn-icons-png.flaticon.com/512/124/124011.png',
    ];
    
    foreach ($replacements as $local => $cdn) {
        // Replace src="filename" with src="cdn-url"
        $html = str_replace('src="' . $local . '"', 'src="' . $cdn . '"', $html);
        // Also handle src='filename'
        $html = str_replace("src='" . $local . "'", "src='" . $cdn . "'", $html);
    }
    
    return $html;
}

/**
 * Create new template
 */
function createTemplate($conn) {
    // Use unified auth
    $currentUser = requireAuth();
    error_log("mail_templates.php createTemplate - User: " . json_encode($currentUser));
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $template_name = isset($data['template_name']) ? trim($data['template_name']) : '';
    $template_description = isset($data['template_description']) ? trim($data['template_description']) : '';
    $template_html = isset($data['template_html']) ? $data['template_html'] : '';
    $merge_fields = isset($data['merge_fields']) ? $data['merge_fields'] : [];
    
    if (empty($template_name) || empty($template_html)) {
        throw new Exception('Template name and HTML are required');
    }
    
    // Fix image paths automatically
    $template_html = fixImagePaths($template_html);
    
    // Auto-detect merge fields from HTML if not provided
    if (empty($merge_fields)) {
        $merge_fields = extractMergeFields($template_html);
    }
    
    $merge_fields_json = json_encode($merge_fields);
    
    // Get user ID (already verified above)
    $user_id = $currentUser['id'];
    
    $stmt = $conn->prepare("INSERT INTO mail_templates (template_name, template_description, template_html, merge_fields, user_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssi", $template_name, $template_description, $template_html, $merge_fields_json, $user_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to create template: ' . $stmt->error);
    }
    
    $template_id = $stmt->insert_id;
    
    echo json_encode([
        'success' => true, 
        'message' => 'Template created successfully',
        'template_id' => $template_id,
        'merge_fields' => $merge_fields
    ]);
}

/**
 * Update existing template
 */
function updateTemplate($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $template_id = isset($data['template_id']) ? intval($data['template_id']) : 0;
    
    if ($template_id === 0) {
        throw new Exception('Template ID is required');
    }
    
    // Check user access
    if (!isAdmin()) {
        $checkStmt = $conn->prepare("SELECT user_id FROM mail_templates WHERE template_id = ?");
        $checkStmt->bind_param("i", $template_id);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $templateData = $checkResult->fetch_assoc();
        $checkStmt->close();
        
        if (!$templateData || !canAccessRecord($templateData['user_id'])) {
            throw new Exception('Access denied');
        }
    }
    
    $template_name = isset($data['template_name']) ? trim($data['template_name']) : '';
    $template_description = isset($data['template_description']) ? trim($data['template_description']) : '';
    $template_html = isset($data['template_html']) ? $data['template_html'] : '';
    $merge_fields = isset($data['merge_fields']) ? $data['merge_fields'] : [];
    $is_active = isset($data['is_active']) ? intval($data['is_active']) : 1;
    
    if ($template_id === 0 || empty($template_name) || empty($template_html)) {
        throw new Exception('Template ID, name and HTML are required');
    }
    
    // Fix image paths automatically
    $template_html = fixImagePaths($template_html);
    
    // Auto-detect merge fields from HTML if not provided
    if (empty($merge_fields)) {
        $merge_fields = extractMergeFields($template_html);
    }
    
    $merge_fields_json = json_encode($merge_fields);
    
    $stmt = $conn->prepare("UPDATE mail_templates SET template_name = ?, template_description = ?, template_html = ?, merge_fields = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE template_id = ?");
    $stmt->bind_param("ssssii", $template_name, $template_description, $template_html, $merge_fields_json, $is_active, $template_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update template: ' . $stmt->error);
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Template updated successfully',
        'merge_fields' => $merge_fields
    ]);
}

/**
 * Delete template
 */
function deleteTemplate($conn) {
    $template_id = isset($_GET['template_id']) ? intval($_GET['template_id']) : 0;
    
    if ($template_id === 0) {
        throw new Exception('Invalid template ID');
    }
    
    // Check user access
    if (!isAdmin()) {
        $checkStmt = $conn->prepare("SELECT user_id FROM mail_templates WHERE template_id = ?");
        $checkStmt->bind_param("i", $template_id);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $templateData = $checkResult->fetch_assoc();
        $checkStmt->close();
        
        if (!$templateData || !canAccessRecord($templateData['user_id'])) {
            throw new Exception('Access denied');
        }
    }
    
    // Check if template is used in any campaigns
    $check = $conn->query("SELECT COUNT(*) as count FROM campaign_master WHERE template_id = $template_id");
    $count = $check->fetch_assoc()['count'];
    
    if ($count > 0) {
        throw new Exception("Cannot delete template: it is used in $count campaign(s)");
    }
    
    $stmt = $conn->prepare("DELETE FROM mail_templates WHERE template_id = ?");
    $stmt->bind_param("i", $template_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to delete template: ' . $stmt->error);
    }
    
    echo json_encode(['success' => true, 'message' => 'Template deleted successfully']);
}

/**
 * Preview merged template with sample data
 */
function mergePreview($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $template_html = isset($data['template_html']) ? $data['template_html'] : '';
    $merge_data = isset($data['merge_data']) ? $data['merge_data'] : [];
    $import_batch_id = isset($data['import_batch_id']) ? $data['import_batch_id'] : null;
    $csv_list_id = isset($data['csv_list_id']) ? intval($data['csv_list_id']) : null;
    
    if (empty($template_html)) {
        throw new Exception('Template HTML is required');
    }
    
    // Try to get real data from database if batch_id or csv_list_id is provided
    $realData = [];
    
    if ($import_batch_id) {
        // Get first row from imported_recipients
        $batch_escaped = $conn->real_escape_string($import_batch_id);
        $query = "SELECT * FROM imported_recipients 
                  WHERE import_batch_id = '$batch_escaped' 
                  AND is_active = 1 
                  LIMIT 1";
        $result = $conn->query($query);
        
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            
            // Merge extra_data JSON if exists
            if (!empty($row['extra_data'])) {
                $extraData = json_decode($row['extra_data'], true);
                if (is_array($extraData)) {
                    $row = array_merge($row, $extraData);
                }
            }
            
            // Map all Excel columns to template fields
            $realData = array_filter($row, function($value) {
                return $value !== null && $value !== '';
            });
            
            // Add common aliases
            if (isset($row['Group Name'])) $realData['Company'] = $row['Group Name'];
            if (isset($row['BilledName'])) $realData['Name'] = $row['BilledName'];
            if (isset($row['Emails'])) $realData['Email'] = $row['Emails'];
            
            // Add case variants for all fields
            $caseVariants = [];
            foreach ($realData as $key => $value) {
                $caseVariants[$key] = $value;
                $caseVariants[strtoupper($key)] = $value;
                $caseVariants[strtolower($key)] = $value;
            }
            $realData = $caseVariants;
        }
    } elseif ($csv_list_id > 0) {
        // Get first row from emails table
        $query = "SELECT * FROM emails WHERE csv_list_id = $csv_list_id LIMIT 1";
        $result = $conn->query($query);
        
        if ($result && $result->num_rows > 0) {
            $realData = $result->fetch_assoc();
        }
    }
    
    // Use real data if available, otherwise use provided merge_data
    $finalData = !empty($realData) ? $realData : $merge_data;
    
    $merged_html = mergeTemplate($template_html, $finalData);
    
    echo json_encode([
        'success' => true, 
        'merged_html' => $merged_html,
        'merge_fields_found' => extractMergeFields($template_html),
        'data_source' => !empty($realData) ? 'database' : 'sample',
        'fields_used' => array_keys($finalData)
    ]);
}

/**
 * Get available merge fields for CSV data
 */
function getMergeFields($conn) {
    $csv_list_id = isset($_GET['csv_list_id']) ? intval($_GET['csv_list_id']) : 0;
    
    if ($csv_list_id === 0) {
        echo json_encode(['success' => true, 'merge_fields' => []]);
        return;
    }
    
    // Get first row of CSV to extract column names
    $query = "SELECT * FROM emails WHERE csv_list_id = $csv_list_id LIMIT 1";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $fields = array_keys($row);
        // Filter out system fields
        $fields = array_filter($fields, function($f) {
            return !in_array($f, ['id', 'domain_verified', 'domain_status', 'validation_response', 'domain_processed', 'validation_status', 'worker_id']);
        });
        
        echo json_encode(['success' => true, 'merge_fields' => array_values($fields)]);
    } else {
        echo json_encode(['success' => true, 'merge_fields' => []]);
    }
}

/**
 * Extract merge field placeholders from HTML
 * Finds patterns like [[FieldName]]
 */
function extractMergeFields($html) {
    $fields = [];
    preg_match_all('/\[\[([^\]]+)\]\]/', $html, $matches);
    
    if (!empty($matches[1])) {
        $fields = array_unique($matches[1]);
        sort($fields);
    }
    
    return array_values($fields);
}

/**
 * Merge template with data
 * Replaces [[FieldName]] with actual values
 * Uses intelligent field mapping from template_merge_helper.php
 */
function mergeTemplate($html, $data) {
    // Add current date if not provided
    if (!isset($data['CurrentDate'])) {
        $data['CurrentDate'] = date('F jS, Y');
    }
    
    // Use the intelligent merge function from template_merge_helper
    require_once __DIR__ . '/template_merge_helper.php';
    return mergeTemplateWithData($html, $data);
}
