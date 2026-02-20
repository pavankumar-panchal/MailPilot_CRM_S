-- =========================================
-- CLEANUP DUPLICATE MAIL_BLASTER RECORDS
-- =========================================
-- This script helps identify and clean up duplicate email records 
-- in the mail_blaster table that may be causing send failures

-- Step 1: Find duplicate records (same campaign_id + to_mail)
SELECT 
    campaign_id, 
    to_mail, 
    COUNT(*) as duplicate_count,
    GROUP_CONCAT(id ORDER BY id) as record_ids,
    GROUP_CONCAT(status ORDER BY id) as statuses,
    GROUP_CONCAT(delivery_time ORDER BY id) as delivery_times
FROM mail_blaster 
GROUP BY campaign_id, to_mail 
HAVING COUNT(*) > 1
ORDER BY duplicate_count DESC, campaign_id, to_mail;

-- Step 2: Identify records that are preventing new sends
-- (showing 'pending' records that have a 'success' duplicate)
SELECT mb1.id, mb1.campaign_id, mb1.to_mail, mb1.status as pending_status, 
       mb2.id as success_id, mb2.status as success_status
FROM mail_blaster mb1
JOIN mail_blaster mb2 ON mb1.campaign_id = mb2.campaign_id AND mb1.to_mail = mb2.to_mail
WHERE mb1.status = 'pending' 
  AND mb2.status = 'success'
  AND mb1.id != mb2.id
ORDER BY mb1.campaign_id, mb1.to_mail;

-- Step 3: BACKUP before cleanup
-- CREATE TABLE mail_blaster_backup_20260217 AS SELECT * FROM mail_blaster WHERE 1=1;

-- Step 4: Clean up - Option A: Keep only the LATEST record for each campaign+email
-- This keeps the most recent status
/*
DELETE mb1 FROM mail_blaster mb1
INNER JOIN (
    SELECT campaign_id, to_mail, MAX(id) as max_id
    FROM mail_blaster
    GROUP BY campaign_id, to_mail
    HAVING COUNT(*) > 1
) mb2 ON mb1.campaign_id = mb2.campaign_id 
     AND mb1.to_mail = mb2.to_mail 
     AND mb1.id < mb2.max_id;
*/

-- Step 4: Clean up - Option B: Keep only SUCCESS records (delete failed/pending duplicates)
-- Use this if you want to keep successful sends and remove retry attempts
/*
DELETE mb1 FROM mail_blaster mb1
INNER JOIN mail_blaster mb2 
WHERE mb1.id < mb2.id 
  AND mb1.campaign_id = mb2.campaign_id 
  AND mb1.to_mail = mb2.to_mail 
  AND mb2.status = 'success'
  AND mb1.status IN ('pending', 'failed', 'processing');
*/

-- Step 5: Verify cleanup worked
SELECT 
    campaign_id, 
    to_mail, 
    COUNT(*) as record_count
FROM mail_blaster 
GROUP BY campaign_id, to_mail 
HAVING COUNT(*) > 1;

-- Step 6: Ensure UNIQUE constraint exists to prevent future duplicates
-- Note: This may fail if duplicates still exist - run cleanup first
/*
ALTER TABLE mail_blaster 
ADD UNIQUE KEY unique_campaign_email (campaign_id, to_mail);
*/

-- =========================================
-- CAMPAIGN-SPECIFIC CLEANUP
-- =========================================
-- For a specific campaign (e.g., campaign_id = 68):

-- Show duplicates for campaign 68
SELECT 
    to_mail, 
    GROUP_CONCAT(id ORDER BY id) as ids,
    GROUP_CONCAT(status ORDER BY id) as statuses,
    GROUP_CONCAT(delivery_time ORDER BY id) as times
FROM mail_blaster 
WHERE campaign_id = 68
GROUP BY to_mail 
HAVING COUNT(*) > 1;

-- Delete duplicates for campaign 68, keeping only the most recent record
/*
DELETE mb1 FROM mail_blaster mb1
INNER JOIN (
    SELECT to_mail, MAX(id) as max_id
    FROM mail_blaster
    WHERE campaign_id = 68
    GROUP BY to_mail
    HAVING COUNT(*) > 1
) mb2 ON mb1.to_mail = mb2.to_mail 
     AND mb1.id < mb2.max_id
WHERE mb1.campaign_id = 68;
*/

-- =========================================
-- RESET CAMPAIGN FOR FRESH START
-- =========================================
-- If you want to completely reset a campaign and resend all emails:
/*
-- Delete all mail_blaster records for campaign
DELETE FROM mail_blaster WHERE campaign_id = 68;

-- Reset campaign status counts
UPDATE campaign_master SET 
    sent_emails = 0,
    failed_emails = 0,
    pending_emails = (SELECT COUNT(*) FROM imported_recipients WHERE campaign_id = 68)
WHERE campaign_id = 68;

-- Reset campaign status to running
UPDATE campaign_status SET status = 'running' WHERE campaign_id = 68;
*/
