EMAIL WORKER FIX - TESTING GUIDE
================================

## PROBLEM FIXED
The worker was claiming emails successfully but then detecting false duplicates, 
preventing ANY emails from being sent. This happened because:
1. Variables were not reset between loop iterations
2. Duplicate records existed in mail_blaster table
3. Global duplicate checks were running even for pre-claimed records

## CHANGES MADE

### 1. Variable Initialization (Line ~300)
- Added proper reset of all email-related variables at start of each loop iteration
- Prevents stale data from previous iterations

### 2. Parameter Preparation (Line ~525)
- Moved csv_id_param and mail_blaster_id_param preparation BEFORE logging
- Added mail_blaster_id to debug output

### 3. Pre-claimed Record Handling (Line ~785)
- When mail_blaster_id is provided (from fetchNextPending), skip global duplicate checks
- Only verify the specific claimed record
- Proceed with sending if status is not 'success'

### 4. Debug Logging
- Added logging to show mail_blaster_id value being passed to sendEmail()
- Shows in logs: "sendEmail() called with mail_blaster_id=123" or "mail_blaster_id=NULL"

## TESTING STEPS

### Step 1: Start a New Campaign
```bash
# Watch the worker logs in real-time
tail -f /var/www/vhosts/relyonmail.xyz/httpdocs/emailvalidation/backend/logs/email_worker_$(date +%Y-%m-%d).log
```

### Step 2: Look for These Log Messages

✅ GOOD - Email should send:
```
🔧 sendEmail() called with mail_blaster_id=123
✓ Working with pre-claimed mail_blaster record ID: 123
✓ Pre-claimed record verified: ID=123, status=processing
✅ SEND SUCCESS to email@example.com
```

❌ BAD - mail_blaster_id not passed:
```
🔧 sendEmail() called with mail_blaster_id=NULL
✓ SKIP: Email already sent successfully - will NOT re-send
```
→ If you see this, there's still an issue with variable passing

❌ BAD - Record already success:
```
🔧 sendEmail() called with mail_blaster_id=123
✓ SKIP: Mail blaster record 123 already has status='success' - will NOT re-send
```
→ This means the record was already sent (race condition or duplicate)

### Step 3: Check Database for Duplicates

```sql
-- Connect to your database
mysql -u root -p

-- Check for duplicate records
USE CRM;

SELECT 
    campaign_id, 
    to_mail, 
    COUNT(*) as duplicate_count,
    GROUP_CONCAT(id ORDER BY id) as record_ids,
    GROUP_CONCAT(status ORDER BY id) as statuses
FROM mail_blaster 
WHERE campaign_id = YOUR_CAMPAIGN_ID
GROUP BY campaign_id, to_mail 
HAVING COUNT(*) > 1;
```

If you find duplicates, run the cleanup script:
```bash
mysql -u root -p CRM < /opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/backend/scripts/cleanup_duplicate_mail_blaster_records.sql
```

### Step 4: Verify SMTP Usage Updates

After emails send successfully, verify smtp_usage is being updated:

```sql
SELECT 
    smtp_id,
    date,
    hour,
    emails_sent,
    timestamp
FROM smtp_usage
WHERE date = CURDATE()
ORDER BY timestamp DESC
LIMIT 20;
```

### Step 5: Verify Mail Blaster Updates

Check that mail_blaster records have status='success':

```sql
SELECT 
    id,
    campaign_id,
    to_mail,
    status,
    smtp_email,
    delivery_time,
    attempt_count
FROM mail_blaster
WHERE campaign_id = YOUR_CAMPAIGN_ID
ORDER BY id DESC
LIMIT 20;
```

## TROUBLESHOOTING

### Issue: Still seeing "mail_blaster_id=NULL"
**Cause**: Variable not being captured from fetchNextPending() or claimNextEmail()
**Fix**: Check if fetchNextPending() is actually returning 'mail_blaster_id' in the array

### Issue: Emails stuck in 'processing' status
**Cause**: Worker crashed after claiming but before sending
**Fix**: Run this query to reset stuck emails:
```sql
UPDATE mail_blaster 
SET status = 'pending', delivery_time = NULL 
WHERE status = 'processing' 
  AND delivery_time < DATE_SUB(NOW(), INTERVAL 2 MINUTE)
  AND campaign_id = YOUR_CAMPAIGN_ID;
```

### Issue: Duplicate records keep appearing
**Cause**: Missing UNIQUE constraint on (campaign_id, to_mail)
**Fix**: 
```sql
-- First clean duplicates, then add constraint
ALTER TABLE mail_blaster 
ADD UNIQUE KEY unique_campaign_email (campaign_id, to_mail);
```

### Issue: "SMTP connection failed"
**Cause**: SMTP credentials invalid or server down
**Check**:
```sql
SELECT id, email, smtp_server_id, status 
FROM smtp_accounts 
WHERE id = YOUR_SMTP_ACCOUNT_ID;

SELECT id, host, port, encryption, status 
FROM smtp_servers 
WHERE id = YOUR_SMTP_SERVER_ID;
```

## EXPECTED BEHAVIOR NOW

1. ✅ Worker claims email from mail_blaster with status='pending'
2. ✅ Changes status to 'processing' and commits immediately
3. ✅ Returns mail_blaster_id to worker loop
4. ✅ Worker passes mail_blaster_id to sendEmail()
5. ✅ sendEmail() checks ONLY that specific record (not global search)
6. ✅ If status != 'success', proceeds to send
7. ✅ Email sends via SMTP
8. ✅ recordDelivery() updates mail_blaster to status='success'
9. ✅ smtp_usage gets incremented
10. ✅ smtp_accounts counters get incremented

## FILES MODIFIED

1. `/backend/includes/email_blast_worker.php`
   - Added variable initialization in loop
   - Added debug logging for mail_blaster_id
   - Fixed pre-claimed record duplicate check logic

2. `/backend/scripts/cleanup_duplicate_mail_blaster_records.sql` (NEW)
   - Database cleanup queries for duplicate records

## SUPPORT

If emails still don't send after these fixes:
1. Share the worker log output showing the "sendEmail() called with mail_blaster_id=..." line
2. Share the output of the duplicate detection query
3. Share the campaign_id and how many emails expected to send
