-- Ensure only one email per (campaign_id, to_mail)
ALTER TABLE `mail_blaster`
  ADD UNIQUE KEY `uq_campaign_email` (`campaign_id`,`to_mail`);
