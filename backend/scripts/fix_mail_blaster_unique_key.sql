-- =====================================================
-- Fix mail_blaster table UNIQUE constraint
-- Ensures uq_campaign_email exists on (campaign_id, to_mail)
-- Compatible with MariaDB 5.5
-- Works without information_schema access
-- =====================================================

USE email_id;

-- =====================================================
-- STEP 1: Remove duplicate records
-- =====================================================
DELETE mb1 FROM mail_blaster mb1
INNER JOIN mail_blaster mb2 
WHERE mb1.id > mb2.id 
AND mb1.campaign_id = mb2.campaign_id 
AND mb1.to_mail = mb2.to_mail;

-- =====================================================
-- STEP 2: Drop old index (uniq_campaign_email) if exists
-- Use error handler approach
-- =====================================================
DROP PROCEDURE IF EXISTS drop_old_index;

DELIMITER $$
CREATE PROCEDURE drop_old_index()
BEGIN
    DECLARE CONTINUE HANDLER FOR 1091 -- Can't DROP; check that column/key exists
    BEGIN END;
    
    ALTER TABLE mail_blaster DROP INDEX uniq_campaign_email;
END$$
DELIMITER ;

CALL drop_old_index();
DROP PROCEDURE drop_old_index;

-- =====================================================
-- STEP 3: Add new index (uq_campaign_email) if not exists
-- Use error handler approach
-- =====================================================
DROP PROCEDURE IF EXISTS add_new_index;

DELIMITER $$
CREATE PROCEDURE add_new_index()
BEGIN
    DECLARE CONTINUE HANDLER FOR 1061 -- Duplicate key name
    BEGIN END;
    
    ALTER TABLE mail_blaster ADD UNIQUE KEY `uq_campaign_email` (`campaign_id`, `to_mail`(191));
END$$
DELIMITER ;

CALL add_new_index();
DROP PROCEDURE add_new_index;

-- =====================================================
-- VERIFICATION: Show indexes on mail_blaster table
-- =====================================================
SHOW INDEX FROM mail_blaster WHERE Key_name LIKE '%campaign%';

