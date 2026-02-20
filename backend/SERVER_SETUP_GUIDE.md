# Multi-Server, Multi-User SMTP Validation Setup Guide

## System Architecture

This system now supports:
- **2 Servers** (each with its own worker_id)
- **3 Concurrent Users per server**
- **Automatic worker allocation** between active users
- **Comprehensive logging** for monitoring

## How It Works

### Two-Level Filtering:

1. **Server Level (worker_id)**:
   - Server 1 processes only `worker_id = 1` emails
   - Server 2 processes only `worker_id = 2` emails

2. **User Level (user_id)**:
   - Each server can handle up to 3 concurrent users
   - Workers are divided fairly among active users
   - If only 1 user active: gets all 50 workers
   - If 2 users active: ~25 workers each
   - If 3 users active: ~16-17 workers each

## Server Configuration

### Server 1 Setup

**File:** `/opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/backend/includes/smtp_validation_cron.php`

```php
// Line ~32 - Edit this value
$CONFIGURED_WORKER_ID = 1; // ← Server 1 uses worker_id = 1
```

**Cron Job:**
```bash
# Edit crontab
crontab -e

# Add this line (runs every minute)
* * * * * /usr/bin/php /opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/backend/includes/smtp_validation_cron.php >> /opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/backend/logs/cron_output.log 2>&1
```

### Server 2 Setup

**File:** `/opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/backend/includes/smtp_validation_cron.php`

```php
// Line ~32 - Edit this value
$CONFIGURED_WORKER_ID = 2; // ← Server 2 uses worker_id = 2
```

**Cron Job:**
```bash
# Edit crontab
crontab -e

# Add this line (runs every minute)
* * * * * /usr/bin/php /opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/backend/includes/smtp_validation_cron.php >> /opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/backend/logs/cron_output.log 2>&1
```

## Database Configuration

Ensure your `emails` table has these columns:

```sql
ALTER TABLE emails ADD COLUMN IF NOT EXISTS worker_id INT DEFAULT 1;
ALTER TABLE emails ADD COLUMN IF NOT EXISTS user_id INT DEFAULT NULL;
ALTER TABLE emails ADD INDEX idx_worker_processed (worker_id, domain_processed);
ALTER TABLE emails ADD INDEX idx_user_processed (user_id, domain_processed);
```

When importing emails, assign worker_id:
- **Odd user IDs (1, 3, 5, 7...)** → worker_id = 1 (Server 1)
- **Even user IDs (2, 4, 6, 8...)** → worker_id = 2 (Server 2)

Or distribute based on load balancing logic.

## Processing Flow

```
┌─────────────────────────────────────────┐
│          Emails Table                   │
│  (worker_id + user_id assigned)         │
└─────────────────────────────────────────┘
           │                    │
           │                    │
    worker_id=1            worker_id=2
           │                    │
           ▼                    ▼
┌──────────────────┐  ┌──────────────────┐
│    Server 1      │  │    Server 2      │
│  (50 workers)    │  │  (50 workers)    │
└──────────────────┘  └──────────────────┘
           │                    │
      Detects active           Detects active
      users with               users with
      worker_id=1              worker_id=2
           │                    │
           ▼                    ▼
┌──────────────────┐  ┌──────────────────┐
│ User 1: 25 wkrs  │  │ User 2: 25 wkrs  │
│ User 3: 25 wkrs  │  │ User 4: 25 wkrs  │
└──────────────────┘  └──────────────────┘
           │                    │
           ▼                    ▼
    Processes emails      Processes emails
    in parallel           in parallel
```

## Example Scenario

### Scenario 1: Balanced Load
- **Server 1**: User 1 (1000 emails), User 3 (800 emails)
  - User 1 gets ~28 workers
  - User 3 gets ~22 workers
  
- **Server 2**: User 2 (1500 emails), User 4 (500 emails)
  - User 2 gets ~37 workers
  - User 4 gets ~13 workers

### Scenario 2: Single Active User per Server
- **Server 1**: User 1 (5000 emails)
  - User 1 gets ALL 50 workers
  
- **Server 2**: User 2 (3000 emails)
  - User 2 gets ALL 50 workers

**Result:** Maximum speed for both users!

### Scenario 3: One Busy Server
- **Server 1**: User 1 (2000), User 3 (1500), User 5 (1000)
  - User 1: ~22 workers
  - User 3: ~17 workers
  - User 5: ~11 workers
  
- **Server 2**: No active users
  - Server 2 is idle (no processing)

## Monitoring

### Check Server 1 Activity
```bash
# View main log
tail -f /opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/backend/logs/smtp_validation_cron_$(date +%Y-%m-%d).log | grep "WORKER_ID: 1"

# View Server 1 worker logs
ls -lh /opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/backend/logs/srv1_*.log
```

### Check Server 2 Activity
```bash
# View main log
tail -f /opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/backend/logs/smtp_validation_cron_$(date +%Y-%m-%d).log | grep "WORKER_ID: 2"

# View Server 2 worker logs
ls -lh /opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/backend/logs/srv2_*.log
```

### Monitor Specific User Across Servers
```bash
# Replace {USER_ID} with actual user ID
tail -f /opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/backend/logs/*user_{USER_ID}_*.log
```

## Log Files Naming Convention

- **Main cron log**: `smtp_validation_cron_YYYY-MM-DD.log`
- **User log**: `user_{USER_ID}_YYYY-MM-DD.log`
- **Worker log (with server)**: `srv{WORKER_ID}_user_{USER_ID}_worker_{PARALLEL_WORKER_ID}_YYYY-MM-DD_HH-MM-SS.log`

Example:
- `srv1_user_5_worker_1_2026-02-19_10-15-03.log` → Server 1, User 5, Parallel Worker 1
- `srv2_user_8_worker_10_2026-02-19_10-16-22.log` → Server 2, User 8, Parallel Worker 10

## Testing the Setup

### Step 1: Insert Test Data
```sql
-- User 1 on Server 1
INSERT INTO emails (raw_emailid, worker_id, user_id, csv_list_id, domain_processed) 
VALUES 
('test1@example.com', 1, 1, 1, 0),
('test2@example.com', 1, 1, 1, 0);

-- User 2 on Server 2
INSERT INTO emails (raw_emailid, worker_id, user_id, csv_list_id, domain_processed) 
VALUES 
('test3@example.com', 2, 2, 2, 0),
('test4@example.com', 2, 2, 2, 0);
```

### Step 2: Wait for Cron to Run
Both servers will automatically pick up their assigned emails within 1 minute.

### Step 3: Check Logs
```bash
# Server 1 should show User 1 activity
grep "User 1" /opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/backend/logs/smtp_validation_cron_$(date +%Y-%m-%d).log

# Server 2 should show User 2 activity
grep "User 2" /opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/backend/logs/smtp_validation_cron_$(date +%Y-%m-%d).log
```

### Step 4: Verify Processing
```sql
-- Check processed emails
SELECT raw_emailid, worker_id, user_id, domain_processed, validation_status 
FROM emails 
WHERE raw_emailid LIKE 'test%';
```

## Performance Expectations

### Per Server
- **Total Workers**: 50
- **Concurrent Users**: Up to 3
- **Processing Speed**: ~2,500-5,000 emails/minute

### Combined (Both Servers)
- **Total Workers**: 100
- **Concurrent Users**: Up to 6 (3 per server)
- **Processing Speed**: ~5,000-10,000 emails/minute

## Troubleshooting

### Issue: Server processing wrong worker_id emails
**Solution:** Check the `$CONFIGURED_WORKER_ID` value in `smtp_validation_cron.php`

### Issue: Users not being detected
**Solution:** Verify that emails table has both `worker_id` and `user_id` columns populated

### Issue: No processing happening
**Solution:**
```bash
# Check cron is running
ps aux | grep smtp_validation_cron.php

# Check for lock file issues
rm /opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/backend/storage/cron.lock

# Check database connection
mysql -u [user] -p[pass] -h [host] [database]
```

### Issue: Logs not being created
**Solution:**
```bash
# Create logs directory
mkdir -p /opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/backend/logs/
chmod 755 /opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/backend/logs/
```

## Configuration Summary

| Setting | Server 1 | Server 2 |
|---------|----------|----------|
| WORKER_ID | 1 | 2 |
| MAX_CONCURRENT_USERS | 3 | 3 |
| TOTAL_WORKERS_POOL | 50 | 50 |
| Processes emails with | worker_id = 1 | worker_id = 2 |
| Log prefix | srv1_* | srv2_* |

## Quick Start Checklist

### Server 1
- [ ] Set `$CONFIGURED_WORKER_ID = 1` in smtp_validation_cron.php
- [ ] Upload both files (smtp_validation_cron.php, smtp_worker_parallel.php)
- [ ] Create logs directory with permissions
- [ ] Add cron job
- [ ] Test with sample emails (worker_id = 1)

### Server 2
- [ ] Set `$CONFIGURED_WORKER_ID = 2` in smtp_validation_cron.php
- [ ] Upload both files (smtp_validation_cron.php, smtp_worker_parallel.php)
- [ ] Create logs directory with permissions
- [ ] Add cron job
- [ ] Test with sample emails (worker_id = 2)

### Database
- [ ] Add worker_id and user_id columns to emails table
- [ ] Add appropriate indexes
- [ ] Verify data has worker_id assigned

**System Ready!** 🚀

Both servers will now process emails independently based on worker_id, while each server handles up to 3 concurrent users with automatic worker allocation and comprehensive logging.
