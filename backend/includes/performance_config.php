<?php
/**
 * Campaign System Performance Configuration
 * 
 * Adjust these settings to tune performance for your infrastructure
 */

// ============================================================================
// WORKER CONFIGURATION
// ============================================================================

// Number of parallel workers per SMTP server
// Higher = faster sending, but more CPU/memory usage
// Recommended: 2-5 workers per server
define('WORKERS_PER_SERVER', 3);

// Delay between emails (microseconds)
// Lower = faster, but may trigger spam filters
// 10000 = 10ms = 100 emails/sec per worker
// 50000 = 50ms = 20 emails/sec per worker
define('EMAIL_SEND_DELAY_US', 10000);

// SMTP connection timeout (seconds)
// Lower = faster failure detection, but may timeout valid slow servers
// Recommended: 8-15 seconds
define('SMTP_TIMEOUT_SECONDS', 10);

// ============================================================================
// RETRY CONFIGURATION
// ============================================================================

// Delay between retry cycles (seconds)
// Lower = faster retries, but may overload failing servers
// Recommended: 1-5 seconds
define('RETRY_DELAY_SECONDS', 1);

// Maximum retry attempts per email
// Higher = more persistent, but slower failure handling
// Recommended: 3-5 attempts
define('MAX_RETRY_ATTEMPTS', 5);

// Lock wait timeout (seconds)
// How long to wait for row locks before giving up
// Lower = faster deadlock detection
// Recommended: 2-5 seconds
define('LOCK_WAIT_TIMEOUT', 2);

// ============================================================================
// MULTI-CAMPAIGN CONFIGURATION
// ============================================================================

// Maximum concurrent campaigns
// How many campaigns can run simultaneously
// Higher = more throughput, but more resource usage
// Recommended: 3-10 campaigns
define('MAX_CONCURRENT_CAMPAIGNS', 5);

// Delay between worker launches (microseconds)
// Small delay prevents server overload during startup
// Recommended: 5000-20000 (5-20ms)
define('WORKER_LAUNCH_DELAY_US', 5000);

// ============================================================================
// CLAIM/RETRY BACKOFF CONFIGURATION
// ============================================================================

// Initial retry backoff (milliseconds)
// When workers compete for emails, how long to wait before retry
// Recommended: 25-100ms
define('RETRY_BACKOFF_START_MS', 50);

// Maximum retry backoff (milliseconds)
// Maximum wait time when workers compete for emails
// Recommended: 250-1000ms
define('RETRY_BACKOFF_MAX_MS', 500);

// Maximum retry attempts before long break
// After this many failed claims, take a longer break
// Recommended: 8-15 attempts
define('MAX_RETRY_ATTEMPTS_BEFORE_BREAK', 10);

// Long break duration (seconds)
// How long to wait after max retry attempts
// Recommended: 1-3 seconds
define('RETRY_LONG_BREAK_SECONDS', 1);

// ============================================================================
// MONITORING CONFIGURATION
// ============================================================================

// Check campaign status every N iterations
// Lower = more responsive to pause/stop, but more DB queries
// Recommended: 10-50 iterations
define('STATUS_CHECK_INTERVAL', 10);

// Log progress every N emails
// Lower = more verbose logs, higher = cleaner logs
// Recommended: 10-100 emails
define('PROGRESS_LOG_INTERVAL', 10);

// ============================================================================
// PERFORMANCE PROFILES
// ============================================================================

/**
 * CONSERVATIVE (Safe for most servers)
 * - 2 workers per server
 * - 20ms delay (50 emails/sec per worker)
 * - 15s SMTP timeout
 * - 3 concurrent campaigns
 */
function applyConservativeProfile() {
    return [
        'WORKERS_PER_SERVER' => 2,
        'EMAIL_SEND_DELAY_US' => 20000,
        'SMTP_TIMEOUT_SECONDS' => 15,
        'MAX_CONCURRENT_CAMPAIGNS' => 3
    ];
}

/**
 * BALANCED (Default - good performance)
 * - 3 workers per server
 * - 10ms delay (100 emails/sec per worker)
 * - 10s SMTP timeout
 * - 5 concurrent campaigns
 */
function applyBalancedProfile() {
    return [
        'WORKERS_PER_SERVER' => 3,
        'EMAIL_SEND_DELAY_US' => 10000,
        'SMTP_TIMEOUT_SECONDS' => 10,
        'MAX_CONCURRENT_CAMPAIGNS' => 5
    ];
}

/**
 * AGGRESSIVE (Maximum speed, requires powerful server)
 * - 5 workers per server
 * - 5ms delay (200 emails/sec per worker)
 * - 8s SMTP timeout
 * - 10 concurrent campaigns
 */
function applyAggressiveProfile() {
    return [
        'WORKERS_PER_SERVER' => 5,
        'EMAIL_SEND_DELAY_US' => 5000,
        'SMTP_TIMEOUT_SECONDS' => 8,
        'MAX_CONCURRENT_CAMPAIGNS' => 10
    ];
}

// ============================================================================
// APPLY CONFIGURATION
// ============================================================================

// To use a profile, uncomment one of these:
// $config = applyConservativeProfile();
// $config = applyBalancedProfile(); // Default
// $config = applyAggressiveProfile();

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Get current configuration as array
 */
function getCurrentConfig() {
    return [
        'workers_per_server' => WORKERS_PER_SERVER,
        'email_delay_ms' => EMAIL_SEND_DELAY_US / 1000,
        'emails_per_sec_per_worker' => round(1000000 / EMAIL_SEND_DELAY_US, 1),
        'smtp_timeout' => SMTP_TIMEOUT_SECONDS,
        'retry_delay' => RETRY_DELAY_SECONDS,
        'max_retries' => MAX_RETRY_ATTEMPTS,
        'max_concurrent_campaigns' => MAX_CONCURRENT_CAMPAIGNS,
        'lock_timeout' => LOCK_WAIT_TIMEOUT
    ];
}

/**
 * Estimate throughput for given number of servers
 */
function estimateThroughput($num_servers = 1) {
    $emails_per_sec_per_worker = 1000000 / EMAIL_SEND_DELAY_US;
    $total_workers = $num_servers * WORKERS_PER_SERVER;
    $total_emails_per_sec = $total_workers * $emails_per_sec_per_worker;
    
    return [
        'workers' => $total_workers,
        'emails_per_second' => round($total_emails_per_sec, 1),
        'emails_per_minute' => round($total_emails_per_sec * 60, 0),
        'emails_per_hour' => round($total_emails_per_sec * 3600, 0)
    ];
}

/**
 * Display configuration info
 */
function displayConfigInfo() {
    echo "========================================\n";
    echo "Campaign System Configuration\n";
    echo "========================================\n\n";
    
    $config = getCurrentConfig();
    echo "Workers per server: {$config['workers_per_server']}\n";
    echo "Email delay: {$config['email_delay_ms']}ms\n";
    echo "Speed per worker: {$config['emails_per_sec_per_worker']} emails/sec\n";
    echo "SMTP timeout: {$config['smtp_timeout']}s\n";
    echo "Max retries: {$config['max_retries']}\n";
    echo "Max concurrent campaigns: {$config['max_concurrent_campaigns']}\n\n";
    
    echo "Estimated Throughput:\n";
    echo "--------------------\n";
    foreach ([1, 2, 5, 10] as $servers) {
        $est = estimateThroughput($servers);
        echo "$servers server(s): {$est['workers']} workers = {$est['emails_per_second']} emails/sec";
        echo " ({$est['emails_per_minute']}/min, {$est['emails_per_hour']}/hour)\n";
    }
    echo "\n";
}

// If run directly, display config info
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    displayConfigInfo();
}
