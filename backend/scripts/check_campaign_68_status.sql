-- Check the actual status of emails in campaign 68

-- 1. Show all email statuses
SELECT 
    status, 
    COUNT(*) as count 
FROM mail_blaster 
WHERE campaign_id = 68 
GROUP BY status;

-- 2. Show recent emails with details
SELECT 
    id,
    to_mail,
    status,
    smtp_email,
    attempt_count,
    delivery_time,
    error_message
FROM mail_blaster 
WHERE campaign_id = 68 
ORDER BY id DESC 
LIMIT 20;

-- 3. Check if emails are marked as 'success' but worker thinks they're 'pending'
SELECT 
    id,
    to_mail,
    status,
    delivery_time
FROM mail_blaster 
WHERE campaign_id = 68 
  AND status = 'success'
ORDER BY id;

-- 4. Check pending emails
SELECT 
    id,
    to_mail,
    status,
    delivery_time
FROM mail_blaster 
WHERE campaign_id = 68 
  AND status = 'pending'
ORDER BY id;

-- 5. Check if there are emails the worker claimed but never sent
SELECT 
    id,
    to_mail,
    status,
    delivery_time,
    TIMESTAMPDIFF(MINUTE, delivery_time, NOW()) as minutes_ago
FROM mail_blaster 
WHERE campaign_id = 68 
  AND status = 'processing'
ORDER BY delivery_time DESC;
