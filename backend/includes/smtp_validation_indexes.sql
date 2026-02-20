-- ============================================================
-- HIGH-PERFORMANCE SMTP VALIDATION - DATABASE INDEXES
-- Execute these queries to optimize performance for crores of emails
-- Compatible with MariaDB 5.5.68
-- ============================================================

-- ============================================================
-- IMPORTANT: Check existing indexes first
-- ============================================================
-- Run this command to see what indexes already exist:
-- SHOW INDEX FROM emails WHERE Key_name LIKE 'idx_%';
-- SHOW INDEX FROM csv_list WHERE Key_name LIKE 'idx_%';

-- ============================================================
-- STEP 1: DROP existing custom indexes (run only if they exist)
-- ============================================================
-- Uncomment and run these ONLY if you see these indexes in SHOW INDEX output:

-- For emails table:
-- ALTER TABLE `emails` DROP INDEX `idx_worker_processed`;
-- ALTER TABLE `emails` DROP INDEX `idx_user_worker_status`;
-- ALTER TABLE `emails` DROP INDEX `idx_csv_list_processing`;
-- ALTER TABLE `emails` DROP INDEX `idx_raw_emailid`;

-- For csv_list table:
-- ALTER TABLE `csv_list` DROP INDEX `idx_status_user`;
-- ALTER TABLE `csv_list` DROP INDEX `idx_created_at`;

-- ============================================================
-- STEP 2: CREATE NEW INDEXES (skip any that already exist)
-- ============================================================

-- CRITICAL: Index for worker_id + domain_processed (most important for cron queries)
-- This supports: WHERE domain_processed = 0 AND worker_id = X
-- Skip if error: #1061 - Duplicate key name 'idx_worker_processed'
ALTER TABLE `emails` 
ADD INDEX `idx_worker_processed` (`worker_id`, `domain_processed`, `id`);

-- Composite index for user + worker + status queries
-- This supports: WHERE worker_id = X AND domain_processed = 0 AND user_id = Y
-- Skip if error: #1061 - Duplicate key name 'idx_user_worker_status'
ALTER TABLE `emails` 
ADD INDEX `idx_user_worker_status` (`user_id`, `worker_id`, `domain_processed`);

-- Index for csv_list_id with processing status
-- This supports efficient csv_list updates and completion checks
-- Skip if error: #1061 - Duplicate key name 'idx_csv_list_processing'
ALTER TABLE `emails` 
ADD INDEX `idx_csv_list_processing` (`csv_list_id`, `domain_processed`, `domain_status`);

-- Index for raw_emailid lookups (used in batch aggregation)
-- Full column index (varchar(150) is already within utf8mb4 limits)
-- Skip if error: #1061 - Duplicate key name 'idx_raw_emailid'
ALTER TABLE `emails` 
ADD INDEX `idx_raw_emailid` (`raw_emailid`);

-- Index for csv_list status tracking
-- Skip if error: #1061 - Duplicate key name 'idx_status_user'
ALTER TABLE `csv_list` 
ADD INDEX `idx_status_user` (`status`, `user_id`);

-- Index for created_at timestamp queries (for analytics)
-- Skip if error: #1061 - Duplicate key name 'idx_created_at'
ALTER TABLE `csv_list` 
ADD INDEX `idx_created_at` (`created_at`);

-- ============================================================
-- VERIFY INDEXES - Run this to check if indexes were created
-- ============================================================
SHOW INDEX FROM `emails`;
SHOW INDEX FROM `csv_list`;

-- ============================================================
-- TABLE ANALYSIS - Update statistics for query optimizer
-- ============================================================
ANALYZE TABLE `emails`;
ANALYZE TABLE `csv_list`;

-- ============================================================
-- PERFORMANCE MONITORING QUERIES
-- ============================================================

-- Check table size and row count
SELECT 
    table_name,
    CONCAT(ROUND(table_rows / 10000000, 2), ' Cr') as 'Rows (Crores)',
    table_rows as 'Total Rows',
    ROUND((data_length + index_length) / 1024 / 1024, 2) AS 'Total Size (MB)',
    ROUND(data_length / 1024 / 1024, 2) AS 'Data Size (MB)',
    ROUND(index_length / 1024 / 1024, 2) AS 'Index Size (MB)',
    ROUND((index_length / (data_length + index_length)) * 100, 2) AS 'Index %'
FROM information_schema.TABLES
WHERE table_schema = 'email_id'
AND table_name IN ('emails', 'csv_list');

-- Check index cardinality and efficiency
SELECT 
    TABLE_NAME,
    INDEX_NAME,
    SEQ_IN_INDEX,
    COLUMN_NAME,
    CARDINALITY,
    SUB_PART,
    INDEX_TYPE
FROM information_schema.STATISTICS
WHERE table_schema = 'email_id'
AND table_name IN ('emails', 'csv_list')
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;

-- Find slow or missing indexes (check EXPLAIN on critical queries)
-- Example: Test the main cron query
EXPLAIN SELECT 
    COALESCE(e.user_id, cl.user_id) as user_id,
    COUNT(*) as pending_count
FROM emails e
LEFT JOIN csv_list cl ON e.csv_list_id = cl.id
WHERE e.domain_processed = 0 AND e.worker_id = 1
GROUP BY COALESCE(e.user_id, cl.user_id);

-- ============================================================
-- MYSQL OPTIMIZATION SETTINGS (Run as root/admin)
-- ============================================================

/*
-- View current settings
SHOW VARIABLES LIKE 'max_connections';
SHOW VARIABLES LIKE 'innodb_buffer_pool_size';
SHOW VARIABLES LIKE 'innodb_log_file_size';

-- Recommended settings for processing crores of emails
-- Adjust based on your server's RAM (these assume 8GB+ available)

-- Increase connection limit (default 151 may be too low)
SET GLOBAL max_connections = 250;

-- Increase InnoDB buffer pool (critical for large tables)
-- Set to 50-70% of available RAM for dedicated DB server
-- SET GLOBAL innodb_buffer_pool_size = 4294967296;  -- 4GB

-- Optimize for bulk operations
SET GLOBAL innodb_flush_log_at_trx_commit = 2;  -- Better performance, slight risk on crash
SET GLOBAL innodb_buffer_pool_instances = 8;     -- Parallel buffer processing
SET GLOBAL innodb_write_io_threads = 8;
SET GLOBAL innodb_read_io_threads = 8;
SET GLOBAL innodb_io_capacity = 2000;            -- For SSD drives

-- Increase sort and join buffers
SET GLOBAL sort_buffer_size = 4194304;           -- 4MB per connection
SET GLOBAL join_buffer_size = 4194304;           -- 4MB per connection

-- Query cache (only for MySQL < 8.0, deprecated in 8.0+)
-- SET GLOBAL query_cache_size = 67108864;       -- 64MB
-- SET GLOBAL query_cache_limit = 2097152;       -- 2MB

-- For persistent changes, add these to /etc/my.cnf or /etc/mysql/my.cnf:
[mysqld]
max_connections = 250
innodb_buffer_pool_size = 4G
innodb_buffer_pool_instances = 8
innodb_flush_log_at_trx_commit = 2
innodb_write_io_threads = 8
innodb_read_io_threads = 8
innodb_io_capacity = 2000
sort_buffer_size = 4M
join_buffer_size = 4M

# Then restart MySQL:
# systemctl restart mysqld  (or: service mysql restart)
*/

-- ============================================================
-- TABLE MAINTENANCE (Run monthly or after processing crores)
-- ============================================================

-- Optimize tables to reclaim space and rebuild indexes
-- WARNING: This locks the table during execution - run during low traffic
OPTIMIZE TABLE `emails`;
OPTIMIZE TABLE `csv_list`;

-- Alternative: Analyze only (faster, no locking)
ANALYZE TABLE `emails`;
ANALYZE TABLE `csv_list`;

-- Check for table fragmentation
SELECT 
    table_name,
    engine,
    ROUND(data_length / 1024 / 1024, 2) AS 'Data Size (MB)',
    ROUND(data_free / 1024 / 1024, 2) AS 'Free Space (MB)',
    ROUND((data_free / NULLIF(data_length, 0)) * 100, 2) AS 'Fragmentation %',
    CASE 
        WHEN (data_free / NULLIF(data_length, 0)) * 100 > 20 THEN 'OPTIMIZE RECOMMENDED'
        WHEN (data_free / NULLIF(data_length, 0)) * 100 > 10 THEN 'Consider optimizing'
        ELSE 'Good'
    END AS 'Status'
FROM information_schema.TABLES
WHERE table_schema = 'email_id'
AND table_name IN ('emails', 'csv_list')
AND data_free > 0;

-- ============================================================
-- CLEANUP OLD/COMPLETED DATA (Optional - for long-term maintenance)
-- ============================================================

/*
-- Archive completed lists older than 6 months
-- (Create archive table first with same structure)

CREATE TABLE IF NOT EXISTS `emails_archive` LIKE `emails`;
CREATE TABLE IF NOT EXISTS `csv_list_archive` LIKE `csv_list`;

-- Move old completed data to archive
INSERT INTO csv_list_archive 
SELECT * FROM csv_list 
WHERE status = 'completed' 
AND created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH);

INSERT INTO emails_archive 
SELECT e.* FROM emails e
JOIN csv_list cl ON e.csv_list_id = cl.id
WHERE cl.status = 'completed' 
AND cl.created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH);

-- Delete archived records from main table
DELETE e FROM emails e
JOIN csv_list cl ON e.csv_list_id = cl.id
WHERE cl.status = 'completed' 
AND cl.created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH);

DELETE FROM csv_list 
WHERE status = 'completed' 
AND created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH);

-- Optimize after large deletes
OPTIMIZE TABLE emails;
OPTIMIZE TABLE csv_list;
*/

-- ============================================================
-- MONITORING QUERIES FOR HEALTH CHECKS
-- ============================================================

-- Check for stuck/orphaned records
SELECT 
    'Emails without csv_list' as issue,
    COUNT(*) as count
FROM emails 
WHERE csv_list_id IS NULL
UNION ALL
SELECT 
    'Running status >24hrs' as issue,
    COUNT(*) as count
FROM csv_list 
WHERE status = 'running' 
AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR);

-- Check worker distribution
SELECT 
    worker_id,
    COUNT(*) as total_emails,
    SUM(CASE WHEN domain_processed = 1 THEN 1 ELSE 0 END) as processed,
    SUM(CASE WHEN domain_processed = 0 THEN 1 ELSE 0 END) as pending,
    ROUND((SUM(CASE WHEN domain_processed = 1 THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as 'completion_%'
FROM emails
GROUP BY worker_id
ORDER BY worker_id;

-- Check processing rate per user
SELECT 
    user_id,
    COUNT(*) as total,
    SUM(CASE WHEN domain_processed = 1 THEN 1 ELSE 0 END) as processed,
    SUM(CASE WHEN domain_status = 1 AND domain_processed = 1 THEN 1 ELSE 0 END) as valid,
    ROUND((SUM(CASE WHEN domain_status = 1 AND domain_processed = 1 THEN 1 ELSE 0 END) / 
           NULLIF(SUM(CASE WHEN domain_processed = 1 THEN 1 ELSE 0 END), 0)) * 100, 2) as 'valid_%'
FROM emails
WHERE user_id IS NOT NULL
GROUP BY user_id
ORDER BY total DESC;

-- ============================================================
-- NOTES FOR PROCESSING CRORES OF EMAILS
-- ============================================================

/*
PERFORMANCE TIPS:

1. INDEXES: Run the ALTER TABLE statements at the top of this file
   - Critical for filtering by worker_id and domain_processed
   - Speeds up GROUP BY user_id queries by 50-100x

2. BATCH SIZE: The cron uses CHUNK_SIZE=10000
   - Increase to 50000 for systems with >16GB RAM
   - Decrease to 5000 for systems with <8GB RAM

3. WORKER POOL: Default is 25 workers per server
   - Safe for typical MySQL configs (max_connections=151)
   - Can increase to 40-50 if you raise max_connections to 250+

4. MEMORY: Set PHP memory_limit in cron to at least 512M
   - Already configured in improved smtp_validation_cron.php
   - Garbage collection runs automatically every chunk

5. MONITORING: Use the monitoring script:
   php smtp_validation_monitor.php --watch

6. DATABASE BACKUPS: 
   - Backup before processing millions of emails
   - Use mysqldump with --single-transaction for InnoDB:
     mysqldump --single-transaction email_id > backup.sql

7. DISK SPACE:
   - For 1 crore (10 million) emails: ~2-4 GB database size
   - Monitor disk space: df -h
   - Keep at least 50% free for MySQL operations

8. NETWORK: If using multiple servers
   - Ensure low latency to database server (<5ms)
   - Use persistent connections (already configured)
   - Consider MySQL replication for read scaling
*/

