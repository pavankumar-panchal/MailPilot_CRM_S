-- =====================================================
-- MailPilot CRM - Campaign Heavy Load Tables
-- =====================================================
-- This SQL contains ONLY the high-traffic campaign tables for campaign operations
-- Import this to the CRM database on local server (127.0.0.1)
-- Campaign master data stays on the remote server (174.141.233.174)
-- Generated: February 16, 2026
-- 
-- USAGE:
--   mysql -h 127.0.0.1 -u root -p CRM < campaign_heavy_tables.sql
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- =====================================================
-- HEAVY LOAD TABLES (New Server)
-- =====================================================

-- Email queue and delivery status (MAIN WORK QUEUE - Heavy writes)
CREATE TABLE `mail_blaster` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_id` bigint(20) UNSIGNED NOT NULL,
  `smtp_account_id` int(11) DEFAULT NULL,
  `smtp_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_mail` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `csv_list_id` int(10) UNSIGNED DEFAULT NULL,
  `reply_to` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `smtpid` int(11) NOT NULL,
  `delivery_date` date NOT NULL,
  `delivery_time` time NOT NULL,
  `status` enum('pending','success','failed','processing') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `sent_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `attempt_count` int(11) DEFAULT '0',
  `user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_campaign_email` (`campaign_id`,`to_mail`(191)),
  KEY `idx_campaign_id` (`campaign_id`),
  KEY `idx_to_mail` (`to_mail`(191)),
  KEY `idx_status` (`status`),
  KEY `idx_delivery_date` (`delivery_date`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_campaign_status_attempt` (`campaign_id`,`status`,`attempt_count`),
  KEY `idx_campaign_tomail` (`campaign_id`,`to_mail`(191)),
  KEY `idx_smtpid_campaign` (`smtpid`,`campaign_id`,`status`),
  KEY `idx_delivery_time` (`delivery_date`,`delivery_time`),
  KEY `idx_campaign_pending` (`campaign_id`,`status`,`attempt_count`,`id`),
  KEY `idx_campaign_processing` (`campaign_id`,`status`,`delivery_time`),
  KEY `idx_campaign_email_unique` (`campaign_id`,`to_mail`(191),`status`),
  KEY `idx_campaign_status_processing` (`campaign_id`,`status`,`delivery_time`,`attempt_count`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SMTP server configurations
CREATE TABLE `smtp_servers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `host` varchar(100) NOT NULL,
  `port` int(11) NOT NULL,
  `encryption` varchar(10) NOT NULL,
  `received_email` varchar(150) NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_uid` bigint(20) UNSIGNED DEFAULT '0',
  `user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- SMTP account credentials and limits
CREATE TABLE `smtp_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `smtp_server_id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `from_name` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `daily_limit` int(11) DEFAULT '500',
  `hourly_limit` int(11) DEFAULT '100',
  `hour_started_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sent_today` int(11) NOT NULL DEFAULT '0',
  `total_sent` int(11) NOT NULL DEFAULT '0',
  `user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_server_active_user` (`smtp_server_id`,`is_active`,`user_id`),
  KEY `idx_user_active` (`user_id`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- SMTP usage tracking (Heavy writes - hourly/daily quotas)
CREATE TABLE `smtp_usage` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `smtp_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `hour` tinyint(2) NOT NULL,
  `timestamp` datetime NOT NULL,
  `emails_sent` int(11) NOT NULL DEFAULT '0',
  `user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_smtp_hour` (`smtp_id`,`date`,`hour`,`user_id`),
  KEY `idx_smtp_date_hour` (`smtp_id`,`date`,`hour`),
  KEY `idx_user_date` (`user_id`,`date`,`hour`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- SMTP health monitoring
CREATE TABLE `smtp_health` (
  `smtp_id` int(11) NOT NULL,
  `health` enum('healthy','degraded','suspended') DEFAULT 'healthy',
  `consecutive_failures` int(11) DEFAULT '0',
  `last_success_at` datetime DEFAULT NULL,
  `last_failure_at` datetime DEFAULT NULL,
  `suspend_until` datetime DEFAULT NULL,
  `last_error_type` varchar(50) DEFAULT NULL,
  `last_error_message` text,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`smtp_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- SMTP round-robin rotation tracking
CREATE TABLE `smtp_rotation` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `last_smtp_index` int(11) NOT NULL DEFAULT '0',
  `last_smtp_id` int(11) DEFAULT NULL,
  `total_smtp_count` int(11) NOT NULL DEFAULT '0',
  `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- =====================================================
-- DEPLOYMENT NOTES
-- =====================================================
-- 
-- CAMPAIGN DATABASE (Local - CRM):
-- - mail_blaster (email queue - heavy writes)
-- - smtp_servers
-- - smtp_accounts
-- - smtp_usage (high-frequency updates)
-- - smtp_health
-- - smtp_rotation
-- 
-- MAIN DATABASE (Remote - 174.141.233.174 - email_id):
-- - campaign_master
-- - campaign_status
-- - imported_recipients
-- - mail_templates
-- - campaign_distribution
-- - users, user_tokens
-- - All other tables
-- 
-- =====================================================
