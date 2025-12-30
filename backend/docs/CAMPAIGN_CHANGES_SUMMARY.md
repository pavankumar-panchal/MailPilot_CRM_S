# Campaign System Changes Summary

## ✅ What Was Changed

### Problem
Campaign system was only fetching emails from the `emails` table (CSV uploads), not from `imported_recipients` table (Excel imports). Templates were not being merged with Excel data.

### Solution
Modified the campaign system to:
1. **Fetch emails from `imported_recipients`** when campaign has `import_batch_id`
2. **Use template merging** to personalize emails with Excel data
3. **Support both data sources** (emails table OR imported_recipients table)

---

## 📝 Files Modified

### 1. **backend/includes/email_blast_worker.php**

#### Changes in `claimNextEmail()` function:
- Added logic to detect `import_batch_id` from campaign
- Fetches emails from `imported_recipients` when batch ID exists
- Falls back to `emails` table for CSV-based campaigns
- Returns `import_batch_id` in claim result for template merging

**Before:**
```php
// Only queried emails table
SELECT e.id, e.raw_emailid AS to_mail, e.csv_list_id 
FROM emails e 
WHERE e.domain_status = 1 AND ...
```

**After:**
```php
// Checks campaign.import_batch_id
if ($import_batch_id) {
    // Query imported_recipients
    SELECT id, Emails AS to_mail, '$import_batch_id' AS import_batch_id 
    FROM imported_recipients 
    WHERE import_batch_id = '$batch_escaped' AND ...
} else {
    // Query emails table (original logic)
    SELECT e.id, e.raw_emailid AS to_mail, e.csv_list_id 
    FROM emails e ...
}
```

#### Changes in completion checker:
- Updated to check correct table based on campaign source
- Checks `imported_recipients` when `import_batch_id` is set
- Checks `emails` table for CSV campaigns

### 2. **backend/includes/start_campaign.php**

#### Changes in recipient counting:
- Counts recipients from `imported_recipients` when `import_batch_id` exists
- Shows appropriate error messages based on data source

**Before:**
```php
// Only counted from emails table
SELECT COUNT(*) FROM emails WHERE domain_status = 1 AND csv_list_id = ?
```

**After:**
```php
if ($import_batch_id) {
    // Count from imported_recipients
    SELECT COUNT(*) FROM imported_recipients 
    WHERE import_batch_id = ? AND is_active = 1 AND Emails IS NOT NULL
} else {
    // Count from emails table (original)
    SELECT COUNT(*) FROM emails WHERE domain_status = 1 AND csv_list_id = ?
}
```

### 3. **backend/includes/template_merge_helper.php**

**No changes needed!** Already had proper support:
- `processCampaignBody()` extracts `import_batch_id` from campaign
- `getEmailRowData()` queries `imported_recipients` when batch ID provided
- `mergeTemplateWithData()` replaces `[[placeholders]]` with actual values

---

## 🔧 How It Works Now

### Campaign Creation Flow:

```
1. Import Excel File
   ↓
   Returns: import_batch_id (e.g., BATCH_20251222_153140_69491704d572d)

2. Create Mail Template (optional)
   ↓
   Template with [[Field]] placeholders stored in mail_templates

3. Create Campaign
   ↓
   Set: import_batch_id + template_id

4. Start Campaign
   ↓
   System fetches emails from imported_recipients
   ↓
   For each email:
     - Load template (if template_id is set)
     - Get recipient data from imported_recipients
     - Merge template with data
     - Send personalized email
```

### Data Source Selection Logic:

```php
// In email_blast_worker.php
if (campaign has import_batch_id) {
    // Use imported_recipients table
    SELECT Emails FROM imported_recipients 
    WHERE import_batch_id = ?
} else {
    // Use emails table (CSV)
    SELECT raw_emailid FROM emails 
    WHERE csv_list_id = ?
}
```

---

## 📊 Database Schema

### campaign_master Table:
```sql
- template_id (INT) - Links to mail_templates
- import_batch_id (VARCHAR) - Links to imported_recipients batch
- csv_list_id (INT) - Links to emails table (CSV uploads)
- mail_subject (VARCHAR)
- mail_body (TEXT) - Fallback if no template
```

### imported_recipients Table:
```sql
- import_batch_id (VARCHAR) - Batch identifier
- Emails (VARCHAR) - Recipient email
- source_file_type (ENUM) - 'invoice' or 'customer'
- [All Excel columns] - Amount, Days, BillNumber, Company, etc.
```

### mail_templates Table:
```sql
- template_id (INT)
- template_html (LONGTEXT) - HTML with [[Placeholders]]
- merge_fields (TEXT) - JSON array of field names
```

---

## 🎯 Test Results

Ran test script: `backend/scripts/test_campaign_excel_template.php`

**Results:**
```
✅ Import batches found:     2 batches (1,572 + 9,581 emails)
✅ Templates available:      2 templates
✅ Template merging:         Working (8/8 placeholders replaced)
✅ Recipient data fetch:     Working (47 fields retrieved)
✅ Email claiming logic:     Working (5 emails fetched)
```

**Test Example:**
- Email: mithun@10kinfo.com
- Data: BilledName="10K INFO DATA", Amount=6313, Days=3, BillNumber="RSL2024RL006315"
- Template: Outstanding Payment with 8 placeholders
- Result: All placeholders successfully replaced

---

## 📦 Current System Status

### Available Import Batches:

**1. Invoice Batch**
- ID: `BATCH_20251222_153140_69491704d572d`
- Emails: 1,572
- Type: invoice
- Fields: Amount, Days, BillNumber, BillDate, BilledName, ExecutiveName, ExecutiveContact

**2. Customer Batch**
- ID: `BATCH_20251222_153253_6949174dde3f7`
- Emails: 9,581
- Type: customer
- Fields: Company, CustomerID, State, District, Price, Tax, NetPrice, DealerName, DealerEmail

### Available Templates:

**1. Outstanding Payment (#1)**
- Fields: Amount, BillDate, BillNumber, BilledName, Days, ExecutiveContact, ExecutiveName

**2. TDS Updation Report (#3)**
- Fields: Company, CustomerID, DISTRICT, DealerCell, DealerEmail, DealerName, Edition, Email, LastProduct, NetPrice, Price, Tax, UsageType

---

## 🚀 Usage Examples

### Example 1: Create Campaign with Excel Data + Template

```bash
POST /api/master/campaigns
{
    "description": "Outstanding Payment Reminders",
    "mail_subject": "Payment Reminder - Invoice [[BillNumber]]",
    "template_id": 1,
    "import_batch_id": "BATCH_20251222_153140_69491704d572d"
}
```

### Example 2: Check Campaign Recipients

```sql
-- This will show recipient count from imported_recipients
SELECT 
    cm.campaign_id,
    cm.description,
    cm.import_batch_id,
    cm.template_id,
    COUNT(ir.Emails) as recipient_count
FROM campaign_master cm
LEFT JOIN imported_recipients ir 
    ON ir.import_batch_id = cm.import_batch_id 
    AND ir.is_active = 1
WHERE cm.import_batch_id IS NOT NULL
GROUP BY cm.campaign_id;
```

### Example 3: Start Campaign

```bash
POST /api/start_campaign
{
    "campaign_id": 123
}
```

Campaign will:
1. Fetch 1,572 emails from imported_recipients (invoice batch)
2. Load template #1 (Outstanding Payment)
3. For each email, merge [[placeholders]] with Excel data
4. Send personalized emails

---

## 🔍 Verification Queries

### Check if campaign is using imported data:
```sql
SELECT 
    campaign_id,
    description,
    CASE 
        WHEN import_batch_id IS NOT NULL THEN 'Imported Excel'
        WHEN csv_list_id IS NOT NULL THEN 'CSV Upload'
        ELSE 'No data source'
    END as data_source,
    template_id
FROM campaign_master
ORDER BY campaign_id DESC
LIMIT 10;
```

### Check email sending progress:
```sql
SELECT 
    cm.description,
    cs.status,
    cs.sent_emails,
    cs.pending_emails,
    cs.failed_emails,
    COUNT(ir.Emails) as total_recipients
FROM campaign_master cm
JOIN campaign_status cs ON cs.campaign_id = cm.campaign_id
LEFT JOIN imported_recipients ir 
    ON ir.import_batch_id = cm.import_batch_id 
    AND ir.is_active = 1
WHERE cm.import_batch_id IS NOT NULL
GROUP BY cm.campaign_id;
```

---

## ✨ Benefits

1. **Unified System** - Single campaign system handles both CSV and Excel imports
2. **Template Personalization** - Emails automatically customized with recipient data
3. **Excel Integration** - Direct use of imported Excel columns
4. **Flexible Data Sources** - Supports invoice data AND customer data
5. **Automatic Merging** - No manual work needed - system merges template + data automatically

---

## 📖 Documentation

**Full Guide:** `backend/docs/CAMPAIGN_EXCEL_TEMPLATE_GUIDE.md`
**Test Script:** `backend/scripts/test_campaign_excel_template.php`

---

## 🎉 Summary

**System is now fully operational with:**
- ✅ Excel import integration
- ✅ Template merging with Excel data
- ✅ Automatic email personalization
- ✅ Support for both invoice and customer data
- ✅ All tests passing

Campaign master can now store HTML/Excel data and automatically:
1. Fetch emails from `imported_recipients` 
2. Use templates to merge with Excel data
3. Send personalized emails to all recipients
