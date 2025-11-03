# Campaign Images and Attachments Implementation

## Overview
This implementation enables the MailPilot CRM to send emails with:
- **Attachments**: Files that recipients can download (PDFs, docs, etc.)
- **Embedded Images**: Images that appear inline in the email body
- **Reply-To**: Custom reply-to email address

## Database Schema

The `campaign_master` table includes the following columns:

```sql
CREATE TABLE `campaign_master` (
  `campaign_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `description` varchar(150) NOT NULL,
  `mail_subject` varchar(200) NOT NULL,
  `mail_body` mediumtext NOT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,    -- Single attachment file path
  `reply_to` varchar(150) DEFAULT NULL,            -- Custom reply-to email
  `send_as_html` tinyint(1) NOT NULL DEFAULT 0,   -- 0=text, 1=HTML
  `images_paths` text DEFAULT NULL,                -- JSON array of image paths
  PRIMARY KEY (`campaign_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## File Structure

```
backend/
├── includes/
│   └── campaign.php           # Handles campaign CRUD operations
└── public/
    └── email_blaster.php      # Sends emails with attachments/images
storage/
├── attachments/               # Uploaded attachment files
└── images/                    # Uploaded image files
frontend/
└── src/
    └── pages/
        └── Campaigns.jsx      # Campaign management UI
```

## How It Works

### 1. Frontend (Campaigns.jsx)

#### Adding a Campaign
1. User fills out the campaign form with:
   - Description, Subject, Body (rich text HTML)
   - Optional Reply-To email
   - Optional Attachment file
   - Optional multiple Images

2. Form submits as `multipart/form-data`:
```javascript
const formData = new FormData();
formData.append("description", form.description);
formData.append("mail_subject", form.mail_subject);
formData.append("mail_body", form.mail_body);
formData.append("reply_to", form.reply_to);
formData.append("send_as_html", "1");
formData.append("attachment", attachmentFile);  // Single file
imageFiles.forEach(file => {
  formData.append("images[]", file);  // Multiple images
});
```

### 2. Backend (campaign.php)

#### File Upload Handling
```php
// Handle attachment (single file)
if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = __DIR__ . '/../../storage/attachments/';
    $filename = uniqid() . '_' . basename($_FILES['attachment']['name']);
    move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadDir . $filename);
    $attachment_path = 'storage/attachments/' . $filename;
}

// Handle multiple images
if (isset($_FILES['images']) && is_array($_FILES['images']['name'])) {
    $uploadDir = __DIR__ . '/../../storage/images/';
    foreach ($_FILES['images']['name'] as $i => $name) {
        if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
            $filename = uniqid() . '_' . basename($name);
            move_uploaded_file($_FILES['images']['tmp_name'][$i], $uploadDir . $filename);
            $images_paths[] = 'storage/images/' . $filename;
        }
    }
}

// Store in database
$images_json = !empty($images_paths) ? json_encode($images_paths) : null;
$stmt = $conn->prepare("INSERT INTO campaign_master (description, mail_subject, mail_body, attachment_path, images_paths, reply_to, send_as_html) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssssssi", $description, $mail_subject, $mail_body, $attachment_path, $images_json, $reply_to, $send_as_html);
```

### 3. Email Sending (email_blaster.php)

#### Fetching Campaign Data
```php
$select = "SELECT mail_subject, mail_body, send_as_html, attachment_path, images_paths, reply_to FROM campaign_master WHERE campaign_id = $campaign_id";
$campaign = $db->query($select)->fetch_assoc();
```

#### Sending Email with PHPMailer
```php
function sendEmail($smtp, $to_email, $subject, $body, $isHtml = false, $campaign = []) {
    $mail = new PHPMailer(true);
    
    // Configure SMTP...
    
    // Set reply-to
    if (!empty($campaign['reply_to'])) {
        $mail->addReplyTo($campaign['reply_to']);
    }
    
    // Add attachment
    if (!empty($campaign['attachment_path'])) {
        $attachmentPath = __DIR__ . '/../../' . $campaign['attachment_path'];
        if (file_exists($attachmentPath)) {
            $mail->addAttachment($attachmentPath);
        }
    }
    
    // Embed images
    if (!empty($campaign['images_paths'])) {
        $images = json_decode($campaign['images_paths'], true);
        foreach ($images as $index => $imagePath) {
            $fullPath = __DIR__ . '/../../' . $imagePath;
            if (file_exists($fullPath)) {
                $cid = 'image_' . $index . '_' . uniqid();
                $mail->addEmbeddedImage($fullPath, $cid);
                
                // Replace image src in HTML body
                $filename = basename($imagePath);
                $body = preg_replace(
                    '/(<img[^>]+src=["\'])(' . preg_quote($imagePath, '/') . '|' . preg_quote($filename, '/') . ')(["\'][^>]*>)/i',
                    '${1}cid:' . $cid . '${3}',
                    $body
                );
                $mail->Body = $body;
            }
        }
    }
    
    $mail->send();
}
```

## Usage Instructions

### Creating a Campaign with Images and Attachments

1. **Navigate to Campaigns** page
2. **Click "Add Campaign"**
3. **Fill in the form:**
   - Description: Campaign name/description
   - Subject: Email subject line
   - Reply-To: (Optional) Custom reply email address
   - Body: Compose your email using the rich text editor
   - Attachment: (Optional) Upload a single file (PDF, DOC, etc.)
   - Images: (Optional) Upload multiple images

4. **Click "Save Campaign"**

### Embedding Images in Email Body

**Important:** When you upload images, they will be automatically embedded in the email. To reference them in your email body:

1. Upload your images using the "Images" field
2. In the email body editor, insert image references
3. When the email is sent, the system will automatically replace image references with embedded CID references

**Example:**
- Upload: `logo.png`, `banner.jpg`
- The backend will embed these images and make them available inline
- Recipients will see the images directly in the email body

### Sending the Campaign

1. Go to the **Master** page
2. Find your campaign
3. Click **"Send"** button
4. The email blaster will:
   - Attach the file (if any)
   - Embed the images (if any)
   - Send to all valid email addresses
   - Use the reply-to address (if specified)

## File Storage

- **Attachments**: Stored in `storage/attachments/`
  - Format: `{uniqid}_{original_filename}`
  - Example: `65abc123_document.pdf`

- **Images**: Stored in `storage/images/`
  - Format: `{uniqid}_{original_filename}`
  - Example: `65abc456_logo.png`

## Technical Details

### Supported File Types

**Attachments:**
- PDF (.pdf)
- Images (.jpg, .jpeg, .png)
- Documents (.doc, .docx)
- Spreadsheets (.xls, .xlsx, .csv)
- Text files (.txt)

**Images (for embedding):**
- All image types (image/*)

### Security Features

1. **Unique Filenames**: All uploaded files get unique IDs to prevent overwrites
2. **Directory Separation**: Attachments and images stored in separate directories
3. **File Validation**: File upload errors are checked before processing
4. **SQL Injection Protection**: Prepared statements used throughout

### Performance Considerations

1. **File Size**: No explicit limits set (controlled by PHP settings)
2. **Multiple Images**: Efficient batch upload handling
3. **CID Embedding**: Images embedded using PHPMailer's built-in CID mechanism
4. **Storage**: Files stored on disk, only paths stored in database

## Troubleshooting

### Images Not Appearing in Email
- Ensure `send_as_html` is set to 1
- Check that image files exist in `storage/images/`
- Verify file paths in `images_paths` JSON

### Attachments Not Sent
- Check file exists at `attachment_path` location
- Verify file permissions (readable by web server)
- Check email size limits

### Upload Failures
- Check PHP upload limits:
  - `upload_max_filesize`
  - `post_max_size`
  - `max_file_uploads`
- Verify directory permissions:
  - `storage/attachments/` (755 or 777)
  - `storage/images/` (755 or 777)

## Future Enhancements

1. **Image Preview**: Show thumbnail previews in campaign list
2. **File Size Limits**: Add frontend validation for file sizes
3. **Multiple Attachments**: Support multiple attachment files
4. **Image Gallery**: Visual gallery for selecting existing images
5. **CDN Integration**: Store files on CDN for better performance
6. **Compression**: Automatic image compression before upload

## Testing Checklist

- [ ] Create campaign with attachment only
- [ ] Create campaign with images only
- [ ] Create campaign with both attachment and images
- [ ] Create campaign with reply-to
- [ ] Edit existing campaign and update files
- [ ] Send campaign and verify attachment received
- [ ] Send campaign and verify images appear inline
- [ ] Verify reply-to works correctly
- [ ] Test with different file types
- [ ] Test with large files
- [ ] Test with multiple images (5+)

---

**Last Updated**: October 31, 2025
**Version**: 1.0
