-- Fix mail_blaster table: Remove duplicates and add UNIQUE constraint
-- Run this once to clean up existing duplicates

-- Step 1: Backup current data (optional but recommended)
-- CREATE TABLE mail_blaster_backup_20260130 AS SELECT * FROM mail_blaster;

-- Step 2: Delete duplicate rows, keeping only the latest one per campaign_id + to_mail
DELETE mb1 FROM mail_blaster mb1
INNER JOIN mail_blaster mb2 
WHERE 
    mb1.campaign_id = mb2.campaign_id 
    AND mb1.to_mail = mb2.to_mail
    AND mb1.id < mb2.id;  -- Keep the newer record (higher ID)

-- Step 3: Add UNIQUE constraint to prevent future duplicates
-- First check if constraint exists
SELECT COUNT(*) as constraint_exists 
FROM information_schema.TABLE_CONSTRAINTS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'mail_blaster' 
AND CONSTRAINT_NAME = 'unique_campaign_email';

-- If constraint doesn't exist (constraint_exists = 0), run this:
ALTER TABLE mail_blaster 
ADD UNIQUE KEY unique_campaign_email (campaign_id, to_mail);

-- Step 4: Verify cleanup
SELECT 
    campaign_id, 
    to_mail, 
    COUNT(*) as duplicate_count 
FROM mail_blaster 
GROUP BY campaign_id, to_mail 
HAVING COUNT(*) > 1;

-- Step 5: Show cleanup summary
SELECT 
    'Total records' as info,
    COUNT(*) as count 
FROM mail_blaster
UNION ALL
SELECT 
    'Unique emails per campaign',
    COUNT(DISTINCT CONCAT(campaign_id, '-', to_mail))
FROM mail_blaster;
