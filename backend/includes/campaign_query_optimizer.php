<?php
/**
 * ============================================================================
 * CAMPAIGN QUERY OPTIMIZER
 * ============================================================================
 * Lightweight query execution for campaign processes
 * Prevents campaign operations from affecting other server APIs
 * ============================================================================
 */

class CampaignQueryOptimizer {
    private static $queryCache = [];
    private static $cacheExpiry = [];
    private static $queryCount = 0;
    
    /**
     * Execute a SELECT query with optimization
     * - Uses query result limiting
     * - Implements short timeouts
     * - Caches results when appropriate
     * - Uses low-priority reads
     */
    public static function selectWithOptimization($conn, $query, $cacheKey = null, $cacheTTL = 0) {
        self::$queryCount++;
        
        // Check cache first (for frequently accessed data)
        if ($cacheKey && isset(self::$queryCache[$cacheKey])) {
            if (time() < self::$cacheExpiry[$cacheKey]) {
                return self::$queryCache[$cacheKey];
            } else {
                unset(self::$queryCache[$cacheKey]);
                unset(self::$cacheExpiry[$cacheKey]);
            }
        }
        
        // Set short query timeout to prevent blocking
        @$conn->query("SET SESSION MAX_EXECUTION_TIME = 5000"); // 5 seconds max
        
        // Execute query
        $result = @$conn->query($query);
        
        // Reset timeout
        @$conn->query("SET SESSION MAX_EXECUTION_TIME = 0");
        
        if (!$result) {
            return false;
        }
        
        // Store in cache if requested
        if ($cacheKey && $cacheTTL > 0 && $result->num_rows > 0) {
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            self::$queryCache[$cacheKey] = $data;
            self::$cacheExpiry[$cacheKey] = time() + $cacheTTL;
            
            // Return cached array
            return $data;
        }
        
        return $result;
    }
    
    /**
     * Execute UPDATE/INSERT with low priority
     * This ensures web server queries get priority
     */
    public static function executeWithLowPriority($conn, $query) {
        self::$queryCount++;
        
        // Set low priority for this session
        @$conn->query("SET SESSION sql_low_priority_updates = 1");
        
        // Set short lock timeout to fail fast instead of blocking
        @$conn->query("SET SESSION innodb_lock_wait_timeout = 3");
        
        // Execute query
        $result = @$conn->query($query);
        
        return $result;
    }
    
    /**
     * Get campaign status with caching (reduces Server 1 queries)
     */
    public static function getCampaignStatus($conn, $campaign_id, $useCache = true) {
        if ($useCache) {
            $cached = self::selectWithOptimization(
                $conn,
                "SELECT status, total_emails, sent_emails, failed_emails, pending_emails 
                 FROM campaign_status 
                 WHERE campaign_id = $campaign_id 
                 LIMIT 1",
                "campaign_status_$campaign_id",
                5 // Cache for 5 seconds
            );
            
            if (is_array($cached)) {
                return isset($cached[0]) ? $cached[0] : null;
            }
        }
        
        // Fallback: direct query
        $result = $conn->query("SELECT status, total_emails, sent_emails, failed_emails, pending_emails 
                                FROM campaign_status 
                                WHERE campaign_id = $campaign_id 
                                LIMIT 1");
        return $result ? $result->fetch_assoc() : null;
    }
    
    /**
     * Update campaign stats incrementally (batch updates)
     * Only updates when threshold is reached
     */
    private static $pendingUpdates = [];
    private static $updateThreshold = 100; // Update every 100 emails
    
    public static function queueStatsUpdate($campaign_id, $sent = 0, $failed = 0) {
        if (!isset(self::$pendingUpdates[$campaign_id])) {
            self::$pendingUpdates[$campaign_id] = ['sent' => 0, 'failed' => 0];
        }
        
        self::$pendingUpdates[$campaign_id]['sent'] += $sent;
        self::$pendingUpdates[$campaign_id]['failed'] += $failed;
        
        // Return whether to flush (threshold reached)
        $total = self::$pendingUpdates[$campaign_id]['sent'] + self::$pendingUpdates[$campaign_id]['failed'];
        return $total >= self::$updateThreshold;
    }
    
    public static function flushStatsUpdates($conn) {
        foreach (self::$pendingUpdates as $campaign_id => $stats) {
            if ($stats['sent'] > 0 || $stats['failed'] > 0) {
                self::executeWithLowPriority(
                    $conn,
                    "UPDATE campaign_status 
                     SET sent_emails = sent_emails + {$stats['sent']},
                         failed_emails = failed_emails + {$stats['failed']},
                         pending_emails = pending_emails - " . ($stats['sent'] + $stats['failed']) . "
                     WHERE campaign_id = $campaign_id"
                );
                
                // Clear cache for this campaign
                self::clearCache("campaign_status_$campaign_id");
            }
        }
        
        self::$pendingUpdates = [];
    }
    
    /**
     * Execute query with automatic retry on deadlock
     */
    public static function queryWithRetry($conn, $query, $maxRetries = 3) {
        $attempt = 0;
        
        while ($attempt < $maxRetries) {
            $result = @$conn->query($query);
            
            if ($result !== false) {
                return $result;
            }
            
            // Check if it's a deadlock or lock timeout
            $errno = $conn->errno;
            if ($errno === 1213 || $errno === 1205) {
                $attempt++;
                if ($attempt < $maxRetries) {
                    // Exponential backoff: 50ms, 100ms, 200ms
                    usleep(50000 * pow(2, $attempt - 1));
                    continue;
                }
            }
            
            break;
        }
        
        return false;
    }
    
    /**
     * Clear specific cache entry
     */
    public static function clearCache($key) {
        unset(self::$queryCache[$key]);
        unset(self::$cacheExpiry[$key]);
    }
    
    /**
     * Clear all cache
     */
    public static function clearAllCache() {
        self::$queryCache = [];
        self::$cacheExpiry = [];
    }
    
    /**
     * Get query statistics
     */
    public static function getStats() {
        return [
            'total_queries' => self::$queryCount,
            'cached_entries' => count(self::$queryCache),
            'pending_updates' => count(self::$pendingUpdates)
        ];
    }
    
    /**
     * Optimize SELECT query by adding LIMIT if missing
     */
    public static function ensureLimit($query, $defaultLimit = 1000) {
        if (stripos($query, 'LIMIT') === false && stripos($query, 'SELECT') === 0) {
            $query = rtrim($query, ';') . " LIMIT $defaultLimit";
        }
        return $query;
    }
    
    /**
     * Check if query is safe (prevents full table scans)
     */
    public static function isSafeQuery($query) {
        // Check for WHERE clause in UPDATE/DELETE
        if (preg_match('/^(UPDATE|DELETE)/i', $query) && stripos($query, 'WHERE') === false) {
            return false;
        }
        
        // Check for LIMIT in large SELECTs
        if (preg_match('/^SELECT/i', $query) && stripos($query, 'LIMIT') === false) {
            // Allow only if it has specific WHERE conditions
            if (stripos($query, 'WHERE') === false) {
                return false;
            }
        }
        
        return true;
    }
}

/**
 * ============================================================================
 * LIGHTWEIGHT CONNECTION MANAGER FOR CAMPAIGNS
 * ============================================================================
 */
class LightweightConnectionManager {
    private static $connections = [];
    private static $lastPing = [];
    
    /**
     * Get optimized connection for campaign process
     */
    public static function getOptimizedConnection($dbConfig, $connectionName = 'default') {
        // Reuse existing connection if valid
        if (isset(self::$connections[$connectionName])) {
            // Ping connection every 30 seconds
            if (!isset(self::$lastPing[$connectionName]) || 
                (time() - self::$lastPing[$connectionName]) > 30) {
                
                if (self::$connections[$connectionName]->ping()) {
                    self::$lastPing[$connectionName] = time();
                    return self::$connections[$connectionName];
                } else {
                    // Connection dead, remove it
                    unset(self::$connections[$connectionName]);
                    unset(self::$lastPing[$connectionName]);
                }
            } else {
                return self::$connections[$connectionName];
            }
        }
        
        // Create new optimized connection
        $conn = new mysqli(
            $dbConfig['host'],
            $dbConfig['username'],
            $dbConfig['password'],
            $dbConfig['name'],
            $dbConfig['port'] ?? 3306
        );
        
        if ($conn->connect_error) {
            error_log("Database connection failed: " . $conn->connect_error);
            return null;
        }
        
        // Campaign-specific optimizations
        @$conn->query("SET SESSION sql_low_priority_updates = 1");
        @$conn->query("SET SESSION innodb_lock_wait_timeout = 5");
        @$conn->query("SET SESSION transaction_isolation = 'READ-COMMITTED'");
        @$conn->query("SET SESSION sql_buffer_result = 1");
        @$conn->query("SET SESSION net_read_timeout = 30");
        @$conn->query("SET SESSION net_write_timeout = 60");
        
        // Disable query cache for background processes (they don't benefit from it)
        @$conn->query("SET SESSION query_cache_type = OFF");
        
        self::$connections[$connectionName] = $conn;
        self::$lastPing[$connectionName] = time();
        
        return $conn;
    }
    
    /**
     * Close all connections
     */
    public static function closeAll() {
        foreach (self::$connections as $conn) {
            @$conn->close();
        }
        self::$connections = [];
        self::$lastPing = [];
    }
}
