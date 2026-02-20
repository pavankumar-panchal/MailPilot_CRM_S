#!/bin/bash
# ============================================================================
# VERIFY MULTI-USER DATABASE INDEXES
# ============================================================================
# This script checks if you have all necessary indexes for 100+ concurrent users
# Run this before applying ADD_MISSING_INDEXES.sql
# ============================================================================

echo "=================================="
echo "Multi-User Index Verification"
echo "=================================="
echo ""

# Database credentials - UPDATE THESE
DB_USER="root"
DB_PASS=""  # Add your password or run with -p flag
DB_SERVER2="CRM"      # Server 2 database name
DB_SERVER1="campaign_db"  # Server 1 database name (update if different)

# Function to check if index exists
check_index() {
    local db=$1
    local table=$2
    local index=$3
    
    result=$(mysql -u$DB_USER -p$DB_PASS -D$db -Nse "
        SELECT COUNT(*) FROM information_schema.STATISTICS 
        WHERE TABLE_SCHEMA = '$db' 
        AND TABLE_NAME = '$table' 
        AND INDEX_NAME = '$index'
    " 2>/dev/null)
    
    if [ "$result" -gt 0 ]; then
        echo "  ✅ $index exists"
        return 0
    else
        echo "  ❌ MISSING: $index"
        return 1
    fi
}

echo "Checking SERVER 2 (CRM) Indexes..."
echo "===================================="
echo ""

echo "mail_blaster table:"
check_index "$DB_SERVER2" "mail_blaster" "idx_user_campaign"
check_index "$DB_SERVER2" "mail_blaster" "idx_processing_recovery"
check_index "$DB_SERVER2" "mail_blaster" "unique_campaign_email"
check_index "$DB_SERVER2" "mail_blaster" "idx_campaign_status_attempt"
echo ""

echo "smtp_servers table:"
check_index "$DB_SERVER2" "smtp_servers" "idx_user_active"
check_index "$DB_SERVER2" "smtp_servers" "idx_active_user_server"
echo ""

echo "smtp_accounts table:"
check_index "$DB_SERVER2" "smtp_accounts" "idx_server_active_user"
check_index "$DB_SERVER2" "smtp_accounts" "idx_user_server"
check_index "$DB_SERVER2" "smtp_accounts" "idx_user_active"
echo ""

echo "smtp_usage table:"
check_index "$DB_SERVER2" "smtp_usage" "idx_smtp_date_hour"
check_index "$DB_SERVER2" "smtp_usage" "idx_date_smtp"
check_index "$DB_SERVER2" "smtp_usage" "unique_smtp_hour"
echo ""

echo "smtp_health table:"
check_index "$DB_SERVER2" "smtp_health" "PRIMARY"
check_index "$DB_SERVER2" "smtp_health" "idx_health_suspend"
echo ""

echo "===================================="
echo "Checking SERVER 1 Indexes..."
echo "===================================="
echo ""

echo "campaign_master table:"
check_index "$DB_SERVER1" "campaign_master" "idx_user_campaign"
check_index "$DB_SERVER1" "campaign_master" "idx_campaign_user"
echo ""

echo "campaign_status table:"
check_index "$DB_SERVER1" "campaign_status" "idx_campaign_status"
check_index "$DB_SERVER1" "campaign_status" "idx_status_user"
echo ""

echo "===================================="
echo "Summary"
echo "===================================="
echo ""
echo "If you see any ❌ MISSING indexes above, run:"
echo "  mysql -u root -p $DB_SERVER2 < ADD_MISSING_INDEXES.sql"
echo "  mysql -u root -p $DB_SERVER1 < ADD_MISSING_INDEXES.sql"
echo ""
echo "If all indexes show ✅, you're ready for 100+ concurrent users!"
echo ""
