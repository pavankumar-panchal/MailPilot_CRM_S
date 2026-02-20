# Multi-User SMTP Validation System - Complete Guide

## Overview

This enhanced SMTP validation system now supports **up to 3 concurrent users** working simultaneously on 2 different servers. The system intelligently allocates workers between users to ensure fair and optimal processing speed for all.

## Key Features

### 1. **Multi-User Support**
- Supports up to 3 concurrent users processing emails simultaneously
- Automatically detects active users (those with pending/unprocessed emails)
- Fair worker allocation based on workload

### 2. **Dynamic Worker Allocation**
The system dynamically allocates workers based on the number of active users:

| Active Users | Worker Allocation |
|--------------|-------------------|
| 1 user | ALL 50 workers for that single user |
| 2 users | 25 workers each (or proportional based on pending emails) |
| 3 users | ~16-17 workers each (or proportional based on pending emails) |

### 3. **Comprehensive Logging**
- Main cron log: `backend/logs/smtp_validation_cron_YYYY-MM-DD.log`
- User-specific logs: `backend/logs/user_{USER_ID}_YYYY-MM-DD.log`
- Worker-specific logs: `backend/logs/user_{USER_ID}_worker_{WORKER_ID}_YYYY-MM-DD_HH-MM-SS.log`

### 4. **Real-Time Monitoring**
All processing is logged in detail, including:
- Each email processed (one by one)
- Validation status (valid/invalid/catch-all)
- SMTP responses
- Worker allocation and progress
- Processing speed and completion times

## System Architecture

### Files Modified

1. **`backend/includes/smtp_validation_cron.php`**
   - Main orchestrator for multi-user processing
   - Detects active users
   - Allocates workers fairly
   - Spawns parallel workers per user
   - Comprehensive logging

2. **`backend/worker/smtp_worker_parallel.php`**
   - Individual worker that processes email batches
   - User-aware validation
   - Detailed per-email logging
   - Real-time progress updates

### Configuration

Adjust these constants in `smtp_validation_cron.php`:

```php
// Maximum concurrent users supported
define('MAX_CONCURRENT_USERS', 3);

// Total workers available (divided among active users)
define('TOTAL_WORKERS_POOL', 50);

// Minimum workers per user (ensures decent speed)
define('MIN_WORKERS_PER_USER', 5);

// Enable/disable detailed logging
define('ENABLE_DETAILED_LOGGING', true);
```

## How It Works

### Workflow

```
┌─────────────────────────────────────────┐
│  1. CRON runs every minute              │
│     (smtp_validation_cron.php)          │
└─────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────┐
│  2. Detect Active Users                 │
│     - Query emails table for            │
│       unprocessed emails                │
│     - Group by user_id                  │
│     - Limit to 3 users (FIFO)          │
└─────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────┐
│  3. Allocate Workers                    │
│     - 1 user: All 50 workers           │
│     - 2 users: 25/25 split             │
│     - 3 users: ~16/17/17 split         │
└─────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────┐
│  4. Process Each User                   │
│     For each user:                      │
│     a. Fetch pending emails             │
│     b. Create batch directory           │
│     c. Spawn allocated workers          │
│     d. Monitor progress                 │
└─────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────┐
│  5. Workers Process Emails              │
│     - Validate domain + SMTP            │
│     - Update database immediately       │
│     - Log each email                    │
│     - Save results to JSON              │
└─────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────┐
│  6. Complete & Cleanup                  │
│     - Mark completed csv_lists          │
│     - Generate summary logs             │
│     - Release lock                      │
└─────────────────────────────────────────┘
```

## Monitoring & Logs

### Main Cron Log
Location: `backend/logs/smtp_validation_cron_YYYY-MM-DD.log`

Shows:
- Active users detected
- Worker allocation per user
- Overall progress
- Completion summary

Example:
```
[2026-02-19 10:15:01] === MULTI-USER SMTP VALIDATION CRON START ===
[2026-02-19 10:15:01] Database connected successfully
[2026-02-19 10:15:01] Max concurrent users: 3
[2026-02-19 10:15:01] Total workers pool: 50
[2026-02-19 10:15:02] Found 2 active user(s) with pending emails:
[2026-02-19 10:15:02]   - User ID 5: 1500 pending emails
[2026-02-19 10:15:02]   - User ID 8: 800 pending emails
[2026-02-19 10:15:02] User 5: Allocated 28 workers for 1500 emails
[2026-02-19 10:15:02] User 8: Allocated 22 workers for 800 emails
```

### User-Specific Logs
Location: `backend/logs/user_{USER_ID}_YYYY-MM-DD.log`

Shows all activity for a specific user:
- Worker spawning
- Progress updates
- Completion status

Example:
```
[2026-02-19 10:15:02] [User 5] ======= PROCESSING USER 5 =======
[2026-02-19 10:15:02] [User 5] Allocated workers: 28
[2026-02-19 10:15:02] [User 5] Found 1500 emails to validate
[2026-02-19 10:15:03] [User 5] DYNAMIC ALLOCATION: 1500 emails → 28 workers (~54 emails/worker)
[2026-02-19 10:15:03] [User 5 | Worker 1] Spawned successfully (processing emails 0 to 54)
[2026-02-19 10:15:03] [User 5 | Worker 2] Spawned successfully (processing emails 54 to 108)
...
```

### Worker-Specific Logs
Location: `backend/logs/user_{USER_ID}_worker_{WORKER_ID}_YYYY-MM-DD_HH-MM-SS.log`

Shows detailed processing for each worker:
- Individual email validation
- SMTP responses
- Success/failure for each email

Example:
```
=== WORKER LOG STARTED ===
Worker ID: 1
User ID: 5
Process ID: abc123def456
Started: 2026-02-19 10:15:03
=========================

[2026-02-19 10:15:03] [User 5 | Worker 1] Processing emails from index 0 to 54 (54 emails)
[2026-02-19 10:15:05] ✓ EMAIL: john@example.com | STATUS: valid | RESPONSE: 192.168.1.1
[2026-02-19 10:15:06] ✗ EMAIL: invalid@bad.com | STATUS: invalid | RESPONSE: 550 User not found
[2026-02-19 10:15:08] ✓ EMAIL: jane@company.com | STATUS: valid | RESPONSE: 10.0.0.5
...
[2026-02-19 10:16:23] [User 5 | Worker 1] Processed 54 emails: Valid=42, Invalid=12
```

## Monitoring Commands

### View Main Cron Log (Real-Time)
```bash
tail -f /opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/backend/logs/smtp_validation_cron_$(date +%Y-%m-%d).log
```

### View Specific User's Activity
```bash
# Replace {USER_ID} with actual user ID (e.g., 5)
tail -f /opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/backend/logs/user_{USER_ID}_$(date +%Y-%m-%d).log
```

### View All Logs (Combined)
```bash
tail -f /opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/backend/logs/*.log
```

### Count Processed Emails
```bash
grep "✓ EMAIL:" /opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/backend/logs/*.log | wc -l
```

### Check Current Active Workers
```bash
ps aux | grep smtp_worker_parallel.php
```

## Database Schema

The system uses the following fields in the `emails` table:

```sql
- raw_emailid VARCHAR(255) - Original email address
- user_id INT - Owner of this email (for multi-user support)
- csv_list_id INT - Associated CSV list
- domain_processed TINYINT - 0=pending, 1=processed
- domain_status TINYINT - 0=invalid, 1=valid
- validation_status VARCHAR(50) - 'valid', 'invalid', 'catch-all', 'disposable'
- validation_response TEXT - SMTP response or IP address
- domain_verified TINYINT - Domain verification flag
```

The `csv_list` table:
```sql
- id INT - Primary key
- user_id INT - Owner of this list
- status VARCHAR(50) - 'pending', 'running', 'completed'
- total_emails INT - Total emails in list
- valid_count INT - Count of valid emails
- invalid_count INT - Count of invalid emails
```

## Deployment on 2 Servers

### Server 1 Setup
```bash
# Install cron job
crontab -e

# Add this line (runs every minute)
* * * * * /usr/bin/php /opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/backend/includes/smtp_validation_cron.php >> /opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/backend/logs/cron_output.log 2>&1
```

### Server 2 Setup
```bash
# Same setup as Server 1
crontab -e

# Add this line (runs every minute)
* * * * * /usr/bin/php /opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/backend/includes/smtp_validation_cron.php >> /opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/backend/logs/cron_output.log 2>&1
```

**Note:** Both servers will work on the same database, automatically coordinating user processing without conflicts.

## Performance Expectations

### Single User
- Workers: 50
- Emails per worker: 50-100
- Processing speed: ~2,500-5,000 emails/minute

### Two Users
- Workers per user: 25 each
- Processing speed per user: ~1,250-2,500 emails/minute
- Total throughput: ~2,500-5,000 emails/minute

### Three Users
- Workers per user: ~16-17 each
- Processing speed per user: ~800-1,700 emails/minute
- Total throughput: ~2,500-5,000 emails/minute

## Troubleshooting

### No Processing Happening
```bash
# Check if cron is running
ps aux | grep smtp_validation_cron.php

# Check lock file
ls -lah /opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/backend/storage/cron.lock

# If stuck, remove lock file
rm /opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/backend/storage/cron.lock
```

### Workers Not Starting
```bash
# Check PHP binary path
which php

# Update PHP_BINARY in smtp_validation_cron.php if different
```

### Logs Not Creating
```bash
# Check logs directory permissions
chmod 755 /opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/backend/logs/

# Create directory if missing
mkdir -p /opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/backend/logs/
```

### Database Connection Issues
```bash
# Check database configuration
cat /opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/backend/config/db.php

# Test connection
mysql -u [username] -p[password] -h [host] [database]
```

## Best Practices

1. **Monitor Regularly**: Check logs daily to ensure smooth processing
2. **Backup Logs**: Archive logs weekly to prevent disk space issues
3. **Database Maintenance**: Run `OPTIMIZE TABLE emails, csv_list` weekly
4. **Resource Monitoring**: Monitor CPU/memory usage on both servers
5. **Scale Gradually**: Start with fewer workers if experiencing performance issues

## Support & Maintenance

### Log Rotation
```bash
# Add to crontab for automatic log cleanup (keeps last 7 days)
0 0 * * * find /opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/backend/logs/ -name "*.log" -mtime +7 -delete
```

### Performance Tuning
Adjust these values based on server capacity:
- `TOTAL_WORKERS_POOL`: Increase/decrease based on CPU cores
- `MIN_EMAILS_PER_WORKER`: Higher = fewer workers, lower = more workers
- `SIP_SMTP_SOCKET_TIMEOUT`: Increase for slow networks

---

## Quick Start Checklist

- [ ] Files deployed on both servers
- [ ] Logs directory created with proper permissions
- [ ] Cron jobs configured on both servers
- [ ] Database accessible from both servers
- [ ] PHP binary path verified
- [ ] Test run with single user
- [ ] Monitor logs for 24 hours
- [ ] Scale to multiple users

**System Ready!** 🚀

Your multi-user SMTP validation system is now configured and ready to process emails for up to 3 concurrent users with automatic worker allocation and comprehensive monitoring.
