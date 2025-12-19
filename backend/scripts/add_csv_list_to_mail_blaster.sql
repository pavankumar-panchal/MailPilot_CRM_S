-- Add csv_list_id column to mail_blaster table
-- This tracks which CSV list each sent email belongs to

ALTER TABLE `mail_blaster` 
ADD COLUMN `csv_list_id` INT(10) UNSIGNED DEFAULT NULL AFTER `to_mail`,
ADD INDEX `idx_mail_blaster_csv_list` (`csv_list_id`),
ADD INDEX `idx_mail_blaster_campaign_csv` (`campaign_id`, `csv_list_id`, `status`);

-- Update existing records with csv_list_id from emails table
UPDATE mail_blaster mb
INNER JOIN emails e ON e.raw_emailid = mb.to_mail
SET mb.csv_list_id = e.csv_list_id
WHERE mb.csv_list_id IS NULL;
