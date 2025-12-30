-- =====================================================
-- Migration Script: Add Template Support Columns
-- =====================================================
-- This script adds template_id and import_batch_id columns 
-- to campaign_master table for template and Excel import support
-- Safe to run multiple times (uses IF NOT EXISTS checks)
-- =====================================================

-- Add template_id column if it doesn't exist
SET @db_name = DATABASE();
SET @col_exists = (SELECT COUNT(*) 
                   FROM information_schema.COLUMNS 
                   WHERE TABLE_SCHEMA = @db_name 
                   AND TABLE_NAME = 'campaign_master' 
                   AND COLUMN_NAME = 'template_id');

SET @query = IF(@col_exists = 0, 
    'ALTER TABLE campaign_master ADD COLUMN template_id INT NULL DEFAULT NULL COMMENT "ID of mail template to use for this campaign" AFTER csv_list_id',
    'SELECT "template_id column already exists" AS Status');

PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add import_batch_id column if it doesn't exist
SET @col_exists = (SELECT COUNT(*) 
                   FROM information_schema.COLUMNS 
                   WHERE TABLE_SCHEMA = @db_name 
                   AND TABLE_NAME = 'campaign_master' 
                   AND COLUMN_NAME = 'import_batch_id');

SET @query = IF(@col_exists = 0, 
    'ALTER TABLE campaign_master ADD COLUMN import_batch_id VARCHAR(100) NULL DEFAULT NULL COMMENT "Batch ID from imported_recipients table" AFTER template_id',
    'SELECT "import_batch_id column already exists" AS Status');

PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verify the columns were added
SELECT 
    COLUMN_NAME, 
    DATA_TYPE, 
    IS_NULLABLE, 
    COLUMN_DEFAULT,
    COLUMN_COMMENT                                                                                                                                                                                                                      
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'campaign_master'
AND COLUMN_NAME IN ('template_id', 'import_batch_id', 'csv_list_id', 'send_as_html');

-- Done!
SELECT '✓ Migration completed successfully!' AS Status;                                                                                                                                                                                                    
