-- ============================================================
-- CHECK EXISTING INDEXES
-- Run this to see which indexes already exist
-- ============================================================

SELECT 
    'emails' as table_name,
    INDEX_NAME,
    GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) as columns,
    INDEX_TYPE,
    NON_UNIQUE
FROM information_schema.STATISTICS
WHERE table_schema = 'email_id'
AND table_name = 'emails'
AND INDEX_NAME LIKE 'idx_%'
GROUP BY INDEX_NAME, INDEX_TYPE, NON_UNIQUE

UNION ALL

SELECT 
    'csv_list' as table_name,
    INDEX_NAME,
    GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) as columns,
    INDEX_TYPE,
    NON_UNIQUE
FROM information_schema.STATISTICS
WHERE table_schema = 'email_id'
AND table_name = 'csv_list'
AND INDEX_NAME LIKE 'idx_%'
GROUP BY INDEX_NAME, INDEX_TYPE, NON_UNIQUE

ORDER BY table_name, INDEX_NAME;
