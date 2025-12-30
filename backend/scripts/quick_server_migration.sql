-- =====================================================
-- Quick Migration: Add Template Columns to Server
-- =====================================================
-- Run this in phpMyAdmin on your server's email_id database
-- =====================================================

-- Add template_id column
ALTER TABLE `campaign_master` 
ADD COLUMN `template_id` INT NULL DEFAULT NULL 
COMMENT 'ID of mail template to use for this campaign' 
AFTER `csv_list_id`;

-- Add import_batch_id column  
ALTER TABLE `campaign_master` 
ADD COLUMN `import_batch_id` VARCHAR(100) NULL DEFAULT NULL 
COMMENT 'Batch ID from imported_recipients table for Excel imports' 
AFTER `template_id`;

-- Verify columns were added
SELECT 'Migration completed! Verifying...' AS Status;

SELECT 
    COLUMN_NAME, 
    DATA_TYPE, 
    IS_NULLABLE, 
    COLUMN_DEFAULT,
    COLUMN_COMMENT
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = 'email_id'
AND TABLE_NAME = 'campaign_master'
ORDER BY ORDINAL_POSITION;
