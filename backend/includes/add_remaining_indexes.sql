-- ============================================================
-- ADD REMAINING OPTIONAL INDEXES (only missing ones)
-- These provide additional performance benefits for specific queries
-- ============================================================

-- Index for raw_emailid lookups (used in batch aggregation)
ALTER TABLE `emails` 
ADD INDEX `idx_raw_emailid` (`raw_emailid`);

-- Index for csv_list status tracking
ALTER TABLE `csv_list` 
ADD INDEX `idx_status_user` (`status`, `user_id`);

-- Index for created_at timestamp queries (for analytics)
ALTER TABLE `csv_list` 
ADD INDEX `idx_created_at` (`created_at`);
