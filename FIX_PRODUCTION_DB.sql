-- ============================================================
-- FIX PRODUCTION DATABASE (email_id)
-- Run this on the production server to fix missing user_id
-- ============================================================

-- STEP 1: Assign user_id to campaign_master (set to user 1, adjust if needed)
UPDATE campaign_master 
SET user_id = 1 
WHERE campaign_id = 1 AND user_id IS NULL;

-- STEP 2: Assign user_id to campaign_status
UPDATE campaign_status 
SET user_id = 1 
WHERE campaign_id = 1 AND user_id IS NULL;

-- STEP 3: Clean up old mail_blaster records with NULL values
-- Option A: Delete old records and let system recreate them with proper values
DELETE FROM mail_blaster WHERE campaign_id = 1;

-- Option B: If you want to keep history, update existing records
-- UPDATE mail_blaster 
-- SET user_id = 1,
--     smtp_account_id = CASE 
--         WHEN smtpid = 33 THEN 33
--         WHEN smtpid = 99 THEN 99
--         WHEN smtpid = 124 THEN 124
--         ELSE smtpid 
--     END,
--     smtp_email = (SELECT email FROM smtp_accounts WHERE id = mail_blaster.smtpid LIMIT 1)
-- WHERE campaign_id = 1;

-- STEP 4: Reset campaign status to allow restart
UPDATE campaign_status 
SET status = 'pending',
    sent_emails = 0,
    failed_emails = 0,
    pending_emails = total_emails,
    process_pid = NULL,
    start_time = NULL
WHERE campaign_id = 1;

-- ============================================================
-- VERIFICATION QUERIES
-- ============================================================

-- Check campaign_master has user_id
SELECT campaign_id, description, user_id, csv_list_id 
FROM campaign_master 
WHERE campaign_id = 1;

-- Check campaign_status has user_id
SELECT campaign_id, status, user_id, total_emails, sent_emails, pending_emails 
FROM campaign_status 
WHERE campaign_id = 1;

-- Check mail_blaster is clean
SELECT COUNT(*) as record_count 
FROM mail_blaster 
WHERE campaign_id = 1;

-- Check user has active SMTP accounts
SELECT sa.id, sa.email, sa.is_active, sa.user_id, ss.name as server_name 
FROM smtp_accounts sa 
JOIN smtp_servers ss ON sa.smtp_server_id = ss.id 
WHERE sa.user_id = 1 AND sa.is_active = 1;
