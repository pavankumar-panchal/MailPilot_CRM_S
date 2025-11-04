# Email Campaign Logging System

## Overview
The email blaster now creates comprehensive logs to track every email sent, including which SMTP account was used, success/failure status, and detailed error messages.

## Log Files Location
All logs are stored in: `backend/storage/logs/`

## Log File Types

### 1. Email Details Log
**File**: `email_details_YYYY-MM-DD.log`
**Format**: Pipe-separated values (CSV-like)
```
Timestamp|Campaign_ID|To_Email|SMTP_Account_ID|SMTP_Email|Status|Error_Message
```
**Example**:
```
2025-11-03 14:30:45|11|user@example.com|29|smtp1@gmail.com|success|
2025-11-03 14:30:46|11|bad@example.com|30|smtp2@gmail.com|failed|SMTP Error: Could not connect
```
**Use Case**: Complete audit trail of all emails sent

### 2. Success Log
**File**: `success_YYYY-MM-DD.log`
**Format**: Human-readable success entries
```
Timestamp | Campaign: ID | To: email | SMTP: smtp_email (ID: smtp_id)
```
**Example**:
```
2025-11-03 14:30:45 | Campaign: 11 | To: user@example.com | SMTP: smtp1@gmail.com (ID: 29)
```
**Use Case**: Quick view of all successful sends

### 3. Failures Log
**File**: `failures_YYYY-MM-DD.log`
**Format**: Human-readable failure entries with error details
```
Timestamp | Campaign: ID | To: email | SMTP: smtp_email (ID: smtp_id) | Error: error_message
```
**Example**:
```
2025-11-03 14:30:46 | Campaign: 11 | To: bad@example.com | SMTP: smtp2@gmail.com (ID: 30) | Error: SMTP Error: Could not authenticate
```
**Use Case**: Troubleshooting failed emails

### 4. Per-SMTP Account Logs
**File**: `smtp_{SMTP_ACCOUNT_ID}_YYYY-MM-DD.log`
**Format**: Human-readable per-SMTP tracking
```
Timestamp | Campaign: ID | To: email | Status: status | Error: error_message
```
**Example**:
```
2025-11-03 14:30:45 | Campaign: 11 | To: user1@example.com | Status: success | Error: 
2025-11-03 14:31:12 | Campaign: 11 | To: user2@example.com | Status: success | Error: 
```
**Use Case**: Track performance of specific SMTP accounts

### 5. Campaign Main Log
**File**: `campaign_{CAMPAIGN_ID}.log`
**Format**: Timestamped campaign activity log
```
[Timestamp] [LEVEL] Message
```
**Example**:
```
[2025-11-03 14:30:40] [INFO] Starting email blaster for campaign 11
[2025-11-03 14:30:45] [SUCCESS] [SEND_SUCCESS] To: user@example.com | SMTP: smtp1@gmail.com (ID: 29)
[2025-11-03 14:30:46] [ERROR] [SEND_FAILED] To: bad@example.com | SMTP: smtp2@gmail.com (ID: 30) | Error: Connection timeout
```
**Use Case**: Complete campaign execution log

### 6. Campaign Errors Log
**File**: `campaign_{CAMPAIGN_ID}_errors.log`
**Format**: Same as campaign log, but only ERROR level entries
**Use Case**: Quick error review for specific campaign

## Using the Log Viewer Script

### Basic Usage
```bash
# View all logs for today
php backend/scripts/view_logs.php

# View specific log type
php backend/scripts/view_logs.php --type=failures --date=2025-11-03

# View SMTP account logs
php backend/scripts/view_logs.php --type=smtp --smtp=29 --date=2025-11-03

# View campaign logs
php backend/scripts/view_logs.php --type=campaign --campaign=11

# Search for specific text
php backend/scripts/view_logs.php --search="SMTP error"

# Show more lines
php backend/scripts/view_logs.php --type=failures --tail=200
```

### Options
- `--type=TYPE` - Log type: `success`, `failures`, `smtp`, `details`, `campaign`, `all` (default)
- `--date=DATE` - Date in YYYY-MM-DD format (default: today)
- `--smtp=ID` - SMTP account ID for smtp logs
- `--campaign=ID` - Campaign ID for campaign logs
- `--tail=N` - Number of lines to show (default: 50)
- `--search=TEXT` - Search for text in logs
- `--help` - Show help message

## Quick Analysis Examples

### Find all failures for a specific SMTP account
```bash
php backend/scripts/view_logs.php --type=smtp --smtp=29 | grep failed
```

### Count successful sends today
```bash
wc -l backend/storage/logs/success_$(date +%Y-%m-%d).log
```

### Find authentication errors
```bash
php backend/scripts/view_logs.php --type=failures --search="authenticate"
```

### View last 100 emails sent
```bash
php backend/scripts/view_logs.php --type=details --tail=100
```

### Check which SMTP accounts are being used most
```bash
php backend/scripts/view_logs.php | tail -20
```

## Log Rotation
Logs are automatically organized by date. Old logs can be archived or deleted manually:

```bash
# Archive logs older than 30 days
find backend/storage/logs/ -name "*.log" -mtime +30 -exec gzip {} \;

# Delete logs older than 90 days
find backend/storage/logs/ -name "*.log.gz" -mtime +90 -delete
```

## Troubleshooting Common Issues

### "SMTP error: Message body empty"
Check campaign main log to see if validation caught empty body:
```bash
php backend/scripts/view_logs.php --type=campaign --campaign=11 --search="empty"
```

### Why is SMTP account X failing?
View that SMTP's specific log:
```bash
php backend/scripts/view_logs.php --type=smtp --smtp=29
```

### Which emails failed in campaign?
```bash
php backend/scripts/view_logs.php --type=failures | grep "Campaign: 11"
```

### Performance check - how many emails per minute?
```bash
grep "$(date +%Y-%m-%d' '%H:%M)" backend/storage/logs/success_$(date +%Y-%m-%d).log | wc -l
```

## Log File Permissions
Ensure the web server has write permissions:
```bash
chmod 755 backend/storage/logs
chmod 644 backend/storage/logs/*.log
```
