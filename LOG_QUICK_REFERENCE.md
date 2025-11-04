# Email Campaign Logging - Quick Reference

## 📊 Quick Commands

### View Today's Summary
```bash
./backend/scripts/log_summary.sh
```

### Monitor Live Activity (All Campaigns)
```bash
./backend/scripts/monitor_logs.sh
```

### Monitor Specific Campaign (e.g., Campaign 11)
```bash
./backend/scripts/monitor_logs.sh 11
```

### View All Failures Today
```bash
php backend/scripts/view_logs.php --type=failures
```

### View Successful Sends
```bash
php backend/scripts/view_logs.php --type=success
```

### Check Specific SMTP Account (e.g., ID 29)
```bash
php backend/scripts/view_logs.php --type=smtp --smtp=29
```

### Search for Specific Error
```bash
php backend/scripts/view_logs.php --search="authentication failed"
```

### View Campaign Details
```bash
php backend/scripts/view_logs.php --type=campaign --campaign=11
```

## 📁 Log File Types

| File | Purpose | Location |
|------|---------|----------|
| `email_details_YYYY-MM-DD.log` | Complete audit trail | `backend/storage/logs/` |
| `success_YYYY-MM-DD.log` | All successful sends | `backend/storage/logs/` |
| `failures_YYYY-MM-DD.log` | All failed sends with errors | `backend/storage/logs/` |
| `smtp_{ID}_YYYY-MM-DD.log` | Per-SMTP account tracking | `backend/storage/logs/` |
| `campaign_{ID}.log` | Campaign activity log | `backend/storage/logs/` |
| `campaign_{ID}_errors.log` | Campaign errors only | `backend/storage/logs/` |

## 🔍 Troubleshooting Examples

### Find why emails are failing
```bash
php backend/scripts/view_logs.php --type=failures --tail=50
```

### Check if a specific SMTP is having issues
```bash
php backend/scripts/view_logs.php --type=smtp --smtp=29 | grep failed
```

### See which SMTPs are being used most
```bash
./backend/scripts/log_summary.sh
```

### Find "Message body empty" errors
```bash
php backend/scripts/view_logs.php --search="body empty"
```

### Count how many emails sent in last hour
```bash
grep "$(date +%Y-%m-%d' '%H:)" backend/storage/logs/success_$(date +%Y-%m-%d).log | wc -l
```

### Check campaign progress
```bash
tail -20 backend/storage/logs/campaign_11.log
```

## 📈 Log Format Reference

### Email Details Log Format
```
Timestamp|Campaign_ID|To_Email|SMTP_Account_ID|SMTP_Email|Status|Error_Message
```

Example:
```
2025-11-03 14:30:45|11|user@example.com|29|smtp1@gmail.com|success|
2025-11-03 14:30:46|11|bad@example.com|30|smtp2@gmail.com|failed|SMTP connect() failed
```

### Success/Failure Log Format
```
Timestamp | Campaign: ID | To: email | SMTP: smtp_email (ID: id) | Error: message
```

## 🎯 Common Use Cases

### 1. Monitor Campaign in Real-Time
```bash
# Terminal 1: Start campaign from UI
# Terminal 2: Monitor logs
./backend/scripts/monitor_logs.sh 11
```

### 2. Daily Email Report
```bash
./backend/scripts/log_summary.sh
```

### 3. Find Authentication Issues
```bash
php backend/scripts/view_logs.php --type=failures --search="authenticate"
```

### 4. Check SMTP Account Health
```bash
for i in {1..50}; do
  php backend/scripts/view_logs.php --type=smtp --smtp=$i --tail=10 2>/dev/null
done | grep failed
```

### 5. Export Failures to CSV
```bash
grep "failed" backend/storage/logs/email_details_$(date +%Y-%m-%d).log > failures_export.csv
```

## 🧹 Log Maintenance

### Archive old logs (older than 30 days)
```bash
find backend/storage/logs/ -name "*.log" -mtime +30 -exec gzip {} \;
```

### Delete very old logs (older than 90 days)
```bash
find backend/storage/logs/ -name "*.log.gz" -mtime +90 -delete
```

### Check log directory size
```bash
du -sh backend/storage/logs/
```

## 🚨 Alert Setup (Optional)

### Get notified of high failure rate
```bash
# Add to cron (check every 5 minutes)
*/5 * * * * php /path/to/check_failure_rate.php
```

### Watch for specific errors
```bash
tail -f backend/storage/logs/failures_$(date +%Y-%m-%d).log | grep -i "authentication" | mail -s "SMTP Auth Errors" admin@example.com
```
