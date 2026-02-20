-- ============================================================================
-- SERVER 1 ONLY - Campaign Database Missing Indexes
-- ============================================================================
-- Database: campaign_db (or your campaign database name)
-- Execute: mysql -u root -p campaign_db < ADD_MISSING_INDEXES_SERVER1.sql
-- ============================================================================
-- Purpose: Add missing indexes for campaign and email source tables
-- These are OPTIONAL but recommended for better performance
-- ============================================================================

-- ============================================================================
-- SERVER 1 ONLY - Campaign Database Missing Indexes
-- ============================================================================
-- Database: campaign_db (or your campaign database name)
-- Execute: mysql -u root -p campaign_db < ADD_MISSING_INDEXES_SERVER1.sql
-- ============================================================================
-- Purpose: Add missing indexes for campaign and email source tables
-- These are OPTIONAL but recommended for better performance
-- ============================================================================
-- ⚠️ IMPORTANT: You may see "Duplicate key name" errors
--    This is NORMAL and SAFE - it means you already have that index
--    Just continue to the next one!
-- ============================================================================

-- ⚠️ NOTE: Replace 'campaign_db' with your actual Server 1 database name
-- USE campaign_db;

-- ============================================================================
-- ✅ You already have idx_user_campaign - SKIP THIS!
-- ============================================================================
-- ALTER TABLE campaign_master ADD INDEX idx_user_campaign (user_id, campaign_id);

-- ============================================================================
-- CAMPAIGN_MASTER - Add Campaign-User Reverse Lookup
-- ============================================================================
ALTER TABLE campaign_master 
ADD INDEX idx_campaign_user (campaign_id, user_id);

-- ============================================================================
-- CAMPAIGN_STATUS - Add 2 Recommended Indexes
-- ============================================================================

-- Fast status checks with campaign filtering
ALTER TABLE campaign_status 
ADD INDEX idx_campaign_status (campaign_id, status);

-- Status-based campaign lookup (for monitoring queries)
ALTER TABLE campaign_status 
ADD INDEX idx_status_user (status, campaign_id);

-- ============================================================================
-- EMAILS - Add 2 Recommended Indexes
-- ============================================================================

-- Fast validation status filtering (for email source queries)
ALTER TABLE emails 
ADD INDEX idx_validation_status (domain_status, validation_status, csv_list_id);

-- CSV list with validation filtering
ALTER TABLE emails 
ADD INDEX idx_csv_list_valid (csv_list_id, validation_status, domain_status);

-- ============================================================================
-- IMPORTED_RECIPIENTS - Add 2 Recommended Indexes
-- ============================================================================

-- Fast batch filtering (for import-based campaigns)
ALTER TABLE imported_recipients 
ADD INDEX idx_batch_active (import_batch_id, is_active);

-- Batch with email lookup
ALTER TABLE imported_recipients 
ADD INDEX idx_batch_email (import_batch_id, Emails(100));

-- ============================================================================
-- VERIFICATION - Check if indexes were created successfully
-- ============================================================================

SELECT '============================================' as '';
SELECT 'SERVER 1 (Campaign DB) Index Verification' as '';
SELECT '============================================' as '';

-- Show all indexes on critical tables
SELECT 
    TABLE_NAME,
    INDEX_NAME,
    GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) as COLUMNS
FROM information_schema.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME IN ('campaign_master', 'campaign_status', 'emails', 'imported_recipients')
AND INDEX_NAME NOT IN ('PRIMARY')
GROUP BY TABLE_NAME, INDEX_NAME
ORDER BY TABLE_NAME, INDEX_NAME;

-- ============================================================================
-- SUCCESS MESSAGE
-- ============================================================================

SELECT '' as '';
SELECT '✅ SERVER 1 (Campaign DB) setup complete!' as STATUS;
SELECT '💡 Any "Duplicate key name" errors are SAFE - means you already had those indexes' as NOTE;
SELECT '✅ Campaign queries will be faster for multi-user scenarios' as MESSAGE;
SELECT '' as '';
SELECT 'New indexes attempted:' as DETAILS;
SELECT '  ✓ campaign_master.idx_campaign_user' as '';
SELECT '  ✓ campaign_status.idx_campaign_status' as '';
SELECT '  ✓ campaign_status.idx_status_user' as '';
SELECT '  ✓ emails.idx_validation_status' as '';
SELECT '  ✓ emails.idx_csv_list_valid' as '';
SELECT '  ✓ imported_recipients.idx_batch_active' as '';
SELECT '  ✓ imported_recipients.idx_batch_email' as '';
SELECT '' as '';
SELECT '  ℹ️  Skipped: campaign_master.idx_user_campaign (you already have it!)' as '';

-- ============================================================================
-- PRIORITY NOTES
-- ============================================================================

SELECT '' aIMPORTANT: "Duplicate key name" errors are NORMAL!' as NOTE;
SELECT '   It means you already have that index - SAFE to ignore' as '';
SELECT '   Only worry if you see other types of errors' as '';
SELECT '' as '';
SELECT '📌 These Server 1 indexes are OPTIONAL' as NOTE2
SELECT '📌 These Server 1 indexes are OPTIONAL' as NOTE;
SELECT '   Server 2 indexes are CRITICAL (apply those first!)' as PRIORITY;
SELECT '   Only apply Server 1 indexes if you notice:' as WHEN;
SELECT '     - Slow campaign listing in frontend' as '';
SELECT '     - Slow campaign status updates' as '';
SELECT '     - Slow email source queries' as '';

-- ============================================================================
