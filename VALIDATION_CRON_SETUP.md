# Email Validation Cron Setup

## Overview
These two files work together to perform **complete email validation** (both domain and SMTP) in parallel.

## Files

### 1. `/backend/includes/smtp_validation_cron.php`
**Main orchestrator** - Run this in cron every minute

**What it does:**
- Fetches all unprocessed emails (`domain_processed = 0`)
- Spawns parallel workers based on email count (1-30 workers)
- Collects results from workers
- Updates `csv_list` counts (valid_count, invalid_count)
- **Marks csv_list as 'completed' when all emails are validated**

### 2. `/backend/worker/smtp_worker_parallel.php`
**Worker process** - Called automatically by the cron script

**What it does:**
- Validates each email through:
  - ✓ Domain verification (MX records, DNS)
  - ✓ SMTP validation (mailbox existence)
  - ✓ Catch-all detection
  - ✓ Disposable email detection
  - ✓ Role email detection
- Updates `emails` table immediately with results
- Marks emails as processed (`domain_processed = 1`)
- Updates csv_list progress every 10 emails

## Database Updates

### emails table:
```sql
UPDATE emails SET
  domain_status = 1,              -- 1=valid, 0=invalid
  validation_status = 'valid',     -- 'valid' or 'invalid'
  domain_processed = 1,            -- Marks as completed
  domain_verified = 1,             -- Domain check done
  validation_response = 'SMTP response message'
WHERE raw_emailid = 'email@example.com'
```

### csv_list table:
```sql
-- Updated continuously:
UPDATE csv_list SET
  valid_count = (count of valid emails),
  invalid_count = (count of invalid emails)
WHERE id = ?

-- Marked as completed when all emails processed:
UPDATE csv_list SET
  status = 'completed'
WHERE id = ? 
  AND (valid_count + invalid_count) >= total_emails
```

## Cron Setup

Add to crontab (run every minute):
```bash
* * * * * /usr/bin/php /opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/backend/includes/smtp_validation_cron.php >> /opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/backend/logs/validation_cron.log 2>&1
```

Or for XAMPP:
```bash
* * * * * /opt/lampp/bin/php /opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/backend/includes/smtp_validation_cron.php >> /opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/backend/logs/validation_cron.log 2>&1
```

## Features

### ✅ Complete Validation
- No dependency on external domain_worker
- All validation in one pass (domain + SMTP)
- Immediate database updates

### ✅ Parallel Processing
- Dynamic worker allocation (1-30 workers)
- Optimized for email count:
  - Small batch (≤20): 2-4 workers
  - Medium batch (≤50): 4-8 workers
  - Large batch (≤200): 8-15 workers
  - Very large (>200): 15-30 workers

### ✅ Completion Tracking
- csv_list marked as 'completed' automatically
- Progress updated every 10 emails
- Handles multiple csv_lists simultaneously

### ✅ Lock Mechanism
- Prevents concurrent runs
- Lock file: `/backend/storage/cron.lock`

## Monitoring

Check logs in terminal:
```bash
tail -f /opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/backend/logs/validation_cron.log
```

Check worker output:
```bash
ls -la /tmp/bulk_workers_*/
```

## Troubleshooting

### Workers not cleaning up
- Check `/tmp/bulk_workers_*/` directories
- Manual cleanup: `rm -rf /tmp/bulk_workers_*`

### csv_list stuck in 'running'
Run cron manually to trigger completion check:
```bash
php /opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/backend/includes/smtp_validation_cron.php
```

### Check database state
```sql
-- Check unprocessed emails
SELECT COUNT(*) FROM emails WHERE domain_processed = 0;

-- Check csv_list status
SELECT id, status, valid_count, invalid_count, total_emails 
FROM csv_list 
WHERE status != 'completed';

-- Check completion status
SELECT 
  csv_list_id,
  COUNT(*) as total,
  SUM(CASE WHEN domain_processed = 1 THEN 1 ELSE 0 END) as processed
FROM emails 
WHERE csv_list_id IS NOT NULL
GROUP BY csv_list_id;
```

## Performance

- **Speed**: 5-30 emails/second depending on workers
- **Accuracy**: Enterprise-grade SMTP validation
- **Scalability**: Handles 1000s of emails efficiently
- **Resource**: Auto-tuned based on batch size
