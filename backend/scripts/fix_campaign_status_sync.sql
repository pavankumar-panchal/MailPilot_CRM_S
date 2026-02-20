-- =========================================
-- MANUAL FIX: Sync campaign_status from mail_blaster
-- =========================================
-- Run this to immediately fix the campaign status counts
-- This syncs SERVER 1 (campaign_status) with SERVER 2 (mail_blaster)

-- For Campaign 68 (replace with your campaign_id)
SET @campaign_id = 68;

-- Get accurate counts from mail_blaster (SERVER 2)
SELECT 
    @total := COUNT(*),
    @sent := SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END),
    @failed := SUM(CASE WHEN status = 'failed' AND attempt_count >= 5 THEN 1 ELSE 0 END),
    @pending := SUM(CASE WHEN status IN ('pending', 'failed', 'processing') AND attempt_count < 5 THEN 1 ELSE 0 END)
FROM mail_blaster
WHERE campaign_id = @campaign_id;

-- Show what will be updated
SELECT 
    @campaign_id as campaign_id,
    @total as total_emails,
    @sent as sent_emails,
    @failed as failed_emails,
    @pending as pending_emails,
    CASE WHEN @pending = 0 THEN 'completed' ELSE 'running' END as should_be_status;

-- Update campaign_status on SERVER 1
UPDATE campaign_status 
SET 
    total_emails = @total,
    sent_emails = @sent,
    failed_emails = @failed,
    pending_emails = @pending,
    status = CASE 
        WHEN @pending = 0 AND @total > 0 THEN 'completed'
        ELSE status 
    END,
    end_time = CASE 
        WHEN @pending = 0 AND @total > 0 AND status != 'completed' THEN NOW()
        ELSE end_time 
    END
WHERE campaign_id = @campaign_id;

-- Verify the update
SELECT 
    campaign_id,
    status,
    total_emails,
    sent_emails,
    failed_emails,
    pending_emails,
    CONCAT(ROUND(sent_emails/NULLIF(total_emails,0)*100, 2), '%') as completion_pct,
    start_time,
    end_time
FROM campaign_status
WHERE campaign_id = @campaign_id;

SELECT '✅ Campaign status synced successfully!' as result;

-- =========================================
-- FIX ALL CAMPAIGNS (use with caution)
-- =========================================
-- Uncomment to sync ALL campaigns at once
/*
UPDATE campaign_status cs
JOIN (
    SELECT 
        campaign_id,
        COUNT(*) as total,
        SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as sent,
        SUM(CASE WHEN status = 'failed' AND attempt_count >= 5 THEN 1 ELSE 0 END) as failed,
        SUM(CASE WHEN status IN ('pending', 'failed', 'processing') AND attempt_count < 5 THEN 1 ELSE 0 END) as pending
    FROM mail_blaster
    GROUP BY campaign_id
) mb ON cs.campaign_id = mb.campaign_id
SET 
    cs.total_emails = mb.total,
    cs.sent_emails = mb.sent,
    cs.failed_emails = mb.failed,
    cs.pending_emails = mb.pending,
    cs.status = CASE 
        WHEN mb.pending = 0 AND mb.total > 0 AND cs.status = 'running' THEN 'completed'
        ELSE cs.status 
    END,
    cs.end_time = CASE 
        WHEN mb.pending = 0 AND mb.total > 0 AND cs.status = 'running' THEN NOW()
        ELSE cs.end_time 
    END
WHERE cs.status IN ('running', 'pending');

SELECT CONCAT('✅ Synced ', ROW_COUNT(), ' campaigns') as result;
*/
