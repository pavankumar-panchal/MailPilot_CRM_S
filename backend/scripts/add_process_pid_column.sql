-- Add process_pid column to campaign_status table to track running campaign processes
-- This prevents duplicate campaign execution and allows cleanup on completion
-- Compatible with MySQL 5.5+ and MariaDB 5.5+

USE email_id;

-- Check and add process_pid column
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = 'email_id' 
    AND TABLE_NAME = 'campaign_status' 
    AND COLUMN_NAME = 'process_pid');

SET @sqlstmt := IF(@exist > 0, 
    'SELECT ''Column process_pid already exists'' AS result',
    'ALTER TABLE campaign_status ADD COLUMN process_pid INT NULL DEFAULT NULL AFTER status');

PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add index
SET @index_exist := (SELECT COUNT(*) FROM information_schema.STATISTICS 
    WHERE TABLE_SCHEMA = 'email_id' 
    AND TABLE_NAME = 'campaign_status' 
    AND INDEX_NAME = 'idx_process_pid');

SET @sqlstmt2 := IF(@index_exist > 0,
    'SELECT ''Index idx_process_pid already exists'' AS result',
    'ALTER TABLE campaign_status ADD INDEX idx_process_pid (process_pid)');

PREPARE stmt2 FROM @sqlstmt2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

-- Clear any stale PIDs from stopped/completed campaigns
UPDATE campaign_status 
SET process_pid = NULL 
WHERE status IN ('completed', 'paused', 'stopped', 'failed');

SELECT 'Migration completed successfully - process_pid column added' AS result;
