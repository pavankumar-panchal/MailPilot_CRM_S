#!/bin/bash
# ============================================================
# PRODUCTION SERVER DIAGNOSTIC - Campaign Not Sending
# Run this on production server to diagnose the issue
# ============================================================

echo "========================================"
echo "CAMPAIGN DIAGNOSTIC TOOL"
echo "========================================"
echo ""

DB_NAME="email_id"
DB_USER="email_id"

echo "Enter database password for '$DB_USER':"
read -s DB_PASS

echo ""
echo "=== STEP 1: Check Campaign #1 Details ==="
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "
SELECT 
    cm.campaign_id,
    cm.description,
    cm.user_id,
    cm.csv_list_id,
    cm.import_batch_id,
    cs.status,
    cs.total_emails,
    cs.sent_emails,
    cs.pending_emails,
    cs.process_pid
FROM campaign_master cm
LEFT JOIN campaign_status cs ON cm.campaign_id = cs.campaign_id
WHERE cm.campaign_id = 1;
"

echo ""
echo "=== STEP 2: Check if CSV List is Assigned ==="
CSV_LIST_ID=$(mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -sN -e "SELECT csv_list_id FROM campaign_master WHERE campaign_id = 1;")
echo "CSV List ID: $CSV_LIST_ID"

if [ -z "$CSV_LIST_ID" ] || [ "$CSV_LIST_ID" = "NULL" ]; then
    echo "❌ NO CSV LIST ASSIGNED!"
    echo ""
    echo "Available CSV lists:"
    mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SELECT id, list_name, total_emails, valid_count FROM csv_list ORDER BY id DESC LIMIT 5;"
    echo ""
    echo "⚠️  FIX: Assign a CSV list to campaign #1:"
    echo "   UPDATE campaign_master SET csv_list_id = 2 WHERE campaign_id = 1;"
else
    echo "✅ CSV List ID: $CSV_LIST_ID"
    echo ""
    echo "Valid emails in this list:"
    mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "
    SELECT COUNT(*) as valid_emails
    FROM emails
    WHERE csv_list_id = $CSV_LIST_ID
    AND domain_status = 1
    AND validation_status = 'valid';
    "
fi

echo ""
echo "=== STEP 3: Check User Has SMTP Accounts ==="
USER_ID=$(mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -sN -e "SELECT user_id FROM campaign_master WHERE campaign_id = 1;")
echo "Campaign User ID: $USER_ID"

if [ -z "$USER_ID" ] || [ "$USER_ID" = "NULL" ]; then
    echo "❌ NO USER ASSIGNED!"
    echo "⚠️  FIX: UPDATE campaign_master SET user_id = 1 WHERE campaign_id = 1;"
else
    mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "
    SELECT 
        sa.id,
        sa.email,
        sa.is_active,
        ss.name as server_name
    FROM smtp_accounts sa
    JOIN smtp_servers ss ON sa.smtp_server_id = ss.id
    WHERE sa.user_id = $USER_ID
    AND sa.is_active = 1;
    "
fi

echo ""
echo "=== STEP 4: Check Cron Job ==="
echo "Cron jobs for current user:"
crontab -l | grep campaign_cron || echo "❌ NO CRON JOB FOUND!"

echo ""
echo "=== STEP 5: Check Campaign Process ==="
ps aux | grep email_blast_parallel | grep -v grep || echo "No orchestrator process running"

echo ""
echo "=== STEP 6: Check Mail Blaster Records ==="
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "
SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as sent,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
    COUNT(DISTINCT smtp_account_id) as unique_smtp_accounts,
    COUNT(DISTINCT user_id) as unique_users
FROM mail_blaster
WHERE campaign_id = 1;
"

echo ""
echo "Sample mail_blaster records:"
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "
SELECT 
    id,
    smtp_account_id,
    smtp_email,
    to_mail,
    status,
    user_id
FROM mail_blaster
WHERE campaign_id = 1
ORDER BY id DESC
LIMIT 5;
"

echo ""
echo "========================================"
echo "DIAGNOSTIC COMPLETE"
echo "========================================"
