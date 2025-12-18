# 📧 EMAIL SENDING SYSTEM - COMPLETE EXPLANATION

## 🏗️ **SYSTEM ARCHITECTURE**

### **3-Tier Structure:**
```
┌─────────────────────────────────────────────────────────────┐
│  LEVEL 1: campaign_cron.php (Cron Scheduler)               │
│  - Runs every minute via cron                               │
│  - Checks for campaigns ready to send                       │
│  - Spawns orchestrator for each campaign                    │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│  LEVEL 2: email_blast_parallel.php (Orchestrator)          │
│  - ONE daemon per campaign                                  │
│  - Loads all SMTP servers for the campaign                  │
│  - Spawns ONE worker per SMTP server                        │
│  - Monitors overall progress                                │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│  LEVEL 3: email_blast_worker.php (Workers)                 │
│  - ONE worker per SMTP server (7 workers total)            │
│  - Each loads its server's SMTP accounts                    │
│  - Sends emails using round-robin account rotation          │
│  - Handles retries and failures                             │
└─────────────────────────────────────────────────────────────┘
```

---

## 📊 **YOUR CAMPAIGN #69 SETUP**

### **SMTP Infrastructure:**
- **7 SMTP Servers** (IDs: 14, 15, 16, 17, 18, 19, 20)
- **162 Total SMTP Accounts** distributed across servers:
  - Server #14 (relyonsoft.info): 24 accounts
  - Server #15 (relyonmail.xyz): 23 accounts
  - Server #16 (payrollsoft.in): 23 accounts
  - Server #17 (relyonmails1.com): 23 accounts
  - Server #18 (relyonmails3.com): 23 accounts
  - Server #19 (relyonmails2.com): 23 accounts
  - Server #20 (relyonmail.online): 23 accounts

### **Email Target:**
- **673,959 valid emails** from `emails` table
- Filter: `domain_status=1 AND validation_status='valid'`

---

## 🔄 **HOW EMAILS ARE SENT (STEP-BY-STEP)**

### **Step 1: Worker Startup**
```
1. Worker receives: campaign_id=69, server_id=19
2. Loads server config: host=relyonmails2.com, port=465, encryption=ssl
3. Loads 23 SMTP accounts for this server from smtp_accounts table
4. Creates unique index on mail_blaster (if doesn't exist)
5. Enters main send loop
```

### **Step 2: Round-Robin Account Selection**
Worker maintains a rotation index and cycles through accounts:
```
Iteration 1:  Account #36 (praveen@relyonmails2.com)
Iteration 2:  Account #43 (chethan@relyonmails2.com)
Iteration 3:  Account #50 (divya@relyonmails2.com)
...
Iteration 23: Account #183 (joel@relyonmails2.com)
Iteration 24: Account #190 (akash@relyonmails2.com)
Iteration 25: Back to Account #36 (strict rotation)
```

**Account Eligibility Check:**
Before using an account, worker verifies:
- ✅ Daily limit not reached (sent_today < daily_limit)
- ✅ Hourly limit not reached (last hour sends < hourly_limit)
- ❌ If both limits reached → skip to next account
- ❌ If ALL accounts at limit → sleep 2 seconds and retry

### **Step 3: Email Claiming (Atomic Operation)**
Two sources for emails to send:

**A) Priority: Failed/Pending Emails (Retry Queue)**
```sql
SELECT to_mail FROM mail_blaster 
WHERE campaign_id = 69 
  AND status IN ('pending', 'failed')
  AND attempt_count < 5
  -- PRIORITIZE cross-server retry (avoid same server)
  AND smtpid NOT IN (accounts from current server)
ORDER BY attempt_count ASC
LIMIT 1
```

**B) Fallback: Fresh Emails**
```sql
SELECT raw_emailid FROM emails 
WHERE domain_status = 1 
  AND validation_status = 'valid'
  AND NOT EXISTS (
    SELECT 1 FROM mail_blaster 
    WHERE to_mail = raw_emailid 
      AND campaign_id = 69
      AND (status = 'success' OR status = 'pending' OR (status = 'failed' AND attempt_count >= 5))
  )
ORDER BY id ASC
LIMIT 1
```

**Claiming Process:**
```sql
INSERT INTO mail_blaster 
  (campaign_id, to_mail, smtpid, status, attempt_count)
VALUES (69, 'user@example.com', NULL, 'pending', 1)
ON DUPLICATE KEY UPDATE
  attempt_count = attempt_count + 1,
  status = 'pending'
```

### **Step 4: Email Sending via PHPMailer**
```php
1. Validate email address format
2. Configure PHPMailer with SMTP settings:
   - Host: relyonmails2.com
   - Port: 465
   - Encryption: SSL
   - Username: praveen@relyonmails2.com
   - Password: (from account)
3. Set From: praveen@relyonmails2.com
4. Set To: user@example.com
5. Set Subject: "testing"
6. Set Body: HTML with embedded images
7. Attach files from storage/attachments/
8. Send via PHPMailer->send()
```

### **Step 5: Record Result**

**✅ SUCCESS:**
```sql
UPDATE mail_blaster SET
  smtpid = 36,
  status = 'success',
  delivery_date = CURDATE(),
  delivery_time = CURTIME()
WHERE campaign_id = 69 AND to_mail = 'user@example.com'

UPDATE smtp_accounts SET
  sent_today = sent_today + 1,
  total_sent = total_sent + 1
WHERE id = 36

INSERT INTO smtp_usage (smtp_id, date, hour, emails_sent)
VALUES (36, '2025-12-15', 3, 1)
ON DUPLICATE KEY UPDATE emails_sent = emails_sent + 1
```

**❌ FAILURE:**
```sql
UPDATE mail_blaster SET
  smtpid = 36,
  status = 'failed',
  error_message = 'SMTP Error: Could not authenticate',
  attempt_count = attempt_count + 1
WHERE campaign_id = 69 AND to_mail = 'user@example.com'
```

### **Step 6: Loop & Throttle**
```
- Move to next account in rotation (Account #43)
- Sleep 100ms (0.1 seconds) between emails
- Repeat from Step 3
```

---

## 🔁 **RETRY LOGIC (5 ATTEMPTS MAXIMUM)**

### **How Retries Work:**

**Attempt 1 (Server #19 - relyonmails2.com):**
```
- Account #36 tries to send → FAILS
- mail_blaster: status='failed', attempt_count=1, smtpid=36
- Email stays in retry queue
```

**Attempt 2 (Different Server #14 - relyonsoft.info):**
```
- Worker #14 picks it from retry queue (cross-server priority)
- Account from Server #14 tries → FAILS
- mail_blaster: status='failed', attempt_count=2, smtpid=(server 14 account)
```

**Attempt 3 (Different Server #17 - relyonmails1.com):**
```
- Worker #17 picks it from retry queue
- Tries with Server #17 account → FAILS
- mail_blaster: status='failed', attempt_count=3
```

**Attempt 4 (Another Server):**
```
- Different server tries again → FAILS
- mail_blaster: status='failed', attempt_count=4
```

**Attempt 5 (Final Try):**
```
- Another server makes 5th attempt → FAILS
- mail_blaster: status='failed', attempt_count=5
- Error message: "Max retries exceeded: [original error]"
- ⛔ PERMANENTLY FAILED - will not retry again
```

### **Retry Priority Logic:**
1. **Cross-server retry** (preferred): Pick failed emails that were tried by OTHER servers
2. **Same-server retry** (fallback): If no cross-server retries available
3. **Fresh emails** (lowest priority): Only when no retries pending

### **Why 5 Attempts?**
- Distributes retries across multiple SMTP servers
- Avoids getting stuck on problematic email addresses
- Balances persistence with efficiency
- After 5 failures across different servers → likely a permanent issue

---

## ⏸️ **PAUSE/STOP FUNCTIONALITY**

### **How Pause Works:**
Every **10 loop iterations**, each worker checks campaign status:
```sql
SELECT status FROM campaign_status WHERE campaign_id = 69
```

**Status Values:**
- `running` → Continue sending
- `paused` → Stop worker gracefully (can resume later)
- `stopped` → Stop worker permanently
- `completed` → Campaign finished

**To Pause Campaign:**
```sql
UPDATE campaign_status 
SET status = 'paused' 
WHERE campaign_id = 69
```

**What Happens:**
1. All 7 workers check status every ~10 iterations (~1 second)
2. Workers see `status='paused'`
3. Workers log: "Campaign status is 'paused', stopping worker"
4. Workers exit gracefully
5. Pending emails remain in mail_blaster for resume

**To Resume:**
```sql
UPDATE campaign_status 
SET status = 'running' 
WHERE campaign_id = 69
```
Then re-run campaign_cron.php to spawn new workers.

---

## 📈 **MONITORING & LOGS**

### **Log Files:**
- **`backend/logs/email_worker.log`** - Worker activity (claiming, sending, failures)
- **`backend/logs/email_blast_parallel_[date].log`** - Orchestrator activity
- **`backend/logs/php_worker_errors.log`** - Fatal errors

### **Database Tracking:**

**mail_blaster table:**
```sql
SELECT 
  status,
  COUNT(*) as count,
  AVG(attempt_count) as avg_attempts
FROM mail_blaster 
WHERE campaign_id = 69
GROUP BY status
```

**smtp_accounts table:**
```sql
SELECT 
  id,
  email,
  sent_today,
  total_sent,
  daily_limit
FROM smtp_accounts
WHERE smtp_server_id = 19
ORDER BY sent_today DESC
```

**smtp_usage table:**
```sql
SELECT 
  smtp_id,
  hour,
  emails_sent
FROM smtp_usage
WHERE date = CURDATE()
ORDER BY hour DESC, emails_sent DESC
```

---

## 🎯 **KEY FEATURES**

### ✅ **Implemented:**
1. **Parallel Processing** - 7 workers send simultaneously
2. **Round-Robin** - Fair distribution across all 162 SMTP accounts
3. **Rate Limiting** - Respects daily/hourly limits per account
4. **Atomic Claiming** - No duplicate sends (unique index on campaign_id + email)
5. **Cross-Server Retry** - Failed emails retry on DIFFERENT servers
6. **5-Attempt Limit** - Prevents infinite retries on bad addresses
7. **Pause Support** - Workers check status every 10 iterations
8. **Comprehensive Logging** - Track every send attempt

### 🚀 **Performance:**
- **162 accounts × 7 servers = parallel sending**
- **~100ms delay between sends** = 10 emails/second per worker
- **7 workers = ~70 emails/second** theoretical max
- **Actual: ~30-50 emails/second** (after limits, retries, pauses)
- **673,959 emails ÷ 40 emails/sec = ~4.7 hours** to complete

---

## 🛠️ **OPERATIONS GUIDE**

### **Start Campaign:**
```bash
cd /opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/backend
/opt/plesk/php/8.1/bin/php campaign_cron.php
```

### **Pause Campaign:**
```sql
UPDATE campaign_status SET status = 'paused' WHERE campaign_id = 69;
```

### **Resume Campaign:**
```sql
UPDATE campaign_status SET status = 'running' WHERE campaign_id = 69;
# Then run campaign_cron.php again
```

### **Check Progress:**
```sql
SELECT 
  total_emails,
  sent_emails,
  failed_emails,
  pending_emails,
  status
FROM campaign_status 
WHERE campaign_id = 69;
```

### **View Recent Activity:**
```bash
tail -f backend/logs/email_worker.log
```

### **Find Failed Accounts:**
```sql
SELECT 
  to_mail,
  attempt_count,
  error_message
FROM mail_blaster 
WHERE campaign_id = 69 
  AND status = 'failed'
  AND attempt_count >= 5
LIMIT 100;
```

---

## 🎓 **SUMMARY**

Your email system sends **673,959 emails** using **162 SMTP accounts** across **7 servers** with **7 parallel workers**. Each worker rotates through its server's accounts in **strict round-robin**, respects **rate limits**, retries failures **up to 5 times across different servers**, and checks **every 10 iterations** if the campaign is **paused/stopped**. The system is **production-ready** with comprehensive **logging**, **atomic operations**, and **graceful handling** of failures.
