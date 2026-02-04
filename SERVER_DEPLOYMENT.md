# Server Deployment Guide - MailPilot CRM

## Prerequisites
- PHP 8.1+ with CLI enabled
- MySQL/MariaDB database
- Apache/Nginx web server
- Required PHP extensions: mysqli, mbstring, curl, json

## Step 1: Database Configuration

### Update Database Credentials
Edit `backend/config/db.php`:
```php
$host = "your_server_host"; // Usually "localhost"
$user = "your_db_user";
$pass = "your_db_password";
$database = "your_database_name";
```

### Verify Timezone Settings
Ensure these files have Asia/Kolkata timezone:
- ✅ `backend/config/db.php` (line ~10)
- ✅ `backend/includes/smtp_usage.php` (line 7)
- ✅ `backend/includes/start_campaign.php` (line 8)
- ✅ `backend/includes/email_blast_parallel.php` (line 9)
- ✅ `backend/includes/email_blast_worker.php` (line 14)

## Step 2: File Permissions

```bash
# Set proper permissions
chmod -R 755 /path/to/MailPilot_CRM_S
chmod -R 777 /path/to/MailPilot_CRM_S/backend/logs
chmod -R 777 /path/to/MailPilot_CRM_S/backend/tmp
chmod -R 777 /path/to/MailPilot_CRM_S/backend/storage

# Make scripts executable
chmod +x /path/to/MailPilot_CRM_S/backend/campaign_cron.php
chmod +x /path/to/MailPilot_CRM_S/backend/test_campaign_system.php
```

## Step 3: Frontend Build & Configuration

### Build Production Frontend
```bash
cd /path/to/MailPilot_CRM_S/frontend
npm install
npm run build
```

### Update API Base URL
Edit `frontend/.env.production`:
```env
VITE_API_BASE_URL=https://your-domain.com/path/to/backend
```

Rebuild after changing:
```bash
npm run build
```

## Step 4: Web Server Configuration

### Apache (.htaccess for backend)
File: `backend/.htaccess`
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^api/(.*)$ routes/api.php?endpoint=/api/$1 [QSA,L]
```

### Nginx Configuration
```nginx
location /backend/api/ {
    try_files $uri $uri/ /backend/routes/api.php?endpoint=$uri&$args;
}

location /backend/ {
    try_files $uri $uri/ =404;
}
```

## Step 5: Setup Cron Job (CRITICAL)

### Option A: System Cron (Recommended)
```bash
# Edit crontab
crontab -e

# Add this line to check campaigns every minute
* * * * * /opt/plesk/php/8.1/bin/php /path/to/MailPilot_CRM_S/backend/campaign_cron.php >> /path/to/MailPilot_CRM_S/backend/logs/cron.log 2>&1
```

### Option B: Alternative PHP Paths
If `/opt/plesk/php/8.1/bin/php` doesn't exist, try:
- `/usr/bin/php`
- `/usr/bin/php8.1`
- `/usr/local/bin/php`

Find your PHP CLI:
```bash
which php
php -v  # Verify it's PHP 8.1+
```

## Step 6: Configure SMTP Accounts

### Add SMTP Servers & Accounts
1. Go to Master page in frontend
2. Add SMTP servers with:
   - Host, Port, Encryption
   - Hourly limit (e.g., 50/hour)
   - Daily limit (e.g., 500/day)
3. Add multiple accounts per server
4. Set `is_active = 1` for all accounts you want to use

### Test SMTP Connection
Use the frontend "Test" button for each SMTP account.

## Step 7: Test the System

### Run Diagnostic Script
```bash
cd /path/to/MailPilot_CRM_S/backend
php test_campaign_system.php
```

This will show:
- ✓ Database connection
- ✓ Campaign status
- ✓ SMTP accounts availability
- ✓ Current hour usage
- ✓ Timezone settings
- ✓ Running workers

### Expected Output
```
✓ 5 SMTP account(s) available for sending
✓ Campaign #3: pending - ready to start
✓ Timezone: Asia/Kolkata (18:30:45)
```

## Step 8: Start a Campaign

### Via Frontend (Master Page)
1. Create campaign with recipients
2. Click "Start" button
3. Campaign status changes to "running"
4. Workers launch automatically (no cron wait needed)

### Monitor Campaign
- Status updates every 5 seconds (running campaigns)
- Email counts refresh every 3 seconds
- Check logs: `backend/logs/campaign_[ID].log`

## Step 9: Troubleshooting

### Issue: Emails Not Sending

**Check 1: Campaign Status**
```bash
/opt/lampp/bin/mysql -u root -e "SELECT campaign_id, status, pending_emails FROM [DB].campaign_status;"
```
- If status = 'paused' → Click "Resume" in frontend
- If status = 'paused_limits' → Wait for next hour or increase limits

**Check 2: Worker Processes**
```bash
ps aux | grep email_blast_worker.php
```
- Should see 1-30 worker processes
- If none, check cron job

**Check 3: SMTP Account Limits**
```bash
php test_campaign_system.php
```
- Check "Account Availability Test" section
- Should show "[CAN SEND]" for at least one account

**Check 4: Logs**
```bash
tail -f backend/logs/campaign_*.log
tail -f backend/logs/worker_debug_*.log
tail -f backend/logs/api_errors.log
```

### Issue: Too Many Database Connections
- Reduce `MAX_PARALLEL_WORKERS` in `email_blast_parallel.php` (line 108)
- Default is 30, try 10-15 for shared hosting

### Issue: Campaign Stuck at "Pending"
```bash
# Manually trigger cron
php backend/campaign_cron.php

# Or restart campaign
# In MySQL:
UPDATE campaign_status SET status = 'pending' WHERE campaign_id = [ID];
```

## Step 10: Performance Optimization

### For Large Campaigns (10k+ emails)
1. Increase PHP memory: `ini_set('memory_limit', '4096M');` (already set)
2. Use dedicated server (not shared hosting)
3. Increase hourly/daily limits on SMTP accounts
4. Add more SMTP accounts for load distribution

### Expected Performance
- **With 5 accounts (50/hr each)**: 250 emails/hour
- **With 30 workers + 10 accounts (100/hr)**: 1,000 emails/hour
- **Best case (no limits)**: ~7,200 emails/hour (120/sec theoretical)

## Monitoring in Production

### Key Metrics to Watch
1. Campaign status (running/paused/completed)
2. SMTP account health (frontend shows status)
3. Worker count (`ps aux | grep worker`)
4. Error rate in logs
5. Hourly sending rate

### Daily Maintenance
```bash
# Rotate logs (older than 7 days)
find backend/logs -name "*.log" -mtime +7 -delete

# Check for stuck campaigns
php test_campaign_system.php
```

## Security Checklist
- ✅ Change database password
- ✅ Set proper file permissions (755/777)
- ✅ Enable HTTPS (SSL certificate)
- ✅ Update `.env.production` with correct domain
- ✅ Set `display_errors = 0` in PHP (already set)
- ✅ Restrict backend folder from direct web access

## Support & Debugging

### Enable Debug Mode (Development Only)
Edit `email_blast_parallel.php`:
```php
ini_set('display_errors', 1); // Line 5
```

### Contact Information
- Check logs first: `backend/logs/`
- Run diagnostic: `php test_campaign_system.php`
- Database queries in `api_errors.log`

## Quick Start Commands

```bash
# 1. Navigate to project
cd /path/to/MailPilot_CRM_S

# 2. Test system
php backend/test_campaign_system.php

# 3. Check if cron is running
ps aux | grep campaign_cron

# 4. Check workers
ps aux | grep email_blast_worker

# 5. View live logs
tail -f backend/logs/campaign_*.log

# 6. Manually trigger campaign check
php backend/campaign_cron.php
```

## Production Checklist
- [ ] Database credentials updated
- [ ] Timezone set to Asia/Kolkata in all files
- [ ] File permissions set (777 for logs/tmp/storage)
- [ ] Frontend built with production API URL
- [ ] Cron job added and running
- [ ] SMTP accounts configured and tested
- [ ] Test campaign sent successfully
- [ ] Logs directory writable
- [ ] HTTPS enabled
- [ ] Diagnostic script runs without errors

---

**System is ready when:**
1. ✓ `php test_campaign_system.php` shows all green checkmarks
2. ✓ Cron job runs every minute
3. ✓ Campaign starts and sends emails
4. ✓ Workers visible in `ps aux`
5. ✓ Logs show successful email delivery

**Current Configuration:**
- Max Workers: 30 parallel
- Worker Model: Round-robin across ALL accounts
- Timezone: Asia/Kolkata (IST)
- Auto-pause: When all accounts hit limits
- Auto-resume: When hour changes (for hourly limits)
