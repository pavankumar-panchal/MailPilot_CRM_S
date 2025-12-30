# Campaign with Excel Import & Template Merging Guide

## Overview
This system allows campaigns to automatically:
1. **Fetch emails from imported Excel files** (via `imported_recipients` table)
2. **Merge templates with Excel data** to personalize each email
3. **Send personalized emails** using the merged content

## How It Works

### 1. Database Structure

#### `campaign_master` Table
- `template_id` - Links to mail template for personalization
- `import_batch_id` - Links to imported Excel batch
- `mail_subject` - Email subject line
- `mail_body` - Fallback body if no template

#### `imported_recipients` Table  
- Stores Excel data (Invoice & Customer records)
- `import_batch_id` - Unique batch identifier
- `Emails` - Recipient email addresses
- `source_file_type` - 'invoice' or 'customer'
- All Excel columns (Amount, Days, BillNumber, Company, etc.)

#### `mail_templates` Table
- `template_html` - HTML template with [[PlaceholderName]] syntax
- `merge_fields` - JSON array of fields used in template

### 2. Campaign Flow

```
Campaign Created
    ↓
[import_batch_id set] → Fetch emails from imported_recipients
    ↓
[template_id set] → Load template from mail_templates
    ↓
For each email:
    ↓
Get recipient data from imported_recipients (by email + batch_id)
    ↓
Merge template with recipient data ([[Field]] → actual value)
    ↓
Send personalized email
```

### 3. Template Placeholder Syntax

Use `[[FieldName]]` in your HTML template to insert data:

**Available Fields (Invoice File):**
- `[[Amount]]` - Invoice amount
- `[[Days]]` - Days overdue
- `[[BillNumber]]` - Bill/Invoice number
- `[[BillDate]]` - Bill date
- `[[BilledName]]` - Customer name
- `[[ExecutiveName]]` - Sales executive name
- `[[ExecutiveContact]]` - Executive phone/email
- `[[Email]]` - Recipient email
- `[[Phone]]` - Customer phone

**Available Fields (Customer File):**
- `[[Company]]` - Company name
- `[[CustomerID]]` - Unique customer ID
- `[[Address]]` - Full address
- `[[State]]` - State name
- `[[District]]` - District name
- `[[Price]]` - Product price
- `[[Tax]]` - Tax amount
- `[[NetPrice]]` - Final price with tax
- `[[Email]]` - Customer email
- `[[DealerName]]` - Dealer name
- `[[DealerEmail]]` - Dealer email
- `[[LastProduct]]` - Last purchased product
- `[[UsageType]]` - Usage type/category

### 4. Code Implementation

#### Key Files Modified:

**backend/includes/email_blast_worker.php**
- `claimNextEmail()` - Fetches emails from `imported_recipients` when `import_batch_id` is set
- Completion checker updated to work with both email sources

**backend/includes/start_campaign.php**
- Counts recipients from `imported_recipients` when batch is set
- Shows appropriate error messages

**backend/includes/template_merge_helper.php**
- `processCampaignBody()` - Merges template with recipient data
- `getEmailRowData()` - Fetches data from correct table (imported_recipients or emails)
- `mergeTemplateWithData()` - Replaces [[placeholders]] with actual values

### 5. Creating a Campaign

#### Step 1: Import Excel File
```bash
POST /api/import/data
- Upload Excel file
- Returns: import_batch_id (e.g., BATCH_20251222_153140_69491704d572d)
```

#### Step 2: Create Mail Template
```sql
INSERT INTO mail_templates (template_name, template_html, merge_fields)
VALUES (
    'Outstanding Payment',
    '<h1>Payment Reminder</h1><p>Dear [[BilledName]],</p><p>Your invoice [[BillNumber]] for amount [[Amount]] is [[Days]] days overdue.</p>',
    '["Amount","BillNumber","BilledName","Days"]'
);
```

#### Step 3: Create Campaign
```bash
POST /api/master/campaigns
{
    "description": "Outstanding Payment Reminders",
    "mail_subject": "Payment Reminder - Invoice [[BillNumber]]",
    "template_id": 1,
    "import_batch_id": "BATCH_20251222_153140_69491704d572d"
}
```

#### Step 4: Start Campaign
```bash
POST /api/start_campaign
{
    "campaign_id": 123
}
```

### 6. Data Flow Example

**Excel Row:**
```
BilledName: John Doe
Amount: 6313
Days: 3
BillNumber: RSL2024RL006315
Email: john@example.com
```

**Template:**
```html
<p>Dear [[BilledName]],</p>
<p>Your invoice [[BillNumber]] for ₹[[Amount]] is [[Days]] days overdue.</p>
```

**Merged Email:**
```html
<p>Dear John Doe,</p>
<p>Your invoice RSL2024RL006315 for ₹6313 is 3 days overdue.</p>
```

### 7. Testing

#### Test 1: Check Import Batch
```sql
SELECT import_batch_id, COUNT(*) as emails, source_file_type 
FROM imported_recipients 
WHERE is_active = 1 
GROUP BY import_batch_id;
```

#### Test 2: Verify Template
```sql
SELECT template_id, template_name, merge_fields 
FROM mail_templates 
WHERE is_active = 1;
```

#### Test 3: Test Campaign Creation
```bash
# Create test campaign with Excel batch + template
curl -X POST http://localhost/backend/api/master/campaigns \
  -F "description=Test Campaign" \
  -F "mail_subject=Test Email" \
  -F "template_id=1" \
  -F "import_batch_id=BATCH_20251222_153140_69491704d572d"
```

#### Test 4: Check Recipients Count
```sql
-- Should show recipient count from imported_recipients
SELECT 
    cm.campaign_id,
    cm.description,
    cm.import_batch_id,
    cm.template_id,
    COUNT(ir.Emails) as recipients
FROM campaign_master cm
LEFT JOIN imported_recipients ir ON ir.import_batch_id = cm.import_batch_id AND ir.is_active = 1
WHERE cm.import_batch_id IS NOT NULL
GROUP BY cm.campaign_id;
```

### 8. Troubleshooting

**Issue: No recipients found**
- Check `import_batch_id` exists in `imported_recipients`
- Verify `is_active = 1` for recipients
- Ensure emails are not NULL or empty

**Issue: Template not merging**
- Check `template_id` is set in campaign
- Verify template exists and `is_active = 1`
- Check placeholder names match Excel columns exactly

**Issue: Empty email body**
- Verify template HTML is not empty
- Check processCampaignBody() is being called
- Ensure import_batch_id in campaign matches recipient batch

### 9. Benefits

✅ **Personalized Emails** - Each recipient gets customized content
✅ **Excel Integration** - Direct use of imported Excel data
✅ **Template Reusability** - Create once, use for multiple campaigns
✅ **Data Flexibility** - Supports both invoice and customer file formats
✅ **Unified System** - Single workflow for all campaign types

### 10. Available Import Batches (Current System)

**Invoice Batch:**
- ID: `BATCH_20251222_153140_69491704d572d`
- Count: 1,572 emails
- Type: invoice
- Fields: Amount, Days, BillNumber, BillDate, BilledName, etc.

**Customer Batch:**
- ID: `BATCH_20251222_153253_6949174dde3f7`
- Count: 9,581 emails
- Type: customer  
- Fields: Company, CustomerID, State, Price, DealerName, etc.

### 11. Template Examples

#### Template 1: Outstanding Payment Reminder
```html
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; }
        .invoice-box { border: 1px solid #ddd; padding: 20px; }
    </style>
</head>
<body>
    <div class="invoice-box">
        <h2>Payment Reminder</h2>
        <p>Dear <strong>[[BilledName]]</strong>,</p>
        <p>This is a friendly reminder that your invoice is overdue:</p>
        <ul>
            <li><strong>Invoice Number:</strong> [[BillNumber]]</li>
            <li><strong>Invoice Date:</strong> [[BillDate]]</li>
            <li><strong>Amount Due:</strong> ₹[[Amount]]</li>
            <li><strong>Days Overdue:</strong> [[Days]] days</li>
        </ul>
        <p>Please make the payment at your earliest convenience.</p>
        <p>For assistance, contact: [[ExecutiveName]] at [[ExecutiveContact]]</p>
        <p>Best regards,<br>Accounts Team</p>
    </div>
</body>
</html>
```

#### Template 2: Customer Update Notification
```html
<!DOCTYPE html>
<html>
<body>
    <h2>Customer Account Update</h2>
    <p>Dear <strong>[[Company]]</strong>,</p>
    <p>Your account details:</p>
    <ul>
        <li><strong>Customer ID:</strong> [[CustomerID]]</li>
        <li><strong>Location:</strong> [[District]], [[State]]</li>
        <li><strong>Current Price:</strong> ₹[[NetPrice]]</li>
        <li><strong>Dealer Contact:</strong> [[DealerName]] ([[DealerEmail]])</li>
    </ul>
    <p>Thank you for your business!</p>
</body>
</html>
```

## Summary

The system now fully supports:
- ✅ Fetching emails from imported Excel files
- ✅ Using templates for personalization  
- ✅ Merging template placeholders with Excel data
- ✅ Sending personalized emails to all recipients
- ✅ Supporting both invoice and customer data formats
- ✅ Automatic completion detection for both data sources
