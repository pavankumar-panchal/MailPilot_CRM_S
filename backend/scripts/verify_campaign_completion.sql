-- =========================================
-- VERIFY CAMPAIGN STATUS & COMPLETION
-- =========================================
-- Use this to verify campaign_status is being updated correctly on SERVER 1

-- 1. CHECK CAMPAIGN STATUS ON SERVER 1
SELECT 
    cs.campaign_id,
    cs.status,
    cs.total_emails,
    cs.sent_emails,
    cs.failed_emails,
    cs.pending_emails,
    cs.start_time,
    cs.end_time,
    CASE 
        WHEN cs.status = 'completed' THEN '✅ COMPLETED'
        WHEN cs.status = 'running' THEN '🔄 RUNNING'
        WHEN cs.status = 'pending' THEN '⏸️ PENDING'
        WHEN cs.status = 'stopped' THEN '🛑 STOPPED'
        ELSE cs.status
    END as status_display,
    CONCAT(
        ROUND((cs.sent_emails / NULLIF(cs.total_emails, 0) * 100), 2), 
        '%'
    ) as completion_percentage
FROM campaign_status cs
WHERE cs.campaign_id = 68  -- Replace with your campaign_id
ORDER BY cs.campaign_id DESC;

-- 2. COMPARE SERVER 1 vs SERVER 2 COUNTS
-- This verifies that counts match between servers
SELECT 
    'SERVER 1 (campaign_status)' as source,
    cs.total_emails,
    cs.sent_emails,
    cs.failed_emails,
    cs.pending_emails
FROM campaign_status cs
WHERE cs.campaign_id = 68

UNION ALL

SELECT 
    'SERVER 2 (mail_blaster counts)' as source,
    COUNT(*) as total_emails,
    SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as sent_emails,
    SUM(CASE WHEN status = 'failed' AND attempt_count >= 5 THEN 1 ELSE 0 END) as failed_emails,
    SUM(CASE WHEN status IN ('pending', 'failed') AND attempt_count < 5 THEN 1 ELSE 0 END) as pending_emails
FROM mail_blaster
WHERE campaign_id = 68;

-- 3. CHECK IF CAMPAIGN SHOULD BE MARKED COMPLETED
-- This shows if all conditions for completion are met
SELECT 
    campaign_id,
    status as current_status,
    pending_emails,
    CASE 
        WHEN pending_emails = 0 AND status = 'running' THEN '✅ SHOULD BE COMPLETED NOW'
        WHEN pending_emails = 0 AND status = 'completed' THEN '✅ CORRECTLY COMPLETED'
        WHEN pending_emails > 0 THEN CONCAT('⏳ STILL ', pending_emails, ' EMAILS PENDING')
        ELSE '❓ UNKNOWN STATE'
    END as should_complete
FROM campaign_status
WHERE campaign_id = 68;

-- 4. DETAILED BREAKDOWN FROM SERVER 2
-- Shows exact status of all emails
SELECT 
    status,
    COUNT(*) as count,
    GROUP_CONCAT(
        DISTINCT CONCAT('attempt_', attempt_count) 
        ORDER BY attempt_count
    ) as attempt_levels,
    MIN(delivery_time) as first_email,
    MAX(delivery_time) as last_email
FROM mail_blaster
WHERE campaign_id = 68
GROUP BY status
ORDER BY 
    FIELD(status, 'success', 'processing', 'pending', 'failed') ASC;

-- 5. VERIFY COMPLETION LOGIC
-- Check if there are any emails that would prevent completion
SELECT 
    'Unclaimed emails' as check_type,
    COUNT(*) as count,
    CASE WHEN COUNT(*) = 0 THEN '✅ OK' ELSE '⚠️ STILL UNCLAIMED' END as result
FROM imported_recipients ir
WHERE ir.campaign_id = 68
  AND ir.is_active = 1
  AND NOT EXISTS (
      SELECT 1 FROM mail_blaster mb 
      WHERE mb.campaign_id = ir.campaign_id 
      AND mb.to_mail = ir.email
  )

UNION ALL

SELECT 
    'Processing emails (stuck?)' as check_type,
    COUNT(*) as count,
    CASE WHEN COUNT(*) = 0 THEN '✅ OK' ELSE CONCAT('⚠️ ', COUNT(*), ' STUCK') END as result
FROM mail_blaster
WHERE campaign_id = 68
  AND status = 'processing'

UNION ALL

SELECT 
    'Retryable failed emails' as check_type,
    COUNT(*) as count,
    CASE WHEN COUNT(*) = 0 THEN '✅ OK' ELSE CONCAT('⏳ ', COUNT(*), ' TO RETRY') END as result
FROM mail_blaster
WHERE campaign_id = 68
  AND status = 'failed'
  AND attempt_count < 5;

-- 6. CAMPAIGN COMPLETION TIMELINE
-- Shows when emails were sent and when campaign should have completed
SELECT 
    MIN(delivery_time) as first_email_sent,
    MAX(delivery_time) as last_email_sent,
    cs.end_time as marked_completed_at,
    CASE 
        WHEN cs.end_time IS NULL AND cs.status = 'completed' THEN '⚠️ Completed but no end_time'
        WHEN cs.end_time IS NOT NULL THEN '✅ Has end_time'
        WHEN cs.status = 'running' THEN '⏳ Still running'
        ELSE '❓ Unknown'
    END as timeline_status,
    TIMESTAMPDIFF(SECOND, MAX(delivery_time), cs.end_time) as seconds_to_mark_complete
FROM mail_blaster mb
JOIN campaign_status cs ON mb.campaign_id = cs.campaign_id
WHERE mb.campaign_id = 68
  AND mb.status = 'success'
GROUP BY cs.end_time, cs.status;

-- 7. QUICK COMPLETION CHECK
-- Run this to see if campaign should be completed
SELECT 
    CASE 
        WHEN (
            SELECT COUNT(*) FROM mail_blaster 
            WHERE campaign_id = 68 
            AND status IN ('pending', 'failed') 
            AND attempt_count < 5
        ) = 0 
        AND (
            SELECT COUNT(*) FROM mail_blaster 
            WHERE campaign_id = 68 
            AND status = 'processing'
        ) = 0
        AND (
            SELECT COUNT(*) FROM mail_blaster 
            WHERE campaign_id = 68
        ) > 0
        THEN '✅ CAMPAIGN SHOULD BE COMPLETED - All emails processed!'
        ELSE '⏳ Campaign still has pending work'
    END as completion_check;

-- 8. FORCE COMPLETION (if needed)
-- Uncomment to manually mark campaign as completed
/*
UPDATE campaign_status 
SET status = 'completed',
    end_time = NOW(),
    process_pid = NULL,
    pending_emails = 0
WHERE campaign_id = 68
  AND status IN ('running', 'pending');

SELECT '✅ Campaign manually marked as completed' as result;
*/

-- 9. RESET STUCK PROCESSING EMAILS (if needed)
-- Uncomment if you have emails stuck in 'processing' status
/*
UPDATE mail_blaster 
SET status = 'pending',
    delivery_time = NULL
WHERE campaign_id = 68
  AND status = 'processing'
  AND delivery_time < DATE_SUB(NOW(), INTERVAL 2 MINUTE);

SELECT CONCAT('✅ Reset ', ROW_COUNT(), ' stuck emails to pending') as result;
*/
