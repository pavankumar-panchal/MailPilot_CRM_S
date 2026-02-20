-- ============================================================================
-- ⚠️ DEPRECATED - USE SEPARATE FILES INSTEAD
-- ============================================================================
-- This file has been split into two separate files for easier management:
--
-- 1. ADD_MISSING_INDEXES_SERVER2.sql (CRITICAL - Apply first!)
--    For CRM database (SMTP, mail_blaster, etc.)
--    Execute: mysql -u root -p CRM < ADD_MISSING_INDEXES_SERVER2.sql
--
-- 2. ADD_MISSING_INDEXES_SERVER1.sql (OPTIONAL - Apply later)
--    For campaign database (campaigns, emails, etc.)
--    Execute: mysql -u root -p campaign_db < ADD_MISSING_INDEXES_SERVER1.sql
-- ============================================================================
--
-- WHY SEPARATE FILES?
-- - Easier to apply to correct database
-- - No confusion about which indexes go where
-- - Can apply Server 2 (critical) without Server 1 (optional)
-- - Clearer error messages if something fails
--
-- QUICK START:
-- Step 1: Apply Server 2 indexes (CRITICAL)
--   mysql -u root -p CRM < ADD_MISSING_INDEXES_SERVER2.sql
--
-- Step 2: (Optional) Apply Server 1 indexes
--   mysql -u root -p campaign_db < ADD_MISSING_INDEXES_SERVER1.sql
--
-- ============================================================================

SELECT '⚠️ This file is deprecated!' as WARNING;
SELECT 'Use ADD_MISSING_INDEXES_SERVER2.sql instead (CRITICAL)' as ACTION;
SELECT 'And optionally ADD_MISSING_INDEXES_SERVER1.sql' as OPTIONAL;

-- ============================================================================
