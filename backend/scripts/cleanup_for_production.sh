#!/bin/bash

# Production Cleanup Script
# Removes test, diagnostic, and development-only files

echo ""
echo "╔══════════════════════════════════════════════════════════╗"
echo "║  PRODUCTION CLEANUP - REMOVING UNWANTED FILES           ║"
echo "╚══════════════════════════════════════════════════════════╝"
echo ""

BACKEND_DIR="/opt/lampp/htdocs/verify_emails/MailPilot_CRM/backend"
REMOVED_COUNT=0

# Function to remove file if it exists
remove_file() {
    if [ -f "$1" ]; then
        echo "  🗑️  Removing: $(basename $1)"
        rm "$1"
        ((REMOVED_COUNT++))
    fi
}

echo "Cleaning up TEST and DIAGNOSTIC scripts..."
echo "─────────────────────────────────────────────────────────"

# Remove test/diagnostic scripts from backend/scripts/
cd "$BACKEND_DIR/scripts" 2>/dev/null || exit 1

remove_file "check_campaign.php"
remove_file "check_campaign_images.php"
remove_file "check_delivery_status.php"
remove_file "check_smtp_rotation.php"
remove_file "diagnose_domain_delivery.php"
remove_file "fix_domain_delivery.sh"
remove_file "fix_existing_campaigns.php"
remove_file "log_summary.sh"
remove_file "monitor_logs.sh"
remove_file "send_one_now.php"
remove_file "test_all_domains.php"
remove_file "test_image_embed.php"
remove_file "test_image_sending.php"
remove_file "view_campaign_body.php"
remove_file "view_logs.php"
remove_file "view_main_log.sh"

echo ""
echo "Cleaning up TEST files from backend/includes/..."
echo "─────────────────────────────────────────────────────────"

cd "$BACKEND_DIR/includes" 2>/dev/null || exit 1

remove_file "test.php"
remove_file "test_attachment.html"
remove_file "test_attachment_status.php"
remove_file "test_email_paths.php"
remove_file "test_image_campaign.php"
remove_file "test_upload.html"

echo ""
echo "Cleaning up OLD/UNUSED files from backend/public/..."
echo "─────────────────────────────────────────────────────────"

cd "$BACKEND_DIR/public" 2>/dev/null || exit 1

remove_file "campaigns_master_old.php"
remove_file "email_processor_imp.php"

echo ""
echo "Cleaning up ROOT directory..."
echo "─────────────────────────────────────────────────────────"

cd "/opt/lampp/htdocs/verify_emails/MailPilot_CRM" 2>/dev/null || exit 1

remove_file "Workers.jsx"  # Duplicate, should be in frontend/src/pages/

echo ""
echo "╔══════════════════════════════════════════════════════════╗"
echo "║  CLEANUP SUMMARY                                         ║"
echo "╚══════════════════════════════════════════════════════════╝"
echo ""
echo "✅ Removed: $REMOVED_COUNT file(s)"
echo ""
echo "PRODUCTION FILES KEPT (Essential):"
echo "─────────────────────────────────────────────────────────"
echo "  backend/scripts/:"
echo "    ✓ import_smtp_accounts.php"
echo "    ✓ import_smtp_accounts_from_excel.php"
echo "    ✓ list_smtp_accounts.php"
echo "    ✓ send_test_smtp.php"
echo ""
echo "  backend/includes/:"
echo "    ✓ campaign.php"
echo "    ✓ campaign_auto_distribute.php"
echo "    ✓ campaign_distribution.php"
echo "    ✓ domain.php, domain_worker.php"
echo "    ✓ get_csv_list.php, get_results.php"
echo "    ✓ master_*.php"
echo "    ✓ monitor_campaigns.php"
echo "    ✓ progress.php"
echo "    ✓ retry_smtp.php, retry_smtp_worker.php"
echo "    ✓ smtp_accounts.php, smtp_worker.php, smtp_worker2.php"
echo "    ✓ upload_image.php"
echo "    ✓ verify_domain.php, verify_smtp.php, verify_smtp2.php"
echo "    ✓ workers.php"
echo ""
echo "  backend/public/:"
echo "    ✓ campaign_monitor.php"
echo "    ✓ campaigns.php, campaigns_master.php"
echo "    ✓ email_blaster.php (MAIN SENDER)"
echo "    ✓ email_processor.php"
echo "    ✓ get_campaign.php"
echo "    ✓ received_response.php"
echo "    ✓ smtp_records.php"
echo ""
echo "═══════════════════════════════════════════════════════════"
echo "✅ Production cleanup complete!"
echo ""
