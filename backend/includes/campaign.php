<?php
error_log("=== campaign.php ENTRY === Method: " . $_SERVER['REQUEST_METHOD'] . ", URI: " . $_SERVER['REQUEST_URI']);
error_log("campaign.php - GET params: " . json_encode($_GET));
error_log("campaign.php - POST data size: " . strlen(file_get_contents('php://input')));

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

// Ensure campaign_master has required columns for templates.
// If missing, add them with safe defaults. This migration runs on-demand
// to keep backwards-compatibility with existing databases.
try {
    $dbNameRes = $conn->query("SELECT DATABASE() as db");
    $dbName = $dbNameRes ? $dbNameRes->fetch_assoc()['db'] : '';
    if ($dbName) {
        // Check and add send_as_html
        $colCheckSql = "SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '" . $conn->real_escape_string($dbName) . "' AND TABLE_NAME = 'campaign_master' AND COLUMN_NAME = 'send_as_html'";
        $colCheck = $conn->query($colCheckSql);
        if ($colCheck) {
            $hasCol = (int)$colCheck->fetch_assoc()['cnt'] > 0;
            if (!$hasCol) {
                @$conn->query("ALTER TABLE campaign_master ADD COLUMN send_as_html TINYINT(1) NOT NULL DEFAULT 0");
            }
        }
        
        // Check and add template_id
        $colCheckSql = "SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '" . $conn->real_escape_string($dbName) . "' AND TABLE_NAME = 'campaign_master' AND COLUMN_NAME = 'template_id'";
        $colCheck = $conn->query($colCheckSql);
        if ($colCheck) {
            $hasCol = (int)$colCheck->fetch_assoc()['cnt'] > 0;
            if (!$hasCol) {
                @$conn->query("ALTER TABLE campaign_master ADD COLUMN template_id INT NULL DEFAULT NULL");
            }
        }
        
        // Check and add import_batch_id
        $colCheckSql = "SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '" . $conn->real_escape_string($dbName) . "' AND TABLE_NAME = 'campaign_master' AND COLUMN_NAME = 'import_batch_id'";
        $colCheck = $conn->query($colCheckSql);
        if ($colCheck) {
            $hasCol = (int)$colCheck->fetch_assoc()['cnt'] > 0;
            if (!$hasCol) {
                @$conn->query("ALTER TABLE campaign_master ADD COLUMN import_batch_id VARCHAR(100) NULL DEFAULT NULL");
            }
        }
    }
} catch (Exception $e) {
    // Log but don't fail - keeps API compatible on restricted environments
    error_log("Campaign schema migration warning: " . $e->getMessage());
}

// Handle preflight (CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

// Helper: get input data for PUT (not used for POST with files)
function getInputData()
{
    $input = file_get_contents('php://input');
    return json_decode($input, true) ?? [];
}

try {
    // GET /api/master/campaigns or /api/master/campaigns?id=1
    if ($method === 'GET') {
        // Require authentication
        $currentUser = requireAuth();
        error_log("campaign.php GET - User: " . json_encode($currentUser));
        
        if (isset($_GET['id'])) {
            $id = intval($_GET['id']);
            
            // Check user access
            $userFilter = getAuthFilterAnd();
            $stmt = $conn->prepare("SELECT * FROM campaign_master WHERE campaign_id = ? $userFilter");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            if ($row) {
                // Use attachment_path only
                $row['has_attachment'] = !empty($row['attachment_path']);
                // Normalize any escaped sequences (e.g. "\r\n") that may have
                // been stored as literal backslash sequences so the client gets
                // real newlines. Use stripcslashes() which converts C-like
                // escape sequences into their character equivalents. If the
                // stored content already contains real newlines this is a no-op.
                $row['mail_body'] = isset($row['mail_body']) ? stripcslashes($row['mail_body']) : $row['mail_body'];
                echo json_encode($row);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Campaign not found']);
            }
            exit;
        } else {
            $userFilter = getAuthFilterWhere();
            error_log("campaign.php GET - User filter: $userFilter");
            
            $result = $conn->query("SELECT * FROM campaign_master $userFilter ORDER BY campaign_id DESC");
            $campaigns = [];
            while ($row = $result->fetch_assoc()) {
                // Add preview (first 30 words)
                    // Add preview (first 30 words) from plain-text version (strip HTML tags)
                    $textOnly = trim(strip_tags($row['mail_body']));
                    $words = preg_split('/\s+/', $textOnly);
                    $preview = implode(' ', array_slice($words, 0, 30));
                if (count($words) > 30) $preview .= '...';
                $row['mail_body_preview'] = $preview;
                // Add attachment indicator for list view
                $row['has_attachment'] = !empty($row['attachment_path']);
                // Normalize escaped sequences in mail_body for consistent
                // client-side editing (turn "\r\n" into real newlines).
                $row['mail_body'] = isset($row['mail_body']) ? stripcslashes($row['mail_body']) : $row['mail_body'];
                // Return stored mail_body as-is (now normalized). For preview,
                // strip tags to create a plain-text snippet.
                $campaigns[] = $row;
            }
            
            error_log("campaign.php GET - Found " . count($campaigns) . " campaigns");
            
            echo json_encode($campaigns);
            exit;
        }
    }

    // POST /api/master/campaigns (create or update)
    if ($method === 'POST') {
        $isJson = isset($_SERVER['CONTENT_TYPE']) && stripos($_SERVER['CONTENT_TYPE'], 'application/json') !== false;
        $hasId = isset($_GET['id']);
        
        error_log("campaign.php POST - hasId: " . ($hasId ? 'YES' : 'NO') . ", isJson: " . ($isJson ? 'YES' : 'NO'));
        error_log("campaign.php POST - Query string: " . ($_SERVER['QUERY_STRING'] ?? 'none'));
        error_log("campaign.php POST - GET params: " . json_encode($_GET));

        // PRIORITY: Handle CSV list only update (JSON with id parameter)
        if ($hasId && $isJson) {
            $id = intval($_GET['id']);
            $input_raw = file_get_contents('php://input');
            $data = json_decode($input_raw, true) ?? [];
            
            error_log("campaign.php - JSON Update detected - ID: $id");
            error_log("campaign.php - JSON Data: $input_raw");
            error_log("campaign.php - Data keys: " . implode(', ', array_keys($data)));
            
            // Check if this is ONLY a CSV list update
            $keys = array_keys($data);
            $isOnlyCsvListUpdate = (count($keys) === 1 && $keys[0] === 'csv_list_id');
            
            if ($isOnlyCsvListUpdate) {
                error_log("campaign.php - CSV List ONLY update detected");
                
                // Check user access
                if (!isAuthenticatedAdmin()) {
                    $checkStmt = $conn->prepare("SELECT user_id FROM campaign_master WHERE campaign_id = ?");
                    $checkStmt->bind_param("i", $id);
                    $checkStmt->execute();
                    $checkResult = $checkStmt->get_result();
                    $campaignData = $checkResult->fetch_assoc();
                    $checkStmt->close();
                    
                    if (!$campaignData) {
                        error_log("campaign.php - Campaign #$id not found");
                        http_response_code(404);
                        echo json_encode(['success' => false, 'message' => 'Campaign not found']);
                        exit;
                    }
                    
                    if (!canAccessRecord($campaignData['user_id'])) {
                        error_log("campaign.php - Access denied for campaign #$id");
                        http_response_code(403);
                        echo json_encode(['success' => false, 'message' => 'Access denied']);
                        exit;
                    }
                }
                
                $csv_list_id = $data['csv_list_id'] !== '' && $data['csv_list_id'] !== null ? (int)$data['csv_list_id'] : null;
                error_log("Updating csv_list_id to: " . ($csv_list_id === null ? 'NULL' : $csv_list_id));
                
                $currentUserData = getAuthenticatedUser();
                $userId = $currentUserData['id'];
                $isAdminUser = isAuthenticatedAdmin();
                
                if ($csv_list_id === null) {
                    if ($isAdminUser) {
                        $stmt = $conn->prepare("UPDATE campaign_master SET csv_list_id=NULL WHERE campaign_id=?");
                        $stmt->bind_param('i', $id);
                    } else {
                        $stmt = $conn->prepare("UPDATE campaign_master SET csv_list_id=NULL WHERE campaign_id=? AND user_id=?");
                        $stmt->bind_param('ii', $id, $userId);
                    }
                } else {
                    if ($isAdminUser) {
                        $stmt = $conn->prepare("UPDATE campaign_master SET csv_list_id=? WHERE campaign_id=?");
                        $stmt->bind_param('ii', $csv_list_id, $id);
                    } else {
                        $stmt = $conn->prepare("UPDATE campaign_master SET csv_list_id=? WHERE campaign_id=? AND user_id=?");
                        $stmt->bind_param('iii', $csv_list_id, $id, $userId);
                    }
                }
                
                if ($stmt->execute()) {
                    $affectedRows = $stmt->affected_rows;
                    if ($affectedRows > 0) {
                        error_log("CSV list update SUCCESS for campaign #$id");
                        echo json_encode(['success' => true, 'message' => 'CSV list updated successfully!']);
                    } else {
                        error_log("CSV list update - No rows affected");
                        echo json_encode(['success' => true, 'message' => 'CSV list value unchanged']);
                    }
                } else {
                    error_log("CSV list update FAILED: " . $conn->error);
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'Error updating campaign: ' . $conn->error]);
                }
                $stmt->close();
                exit;
            }
            // If not CSV list only, continue to full JSON update below
        }

        // UPDATE via multipart/form-data (or when _method=PUT is provided)
        if ($hasId && ((isset($_POST['_method']) && $_POST['_method'] === 'PUT') || !$isJson)) {
            $id = intval($_GET['id']);
            
            error_log("Campaign UPDATE - ID: $id, _method: " . ($_POST['_method'] ?? 'not set') . ", isJson: " . ($isJson ? 'true' : 'false'));
            
            // Check user access using authenticated user
            if (!isAuthenticatedAdmin()) {
                $checkStmt = $conn->prepare("SELECT user_id FROM campaign_master WHERE campaign_id = ?");
                $checkStmt->bind_param("i", $id);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                $campaignData = $checkResult->fetch_assoc();
                $checkStmt->close();
                
                if (!$campaignData || !canAccessRecord($campaignData['user_id'])) {
                    error_log("Campaign UPDATE - Access denied for campaign #$id");
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Access denied']);
                    exit;
                }
            }
            
            $description = $conn->real_escape_string($_POST['description'] ?? '');
            $mail_subject = $conn->real_escape_string($_POST['mail_subject'] ?? '');
            $mail_body = $conn->real_escape_string($_POST['mail_body'] ?? '');
            $send_as_html = isset($_POST['send_as_html']) ? (int)$_POST['send_as_html'] : 0;
            $template_id = isset($_POST['template_id']) && $_POST['template_id'] !== '' ? (int)$_POST['template_id'] : null;
            $import_batch_id = isset($_POST['import_batch_id']) && $_POST['import_batch_id'] !== '' ? $conn->real_escape_string($_POST['import_batch_id']) : null;
            $attachment_path = null;
            $images_paths = [];

            // Handle attachment upload
            if (isset($_FILES['attachment'])) {
                if ($_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = __DIR__ . '/../storage/attachments/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    $filename = uniqid() . '_' . basename($_FILES['attachment']['name']);
                    $targetPath = $uploadDir . $filename;
                    if (move_uploaded_file($_FILES['attachment']['tmp_name'], $targetPath)) {
                        $attachment_path = 'storage/attachments/' . $filename;
                        chmod($targetPath, 0644);
                    } else {
                        error_log("Failed to move uploaded file to: " . $targetPath);
                    }
                } else if (isset($_FILES['attachment']['error'])) {
                    error_log("File upload error: " . $_FILES['attachment']['error']);
                }
            }

            // Handle multiple images upload (legacy)
            if (isset($_FILES['images']) && is_array($_FILES['images']['name'])) {
                $uploadDir = __DIR__ . '/../storage/images/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $fileCount = count($_FILES['images']['name']);
                for ($i = 0; $i < $fileCount; $i++) {
                    if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
                        $filename = uniqid() . '_' . basename($_FILES['images']['name'][$i]);
                        $targetPath = $uploadDir . $filename;
                        if (move_uploaded_file($_FILES['images']['tmp_name'][$i], $targetPath)) {
                            $images_paths[] = 'storage/images/' . $filename;
                            chmod($targetPath, 0644);
                        }
                    }
                }
            }

            // Handle images uploaded via Quill editor (sent as JSON array)
            if (isset($_POST['images_json'])) {
                $quillImages = json_decode($_POST['images_json'], true);
                if (is_array($quillImages) && !empty($quillImages)) {
                    $images_paths = array_merge($images_paths, $quillImages);
                }
            }

            if (!$description || !$mail_subject) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Description and subject are required.']);
                exit;
            }
            
            // Allow campaigns with either template or body (or both)
            // No strict validation here - flexible for different campaign types

            $images_json = !empty($images_paths) ? json_encode($images_paths) : null;
            $updateFields = ['description=?', 'mail_subject=?', 'mail_body=?', 'send_as_html=?', 'template_id=?', 'import_batch_id=?'];
            $params = [$description, $mail_subject, $mail_body, $send_as_html, $template_id, $import_batch_id];
            $types = 'sssiis';

            if ($attachment_path !== null) {
                $updateFields[] = 'attachment_path=?';
                $params[] = $attachment_path;
                $types .= 's';
            }

            $updateFields[] = 'images_paths=?';
            $params[] = $images_json;
            $types .= 's';

            $params[] = $id;
            $types .= 'i';

            $sql = "UPDATE campaign_master SET " . implode(', ', $updateFields) . " WHERE campaign_id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            if ($stmt->execute()) {
                error_log("Campaign UPDATE - Successfully updated campaign #$id, affected_rows: " . $stmt->affected_rows);
                echo json_encode(['success' => true, 'message' => 'Campaign updated successfully!', 'attachment_updated' => ($attachment_path !== null), 'images_updated' => true, 'images_count' => count($images_paths)]);
            } else {
                error_log("Campaign UPDATE - Failed to update campaign #$id: " . $conn->error);
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Error updating campaign: ' . $conn->error]);
            }
            $stmt->close();
            exit;

        // UPDATE via JSON POST (no files) - Full campaign update
        } elseif ($hasId && $isJson) {
            $id = intval($_GET['id']);
            
            // Check user access using authenticated user
            if (!isAuthenticatedAdmin()) {
                $checkStmt = $conn->prepare("SELECT user_id FROM campaign_master WHERE campaign_id = ?");
                $checkStmt->bind_param("i", $id);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                $campaignData = $checkResult->fetch_assoc();
                $checkStmt->close();
                
                if (!$campaignData || !canAccessRecord($campaignData['user_id'])) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Access denied']);
                    exit;
                }
            }
            
            $input_raw = file_get_contents('php://input');
            $data = json_decode($input_raw, true) ?? [];
            
            error_log("Campaign Full Update - ID: $id, Data keys: " . implode(', ', array_keys($data)));
            
            // Full update with all fields
            $description = $conn->real_escape_string($data['description'] ?? '');
            $mail_subject = $conn->real_escape_string($data['mail_subject'] ?? '');
            $mail_body = $conn->real_escape_string($data['mail_body'] ?? '');
            $send_as_html = isset($data['send_as_html']) ? (int)$data['send_as_html'] : 0;
            $csv_list_id = isset($data['csv_list_id']) && $data['csv_list_id'] !== '' ? (int)$data['csv_list_id'] : null;
            $images_paths = [];

            if (isset($data['images_json'])) {
                $ij = $data['images_json'];
                $quillImages = [];
                if (is_string($ij)) {
                    $decoded = json_decode($ij, true);
                    if (is_array($decoded)) {
                        $quillImages = $decoded;
                    }
                } elseif (is_array($ij)) {
                    $quillImages = $ij;
                }
                if (!empty($quillImages) && is_array($quillImages)) {
                    $images_paths = $quillImages;
                }
            }

            if (!$description || !$mail_subject || !$mail_body) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'All fields are required.']);
                exit;
            }

            $images_json = !empty($images_paths) ? json_encode($images_paths) : null;
            
            // Get current user for filtering
            $currentUserData = getAuthenticatedUser();
            $userId = $currentUserData['id'];
            $isAdminUser = isAuthenticatedAdmin();
            
            if ($isAdminUser) {
                $stmt = $conn->prepare("UPDATE campaign_master SET description=?, mail_subject=?, mail_body=?, send_as_html=?, images_paths=?, csv_list_id=? WHERE campaign_id=?");
                $stmt->bind_param('sssssii', $description, $mail_subject, $mail_body, $send_as_html, $images_json, $csv_list_id, $id);
            } else {
                $stmt = $conn->prepare("UPDATE campaign_master SET description=?, mail_subject=?, mail_body=?, send_as_html=?, images_paths=?, csv_list_id=? WHERE campaign_id=? AND user_id=?");
                $stmt->bind_param('sssssiii', $description, $mail_subject, $mail_body, $send_as_html, $images_json, $csv_list_id, $id, $userId);
            }
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    echo json_encode(['success' => true, 'message' => 'Campaign updated successfully!']);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Campaign not found or no permission']);
                }
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Error updating campaign: ' . $conn->error]);
            }
            exit;
        }

        // CREATE (no id)
        error_log("Campaign CREATE - hasId: " . ($hasId ? 'true' : 'false') . ", id param: " . ($_GET['id'] ?? 'not set') . ", _method: " . ($_POST['_method'] ?? 'not set'));
        
        $description = $conn->real_escape_string($_POST['description'] ?? '');
        $mail_subject = $conn->real_escape_string($_POST['mail_subject'] ?? '');
        $mail_body = $conn->real_escape_string($_POST['mail_body'] ?? '');
        $send_as_html = isset($_POST['send_as_html']) ? (int)$_POST['send_as_html'] : 0;
        $csv_list_id = isset($_POST['csv_list_id']) && $_POST['csv_list_id'] !== '' ? (int)$_POST['csv_list_id'] : null;
        $template_id = isset($_POST['template_id']) && $_POST['template_id'] !== '' ? (int)$_POST['template_id'] : null;
        $import_batch_id = isset($_POST['import_batch_id']) && $_POST['import_batch_id'] !== '' ? $conn->real_escape_string($_POST['import_batch_id']) : null;
        $attachment_path = null;
        $images_paths = [];

        // Handle attachment upload
        if (isset($_FILES['attachment'])) {
            if ($_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../storage/attachments/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $filename = uniqid() . '_' . basename($_FILES['attachment']['name']);
                $targetPath = $uploadDir . $filename;
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $targetPath)) {
                    $attachment_path = 'storage/attachments/' . $filename;
                    chmod($targetPath, 0644); // Set proper permissions
                } else {
                    error_log("Failed to move uploaded file to: " . $targetPath);
                }
            } else {
                error_log("File upload error: " . $_FILES['attachment']['error']);
            }
        }

        // Handle multiple images upload (old method - keeping for backward compatibility)
        if (isset($_FILES['images']) && is_array($_FILES['images']['name'])) {
            $uploadDir = __DIR__ . '/../storage/images/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $fileCount = count($_FILES['images']['name']);
            for ($i = 0; $i < $fileCount; $i++) {
                if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
                    $filename = uniqid() . '_' . basename($_FILES['images']['name'][$i]);
                    $targetPath = $uploadDir . $filename;
                    if (move_uploaded_file($_FILES['images']['tmp_name'][$i], $targetPath)) {
                        $images_paths[] = 'storage/images/' . $filename;
                        chmod($targetPath, 0644);
                    }
                }
            }
        }

        // Handle images uploaded via Quill editor (sent as JSON array)
        if (isset($_POST['images_json'])) {
            $quillImages = json_decode($_POST['images_json'], true);
            if (is_array($quillImages) && !empty($quillImages)) {
                $images_paths = array_merge($images_paths, $quillImages);
            }
        }

        if (!$description || !$mail_subject) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Description and subject are required.']);
            exit;
        }
        
        // Allow campaigns with either template or body (or both)
        // No strict validation here - flexible for different campaign types

        // Store images as JSON (null if empty array)
        $images_json = !empty($images_paths) ? json_encode($images_paths) : null;
        
        // Require authentication
        $currentUser = getAuthenticatedUser();
        if (!$currentUser) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.']);
            exit;
        }
        $user_id = $currentUser['id'];
        
        // Check if template_id and import_batch_id columns exist
        try {
            $stmt = $conn->prepare("INSERT INTO campaign_master (description, mail_subject, mail_body, attachment_path, images_paths, send_as_html, csv_list_id, template_id, import_batch_id, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("sssssiissi", $description, $mail_subject, $mail_body, $attachment_path, $images_json, $send_as_html, $csv_list_id, $template_id, $import_batch_id, $user_id);
        } catch (Exception $e) {
            // Fallback: columns might not exist on server
            error_log("Template columns missing, using fallback INSERT: " . $e->getMessage());
            $stmt = $conn->prepare("INSERT INTO campaign_master (description, mail_subject, mail_body, attachment_path, images_paths, send_as_html, csv_list_id, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            if (!$stmt) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
                exit;
            }
            $stmt->bind_param("sssssisi", $description, $mail_subject, $mail_body, $attachment_path, $images_json, $send_as_html, $csv_list_id, $user_id);
        }

        if ($stmt->execute()) {
            $campaignId = $stmt->insert_id;
            echo json_encode([
                'success' => true, 
                'message' => 'Campaign added successfully!',
                'campaign_id' => $campaignId,
                'attachment_saved' => !empty($attachment_path),
                'attachment_path' => $attachment_path,
                'images_saved' => !empty($images_json),
                'images_count' => count($images_paths)
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error adding campaign: ' . $conn->error]);
        }
        exit;
    }

    // PUT /api/master/campaigns?id=1 (multipart/form-data for file upload)
    if ($method === 'PUT') {
        // For PUT, most clients send JSON, but for file upload, use POST with _method=PUT
        // Here, we only support JSON for PUT (no file upload)
        if (!isset($_GET['id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Campaign ID is required.']);
            exit;
        }
        $id = intval($_GET['id']);
        // If you want to support file upload for update, use POST with _method=PUT
        if (isset($_POST['_method']) && $_POST['_method'] === 'PUT') {
            $description = $conn->real_escape_string($_POST['description'] ?? '');
            $mail_subject = $conn->real_escape_string($_POST['mail_subject'] ?? '');
            $mail_body = $conn->real_escape_string($_POST['mail_body'] ?? '');
            $attachment = null;
            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                $attachment = file_get_contents($_FILES['attachment']['tmp_name']);
            }

            if (!$description || !$mail_subject || !$mail_body) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'All fields are required.']);
                exit;
            }

            if ($attachment !== null) {
                $stmt = $conn->prepare("UPDATE campaign_master SET description=?, mail_subject=?, mail_body=?, attachment=? WHERE campaign_id=?");
                $stmt->bind_param("ssssi", $description, $mail_subject, $mail_body, $null, $id);
                $stmt->send_long_data(3, $attachment);
                $null = null;
            } else {
                $stmt = $conn->prepare("UPDATE campaign_master SET description=?, mail_subject=?, mail_body=? WHERE campaign_id=?");
                $stmt->bind_param("sssi", $description, $mail_subject, $mail_body, $id);
            }

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Campaign updated successfully!']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Error updating campaign: ' . $conn->error]);
            }
            exit;
        } else {
            // JSON PUT (no file upload)
            $data = getInputData();
            $description = $conn->real_escape_string($data['description'] ?? '');
            $mail_subject = $conn->real_escape_string($data['mail_subject'] ?? '');
            $mail_body = $conn->real_escape_string($data['mail_body'] ?? '');

            if (!$description || !$mail_subject || !$mail_body) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'All fields are required.']);
                exit;
            }

            $sql = "UPDATE campaign_master SET description='$description', mail_subject='$mail_subject', mail_body='$mail_body' WHERE campaign_id=$id";
            if ($conn->query($sql)) {
                echo json_encode(['success' => true, 'message' => 'Campaign updated successfully!']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Error updating campaign: ' . $conn->error]);
            }
            exit;
        }
    }

    // DELETE /api/master/campaigns?id=1
    if ($method === 'DELETE') {
        error_log("campaign.php DELETE - GET params: " . json_encode($_GET));
        error_log("campaign.php DELETE - Query string: " . ($_SERVER['QUERY_STRING'] ?? 'none'));
        error_log("campaign.php DELETE - Request URI: " . ($_SERVER['REQUEST_URI'] ?? 'none'));
        
        if (!isset($_GET['id'])) {
            error_log("campaign.php DELETE - ERROR: id parameter not found in GET");
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Campaign ID is required.', 'debug' => $_GET]);
            exit;
        }
        $id = intval($_GET['id']);
        
        // Check user access using authenticated user
        if (!isAuthenticatedAdmin()) {
            $checkStmt = $conn->prepare("SELECT user_id FROM campaign_master WHERE campaign_id = ?");
            $checkStmt->bind_param("i", $id);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            $campaignData = $checkResult->fetch_assoc();
            $checkStmt->close();
            
            if (!$campaignData || !canAccessRecord($campaignData['user_id'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access denied']);
                exit;
            }
        }
        
        // Use prepared statement for security
        $stmt = $conn->prepare("DELETE FROM campaign_master WHERE campaign_id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                // Also delete from campaign_status if exists
                $statusStmt = $conn->prepare("DELETE FROM campaign_status WHERE campaign_id = ?");
                $statusStmt->bind_param("i", $id);
                $statusStmt->execute();
                $statusStmt->close();
                
                error_log("Campaign #$id deleted successfully. Also cleaned up campaign_status.");
                echo json_encode(['success' => true, 'message' => 'Campaign deleted successfully!']);
            } else {
                error_log("Campaign #$id not found or already deleted");
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Campaign not found or already deleted']);
            }
        } else {
            error_log("Error deleting campaign #$id: " . $conn->error);
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error deleting campaign: ' . $conn->error]);
        }
        $stmt->close();
        exit;
    }

    // If method not handled
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
