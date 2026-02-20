<?php
/**
 * Resource Manager - Prevent Campaign Processes from Affecting Other Applications
 * 
 * This module ensures campaign processing doesn't freeze the frontend or affect
 * other applications on the same server by:
 * 
 * 1. Setting low CPU priority (nice value)
 * 2. Setting low I/O priority (ionice)
 * 3. Limiting memory usage
 * 4. Using non-blocking database locks with timeouts
 * 5. Managing connection pools properly
 */

class ResourceManager {
    private static $initialized = false;
    private static $isCampaignProcess = false;
    
    /**
     * Initialize resource limits for campaign background processes
     * Call this at the start of any campaign-related script
     * 
     * @param string $processType 'cron'|'worker'|'orchestrator'
     */
    public static function initCampaignProcess($processType = 'worker') {
        if (self::$initialized) {
            return;
        }
        
        self::$isCampaignProcess = true;
        self::$initialized = true;
        
        // 1. Set LOW CPU Priority to prevent affecting web server and other apps
        // nice value 19 = lowest priority (won't compete with web server)
        if (function_exists('proc_nice')) {
            @proc_nice(19); // Lowest priority - won't affect web server or other apps
        }
        
        // 2. Set LOW I/O Priority (ionice - best effort, low priority)
        // Only on Linux systems with ionice available
        if (php_sapi_name() === 'cli' && stripos(PHP_OS, 'linux') !== false) {
            $pid = getmypid();
            // Class 2 = best-effort, Level 7 = lowest priority (0=highest, 7=lowest)
            @exec("ionice -c 2 -n 7 -p $pid 2>/dev/null");
        }
        
        // 3. Set Reasonable Memory Limits based on process type
        switch ($processType) {
            case 'cron':
                ini_set('memory_limit', '256M'); // Cron monitor is lightweight
                set_time_limit(120); // 2 minute max
                break;
            case 'orchestrator':
                ini_set('memory_limit', '512M'); // Orchestrator manages workers
                set_time_limit(86400); // 24 hours max (was 1 hour)
                break;
            case 'worker':
                ini_set('memory_limit', '384M'); // Workers send emails
                set_time_limit(1800); // 30 minutes max
                break;
            default:
                ini_set('memory_limit', '256M');
                set_time_limit(600);
        }
        
        // 4. Optimize PHP settings for background processing
        ini_set('max_execution_time', ini_get('max_execution_time')); // Use current limit
        ini_set('default_socket_timeout', 30); // 30 second socket timeout
        
        // 5. Disable output buffering for background processes (saves memory)
        if (php_sapi_name() === 'cli') {
            @ini_set('output_buffering', 'Off');
            @ini_set('implicit_flush', 'On');
        }
    }
    
    /**
     * Get a database connection with optimized settings for campaign processes
     * Prevents connection exhaustion and reduces lock contention
     * 
     * @param array $dbConfig Database configuration
     * @return mysqli|null
     */
    public static function getDatabaseConnection($dbConfig) {
        // Use persistent connections for background processes to reduce overhead
        // But add a unique identifier to prevent connection reuse issues
        $host = $dbConfig['host'];
        if (self::$isCampaignProcess && php_sapi_name() === 'cli') {
            // Persistent connection - reuse existing connection if available
            $host = 'p:' . $host; 
        }
        
        $conn = new mysqli(
            $host,
            $dbConfig['username'],
            $dbConfig['password'],
            $dbConfig['name'],
            $dbConfig['port']
        );
        
        if ($conn->connect_error) {
            error_log("Database connection failed: " . $conn->connect_error);
            return null;
        }
        
        // Optimize connection for campaign processes
        if (self::$isCampaignProcess) {
            // Set low priority for queries from campaign processes
            // This ensures web server queries get priority
            @$conn->query("SET SESSION sql_low_priority_updates = 1");
            
            // Reduce lock wait timeout to prevent long blocks (default is 50 seconds)
            // Campaign processes should fail fast rather than blocking web queries
            // INCREASED to 10 seconds to handle high concurrency without cascading failures
            @$conn->query("SET SESSION innodb_lock_wait_timeout = 10"); // 10 seconds (was 5)
            
            // Set transaction isolation to reduce lock contention
            // FIX: Backward compatibility for older MySQL/MariaDB versions
            // PHP 8.1+ throws exceptions, so use try-catch
            try {
                $conn->query("SET SESSION transaction_isolation = 'READ-COMMITTED'");
            } catch (mysqli_sql_exception $e) {
                // Fallback for older MySQL/MariaDB versions (pre-5.7.20)
                try {
                    $conn->query("SET SESSION tx_isolation = 'READ-COMMITTED'");
                } catch (mysqli_sql_exception $e2) {
                    // Ignore if both fail - not critical
                }
            }
            
            // Optimize for bulk operations
            @$conn->query("SET SESSION sql_buffer_result = 1");
        }
        
        return $conn;
    }
    
    /**
     * Execute a query with automatic retry on deadlock
     * Prevents transaction failures from affecting other processes
     * 
     * @param mysqli $conn Database connection
     * @param string $query SQL query
     * @param int $maxRetries Maximum retry attempts
     * @return mysqli_result|bool|null
     */
    public static function queryWithRetry($conn, $query, $maxRetries = 3) {
        $attempt = 0;
        $lastError = null;
        
        while ($attempt < $maxRetries) {
            $result = $conn->query($query);
            
            if ($result !== false) {
                return $result;
            }
            
            $errno = $conn->errno;
            $lastError = $conn->error;
            
            // Retry on deadlock (1213) or lock wait timeout (1205)
            if ($errno === 1213 || $errno === 1205) {
                $attempt++;
                if ($attempt < $maxRetries) {
                    // Exponential backoff: 100ms, 200ms, 400ms
                    usleep(100000 * pow(2, $attempt - 1));
                    continue;
                }
            }
            
            // For other errors, don't retry
            break;
        }
        
        error_log("Query failed after $attempt attempts: $lastError");
        return false;
    }
    
    /**
     * Execute a transaction with FOR UPDATE lock and timeout protection
     * 
     * @param mysqli $conn Database connection
     * @param callable $callback Transaction callback function
     * @param int $lockTimeout Lock timeout in seconds (default 3)
     * @return mixed Result from callback or false on failure
     */
    public static function executeWithLock($conn, $callback, $lockTimeout = 3) {
        // Save original lock wait timeout
        $originalTimeout = null;
        $result = $conn->query("SELECT @@innodb_lock_wait_timeout as timeout");
        if ($result) {
            $row = $result->fetch_assoc();
            $originalTimeout = $row['timeout'];
        }
        
        // Set shorter timeout for this transaction
        $conn->query("SET SESSION innodb_lock_wait_timeout = $lockTimeout");
        
        try {
            // Start transaction
            $conn->begin_transaction();
            
            // Execute callback
            $callbackResult = call_user_func($callback, $conn);
            
            // Commit if callback succeeded
            if ($callbackResult !== false) {
                $conn->commit();
                // Restore original timeout
                if ($originalTimeout !== null) {
                    @$conn->query("SET SESSION innodb_lock_wait_timeout = $originalTimeout");
                }
                return $callbackResult;
            } else {
                $conn->rollback();
                // Restore original timeout
                if ($originalTimeout !== null) {
                    @$conn->query("SET SESSION innodb_lock_wait_timeout = $originalTimeout");
                }
                return false;
            }
        } catch (Exception $e) {
            if ($conn) {
                @$conn->rollback();
            }
            error_log("Transaction failed: " . $e->getMessage());
            // Restore original timeout
            if ($originalTimeout !== null) {
                @$conn->query("SET SESSION innodb_lock_wait_timeout = $originalTimeout");
            }
            return false;
        }
    }
    
    /**
     * Sleep with CPU-friendly microsleep intervals
     * Better for system resources than long sleep()
     * Also yields CPU to web processes
     * 
     * @param int $seconds Seconds to sleep
     */
    public static function cpuFriendlySleep($seconds) {
        if ($seconds <= 0) {
            return;
        }
        
        // Yield CPU to other processes before sleeping
        if (function_exists('usleep')) {
            usleep(100); // 100 microseconds yield
        }
        
        // For very short sleeps, use usleep directly
        if ($seconds < 1) {
            usleep($seconds * 1000000);
            return;
        }
        
        // For longer sleeps, break into chunks to allow signal handling
        // and yield CPU more frequently
        $remaining = $seconds;
        while ($remaining > 0) {
            $chunk = min($remaining, 0.5); // 500ms chunks for better responsiveness
            usleep($chunk * 1000000);
            $remaining -= $chunk;
            
            // Allow garbage collection between chunks
            if (function_exists('gc_collect_cycles')) {
                @gc_collect_cycles();
            }
            
            // Additional yield for web processes
            usleep(1000); // 1ms yield between chunks
        }
    }
    
    /**
     * Check if system resources are under pressure
     * Returns true if we should throttle processing
     * 
     * @return bool
     */
    public static function shouldThrottle() {
        // Check system load average (Linux only)
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            // If 1-minute load average > 80% of CPU cores, throttle
            $cpuCount = self::getCpuCount();
            if ($load[0] > ($cpuCount * 0.8)) {
                return true;
            }
        }
        
        // Check memory usage
        $memUsage = memory_get_usage(true);
        $memLimit = self::getMemoryLimit();
        if ($memLimit > 0 && $memUsage > ($memLimit * 0.8)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get number of CPU cores
     * 
     * @return int
     */
    private static function getCpuCount() {
        static $cpuCount = null;
        
        if ($cpuCount !== null) {
            return $cpuCount;
        }
        
        // Try Linux
        if (is_readable('/proc/cpuinfo')) {
            $cpuinfo = file_get_contents('/proc/cpuinfo');
            preg_match_all('/^processor/m', $cpuinfo, $matches);
            $cpuCount = count($matches[0]);
        }
        
        // Fallback
        if (!$cpuCount) {
            $cpuCount = 2; // Conservative default
        }
        
        return $cpuCount;
    }
    
    /**
     * Get memory limit in bytes
     * 
     * @return int
     */
    private static function getMemoryLimit() {
        $memLimit = ini_get('memory_limit');
        if ($memLimit == -1) {
            return -1; // Unlimited
        }
        
        $memLimit = trim($memLimit);
        $last = strtolower($memLimit[strlen($memLimit)-1]);
        $memLimit = (int)$memLimit;
        
        switch($last) {
            case 'g':
                $memLimit *= 1024;
            case 'm':
                $memLimit *= 1024;
            case 'k':
                $memLimit *= 1024;
        }
        
        return $memLimit;
    }
    
    /**
     * Log resource usage statistics
     * 
     * @param string $label Label for the log entry
     */
    public static function logResourceUsage($label = '') {
        if (!self::$isCampaignProcess) {
            return;
        }
        
        $mem = memory_get_usage(true) / 1024 / 1024; // MB
        $peak = memory_get_peak_usage(true) / 1024 / 1024; // MB
        
        $msg = sprintf(
            "[ResourceManager] %s - Memory: %.2fMB (Peak: %.2fMB)",
            $label,
            $mem,
            $peak
        );
        
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            $msg .= sprintf(" - Load: %.2f", $load[0]);
        }
        
        error_log($msg);
    }
}
