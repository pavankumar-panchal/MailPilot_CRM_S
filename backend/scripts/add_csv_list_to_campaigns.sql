-- Add csv_list_id column to campaign_master table
-- This allows campaigns to be linked to specific CSV lists for targeted email sending

ALTER TABLE campaign_master 
ADD COLUMN csv_list_id INT DEFAULT NULL AFTER images_paths,
ADD INDEX idx_csv_list_id (csv_list_id);

-- Add foreign key constraint (optional, uncomment if you want referential integrity)
-- ALTER TABLE campaign_master 
-- ADD CONSTRAINT fk_campaign_csv_list 
-- FOREIGN KEY (csv_list_id) REFERENCES csv_list(id) 
-- ON DELETE SET NULL 
-- ON UPDATE CASCADE;
