#!/bin/bash
# ============================================================
# Run this script on PRODUCTION SERVER to fix campaign #1
# ============================================================

echo "========================================"
echo "Fixing Production Database (email_id)"
echo "========================================"
echo ""

# Database credentials - adjust if needed
DB_NAME="email_id"
DB_USER="email_id"  # Usually same as database name on Plesk

echo "Enter database password for user '$DB_USER':"
read -s DB_PASS

echo ""
echo "Step 1: Assigning user_id to campaign_master..."
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "UPDATE campaign_master SET user_id = 1 WHERE campaign_id = 1 AND user_id IS NULL;"

echo "Step 2: Assigning user_id to campaign_status..."
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "UPDATE campaign_status SET user_id = 1 WHERE campaign_id = 1 AND user_id IS NULL;"

echo "Step 3: Cleaning up old mail_blaster records..."
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "DELETE FROM mail_blaster WHERE campaign_id = 1;"

echo "Step 4: Resetting campaign status..."
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "UPDATE campaign_status SET status = 'pending', sent_emails = 0, failed_emails = 0, pending_emails = total_emails, process_pid = NULL, start_time = NULL WHERE campaign_id = 1;"

echo ""
echo "========================================"
echo "VERIFICATION"
echo "========================================"

echo ""
echo "Campaign Master:"
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SELECT campaign_id, description, user_id, csv_list_id FROM campaign_master WHERE campaign_id = 1;"

echo ""
echo "Campaign Status:"
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SELECT campaign_id, status, user_id, total_emails, sent_emails, pending_emails FROM campaign_status WHERE campaign_id = 1;"

echo ""
echo "Mail Blaster Records:"
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SELECT COUNT(*) as record_count FROM mail_blaster WHERE campaign_id = 1;"

echo ""
echo "Active SMTP Accounts for User 1:"
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SELECT sa.id, sa.email, sa.is_active, sa.user_id, ss.name as server_name FROM smtp_accounts sa JOIN smtp_servers ss ON sa.smtp_server_id = ss.id WHERE sa.user_id = 1 AND sa.is_active = 1;"

echo ""
echo "========================================"
echo "✅ Database fixed!"
echo "========================================"
echo ""
echo "Next steps:"
echo "1. Make sure cron job is installed (see PRODUCTION_DEPLOYMENT.txt)"
echo "2. Go to your website and click 'Start' on campaign #1"
echo "3. Wait 2 minutes for cron to pick it up"
echo "4. Emails will start sending with proper tracking"
