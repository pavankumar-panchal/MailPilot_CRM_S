<?php
/**
 * Campaign Cache Manager
 * 
 * Intelligent caching system for campaign data to reduce database load.
 * Uses memory caching with TTL and change detection to minimize DB queries.
 */

class CampaignCache {
    private static $memoryCache = [];
    private static $cacheTimestamps = [];
    private static $changeHashes = [];
    
    // Cache TTLs (in seconds) - OPTIMIZED for high volume (1 lakh+ emails)
    const TTL_CAMPAIGN_LIST = 10;       // 10 seconds for campaign list (reduced API calls)
    const TTL_CAMPAIGN_STATUS = 5;      // 5 seconds for status updates
    const TTL_EMAIL_COUNTS = 5;         // 5 seconds for email counts
    const TTL_RUNNING_CHECK = 5;        // 5 seconds for running campaign check
    const TTL_AGGREGATES = 15;          // 15 seconds for pre-aggregated counts (expensive queries)
    
    /**
     * Get cached value if valid
     */
    public static function get($key, $ttl = null) {
        if (!isset(self::$memoryCache[$key]) || !isset(self::$cacheTimestamps[$key])) {
            return null;
        }
        
        $age = time() - self::$cacheTimestamps[$key];
        if ($ttl !== null && $age >= $ttl) {
            // Expired
            unset(self::$memoryCache[$key]);
            unset(self::$cacheTimestamps[$key]);
            return null;
        }
        
        return self::$memoryCache[$key];
    }
    
    /**
     * Set cache value
     */
    public static function set($key, $value, $changeHash = null) {
        self::$memoryCache[$key] = $value;
        self::$cacheTimestamps[$key] = time();
        if ($changeHash !== null) {
            self::$changeHashes[$key] = $changeHash;
        }
    }
    
    /**
     * Check if data has changed (for incremental updates)
     */
    public static function hasChanged($key, $newHash) {
        if (!isset(self::$changeHashes[$key])) {
            return true;
        }
        return self::$changeHashes[$key] !== $newHash;
    }
    
    /**
     * Invalidate specific cache key
     */
    public static function invalidate($key) {
        unset(self::$memoryCache[$key]);
        unset(self::$cacheTimestamps[$key]);
        unset(self::$changeHashes[$key]);
    }
    
    /**
     * Invalidate all campaign-related cache
     */
    public static function invalidateCampaign($campaignId) {
        $patterns = [
            "campaign_list",
            "campaign_status_{$campaignId}",
            "email_counts_{$campaignId}",
            "running_campaigns",
            "aggregates"
        ];
        
        foreach ($patterns as $pattern) {
            self::invalidate($pattern);
        }
    }
    
    /**
     * Clear all cache
     */
    public static function clear() {
        self::$memoryCache = [];
        self::$cacheTimestamps = [];
        self::$changeHashes = [];
    }
    
    /**
     * Get cache statistics
     */
    public static function getStats() {
        return [
            'entries' => count(self::$memoryCache),
            'memory_bytes' => strlen(serialize(self::$memoryCache)),
            'oldest_entry' => !empty(self::$cacheTimestamps) ? (time() - min(self::$cacheTimestamps)) : 0
        ];
    }
}

/**
 * Campaign Data Aggregator
 * 
 * Pre-computes expensive aggregates and caches them
 */
class CampaignAggregator {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Get all aggregated counts in one query (ULTRA-OPTIMIZED for lakhs of emails)
     * Uses campaign_status table instead of counting mail_blaster (much faster)
     */
    public function getAggregatedCounts($userId = null, $isAdmin = false) {
        // Check cache first
        $cacheKey = "aggregates_" . ($userId ?? 'admin');
        $cached = CampaignCache::get($cacheKey, CampaignCache::TTL_AGGREGATES);
        if ($cached !== null) {
            return $cached;
        }
        
        $userFilterCm = $isAdmin ? "" : "AND cm.user_id = $userId";
        
        // ULTRA-FAST: Read from campaign_status instead of counting mail_blaster
        // Workers update campaign_status in batches, so it's always current
        $query = "
            SELECT 
                cs.campaign_id,
                cs.sent_emails as sent_count,
                cs.failed_emails as failed_count,
                0 as retryable_count,
                cs.pending_emails as pending_count
            FROM campaign_status cs
            INNER JOIN campaign_master cm ON cs.campaign_id = cm.campaign_id
            WHERE 1=1 $userFilterCm
        ";
        
        $result = $this->conn->query($query);
        $aggregates = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $aggregates[$row['campaign_id']] = [
                    'sent' => (int)$row['sent_count'],
                    'failed' => (int)$row['failed_count'],
                    'retryable' => (int)$row['retryable_count'],
                    'pending' => (int)$row['pending_count']
                ];
            }
        }
        
        // Cache the result
        CampaignCache::set($cacheKey, $aggregates);
        
        return $aggregates;
    }
    
    /**
     * Get CSV list valid counts (cached)
     */
    public function getCsvListCounts($userId = null, $isAdmin = false) {
        $cacheKey = "csv_counts_" . ($userId ?? 'admin');
        $cached = CampaignCache::get($cacheKey, CampaignCache::TTL_AGGREGATES);
        if ($cached !== null) {
            return $cached;
        }
        
        // Check if validation_status column exists
        $hasValidationStatus = $this->conn->query("SHOW COLUMNS FROM emails LIKE 'validation_status'");
        $validationFilter = ($hasValidationStatus && $hasValidationStatus->num_rows > 0) ? "AND validation_status = 'valid'" : "";
        
        $userFilter = $isAdmin ? "" : "AND user_id = $userId";
        
        $query = "
            SELECT csv_list_id, COUNT(*) as cnt 
            FROM emails 
            WHERE domain_status = 1 
            $validationFilter 
            AND csv_list_id IS NOT NULL
            $userFilter
            GROUP BY csv_list_id
        ";
        
        $result = $this->conn->query($query);
        $counts = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $counts[$row['csv_list_id']] = (int)$row['cnt'];
            }
        }
        
        CampaignCache::set($cacheKey, $counts);
        return $counts;
    }
    
    /**
     * Get import batch counts (cached) - filtered by user
     * ✅ FIXED: Now filters by user_id for Excel imports
     */
    public function getImportBatchCounts($userId = null, $isAdmin = false) {
        $cacheKey = "import_batch_counts_" . ($userId ?? 'admin');
        $cached = CampaignCache::get($cacheKey, CampaignCache::TTL_AGGREGATES);
        if ($cached !== null) {
            return $cached;
        }
        
        // Apply user filter for Excel imports
        $userFilter = $isAdmin ? "" : "AND user_id = $userId";
        
        $query = "
            SELECT import_batch_id, COUNT(*) as cnt 
            FROM imported_recipients 
            WHERE is_active = 1 
            AND Emails IS NOT NULL 
            AND Emails <> ''
            $userFilter
            GROUP BY import_batch_id
        ";
        
        $result = $this->conn->query($query);
        $counts = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $counts[$row['import_batch_id']] = (int)$row['cnt'];
            }
        }
        
        CampaignCache::set($cacheKey, $counts);
        return $counts;
    }
    
    /**
     * Get running campaigns only (fast check)
     */
    public function getRunningCampaignIds($userId = null, $isAdmin = false) {
        $cacheKey = "running_campaigns_" . ($userId ?? 'admin');
        $cached = CampaignCache::get($cacheKey, CampaignCache::TTL_RUNNING_CHECK);
        if ($cached !== null) {
            return $cached;
        }
        
        $userFilter = $isAdmin ? "" : "AND cm.user_id = $userId";
        
        $query = "
            SELECT cs.campaign_id
            FROM campaign_status cs
            INNER JOIN campaign_master cm ON cs.campaign_id = cm.campaign_id
            WHERE cs.status = 'running'
            $userFilter
        ";
        
        $result = $this->conn->query($query);
        $runningIds = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $runningIds[] = (int)$row['campaign_id'];
            }
        }
        
        CampaignCache::set($cacheKey, $runningIds);
        return $runningIds;
    }
}

/**
 * Incremental Counter Manager
 * 
 * Maintains in-memory counters to avoid COUNT queries
 */
class IncrementalCounter {
    private static $counters = [];
    
    /**
     * Initialize counter from database
     */
    public static function init($key, $value) {
        self::$counters[$key] = (int)$value;
    }
    
    /**
     * Increment counter
     */
    public static function increment($key, $amount = 1) {
        if (!isset(self::$counters[$key])) {
            self::$counters[$key] = 0;
        }
        self::$counters[$key] += $amount;
    }
    
    /**
     * Decrement counter
     */
    public static function decrement($key, $amount = 1) {
        if (!isset(self::$counters[$key])) {
            self::$counters[$key] = 0;
        }
        self::$counters[$key] = max(0, self::$counters[$key] - $amount);
    }
    
    /**
     * Get counter value
     */
    public static function get($key) {
        return self::$counters[$key] ?? 0;
    }
    
    /**
     * Reset counter
     */
    public static function reset($key) {
        self::$counters[$key] = 0;
    }
}
