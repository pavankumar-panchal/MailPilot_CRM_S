-- Migration script to add is_active status to workers table
-- This allows enabling/disabling workers without deleting them

USE email_id;

-- Add is_active column to workers table
ALTER TABLE workers 
ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 
COMMENT 'Worker active status: 1=active, 0=inactive';

-- Create index for efficient filtering by status
CREATE INDEX idx_workers_active ON workers(is_active);

-- Set all existing workers to active by default (already done by DEFAULT 1, but being explicit)
UPDATE workers SET is_active = 1 WHERE is_active IS NULL;

-- Show updated table structure
DESCRIBE workers;

-- Show all workers with their status
SELECT id, workername, ip, is_active, 
       CASE WHEN is_active = 1 THEN 'Active' ELSE 'Inactive' END AS status_text
FROM workers 
ORDER BY is_active DESC, workername ASC;
