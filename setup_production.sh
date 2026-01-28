#!/bin/bash
# ============================================================
# PRODUCTION SERVER - FINAL SETUP AND TEST
# Run this script on: payrollsoft.in
# ============================================================

echo "========================================"
echo "Production Server Setup - Email Campaign"
echo "========================================"
echo ""

# Step 1: Pull latest code
echo "Step 1: Pulling latest code from git..."
cd /var/www/vhosts/payrollsoft.in/httpdocs/emailvalidation
git pull origin master

# Step 2: Install cron job
echo ""
echo "Step 2: Installing cron job..."
echo "Current crontab:"
crontab -l

echo ""
echo "Adding campaign monitor cron job..."
(crontab -l 2>/dev/null; echo "*/2 * * * * /opt/plesk/php/8.1/bin/php /var/www/vhosts/payrollsoft.in/httpdocs/emailvalidation/backend/campaign_cron.php >> /var/www/vhosts/payrollsoft.in/httpdocs/emailvalidation/backend/logs/cron_output.log 2>&1") | crontab -

echo "Updated crontab:"
crontab -l

# Step 3: Test cron manually
echo ""
echo "Step 3: Testing cron job manually..."
/opt/plesk/php/8.1/bin/php /var/www/vhosts/payrollsoft.in/httpdocs/emailvalidation/backend/campaign_cron.php

# Step 4: Check database
echo ""
echo "Step 4: Checking database..."
echo "Enter MySQL password for 'email_id' user:"
read -s DB_PASS

echo ""
echo "Campaign Status:"
mysql -u email_id -p"$DB_PASS" email_id -e "
SELECT 
    cm.campaign_id,
    cm.description,
    cm.user_id as campaign_user_id,
    cm.csv_list_id,
    cs.status,
    cs.total_emails,
    cs.sent_emails,
    cs.pending_emails,
    cs.failed_emails,
    cs.process_pid
FROM campaign_master cm
LEFT JOIN campaign_status cs ON cm.campaign_id = cs.campaign_id
WHERE cm.campaign_id = 1;
"

echo ""
echo "SMTP Accounts:"
mysql -u email_id -p"$DB_PASS" email_id -e "
SELECT 
    sa.id,
    sa.email,
    sa.user_id,
    sa.is_active,
    ss.name as server_name,
    ss.is_active as server_active
FROM smtp_accounts sa
JOIN smtp_servers ss ON sa.smtp_server_id = ss.id
WHERE sa.user_id = 1
LIMIT 5;
"

echo ""
echo "CSV List:"
mysql -u email_id -p"$DB_PASS" email_id -e "
SELECT 
    id,
    list_name,
    total_emails,
    valid_count,
    invalid_count,
    status
FROM csv_list
WHERE id = 2;
"

echo ""
echo "Valid Emails Count:"
mysql -u email_id -p"$DB_PASS" email_id -e "
SELECT COUNT(*) as valid_emails
FROM emails
WHERE csv_list_id = 2 
AND domain_status = 1 
AND validation_status = 'valid';
"

echo ""
echo "========================================"
echo "✅ Setup Complete!"
echo "========================================"
echo ""
echo "Next steps:"
echo "1. Campaign should already be running (status = 'running')"
echo "2. Wait 2 minutes for cron to detect it"
echo "3. Check logs: tail -f backend/logs/cron_output.log"
echo "4. Watch emails being sent in real-time"
echo ""
echo "To monitor progress:"
echo "watch -n 5 'mysql -u email_id -p\"$DB_PASS\" email_id -e \"SELECT sent_emails, pending_emails FROM campaign_status WHERE campaign_id = 1;\"'"
