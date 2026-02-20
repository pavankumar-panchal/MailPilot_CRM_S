-- ============================================================================
-- SERVER 2 ONLY - CRM Database Missing Indexes
-- ============================================================================
-- Database: CRM (SMTP servers, accounts, mail_blaster, usage tracking)
-- Execute: mysql -u root -p CRM < ADD_MISSING_INDEXES_SERVER2.sql
-- ============================================================================
-- Purpose: Add ONLY missing indexes for 100+ concurrent users
-- Compared with your existing schema - adding only what you DON'T have
-- ============================================================================

-- For MariaDB compatibility
SET SESSION sql_mode = '';

-- ============================================================================
-- MAIL_BLASTER - Add 2 Missing Critical Indexes
-- ============================================================================
-- You already have 15 excellent indexes! Adding only what's missing:

-- ❌ MISSING: User-based campaign filtering (critical for multi-user isolation)
-- Prevents users from accessing each other's campaign data
ALTER TABLE mail_blaster 
ADD INDEX idx_user_campaign (user_id, campaign_id, status);

-- ❌ MISSING: Processing recovery index (for stuck email recovery)
-- Recovers emails stuck in 'processing' state from crashed workers
ALTER TABLE mail_blaster 
ADD INDEX idx_processing_recovery (status, delivery_time, campaign_id);

-- ✅ You already have these - NO ACTION NEEDED:
--    - unique_campaign_email (campaign_id, to_mail)
--    - idx_campaign_status_attempt (campaign_id, status, attempt_count)
--    - idx_campaign_pending (campaign_id, status, attempt_count, id)
--    - idx_campaign_id, idx_status, idx_delivery_date, idx_user_id
--    - and 8 more excellent indexes!

-- ============================================================================
-- SMTP_SERVERS - Add 2 Critical Indexes (Currently has NONE!)
-- ============================================================================
-- ⚠️ CRITICAL: smtp_servers currently has NO indexes except PRIMARY KEY!

-- ❌ MISSING: User filtering (essential for multi-user isolation)
ALTER TABLE smtp_servers 
ADD INDEX idx_user_active (user_id, is_active);

-- ❌ MISSING: Active server lookup with user filtering
ALTER TABLE smtp_servers 
ADD INDEX idx_active_user_server (is_active, user_id, id);

-- ============================================================================
-- SMTP_ACCOUNTS - Add 1 Missing Index
-- ============================================================================
-- You already have: idx_server_active_user, idx_user_active

-- ❌ MISSING: User-server reverse lookup (for failover scenarios)
ALTER TABLE smtp_accounts 
ADD INDEX idx_user_server (user_id, smtp_server_id, is_active);

-- ✅ You already have these - NO ACTION NEEDED:
--    - idx_server_active_user (smtp_server_id, is_active, user_id)
--    - idx_user_active (user_id, is_active)

-- ============================================================================
-- SMTP_USAGE - Add 1 Missing Index
-- ============================================================================
-- You already have: idx_smtp_date_hour, idx_user_date, unique_smtp_hour

-- ❌ MISSING: Reverse date lookup (for daily cleanup queries)
ALTER TABLE smtp_usage 
ADD INDEX idx_date_smtp (date, smtp_id);

-- ✅ You already have these - NO ACTION NEEDED:
--    - unique_smtp_hour (smtp_id, date, hour, user_id)
--    - idx_smtp_date_hour (smtp_id, date, hour)
--    - idx_user_date (user_id, date, hour)

-- ============================================================================
-- SMTP_HEALTH - Add 1 Missing Index
-- ============================================================================
-- You currently only have: PRIMARY KEY (smtp_id)

-- ❌ MISSING: Health status filtering (for excluding suspended accounts)
ALTER TABLE smtp_health 
ADD INDEX idx_health_suspend (health, suspend_until, smtp_id);

-- ============================================================================
-- SMTP_ROTATION - No Changes Needed
-- ============================================================================
-- ✅ You already have: PRIMARY KEY (id) - sufficient for current usage

-- ============================================================================
-- VERIFICATION - Check if indexes were created successfully
-- ============================================================================

SELECT '============================================' as '';
SELECT 'SERVER 2 (CRM) Index Verification' as '';
SELECT '============================================' as '';

SELECT 
    TABLE_NAME,
    INDEX_NAME,
    GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) as COLUMNS,
    INDEX_TYPE,
    NON_UNIQUE
FROM information_schema.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME IN ('mail_blaster', 'smtp_servers', 'smtp_accounts', 'smtp_usage', 'smtp_health')
GROUP BY TABLE_NAME, INDEX_NAME, INDEX_TYPE, NON_UNIQUE
ORDER BY TABLE_NAME, INDEX_NAME;

-- ============================================================================
-- SUCCESS MESSAGE
-- ============================================================================

SELECT '' as '';
SELECT '✅ SERVER 2 (CRM) indexes added successfully!' as STATUS;
SELECT '✅ Total new indexes added: 7' as SUMMARY;
SELECT '✅ Your system is now optimized for 100+ concurrent users' as MESSAGE;
SELECT '' as '';
SELECT 'New indexes added:' as DETAILS;
SELECT '  1. mail_blaster.idx_user_campaign' as '';
SELECT '  2. mail_blaster.idx_processing_recovery' as '';
SELECT '  3. smtp_servers.idx_user_active' as '';
SELECT '  4. smtp_servers.idx_active_user_server' as '';
SELECT '  5. smtp_accounts.idx_user_server' as '';
SELECT '  6. smtp_usage.idx_date_smtp' as '';
SELECT '  7. smtp_health.idx_health_suspend' as '';

-- ============================================================================
