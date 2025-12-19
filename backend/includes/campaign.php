<?php
error_log("=== campaign.php ENTRY === Method: " . $_SERVER['REQUEST_METHOD'] . ", URI: " . $_SERVER['REQUEST_URI']);
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

require_once __DIR__ . '/../config/db.php';

// Ensure campaign_master has a send_as_html column (boolean flag).
// If missing, add it with a safe default of 0. This migration runs on-demand
// to keep backwards-compatibility with existing databases.
// We perform a safe information_schema check and run ALTER TABLE if needed.
try {
    $dbNameRes = $conn->query("SELECT DATABASE() as db");
    $dbName = $dbNameRes ? $dbNameRes->fetch_assoc()['db'] : '';
    if ($dbName) {
        $colCheckSql = "SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '" . $conn->real_escape_string($dbName) . "' AND TABLE_NAME = 'campaign_master' AND COLUMN_NAME = 'send_as_html'";
        $colCheck = $conn->query($colCheckSql);
        if ($colCheck) {
            $hasCol = (int)$colCheck->fetch_assoc()['cnt'] > 0;
            if (!$hasCol) {
                // Try to add the column; ignore error if it fails.
                @$conn->query("ALTER TABLE campaign_master ADD COLUMN send_as_html TINYINT(1) NOT NULL DEFAULT 0");
            }
        }
    }
} catch (Exception $e) {
    // If anything fails here, continue without throwing -- this keeps API
    // compatible on environments where the DB user cannot ALTER TABLE.
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
        if (isset($_GET['id'])) {
            $id = intval($_GET['id']);
            $stmt = $conn->prepare("SELECT * FROM campaign_master WHERE campaign_id = ?");
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
            $result = $conn->query("SELECT * FROM campaign_master ORDER BY campaign_id DESC");
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
            echo json_encode($campaigns);
            exit;
        }
    }

    // POST /api/master/campaigns (create or update)
    if ($method === 'POST') {
        $isJson = isset($_SERVER['CONTENT_TYPE']) && stripos($_SERVER['CONTENT_TYPE'], 'application/json') !== false;
        $hasId = isset($_GET['id']);

        // UPDATE via multipart/form-data (or when _method=PUT is provided)
        if ($hasId && ((isset($_POST['_method']) && $_POST['_method'] === 'PUT') || !$isJson)) {
            $id = intval($_GET['id']);
            $description = $conn->real_escape_string($_POST['description'] ?? '');
            $mail_subject = $conn->real_escape_string($_POST['mail_subject'] ?? '');
            $mail_body = $conn->real_escape_string($_POST['mail_body'] ?? '');
            $send_as_html = isset($_POST['send_as_html']) ? (int)$_POST['send_as_html'] : 0;
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

            if (!$description || !$mail_subject || !$mail_body) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'All fields are required.']);
                exit;
            }

            $images_json = !empty($images_paths) ? json_encode($images_paths) : null;
            $updateFields = ['description=?', 'mail_subject=?', 'mail_body=?', 'send_as_html=?'];
            $params = [$description, $mail_subject, $mail_body, $send_as_html];
            $types = 'sssi';

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
                echo json_encode(['success' => true, 'message' => 'Campaign updated successfully!', 'attachment_updated' => ($attachment_path !== null), 'images_updated' => true, 'images_count' => count($images_paths)]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Error updating campaign: ' . $conn->error]);
            }
            exit;

        // UPDATE via JSON POST (no files)
        } elseif ($hasId && $isJson) {
            $id = intval($_GET['id']);
            $input_raw = file_get_contents('php://input');
            $data = json_decode($input_raw, true) ?? [];
            
            // Debug: Log received data
            error_log("Campaign Update - ID: $id, Raw Input: $input_raw");
            error_log("Campaign Update - Decoded Data: " . json_encode($data));
            error_log("Data keys: " . implode(', ', array_keys($data)));
            
            // Check if this is a CSV list only update
            $keys = array_keys($data);
            $isOnlyCsvListUpdate = (count($keys) === 1 && $keys[0] === 'csv_list_id');
            
            error_log("Is Only CSV List Update: " . ($isOnlyCsvListUpdate ? 'YES' : 'NO'));
            
            if ($isOnlyCsvListUpdate) {
                // Only update csv_list_id
                $csv_list_id = $data['csv_list_id'] !== '' && $data['csv_list_id'] !== null ? (int)$data['csv_list_id'] : null;
                error_log("Updating csv_list_id to: " . ($csv_list_id === null ? 'NULL' : $csv_list_id));
                
                if ($csv_list_id === null) {
                    $stmt = $conn->prepare("UPDATE campaign_master SET csv_list_id=NULL WHERE campaign_id=?");
                    $stmt->bind_param('i', $id);
                } else {
                    $stmt = $conn->prepare("UPDATE campaign_master SET csv_list_id=? WHERE campaign_id=?");
                    $stmt->bind_param('ii', $csv_list_id, $id);
                }
                
                if ($stmt->execute()) {
                    error_log("CSV list update SUCCESS for campaign #$id");
                    echo json_encode(['success' => true, 'message' => 'CSV list updated successfully!']);
                } else {
                    error_log("CSV list update FAILED: " . $conn->error);
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Error updating campaign: ' . $conn->error]);
                }
                exit;
            }
            
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
            $stmt = $conn->prepare("UPDATE campaign_master SET description=?, mail_subject=?, mail_body=?, send_as_html=?, images_paths=?, csv_list_id=? WHERE campaign_id=?");
            $stmt->bind_param('sssssii', $description, $mail_subject, $mail_body, $send_as_html, $images_json, $csv_list_id, $id);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Campaign updated successfully!']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Error updating campaign: ' . $conn->error]);
            }
            exit;
        }

        // CREATE (no id)
        $description = $conn->real_escape_string($_POST['description'] ?? '');
        $mail_subject = $conn->real_escape_string($_POST['mail_subject'] ?? '');
        $mail_body = $conn->real_escape_string($_POST['mail_body'] ?? '');
        $send_as_html = isset($_POST['send_as_html']) ? (int)$_POST['send_as_html'] : 0;
        $csv_list_id = isset($_POST['csv_list_id']) && $_POST['csv_list_id'] !== '' ? (int)$_POST['csv_list_id'] : null;
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

        if (!$description || !$mail_subject || !$mail_body) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'All fields are required.']);
            exit;
        }

        // Store images as JSON (null if empty array)
        $images_json = !empty($images_paths) ? json_encode($images_paths) : null;
        
        $stmt = $conn->prepare("INSERT INTO campaign_master (description, mail_subject, mail_body, attachment_path, images_paths, send_as_html, csv_list_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssis", $description, $mail_subject, $mail_body, $attachment_path, $images_json, $send_as_html, $csv_list_id);

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
        if (!isset($_GET['id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Campaign ID is required.']);
            exit;
        }
        $id = intval($_GET['id']);
        $sql = "DELETE FROM campaign_master WHERE campaign_id=$id";
        if ($conn->query($sql)) {
            echo json_encode(['success' => true, 'message' => 'Campaign deleted successfully!']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error deleting campaign: ' . $conn->error]);
        }
        exit;
    }

    // If method not handled
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
