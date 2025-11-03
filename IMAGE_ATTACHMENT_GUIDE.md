# MailPilot CRM - Images & Attachments Guide

## 🎯 How It Works

### For Localhost (Development)
Images are stored with URLs like: `http://localhost/verify_emails/MailPilot_CRM/backend/storage/images/...`

### For Production Server
Images are stored with URLs like: `https://yourdomain.com/backend/storage/images/...`

### Important: Email Delivery
**Regardless of the URL stored in the database, when emails are sent:**
- Images are **embedded directly** into the email using CID (Content-ID) references
- Recipients **do NOT need internet** to see the images
- Images appear **inline** in the email body
- All image URLs are automatically converted to `cid:` references by the email_blaster.php

## 📁 File Storage

```
backend/
  storage/
    images/          # All images uploaded via Quill editor
    attachments/     # All file attachments
```

### Supported Attachment Types:
- Documents: `.pdf`, `.doc`, `.docx`, `.txt`
- Spreadsheets: `.xls`, `.xlsx`, `.csv`
- Images: `.jpg`, `.jpeg`, `.png`

### Features:
✅ **Visual Indicators**: Campaigns with attachments show a badge in the list  
✅ **Download Existing**: Edit mode allows downloading existing attachments  
✅ **Replace Attachments**: Upload new file to replace existing one  
✅ **Multiple Formats**: Support for all common business file types

## 🚀 Deployment to Production Server

### Step 1: Update Configuration (Auto-Configured!)

The system now **auto-detects** your domain! 🎉

Edit `/backend/config/config.php` only if you need custom paths:

```php
// Auto-detection works for most cases
// For manual configuration:
define('BASE_URL', 'https://yourdomain.com/MailPilot_CRM');
// OR if installed in root:
define('BASE_URL', 'https://yourdomain.com');
```

### Step 2: Set Directory Permissions

```bash
chmod -R 777 backend/storage/
```

Or more securely:
```bash
chmod -R 755 backend/storage/
chown -R www-data:www-data backend/storage/
```

### Step 3: Update Frontend API URL

Edit `/frontend/src/pages/Campaigns.jsx` (line ~63):

Change:
```javascript
const response = await fetch('http://localhost/verify_emails/MailPilot_CRM/backend/includes/upload_image.php', {
```

To:
```javascript
// Use relative path (recommended):
const response = await fetch('/backend/includes/upload_image.php', {
```

Or for absolute URL:
```javascript
const response = await fetch('https://yourdomain.com/backend/includes/upload_image.php', {
```

## 🧪 Testing

### Test Image Upload
1. Open: `http://localhost/verify_emails/MailPilot_CRM/backend/includes/test_upload.html`
2. Select an image
3. Click Upload
4. You should see the JSON response and uploaded image

### Test Campaign with Images
1. Create a new campaign
2. Click the image button in Quill toolbar
3. Upload an image
4. The image should appear in the editor
5. Save the campaign
6. Send a test email to yourself

## 📧 Email Behavior

### What Recipients See:
- ✅ Images embedded directly in email (work offline)
- ✅ Attachments included with email
- ✅ All images display properly in Gmail, Outlook, Yahoo, etc.

### How It Works Behind the Scenes:
1. **User uploads image via Quill** → Stored at `backend/storage/images/xyz.jpg`
2. **Image URL saved in HTML** → `http://domain.com/backend/storage/images/xyz.jpg`
3. **Image path saved in DB** → `storage/images/xyz.jpg`
4. **Email is sent** → email_blaster.php:
   - Reads the image from disk
   - Adds it as embedded image with unique CID
   - Replaces all URLs in HTML with `cid:image_0_abc123`
   - PHPMailer embeds the image in email body
5. **Recipient receives email** → Images show inline, no external requests needed

## 🔧 Configuration Options

### `/backend/config/config.php`

```php
// Base URL (update for production)
define('BASE_URL', 'http://localhost/verify_emails/MailPilot_CRM');

// Storage paths
define('STORAGE_ATTACHMENTS', 'storage/attachments/');
define('STORAGE_IMAGES', 'storage/images/');

// Upload limits
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB

// Allowed image types
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp']);
```

## 🐛 Troubleshooting

### "Failed to save uploaded file"
- Check directory permissions: `chmod -R 777 backend/storage/`
- Verify PHP has write access
- Check disk space

### "Images not showing in email"
- Images ARE embedded, not linked
- Check spam folder (some email clients are strict)
- Verify email_blaster.php has access to image files
- Check file paths are correct

### "Wrong URL in production"
- Update `BASE_URL` in `/backend/config/config.php`
- Update fetch URL in frontend Campaigns.jsx

## ✅ Summary

**The system works correctly!** The URL you see (`localhost`) is only for the editor preview. When emails are actually sent to recipients:
- Images are embedded in the email itself
- Recipients don't need to access your server
- Images work offline
- All email clients supported

The screenshot you showed proves it's working - the image appears in the email!
