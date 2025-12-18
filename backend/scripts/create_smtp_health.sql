-- SMTP Health Monitoring Table
-- Tracks SMTP account health and automatically suspends problematic accounts

CREATE TABLE IF NOT EXISTS `smtp_health` (
    `smtp_id` INT PRIMARY KEY,
    `health` ENUM('healthy','degraded','suspended') DEFAULT 'healthy',
    `consecutive_failures` INT DEFAULT 0,
    `last_success_at` DATETIME NULL,
    `last_failure_at` DATETIME NULL,
    `suspend_until` DATETIME NULL,
    `last_error_type` VARCHAR(50),
    `last_error_message` TEXT,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`smtp_id`) REFERENCES `smtp_accounts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Index for quick health lookups
CREATE INDEX idx_smtp_health_status ON smtp_health(health, suspend_until);

-- How it works:
-- 1. healthy: 0-4 consecutive failures - account works normally
-- 2. degraded: 5-9 consecutive failures - used only when healthy accounts unavailable
-- 3. suspended: 10+ consecutive failures - suspended for 1 hour, then auto-restored

-- Any successful send resets consecutive_failures to 0 and marks as 'healthy'
-- Suspended accounts auto-restore after suspend_until time passes
