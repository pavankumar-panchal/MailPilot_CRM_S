# Campaign Email Sending Flow - Step by Step

## 🔍 How System Fetches Campaign, Template & Emails

### Complete Flow Diagram:

```
┌─────────────────────────────────────────────────────────────────┐
│ 1. CAMPAIGN START                                               │
│    POST /api/start_campaign { campaign_id: 123 }                │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│ 2. LOAD CAMPAIGN FROM DATABASE                                  │
│    File: start_campaign.php (Line 33)                           │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│    SELECT campaign_id, mail_subject, mail_body, csv_list_id,   │
│           template_id, import_batch_id                          │
│    FROM campaign_master                                         │
│    WHERE campaign_id = 123                                      │
│                                                                 │
│    Returns:                                                     │
│    {                                                            │
│      campaign_id: 123,                                          │
│      mail_subject: "Payment Reminder",                          │
│      mail_body: "...",                                          │
│      template_id: 1,              ← Links to mail_templates    │
│      import_batch_id: "BATCH_..." ← Links to imported_recipients│
│      csv_list_id: null                                          │
│    }                                                            │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│ 3. COUNT RECIPIENTS (Determine Source)                          │
│    File: start_campaign.php (Line 50-77)                        │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│    if (import_batch_id) {                                       │
│        // COUNT FROM IMPORTED EXCEL                             │
│        SELECT COUNT(*) FROM imported_recipients                 │
│        WHERE import_batch_id = 'BATCH_...'                      │
│          AND is_active = 1                                      │
│          AND Emails IS NOT NULL                                 │
│                                                                 │
│        Result: 1,572 recipients                                 │
│    }                                                            │
│    else if (csv_list_id) {                                      │
│        // COUNT FROM CSV UPLOAD                                 │
│        SELECT COUNT(*) FROM emails                              │
│        WHERE csv_list_id = 5                                    │
│    }                                                            │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│ 4. SPAWN EMAIL WORKER PROCESS                                   │
│    File: start_campaign.php (Line 184+)                         │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│    Worker receives:                                             │
│    - campaign_id: 123                                           │
│    - campaign data: { template_id, import_batch_id, ... }       │
│    - server config: { smtp details }                            │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│ 5. WORKER LOADS CAMPAIGN                                        │
│    File: email_blast_worker.php (Line 80-93)                    │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│    SELECT * FROM campaign_master WHERE campaign_id = 123       │
│                                                                 │
│    $campaign = {                                                │
│      campaign_id: 123,                                          │
│      mail_subject: "Payment Reminder",                          │
│      mail_body: "fallback body",                                │
│      template_id: 1,              ← Will be used for merging   │
│      import_batch_id: "BATCH_..." ← Will fetch emails from here│
│      csv_list_id: null                                          │
│    }                                                            │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│ 6. CLAIM NEXT EMAIL TO SEND                                     │
│    File: email_blast_worker.php (Line 643-697)                  │
│    Function: claimNextEmail()                                   │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│    Step 6a: Check campaign source                               │
│    ─────────────────────────────                                │
│    SELECT import_batch_id, csv_list_id                          │
│    FROM campaign_master                                         │
│    WHERE campaign_id = 123                                      │
│                                                                 │
│    Returns: import_batch_id = "BATCH_20251222_153140..."       │
│                                                                 │
│    Step 6b: Fetch email from correct source                     │
│    ───────────────────────────────────────────                  │
│    if (import_batch_id) {                                       │
│        // FETCH FROM IMPORTED EXCEL                             │
│        SELECT id, Emails AS to_mail,                            │
│               'BATCH_...' AS import_batch_id                    │
│        FROM imported_recipients                                 │
│        WHERE import_batch_id = 'BATCH_...'                      │
│          AND Emails IS NOT NULL                                 │
│          AND is_active = 1                                      │
│          AND NOT EXISTS (                                       │
│              SELECT 1 FROM mail_blaster                         │
│              WHERE campaign_id = 123                            │
│                AND to_mail = imported_recipients.Emails         │
│          )                                                      │
│        LIMIT 1                                                  │
│                                                                 │
│        Returns: {                                               │
│          id: 1,                                                 │
│          to_mail: "mithun@10kinfo.com",                         │
│          import_batch_id: "BATCH_..."                           │
│        }                                                        │
│    }                                                            │
│    else {                                                       │
│        // FETCH FROM CSV UPLOAD (emails table)                  │
│        SELECT raw_emailid FROM emails WHERE ...                 │
│    }                                                            │
│                                                                 │
│    Step 6c: Mark as claimed in mail_blaster                     │
│    ──────────────────────────────────────────                   │
│    INSERT INTO mail_blaster                                     │
│      (campaign_id, to_mail, status, ...)                        │
│    VALUES (123, 'mithun@10kinfo.com', 'pending', ...)           │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│ 7. SEND EMAIL WITH TEMPLATE MERGE                               │
│    File: email_blast_worker.php (Line 309)                      │
│    Function: sendEmail()                                        │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│    sendEmail($conn, $campaign_id, $to_email, $server,           │
│              $account, $campaign, $csv_list_id)                 │
│                                                                 │
│    Parameters passed:                                           │
│    - campaign_id: 123                                           │
│    - to_email: "mithun@10kinfo.com"                             │
│    - campaign: {                                                │
│        template_id: 1,                                          │
│        import_batch_id: "BATCH_...",                            │
│        mail_subject: "...",                                     │
│        mail_body: "..."                                         │
│      }                                                          │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│ 8. PROCESS CAMPAIGN BODY (Template Merging)                     │
│    File: email_blast_worker.php (Line 314)                      │
│    Calls: processCampaignBody()                                 │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│    $body = processCampaignBody($conn, $campaign,                │
│                                $to_email, $csv_list_id);        │
│                                                                 │
│    This function (in template_merge_helper.php):               │
│                                                                 │
│    Step 8a: Check if template is used                          │
│    ─────────────────────────────────                            │
│    $template_id = $campaign['template_id'];  // = 1            │
│    $import_batch_id = $campaign['import_batch_id'];            │
│                                                                 │
│    if (template_id == 0) {                                      │
│        return $campaign['mail_body'];  // No template          │
│    }                                                            │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│ 9. LOAD TEMPLATE FROM DATABASE                                  │
│    File: template_merge_helper.php                              │
│    Function: loadMailTemplate()                                 │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│    SELECT template_html, merge_fields                           │
│    FROM mail_templates                                          │
│    WHERE template_id = 1                                        │
│      AND is_active = 1                                          │
│                                                                 │
│    Returns:                                                     │
│    {                                                            │
│      template_html: "<h1>Dear [[BilledName]],</h1>             │
│                      <p>Your invoice [[BillNumber]]             │
│                      for [[Amount]] is [[Days]] days            │
│                      overdue.</p>",                             │
│      merge_fields: ["Amount", "Days", "BillNumber",            │
│                     "BilledName", ...]                          │
│    }                                                            │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│ 10. GET RECIPIENT DATA FROM IMPORTED_RECIPIENTS                 │
│     File: template_merge_helper.php                             │
│     Function: getEmailRowData()                                 │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│     SELECT * FROM imported_recipients                           │
│     WHERE Emails = 'mithun@10kinfo.com'                         │
│       AND import_batch_id = 'BATCH_...'                         │
│       AND is_active = 1                                         │
│     LIMIT 1                                                     │
│                                                                 │
│     Returns (47 fields from Excel):                             │
│     {                                                           │
│       Emails: "mithun@10kinfo.com",                             │
│       BilledName: "10K INFO DATA SOLUTIONS...",                 │
│       Amount: "6313",                                           │
│       Days: "3",                                                │
│       BillNumber: "RSL2024RL006315",                            │
│       BillDate: "2024-11-20",                                   │
│       ExecutiveName: "John Doe",                                │
│       ExecutiveContact: "9876543210",                           │
│       Phone: "080-12345678",                                    │
│       ... all other Excel columns ...                           │
│     }                                                           │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│ 11. MERGE TEMPLATE WITH DATA                                    │
│     File: template_merge_helper.php                             │
│     Function: mergeTemplateWithData()                           │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│     Template HTML:                                              │
│     "<h1>Dear [[BilledName]],</h1>                              │
│      <p>Your invoice [[BillNumber]] for ₹[[Amount]]            │
│      is [[Days]] days overdue.</p>"                            │
│                                                                 │
│     Recipient Data:                                             │
│     {                                                           │
│       BilledName: "10K INFO DATA SOLUTIONS...",                 │
│       Amount: "6313",                                           │
│       Days: "3",                                                │
│       BillNumber: "RSL2024RL006315"                             │
│     }                                                           │
│                                                                 │
│     Merge Process:                                              │
│     ─────────────                                               │
│     foreach ($email_data as $key => $value) {                   │
│         $placeholder = "[[" . $key . "]]";                      │
│         $template_html = str_replace($placeholder, $value, ...);│
│     }                                                           │
│                                                                 │
│     [[BilledName]]  → "10K INFO DATA SOLUTIONS..."              │
│     [[BillNumber]]  → "RSL2024RL006315"                         │
│     [[Amount]]      → "6313"                                    │
│     [[Days]]        → "3"                                       │
│                                                                 │
│     Final HTML:                                                 │
│     "<h1>Dear 10K INFO DATA SOLUTIONS...,</h1>                  │
│      <p>Your invoice RSL2024RL006315 for ₹6313                 │
│      is 3 days overdue.</p>"                                   │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│ 12. SEND PERSONALIZED EMAIL VIA SMTP                            │
│     File: email_blast_worker.php (sendEmail function)           │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│     $mail = new PHPMailer();                                    │
│     $mail->setFrom($account['email']);                          │
│     $mail->addAddress('mithun@10kinfo.com');                    │
│     $mail->Subject = 'Payment Reminder';                        │
│     $mail->Body = "<h1>Dear 10K INFO DATA SOLUTIONS...,</h1>    │
│                    <p>Your invoice RSL2024RL006315 for ₹6313   │
│                    is 3 days overdue.</p>";                    │
│     $mail->send();                                              │
│                                                                 │
│     ✓ Email sent with personalized content!                    │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│ 13. REPEAT FOR ALL RECIPIENTS                                   │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│     Loop back to Step 6 (Claim Next Email)                      │
│     Continue until all 1,572 emails sent                        │
│                                                                 │
│     Each recipient gets:                                        │
│     - Their own data from Excel                                 │
│     - Personalized email with their specific values             │
│     - Automatically merged template                             │
└─────────────────────────────────────────────────────────────────┘
```

## 📊 Database Connections Summary

### How Each Table Is Connected:

```
campaign_master (Central Table)
├─ campaign_id: 123
├─ template_id: 1           → Points to mail_templates.template_id
├─ import_batch_id: "BATCH_..." → Points to imported_recipients.import_batch_id
├─ csv_list_id: null        → Points to emails.csv_list_id (not used in this case)
├─ mail_subject: "Payment Reminder"
└─ mail_body: "fallback text"

↓ template_id = 1

mail_templates
├─ template_id: 1
├─ template_name: "Outstanding Payment"
├─ template_html: "<h1>Dear [[BilledName]]...</h1>"
└─ merge_fields: ["Amount", "Days", "BillNumber", "BilledName", ...]

↓ import_batch_id = "BATCH_..."

imported_recipients (1,572 rows)
├─ import_batch_id: "BATCH_20251222_153140_69491704d572d"
├─ Emails: "mithun@10kinfo.com"
├─ BilledName: "10K INFO DATA SOLUTIONS..."
├─ Amount: "6313"
├─ Days: "3"
├─ BillNumber: "RSL2024RL006315"
└─ ... all other Excel columns ...
```

## 🔍 Key Code Locations

### 1. Campaign Loading
**File:** `backend/includes/email_blast_worker.php`
**Line:** 80-93
```php
// Load campaign with template_id and import_batch_id
$result = $conn->query("SELECT * FROM campaign_master WHERE campaign_id = $campaign_id");
$campaign = $result->fetch_assoc();
// Now $campaign has: template_id, import_batch_id, mail_subject, mail_body
```

### 2. Email Source Detection
**File:** `backend/includes/email_blast_worker.php`
**Line:** 643-652
```php
function claimNextEmail($conn, $campaign_id) {
    // Get campaign's import_batch_id to know where to fetch emails
    $existsRes = $conn->query("SELECT import_batch_id, csv_list_id 
                               FROM campaign_master 
                               WHERE campaign_id = " . intval($campaign_id));
    $campaign_row = $existsRes->fetch_assoc();
    $import_batch_id = $campaign_row['import_batch_id'];
    
    if ($import_batch_id) {
        // Fetch from imported_recipients
    } else {
        // Fetch from emails table
    }
}
```

### 3. Fetch Email from Imported Recipients
**File:** `backend/includes/email_blast_worker.php`
**Line:** 658-671
```php
if ($import_batch_id) {
    // Query imported_recipients table
    $batch_escaped = $conn->real_escape_string($import_batch_id);
    $res = $conn->query("SELECT id, Emails AS to_mail, '$import_batch_id' AS import_batch_id 
        FROM imported_recipients 
        WHERE Emails IS NOT NULL 
        AND Emails <> '' 
        AND import_batch_id = '$batch_escaped'
        AND is_active = 1
        AND NOT EXISTS (
            SELECT 1 FROM mail_blaster mb 
            WHERE mb.campaign_id = $campaign_id 
            AND mb.to_mail = imported_recipients.Emails
        )
        LIMIT 1");
}
```

### 4. Template Merging Trigger
**File:** `backend/includes/email_blast_worker.php`
**Line:** 314
```php
// This is where template merging happens
$body = processCampaignBody($conn, $campaign, $to_email, $csv_list_id);
```

### 5. Process Campaign Body (Template Handler)
**File:** `backend/includes/template_merge_helper.php`
**Line:** 147-170
```php
function processCampaignBody($conn, $campaign, $to_email, $csv_list_id = null) {
    $template_id = isset($campaign['template_id']) ? intval($campaign['template_id']) : 0;
    $import_batch_id = isset($campaign['import_batch_id']) ? $campaign['import_batch_id'] : null;
    
    // If no template, return regular mail_body
    if ($template_id === 0) {
        return $campaign['mail_body'];
    }
    
    // Load template from mail_templates table
    $template = loadMailTemplate($conn, $template_id);
    
    // Get recipient data from imported_recipients table
    $email_data = getEmailRowData($conn, $to_email, $csv_list_id, $import_batch_id);
    
    // Merge template with data
    $merged_html = mergeTemplateWithData($template['template_html'], $email_data);
    
    return $merged_html;
}
```

### 6. Get Recipient Data
**File:** `backend/includes/template_merge_helper.php`
**Line:** 41-68
```php
function getEmailRowData($conn, $email, $csv_list_id = null, $import_batch_id = null) {
    // If import_batch_id is provided, query imported_recipients
    if ($import_batch_id) {
        $batch_escaped = $conn->real_escape_string($import_batch_id);
        $query = "SELECT * FROM imported_recipients 
                  WHERE Emails = '$email_escaped' 
                  AND import_batch_id = '$batch_escaped' 
                  AND is_active = 1 
                  LIMIT 1";
        
        $result = $conn->query($query);
        if ($result && $result->num_rows > 0) {
            return $result->fetch_assoc(); // Returns all Excel columns
        }
    }
    // Otherwise, query emails table (CSV)
    // ...
}
```

### 7. Merge Template with Data
**File:** `backend/includes/template_merge_helper.php`
**Line:** 115-138
```php
function mergeTemplateWithData($template_html, $email_data) {
    // Replace all [[FieldName]] placeholders
    foreach ($email_data as $key => $value) {
        $placeholder = '[[' . $key . ']]';
        $safe_value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $template_html = str_replace($placeholder, $safe_value, $template_html);
    }
    
    return $template_html;
}
```

## 🎯 Real Example with Your Data

### Campaign Record:
```sql
SELECT * FROM campaign_master WHERE campaign_id = 123;

Result:
campaign_id: 123
description: "Outstanding Payment Reminders"
template_id: 1                                    ← Uses template #1
import_batch_id: "BATCH_20251222_153140_69491704d572d"  ← Uses invoice batch
mail_subject: "Payment Reminder"
mail_body: "Default body if template fails"
```

### Template Record:
```sql
SELECT * FROM mail_templates WHERE template_id = 1;

Result:
template_id: 1
template_name: "Outstanding Payment"
template_html: "<h1>Dear [[BilledName]],</h1>
                <p>Your invoice [[BillNumber]] for ₹[[Amount]] 
                is [[Days]] days overdue.</p>
                <p>Contact: [[ExecutiveName]] - [[ExecutiveContact]]</p>"
merge_fields: ["Amount", "BillDate", "BillNumber", "BilledName", 
               "Days", "ExecutiveContact", "ExecutiveName"]
```

### Email Recipients (from imported_recipients):
```sql
SELECT * FROM imported_recipients 
WHERE import_batch_id = 'BATCH_20251222_153140_69491704d572d' 
LIMIT 3;

Result (1,572 total records):
1. Emails: "mithun@10kinfo.com"
   BilledName: "10K INFO DATA SOLUTIONS..."
   Amount: "6313"
   Days: "3"
   BillNumber: "RSL2024RL006315"

2. Emails: "hemanth.ananth@24-7intouch.com"
   BilledName: "24/7 INTOUCH"
   Amount: "2953"
   Days: "32"
   BillNumber: "RSL2024RL006316"

3. Emails: "mwmangalore@gmail.com"
   BilledName: "MW TECH SOLUTIONS"
   Amount: "5310"
   Days: "239"
   BillNumber: "RSL2024RL006317"
```

### Final Merged Emails:

**Email 1:**
```
To: mithun@10kinfo.com
Subject: Payment Reminder
Body:
<h1>Dear 10K INFO DATA SOLUTIONS...,</h1>
<p>Your invoice RSL2024RL006315 for ₹6313 is 3 days overdue.</p>
<p>Contact: John Doe - 9876543210</p>
```

**Email 2:**
```
To: hemanth.ananth@24-7intouch.com
Subject: Payment Reminder
Body:
<h1>Dear 24/7 INTOUCH,</h1>
<p>Your invoice RSL2024RL006316 for ₹2953 is 32 days overdue.</p>
<p>Contact: John Doe - 9876543210</p>
```

**Email 3:**
```
To: mwmangalore@gmail.com
Subject: Payment Reminder
Body:
<h1>Dear MW TECH SOLUTIONS,</h1>
<p>Your invoice RSL2024RL006317 for ₹5310 is 239 days overdue.</p>
<p>Contact: John Doe - 9876543210</p>
```

## ✅ Summary

**3 Key Connections:**

1. **Campaign → Template**
   - `campaign_master.template_id` = `mail_templates.template_id`
   - Determines which template to use for merging

2. **Campaign → Email Source**
   - `campaign_master.import_batch_id` = `imported_recipients.import_batch_id`
   - Determines which emails to send to (1,572 recipients)

3. **Template + Email Data → Personalized Content**
   - Template placeholders: `[[BilledName]]`, `[[Amount]]`, etc.
   - Email data from: `imported_recipients` row
   - Result: Unique email for each recipient

**The system automatically:**
- Fetches correct campaign details
- Loads correct template
- Gets correct recipient list
- Merges template with each recipient's data
- Sends personalized emails
