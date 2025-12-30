# Campaign Master - Email Source Display Feature

## ✅ What Was Implemented

The campaign master now **shows which emails are being used** for each campaign, with proper distinction between:
1. **Excel Import** (imported_recipients table)
2. **CSV Upload** (emails table)
3. **All Valid Emails** (system-wide)

---

## 🎯 Key Features

### 1. Email Source Detection

Each campaign now displays:
- **Email Source Type**: Where emails come from
- **Source Label**: User-friendly description
- **Email Count**: Actual number of recipients
- **Template Usage**: Whether template merging is enabled

### 2. Campaign List Enhanced

**Updated `getCampaignsWithStats()` function:**

```php
foreach ($campaigns as &$campaign) {
    // Determine email source type
    if ($campaign['import_batch_id']) {
        $campaign['email_source'] = 'imported_recipients';
        $campaign['email_source_label'] = 'Excel Import';
        // Count from imported_recipients table
        
    } elseif ($campaign['csv_list_id']) {
        $campaign['email_source'] = 'csv_upload';
        $campaign['email_source_label'] = 'CSV Upload';
        // Count from emails table
        
    } else {
        $campaign['email_source'] = 'all_emails';
        $campaign['email_source_label'] = 'All Valid Emails';
    }
}
```

### 3. New API Endpoint: Get Campaign Emails

**Endpoint:** `POST /api/master/campaigns_master`

**Action:** `get_campaign_emails`

**Request:**
```json
{
  "action": "get_campaign_emails",
  "campaign_id": 30,
  "page": 1,
  "limit": 50
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "campaign_id": 30,
    "campaign_description": "Outstanding Payment Reminders",
    "email_source": "imported_recipients",
    "uses_template": true,
    "template_id": 1,
    "total": 1572,
    "page": 1,
    "limit": 50,
    "total_pages": 32,
    "emails": [
      {
        "id": 1,
        "email": "mithun@10kinfo.com",
        "name": "10K INFO DATA SOLUTIONS",
        "company": "10K INFO DATA SOLUTIONS",
        "amount": "6313",
        "days": "3",
        "bill_number": "RSL2024RL006315",
        "phone": "080-12345678",
        "file_type": "invoice",
        "send_status": "success",
        "attempt_count": 1,
        "delivery_info": "2024-12-22 10:30:45",
        "error_message": null
      },
      {
        "id": 2,
        "email": "hemanth@24-7intouch.com",
        "name": "24-7 INTOUCH",
        "amount": "2953",
        "days": "32",
        "bill_number": "RSL2024RL006316",
        "file_type": "invoice",
        "send_status": "not_sent",
        "attempt_count": 0,
        "delivery_info": null,
        "error_message": null
      }
    ]
  }
}
```

---

## 📊 Campaign Types & Email Sources

### Type 1: Excel Import Campaign (With Template)
```
Campaign: "Outstanding Payment Reminders"
├─ Email Source: imported_recipients
├─ Import Batch: BATCH_20251222_153140_69491704d572d
├─ Template: #1 (Outstanding Payment)
├─ Total Emails: 1,572
└─ Emails From: imported_recipients.Emails
   ├─ mithun@10kinfo.com (Amount: 6313, Days: 3)
   ├─ hemanth@24-7intouch.com (Amount: 2953, Days: 32)
   └─ ... 1,570 more emails with their Excel data
```

**Use Case:** Send personalized payment reminders with Excel data merged into template.

### Type 2: CSV Upload Campaign
```
Campaign: "Newsletter Blast"
├─ Email Source: csv_upload
├─ CSV List ID: 7
├─ Template: Optional
├─ Total Emails: 150
└─ Emails From: emails.raw_emailid (WHERE csv_list_id = 7)
   ├─ john@example.com
   ├─ jane@example.com
   └─ ... 148 more emails
```

**Use Case:** Send emails to specific CSV list uploaded via UI.

### Type 3: All Valid Emails
```
Campaign: "System-wide Announcement"
├─ Email Source: all_emails
├─ Template: Optional
├─ Total Emails: 5,432
└─ Emails From: emails.raw_emailid (WHERE validation_status = 'valid')
   ├─ All validated emails in system
   └─ Not filtered by batch or list
```

**Use Case:** Send to all validated emails in the system.

---

## 🔍 How It Works

### Email Source Priority:

```
1. Check campaign.import_batch_id
   ↓ If exists → Use imported_recipients table
   
2. Else check campaign.csv_list_id
   ↓ If exists → Use emails table (filtered by csv_list_id)
   
3. Else → Use all valid emails from emails table
```

### Database Flow:

```
campaign_master
├─ import_batch_id (if set)
│  └─→ imported_recipients
│      └─ Filter: WHERE import_batch_id = ?
│         └─ Get: Emails, BilledName, Amount, Days, etc.
│
├─ csv_list_id (if set)
│  └─→ emails
│      └─ Filter: WHERE csv_list_id = ?
│         └─ Get: raw_emailid, name, company, etc.
│
└─ Neither set
   └─→ emails
       └─ Filter: WHERE validation_status = 'valid'
          └─ Get: All validated emails
```

---

## 📝 Code Changes Summary

### File: `backend/public/campaigns_master.php`

**1. Added new action handler:**
```php
elseif ($action === 'get_campaign_emails') {
    $campaign_id = (int)($input['campaign_id'] ?? 0);
    $page = (int)($input['page'] ?? 1);
    $limit = (int)($input['limit'] ?? 50);
    $response['success'] = true;
    $response['data'] = getCampaignEmails($conn, $campaign_id, $page, $limit);
}
```

**2. Enhanced `getCampaignsWithStats()`:**
- Added `import_batch_id` and `template_id` to SELECT
- Added email source detection logic
- Shows proper counts based on source

**3. Updated `getEmailCounts()`:**
- Now checks both `import_batch_id` and `csv_list_id`
- Counts from correct table based on source

**4. Added new function `getCampaignEmails()`:**
- 260+ lines of code
- Handles all 3 email source types
- Returns paginated email list with send status
- Shows Excel data fields for imported emails
- Shows delivery status from mail_blaster

---

## 🎯 Test Results

**Test Command:**
```bash
php backend/test_campaign_emails.php
```

**Output:**
```
Campaign #30: Outstanding Payment Reminders
  📁 Source: Excel Import (Batch: BATCH_20251222_153140_69491704d572d)
  📧 Total Emails: 1,572 (from imported_recipients)
  📋 Sample Emails:
     • mithun@10kinfo.com - 10K INFO DATA (₹6313, 3 days)
     • hemanth@24-7intouch.com - 24-7 INTOUCH (₹2953, 32 days)
  📝 Uses Template: Yes (ID: 1)

Campaign #29: TDS Updates
  📁 Source: Excel Import (Batch: BATCH_20251222_153253_6949174dde3f7)
  📧 Total Emails: 9,581 (from imported_recipients)
  📝 Uses Template: Yes (ID: 3)

Campaign #19: Newsletter
  📁 Source: CSV Upload (List ID: 7)
  📧 Total Emails: 7 (from emails table)
  📝 Uses Template: Yes (ID: 1)
```

---

## 📖 API Usage Examples

### Example 1: Get Campaign Email List

**Request:**
```bash
curl -X POST http://localhost/backend/api/master/campaigns_master \
  -H "Content-Type: application/json" \
  -d '{
    "action": "get_campaign_emails",
    "campaign_id": 30,
    "page": 1,
    "limit": 50
  }'
```

**Response:** Returns 50 emails from imported_recipients with their Excel data.

### Example 2: Campaign List with Sources

**Request:**
```bash
curl -X POST http://localhost/backend/api/master/campaigns_master \
  -H "Content-Type: application/json" \
  -d '{"action": "list"}'
```

**Response:**
```json
{
  "success": true,
  "data": {
    "campaigns": [
      {
        "campaign_id": 30,
        "description": "Outstanding Payment",
        "email_source": "imported_recipients",
        "email_source_label": "Excel Import",
        "csv_list_valid_count": 1572,
        "template_id": 1
      }
    ]
  }
}
```

---

## ✨ Benefits

1. **Clear Visibility**: Users know exactly which emails are being used
2. **Proper Counting**: Accurate recipient counts based on source
3. **Template Indication**: Shows if template merging is active
4. **Send Status**: Track delivery status for each email
5. **Excel Data Display**: For imported emails, shows relevant Excel fields
6. **Pagination**: Handle large email lists efficiently

---

## 🎯 Summary

**Before:**
- Campaign master didn't show email source
- Unclear where emails were coming from
- No way to view email list for a campaign

**After:**
- ✅ Campaign shows email source (Excel/CSV/All)
- ✅ Displays accurate recipient count
- ✅ New API to list campaign emails
- ✅ Shows template usage
- ✅ Tracks send status per email
- ✅ For Excel imports: Shows Excel data fields
- ✅ For CSV uploads: Shows CSV list info
- ✅ For normal emails: Shows system emails

**Now you can clearly see:**
- Which emails will receive the campaign
- Whether they're from Excel import or CSV upload
- If template merging is enabled
- Send status for each recipient
- All relevant data from Excel for personalized emails
