-- =========================================
-- VERIFY EMAIL SENDING & SERVER 2 UPDATES
-- =========================================
-- Use these queries to verify emails are being sent and 
-- Server 2 tables are being updated correctly

-- 1. CHECK MAIL_BLASTER - Verify emails marked as 'success'
SELECT 
    id,
    campaign_id,
    to_mail,
    smtp_email,
    status,
    delivery_time,
    attempt_count
FROM mail_blaster 
WHERE campaign_id = 68  -- Replace with your campaign_id
ORDER BY delivery_time DESC 
LIMIT 20;

-- 2. CHECK SMS_USAGE - Verify hourly/daily counters are updating
SELECT 
    smtp_id,
    date,
    hour,
    emails_sent,
    timestamp,
    user_id
FROM smtp_usage
WHERE date = CURDATE()
ORDER BY smtp_id, hour DESC
LIMIT 30;

-- 3. CHECK SMTP_ACCOUNTS - Verify sent_today and total_sent incrementing
SELECT 
    id,
    email,
    sent_today,
    total_sent,
    daily_limit,
    hourly_limit,
    status
FROM smtp_accounts
WHERE server_id = 16  -- Replace with your server_id
ORDER BY sent_today DESC;

-- 4. CHECK SMTP_HEALTH - Verify accounts staying healthy
SELECT 
    smtp_id,
    health,
    consecutive_failures,
    last_success_at,
    last_failure_at,
    updated_at
FROM smtp_health
WHERE smtp_id IN (SELECT id FROM smtp_accounts WHERE server_id = 16)
ORDER BY last_success_at DESC;

-- 5. HOURLY USAGE SUMMARY - See usage per hour for today
SELECT 
    sa.email,
    su.hour,
    su.emails_sent,
    sa.hourly_limit,
    CONCAT(su.emails_sent, '/', sa.hourly_limit) as usage_ratio,
    CASE 
        WHEN sa.hourly_limit > 0 AND su.emails_sent >= sa.hourly_limit THEN '🔴 LIMIT REACHED'
        WHEN sa.hourly_limit > 0 AND su.emails_sent >= (sa.hourly_limit * 0.8) THEN '🟡 80% USED'
        ELSE '🟢 OK'
    END as status
FROM smtp_usage su
JOIN smtp_accounts sa ON su.smtp_id = sa.id
WHERE su.date = CURDATE()
ORDER BY sa.email, su.hour DESC;

-- 6. DAILY USAGE SUMMARY - Total sent today per account
SELECT 
    sa.id,
    sa.email,
    COALESCE(SUM(su.emails_sent), 0) as sent_today,
    sa.daily_limit,
    CONCAT(COALESCE(SUM(su.emails_sent), 0), '/', sa.daily_limit) as usage_ratio,
    CASE 
        WHEN sa.daily_limit > 0 AND COALESCE(SUM(su.emails_sent), 0) >= sa.daily_limit THEN '🔴 LIMIT REACHED'
        WHEN sa.daily_limit > 0 AND COALESCE(SUM(su.emails_sent), 0) >= (sa.daily_limit * 0.8) THEN '🟡 80% USED'
        ELSE '🟢 OK'
    END as status
FROM smtp_accounts sa
LEFT JOIN smtp_usage su ON sa.id = su.smtp_id AND su.date = CURDATE()
WHERE sa.server_id = 16  -- Replace with your server_id
GROUP BY sa.id, sa.email, sa.daily_limit
ORDER BY sent_today DESC;

-- 7. CAMPAIGN STATUS - Check overall campaign progress
SELECT 
    cm.campaign_id,
    cm.csv_list_id,
    cs.status,
    cs.sent_emails,
    cs.failed_emails,
    cs.pending_emails,
    cs.total_emails,
    CONCAT(ROUND((cs.sent_emails / cs.total_emails * 100), 2), '%') as completion_pct
FROM campaign_master cm
JOIN campaign_status cs ON cm.campaign_id = cs.campaign_id
WHERE cm.campaign_id = 68;  -- Replace with your campaign_id

-- 8. REAL-TIME SENDING - Last 10 emails sent
SELECT 
    mb.to_mail,
    mb.smtp_email,
    mb.delivery_time,
    mb.status,
    TIMESTAMPDIFF(SECOND, mb.delivery_time, NOW()) as seconds_ago
FROM mail_blaster mb
WHERE mb.campaign_id = 68  -- Replace with your campaign_id
  AND mb.status = 'success'
ORDER BY mb.delivery_time DESC
LIMIT 10;

-- 9. IDENTIFY ISSUES - Stuck or failed emails
SELECT 
    status,
    COUNT(*) as count,
    MAX(delivery_time) as last_update
FROM mail_blaster
WHERE campaign_id = 68  -- Replace with your campaign_id
GROUP BY status;

-- 10. VERIFY HOURLY LIMITS WORKING - Check if any account exceeded limits
SELECT 
    sa.email,
    su.hour,
    su.emails_sent,
    sa.hourly_limit,
    CASE 
        WHEN su.emails_sent > sa.hourly_limit THEN '❌ EXCEEDED (BUG!)'
        WHEN su.emails_sent = sa.hourly_limit THEN '⚠️  AT LIMIT'
        ELSE '✅ WITHIN LIMIT'
    END as limit_status
FROM smtp_usage su
JOIN smtp_accounts sa ON su.smtp_id = sa.id
WHERE su.date = CURDATE()
  AND sa.hourly_limit > 0
ORDER BY limit_status DESC, su.emails_sent DESC;

-- =========================================
-- QUICK VERIFICATION
-- =========================================
-- Run this single query to verify everything is working:

SELECT 
    'mail_blaster' as table_name,
    COUNT(*) as success_count,
    MAX(delivery_time) as last_sent
FROM mail_blaster 
WHERE campaign_id = 68 AND status = 'success'
UNION ALL
SELECT 
    'smtp_usage' as table_name,
    COALESCE(SUM(emails_sent), 0) as total_sent_today,
    MAX(timestamp) as last_update
FROM smtp_usage 
WHERE date = CURDATE()
UNION ALL
SELECT 
    'smtp_accounts' as table_name,
    SUM(sent_today) as total_sent_all_accounts,
    NOW() as timestamp
FROM smtp_accounts
WHERE server_id = 16;
