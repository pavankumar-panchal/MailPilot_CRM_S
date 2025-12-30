# 🚀 Deployment Guide: Template System to Production Server

## 📋 Files to Upload to Server

### 1. **Backend PHP Files** (Upload to server)

```bash
backend/includes/campaign.php              ✅ Updated with auto-migration
backend/includes/mail_templates.php        ✅ NEW - Template management API
backend/includes/template_merge_helper.php ✅ NEW - Template merging functions
```

### 2. **Database Migration Script**

```bash
backend/scripts/add_template_columns.sql   ✅ NEW - Adds template columns
```

---

## 🗄️ Database Setup Steps

### Step 1: Run Migration Script

**Option A: Via phpMyAdmin**
1. Login to phpMyAdmin on server
2. Select `email_id` database
3. Go to SQL tab
4. Copy contents from `backend/scripts/add_template_columns.sql`
5. Click "Go" to execute

**Option B: Via MySQL Command Line**
```bash
mysql -u root -p email_id < backend/scripts/add_template_columns.sql
```

### Step 2: Verify Migration

Run this query to confirm columns were added:
```sql
SHOW COLUMNS FROM campaign_master 
WHERE Field IN ('template_id', 'import_batch_id');
```

**Expected Output:**
```
+------------------+--------------+------+-----+---------+-------+
| Field            | Type         | Null | Key | Default | Extra |
+------------------+--------------+------+-----+---------+-------+
| template_id      | int(11)      | YES  |     | NULL    |       |
| import_batch_id  | varchar(100) | YES  |     | NULL    |       |
+------------------+--------------+------+-----+---------+-------+
```

---

## 📤 File Upload Instructions

### Using FTP/SFTP (FileZilla, WinSCP, etc.)

1. **Connect to your server:**
   - Host: `payrollsoft.in` (or your server IP)
   - Username: Your hosting username
   - Password: Your hosting password
   - Port: 21 (FTP) or 22 (SFTP)

2. **Navigate to directory:**
   ```
   /public_html/emailvalidation/backend/includes/
   ```

3. **Upload these files:**
   - ✅ `campaign.php` (overwrite existing)
   - ✅ `mail_templates.php` (new file)
   - ✅ `template_merge_helper.php` (new file)

4. **Set file permissions:**
   ```
   chmod 644 campaign.php
   chmod 644 mail_templates.php
   chmod 644 template_merge_helper.php
   ```

### Using SSH/Terminal (Advanced)

```bash
# SCP from local to server
scp backend/includes/campaign.php user@payrollsoft.in:/path/to/emailvalidation/backend/includes/
scp backend/includes/mail_templates.php user@payrollsoft.in:/path/to/emailvalidation/backend/includes/
scp backend/includes/template_merge_helper.php user@payrollsoft.in:/path/to/emailvalidation/backend/includes/
```

---

## ✅ Verification Checklist

After deployment, verify:

### 1. **Test Campaign Creation**
```bash
curl -X POST https://payrollsoft.in/emailvalidation/backend/routes/api.php/api/master/campaigns \
  -H "Content-Type: multipart/form-data" \
  -F "description=Test Campaign" \
  -F "mail_subject=Test Subject" \
  -F "mail_body=Test Body"
```

Expected: `{"success":true,"message":"Campaign added successfully!"}`

### 2. **Test Template API**
```bash
curl https://payrollsoft.in/emailvalidation/backend/includes/mail_templates.php?action=list
```

Expected: `{"success":true,"templates":[]}`

### 3. **Check PHP Error Logs**
```bash
# On server, check for errors:
tail -f /path/to/error_log
```

Look for any errors related to `campaign.php`, `mail_templates.php`, or `template_merge_helper.php`

---

## 🔧 Troubleshooting

### Issue: 500 Internal Server Error

**Solution 1: Check file permissions**
```bash
chmod 644 backend/includes/*.php
chmod 755 backend/includes/
```

**Solution 2: Check PHP error logs**
```bash
tail -50 /path/to/error_log | grep -i "campaign\|template"
```

**Solution 3: Verify database columns exist**
```sql
DESC campaign_master;
```

### Issue: "Unknown column 'template_id'"

**Solution: Run migration script again**
```sql
-- Check if columns exist
SELECT COUNT(*) FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = 'email_id' 
AND TABLE_NAME = 'campaign_master' 
AND COLUMN_NAME IN ('template_id', 'import_batch_id');

-- If count is 0, manually add:
ALTER TABLE campaign_master 
ADD COLUMN template_id INT NULL DEFAULT NULL AFTER csv_list_id;

ALTER TABLE campaign_master 
ADD COLUMN import_batch_id VARCHAR(100) NULL DEFAULT NULL AFTER template_id;
```

### Issue: Collation errors

**Solution: Already fixed with COLLATE clauses**
The code now uses `COLLATE utf8mb4_unicode_ci` in all comparison queries.

---

## 📊 Database Compatibility Matrix

| Column | Localhost | Server | Status |
|--------|-----------|---------|--------|
| `template_id` | ✅ | ⚠️ Need to add | Run migration |
| `import_batch_id` | ✅ | ⚠️ Need to add | Run migration |
| `send_as_html` | ✅ | ✅ Already exists | Auto-added |
| `csv_list_id` | ✅ | ✅ Already exists | OK |

---

## 🎯 Post-Deployment Testing

### Test 1: Create Normal Campaign (CSV)
```javascript
// In browser console or Postman
fetch('https://payrollsoft.in/emailvalidation/backend/routes/api.php/api/master/campaigns', {
  method: 'POST',
  body: new FormData(document.querySelector('form'))
})
```

### Test 2: Create Template Campaign (Excel)
1. Upload Excel file via frontend
2. Get `import_batch_id` from response
3. Create template in Templates page
4. Create campaign with `template_id` and `import_batch_id`

### Test 3: Start Campaign
1. Navigate to Campaigns page
2. Click "Start" on a campaign
3. Check worker logs for template merging:
   ```
   [2025-12-23] Using template #3 for campaign #32
   [2025-12-23] Merged template with data for panchalpavan800@gmail.com
   ```

---

## 📝 Rollback Plan

If issues occur, restore previous version:

```bash
# Restore old campaign.php
cp campaign.php.backup campaign.php

# Remove new files
rm mail_templates.php
rm template_merge_helper.php

# Remove database columns (optional)
ALTER TABLE campaign_master DROP COLUMN template_id;
ALTER TABLE campaign_master DROP COLUMN import_batch_id;
```

---

## 🎉 Success Indicators

✅ Campaign API returns 200 OK  
✅ Template API is accessible  
✅ Database columns exist  
✅ No PHP errors in logs  
✅ Can create campaigns via frontend  
✅ Can create templates via frontend  
✅ Workers send emails with merged templates  

---

## 📞 Support

If you encounter issues:
1. Check this guide's Troubleshooting section
2. Review PHP error logs
3. Verify database column existence
4. Check file permissions
5. Test API endpoints individually

**The system is designed to be backwards-compatible - existing campaigns without templates will continue to work normally!** 🚀
