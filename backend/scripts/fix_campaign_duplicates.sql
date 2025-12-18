-- Fix Campaign Duplicates SQL Script
-- Run this on the server database: email_id
-- 
-- This script:
-- 1. Shows current duplicates
-- 2. Removes duplicate campaign_status rows (keeps the latest)
-- 3. Adds UNIQUE constraint on campaign_id

-- Step 1: Check for duplicates
SELECT 'Checking for duplicates...' AS status;
SELECT campaign_id, COUNT(*) as duplicate_count 
FROM campaign_status 
GROUP BY campaign_id 
HAVING duplicate_count > 1;

-- Step 2: Remove duplicates (keep only the row with highest ID for each campaign_id)
DELETE cs1 FROM campaign_status cs1
INNER JOIN campaign_status cs2 
WHERE cs1.campaign_id = cs2.campaign_id 
AND cs1.id < cs2.id;

-- Step 3: Add UNIQUE constraint if not exists
-- First check if constraint already exists
SELECT 'Adding UNIQUE constraint...' AS status;

-- If constraint doesn't exist, this will add it
-- If it already exists, you'll get a duplicate key error (which is fine, can ignore)
ALTER TABLE campaign_status ADD UNIQUE KEY unique_campaign_id (campaign_id);

-- Step 4: Verify the fix
SELECT 'Verification...' AS status;
SELECT 
    COUNT(DISTINCT campaign_id) as unique_campaigns,
    COUNT(*) as total_status_rows
FROM campaign_status;

SELECT 'Cleanup complete!' AS status;
