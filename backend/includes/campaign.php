<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

require_once __DIR__ . '/../config/db.php';

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
                // When rendering mail_body in HTML
                $row['mail_body'] = nl2br(htmlspecialchars($row['mail_body']));
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
                $words = preg_split('/\s+/', $row['mail_body']);
                $preview = implode(' ', array_slice($words, 0, 30));
                if (count($words) > 30) $preview .= '...';
                $row['mail_body_preview'] = $preview;
                // Optionally, don't send the full attachment in list view
                if (isset($row['attachment'])) {
                    $row['has_attachment'] = !empty($row['attachment']);
                    unset($row['attachment']);
                }
                // When rendering mail_body in HTML
                $row['mail_body'] = nl2br(htmlspecialchars($row['mail_body']));
                $campaigns[] = $row;
            }
            echo json_encode($campaigns);
            exit;
        }
    }

    // POST /api/master/campaigns (multipart/form-data for file upload)
    if ($method === 'POST') {
        // Check for update via POST with _method=PUT
        if (isset($_POST['_method']) && $_POST['_method'] === 'PUT' && isset($_GET['id'])) {
            $id = intval($_GET['id']);
            $description = $conn->real_escape_string($_POST['description'] ?? '');
            $mail_subject = $conn->real_escape_string($_POST['mail_subject'] ?? '');
            $mail_body = $conn->real_escape_string($_POST['mail_body'] ?? '');
            $attachment_path = null;

            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../../storage/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $filename = uniqid() . '_' . basename($_FILES['attachment']['name']);
                $targetPath = $uploadDir . $filename;
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $targetPath)) {
                    $attachment_path = 'storage/' . $filename;
                }
            }

            if (!$description || !$mail_subject || !$mail_body) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'All fields are required.']);
                exit;
            }

            if ($attachment_path !== null) {
                $stmt = $conn->prepare("UPDATE campaign_master SET description=?, mail_subject=?, mail_body=?, attachment_path=? WHERE campaign_id=?");
                $stmt->bind_param("ssssi", $description, $mail_subject, $mail_body, $attachment_path, $id);
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
        }

        // Normal insert (no _method=PUT)
        $description = $conn->real_escape_string($_POST['description'] ?? '');
        $mail_subject = $conn->real_escape_string($_POST['mail_subject'] ?? '');
        $mail_body = $conn->real_escape_string($_POST['mail_body'] ?? '');
        $attachment_path = null;

        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../../storage/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $filename = uniqid() . '_' . basename($_FILES['attachment']['name']);
            $targetPath = $uploadDir . $filename;
            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $targetPath)) {
                $attachment_path = 'storage/' . $filename;
            }
        }

        if (!$description || !$mail_subject || !$mail_body) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'All fields are required.']);
            exit;
        }

        if ($attachment_path !== null) {
            $stmt = $conn->prepare("INSERT INTO campaign_master (description, mail_subject, mail_body, attachment_path) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $description, $mail_subject, $mail_body, $attachment_path);
        } else {
            $stmt = $conn->prepare("INSERT INTO campaign_master (description, mail_subject, mail_body) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $description, $mail_subject, $mail_body);
        }

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Campaign added successfully!']);
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
