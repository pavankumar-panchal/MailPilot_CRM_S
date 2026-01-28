# Campaign Email Sending Setup Guide

## Overview
When you click the "Start" button on a campaign, it sets the campaign status to 'running' in the database. The actual email sending is handled by a background cron job that monitors for running campaigns.

## Architecture

### 1. Frontend (Master.jsx)
- User clicks "Start" button on a campaign
- Sends POST request to API: `{ action: "start_campaign", campaign_id: X }`
- Calls: `/backend/routes/api.php?endpoint=/api/master/campaigns_master`

### 2. Backend API (campaigns_master.php)
- Validates user permissions
- Checks for active SMTP accounts
- Verifies campaign has valid emails
- Sets campaign_status.status = 'running'
- **DOES NOT launch email process directly**
- Returns success message

### 3. Cron Job (campaign_cron.php)
- Runs every 2 minutes (configurable)
- Monitors for campaigns with status = 'running'
- Verifies user has active SMTP accounts
- Launches email_blast_parallel.php for each running campaign
- Stores process PID in database
- Prevents duplicate processes

### 4. Email Orchestrator (email_blast_parallel.php)
- Fetches campaign details and user_id
- **Filters SMTP servers by campaign owner's user_id**
- Launches one worker per SMTP server
- Monitors progress and updates database
- Sets status to 'completed' when done

### 5. Email Workers (email_blast_worker.php)
- Send individual emails using PHPMailer
- Track delivery in mail_blaster table
- Record smtp_account_id, smtp_email, and user_id
- Respect daily/hourly limits
- Retry failed emails up to 3 times

## Production Server Setup

### Step 1: Deploy Latest Code
```bash
ssh user@payrollsoft.in
cd /var/www/vhosts/payrollsoft.in/httpdocs/emailvalidation

# Pull latest changes
git pull origin master

# Rebuild frontend
cd frontend
npm install
npm run build
cd ..
```

### Step 2: Install Cron Job

#### For Plesk Server (Production)
```bash
# Edit crontab
crontab -e

# Add this line (adjust paths as needed):
*/2 * * * * /opt/plesk/php/8.1/bin/php /var/www/vhosts/payrollsoft.in/httpdocs/emailvalidation/backend/campaign_cron.php >> /var/www/vhosts/payrollsoft.in/httpdocs/emailvalidation/backend/logs/cron_output.log 2>&1

# Save and exit (:wq in vim)

# Verify cron is installed
crontab -l
```

#### For XAMPP (Localhost Development)
```bash
# Linux/Mac: Edit crontab
crontab -e

# Add this line:
*/2 * * * * /opt/lampp/bin/php /opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/backend/campaign_cron.php >> /opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/backend/logs/cron_output.log 2>&1

# Windows: Use Task Scheduler
# Create a task that runs every 2 minutes:
# Program: C:\xampp\php\php.exe
# Arguments: C:\xampp\htdocs\verify_emails\MailPilot_CRM_S\backend\campaign_cron.php
```

### Step 3: Verify Setup

#### Test Cron Job Manually (Production)
```bash
# SSH to production server
ssh user@payrollsoft.in

# Run cron manually
/opt/plesk/php/8.1/bin/php /var/www/vhosts/payrollsoft.in/httpdocs/emailvalidation/backend/campaign_cron.php

# Expected output:
# Database config loaded for: PRODUCTION - DB: email_id (CLI: YES)
# No running campaigns found
# Task "campaign_cron.php" successfully completed in 0 seconds
```

#### Test Campaign Start Flow
1. **Login to frontend**: https://payrollsoft.in/emailvalidation
2. **Go to Master page** (Campaigns list)
3. **Click "Start"** on a campaign
4. **Expected**: Success message: "Campaign #X started successfully!"
5. **Wait 2 minutes** for cron to run
6. **Check logs**:
   ```bash
   tail -f /var/www/vhosts/payrollsoft.in/httpdocs/emailvalidation/backend/logs/cron_output.log
   tail -f /var/www/vhosts/payrollsoft.in/httpdocs/emailvalidation/backend/logs/campaign_*.log
   ```

#### Verify Database
```bash
# Check campaign status
mysql -u email_id -p email_id -e "
SELECT cs.campaign_id, cs.status, cs.process_pid, 
       cm.description, cm.user_id,
       cs.total_emails, cs.sent_emails, cs.pending_emails
FROM campaign_status cs
JOIN campaign_master cm ON cs.campaign_id = cm.campaign_id
ORDER BY cs.campaign_id DESC LIMIT 5;
"

# Check mail_blaster records with tracking
mysql -u email_id -p email_id -e "
SELECT campaign_id, smtp_account_id, smtp_email, 
       to_mail, status, user_id 
FROM mail_blaster 
ORDER BY id DESC LIMIT 10;
"
```

## Troubleshooting

### Issue: Cron job not running
**Solution**:
```bash
# Check crontab is installed
crontab -l

# Check cron service is running
systemctl status cron  # or: service cron status

# Check cron logs
tail -f /var/log/cron  # or: /var/log/syslog
```

### Issue: Campaign stays in "running" status but no emails sent
**Solution**:
```bash
# 1. Check if cron detected the campaign
tail -f /var/www/vhosts/payrollsoft.in/httpdocs/emailvalidation/backend/logs/cron_output.log

# 2. Check if orchestrator launched
ps aux | grep email_blast_parallel

# 3. Check if user has SMTP accounts
mysql -u email_id -p email_id -e "
SELECT sa.id, sa.email, ss.name, sa.is_active 
FROM smtp_accounts sa
JOIN smtp_servers ss ON sa.smtp_server_id = ss.id
WHERE sa.user_id = 1 AND sa.is_active = 1;
"

# 4. Manually run cron to see errors
/opt/plesk/php/8.1/bin/php /var/www/vhosts/payrollsoft.in/httpdocs/emailvalidation/backend/campaign_cron.php
```

### Issue: No active SMTP accounts error
**Solution**:
1. Go to SMTP Servers page
2. Add at least one SMTP server
3. Add at least one SMTP account with your credentials
4. Ensure both server and account are marked as "Active"

### Issue: "Campaign already running" but process is dead
**Solution**:
```bash
# Reset campaign status
mysql -u email_id -p email_id -e "
UPDATE campaign_status 
SET status = 'pending', process_pid = NULL 
WHERE campaign_id = X;
"

# Clean up stale PID file
rm /var/www/vhosts/payrollsoft.in/httpdocs/emailvalidation/backend/tmp/orchestrator_*.pid
```

## Monitoring

### Real-time Campaign Progress
```bash
# Watch cron output
tail -f /var/www/vhosts/payrollsoft.in/httpdocs/emailvalidation/backend/logs/cron_output.log

# Watch campaign logs
tail -f /var/www/vhosts/payrollsoft.in/httpdocs/emailvalidation/backend/logs/campaign_*.log

# Watch worker logs
tail -f /var/www/vhosts/payrollsoft.in/httpdocs/emailvalidation/backend/logs/worker_*.log
```

### Check Campaign Statistics
```bash
mysql -u email_id -p email_id -e "
SELECT 
    cs.campaign_id,
    cm.description,
    cs.status,
    CONCAT(cs.sent_emails, '/', cs.total_emails) as progress,
    CONCAT(ROUND(cs.sent_emails * 100.0 / NULLIF(cs.total_emails, 0), 1), '%') as percentage,
    cs.start_time,
    TIMESTAMPDIFF(MINUTE, cs.start_time, NOW()) as runtime_minutes
FROM campaign_status cs
JOIN campaign_master cm ON cs.campaign_id = cm.campaign_id
WHERE cs.status IN ('running', 'completed')
ORDER BY cs.start_time DESC;
"
```

## Important Notes

1. **Cron Frequency**: Default is every 2 minutes. For faster pickup, change `*/2` to `*/1` (every minute).

2. **User Isolation**: Each campaign only uses SMTP accounts belonging to the campaign owner (user_id). This prevents users from accessing each other's SMTP credentials.

3. **Process Management**: Campaign_cron.php checks for existing PIDs before launching new processes, preventing duplicates.

4. **Complete Tracking**: All sent emails are recorded in mail_blaster with:
   - smtp_account_id: Which SMTP account was used
   - smtp_email: The sender email address
   - user_id: Which user owns the campaign
   - status: success/failed/pending
   - attempt_count: Number of retry attempts

5. **Automatic Completion**: Campaigns are automatically marked as 'completed' when all emails are sent or max retries reached.

6. **Environment Detection**: The system automatically detects PRODUCTION vs LOCALHOST and uses the correct database (email_id vs CRM).

## Success Criteria

✅ **Cron job installed and running every 2 minutes**
✅ **User clicks "Start" → Campaign status set to 'running'**
✅ **Within 2 minutes, cron detects campaign and launches orchestrator**
✅ **Orchestrator spawns workers, emails start sending**
✅ **Mail_blaster records show smtp_account_id, smtp_email, user_id**
✅ **Campaign progress visible in UI (sent/total counts update)**
✅ **When complete, status changes to 'completed' automatically**

## Next Steps

1. Deploy code to production (git pull + npm build)
2. Install cron job on production server
3. Test campaign start flow
4. Monitor logs for first successful campaign
5. Verify mail_blaster tracking data
