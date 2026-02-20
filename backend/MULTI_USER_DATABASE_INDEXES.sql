-- ============================================================================
-- MULTI-USER DATABASE OPTIMIZATION INDEXES
-- ============================================================================
-- Purpose: Add ONLY MISSING but ESSENTIAL indexes for 100+ concurrent users
-- These indexes are NOT in your current schema but are critical for performance
-- ============================================================================
-- ANALYSIS: Compared with your existing schema - adding only what's missing
-- ============================================================================

-- ============================================================================
-- SERVER 1 INDEXES (Campaign & Email Source Database)
-- ============================================================================

-- Campaign Master: Fast lookup by user_id and campaign_id
ALTER TABLE campaign_master 
ADD INDEX IF NOT EXISTS idx_user_campaign (user_id, campaign_id),
ADD INDEX IF NOT EXISTS idx_campaign_user (campaign_id, user_id);

-- Campaign Status: Fast status checks and user filtering
ALTER TABLE campaign_status 
ADD INDEX IF NOT EXISTS idx_campaign_status (campaign_id, status),
ADD INDEX IF NOT EXISTS idx_status_user (status, campaign_id);

-- Emails table: Fast validation and user filtering
ALTER TABLE emails 
ADD INDEX IF NOT EXISTS idx_validation_status (domain_status, validation_status, csv_list_id),
ADD INDEX IF NOT EXISTS idx_csv_list_valid (csv_list_id, validation_status, domain_status);

-- Imported Recipients: Fast batch lookups
ALTER TABLE imported_recipients 
ADD INDEX IF NOT EXISTS idx_batch_active (import_batch_id, is_active),
ADD INDEX IF NOT EXISTS idx_batch_email (import_batch_id, Emails(100));

-- ============================================================================
-- SERVER 2 INDEXES (SMTP, Mail Blaster, Heavy Operations Database)
-- ============================================================================

-- ✅ You already have most mail_blaster indexes!
-- Adding ONLY the missing critical ones:

-- MISSING: User-based campaign filtering (critical for multi-user isolation)
ALTER TABLE mail_blaster 
ADD INDEX IF NOT EXISTS idx_user_campaign (user_id, campaign_id, status);

-- MISSING: Processing recovery index (for stuck email recovery)
ALTER TABLE mail_blaster 
ADD INDEX IF NOT EXISTS idx_processing_recovery (status, delivery_time, campaign_id);

-- ✅ You already have: unique_campaign_email, idx_campaign_status_attempt, idx_campaign_pending
-- ✅ You already have: idx_campaign_id, idx_status, idx_delivery_date, idx_user_id

-- SMTP Accounts: Missing user-server reverse lookup
-- ✅ You already have: idx_server_active_user, idx_user_active
ALTER TABLE smtp_accounts 
ADD INDEX IF NOT EXISTS idx_user_server (user_id, smtp_server_id, is_active);

-- SMTP Servers: CRITICAL for multi-user filtering
-- ⚠️ You have NO indexes on smtp_servers - these are ESSENTIAL
ALTER TABLE smtp_servers 
ADD INDEX IF NOT EXISTS idx_user_active (user_id, is_active),
ADD INDEX IF NOT EXISTS idx_active_user_server (is_active, user_id, id);

-- SMTP Usage: Missing reverse date lookup
-- ✅ You already have: idx_smtp_date_hour, idx_user_date, unique_smtp_hour
ALTER TABLE smtp_usage 
ADD INDEX IF NOT EXISTS idx_date_smtp (date, smtp_id);

-- SMTP Health: Missing health status filtering
-- ⚠️ You only have PRIMARY KEY - need health filtering for multi-user
ALTER TABLE smtp_health 
ADD INDEX IF NOT EXISTS idx_health_suspend (health, suspend_until, smtp_id);

-- ============================================================================
-- SUMMARY OF WHAT YOU ALREADY HAVE (No action needed)
-- ============================================================================

/*
✅ mail_blaster - You already have these excellent indexes:
  - PRIMARY KEY (id)
  - UNIQUE KEY unique_campaign_email (campaign_id, to_mail)
  - idx_campaign_id, idx_to_mail, idx_status, idx_delivery_date, idx_user_id
  - idx_campaign_status_attempt (campaign_id, status, attempt_count)
  - idx_campaign_pending (campaign_id, status, attempt_count, id)
  - idx_smtpid_campaign, idx_delivery_time
  - idx_campaign_processing, idx_campaign_email_unique
  - idx_campaign_status_processing

✅ smtp_accounts - You already have:
  - idx_server_active_user (smtp_server_id, is_active, user_id)
  - idx_user_active (user_id, is_active)

✅ smtp_usage - You already have:
  - UNIQUE KEY unique_smtp_hour (smtp_id, date, hour, user_id)
  - idx_smtp_date_hour (smtp_id, date, hour)
  - idx_user_date (user_id, date, hour)

✅ smtp_health - You already have:
  - PRIMARY KEY (smtp_id)

✅ smtp_rotation - You already have:
  - PRIMARY KEY (id)
*/

-- ============================================================================
-- PERFORMANCE TIPS FOR 100+ CONCURRENT USERS
-- ============================================================================

-- 1. INNODB SETTINGS (add to my.cnf/my.ini):
--    innodb_buffer_pool_size = 2G (or 50-70% of available RAM)
--    innodb_lock_wait_timeout = 10 (short timeout to prevent long waits)
--    innodb_flush_log_at_trx_commit = 2 (better performance, acceptable durability)
--    max_connections = 500 (allow 100 users × 3-5 connections each)

-- 2. QUERY CACHE (for read-heavy operations):
--    query_cache_type = 1
--    query_cache_size = 256M

-- 3. TABLE MAINTENANCE (run weekly):
--    Only if you have performance issues with large datasets
--    OPTIMIZE TABLE mail_blaster;
--    OPTIMIZE TABLE smtp_accounts;
--    OPTIMIZE TABLE smtp_usage;

-- ============================================================================
-- MONITORING QUERIES
-- ============================================================================

-- Check concurrent campaigns:
SELECT 
    COUNT(DISTINCT cs.campaign_id) as active_campaigns,
    COUNT(DISTINCT cm.user_id) as concurrent_users,
    SUM(cs.total_emails) as total_emails_in_queue,
    SUM(cs.pending_emails) as total_pending
FROM campaign_status cs
JOIN campaign_master cm ON cs.campaign_id = cm.campaign_id
WHERE cs.status = 'running';

-- Check per-user campaign load:
SELECT 
    cm.user_id,
    COUNT(cs.campaign_id) as active_campaigns,
    SUM(cs.total_emails) as total_emails,
    SUM(cs.pending_emails) as pending_emails,
    SUM(cs.sent_emails) as sent_emails
FROM campaign_status cs
JOIN campaign_master cm ON cs.campaign_id = cm.campaign_id
WHERE cs.status = 'running'
GROUP BY cm.user_id
ORDER BY total_emails DESC;

-- Check SMTP account utilization:
SELECT 
    user_id,
    COUNT(*) as total_accounts,
    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_accounts,
    SUM(sent_today) as total_sent_today
FROM smtp_accounts
GROUP BY user_id
ORDER BY total_sent_today DESC;

-- ============================================================================
-- CLEANUP QUERIES (run daily/weekly)
-- ============================================================================

-- Remove old completed campaigns from mail_blaster (older than 30 days):
DELETE FROM mail_blaster 
WHERE delivery_date < DATE_SUB(CURDATE(), INTERVAL 30 DAY)
AND status = 'success';

-- Archive old smtp_usage data (older than 90 days):
DELETE FROM smtp_usage 
WHERE date < DATE_SUB(CURDATE(), INTERVAL 90 DAY);

-- ============================================================================
