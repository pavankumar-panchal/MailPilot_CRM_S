<?php
/**
 * Connection Pool Manager
 * 
 * Prevents connection exhaustion by limiting concurrent connections
 * and reusing existing connections when possible.
 * 
 * CRITICAL: This prevents campaign workers from blocking frontend APIs
 */

class ConnectionPool {
    private static $instance = null;
    private static $activeConnections = 0;
    private static $maxConnections = 20; // Max concurrent connections per database
    private static $connectionQueue = [];
    private static $waitTimeout = 5; // Max seconds to wait for connection
    
    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Acquire connection or wait if pool is full
     * 
     * @return bool True if connection acquired, false if timeout
     */
    public static function acquire($connectionType = 'default') {
        $startTime = time();
        
        while (self::$activeConnections >= self::$maxConnections) {
            // Pool is full - wait a bit
            usleep(100000); // 100ms
            
            if (time() - $startTime > self::$waitTimeout) {
                // Timeout waiting for connection slot
                error_log("CONNECTION POOL TIMEOUT: {$connectionType} - active: " . self::$activeConnections);
                return false;
            }
        }
        
        self::$activeConnections++;
        error_log("CONNECTION ACQUIRED: {$connectionType} - active: " . self::$activeConnections . "/" . self::$maxConnections);
        return true;
    }
    
    /**
     * Release connection back to pool
     */
    public static function release($connectionType = 'default') {
        if (self::$activeConnections > 0) {
            self::$activeConnections--;
            error_log("CONNECTION RELEASED: {$connectionType} - active: " . self::$activeConnections . "/" . self::$maxConnections);
        }
    }
    
    /**
     * Get pool statistics
     */
    public static function getStats() {
        return [
            'active' => self::$activeConnections,
            'max' => self::$maxConnections,
            'available' => self::$maxConnections - self::$activeConnections,
            'utilization' => round((self::$activeConnections / self::$maxConnections) * 100, 2) . '%'
        ];
    }
    
    /**
     * Set max connections (for tuning)
     */
    public static function setMaxConnections($max) {
        self::$maxConnections = max(5, min(100, $max)); // Between 5 and 100
    }
    
    /**
     * Check connection health and close stale connections
     * 
     * @param mysqli $conn Connection to check
     * @return bool True if healthy, false if needs reconnection
     */
    public static function checkHealth($conn) {
        if (!$conn || !($conn instanceof mysqli)) {
            return false;
        }
        
        // Ping to check if connection is alive
        if (!@$conn->ping()) {
            error_log("CONNECTION HEALTH CHECK FAILED: Connection lost");
            return false;
        }
        
        // Check for thread errors
        if ($conn->errno) {
            error_log("CONNECTION HEALTH CHECK FAILED: Error " . $conn->errno . " - " . $conn->error);
            return false;
        }
        
        return true;
    }
    
    /**
     * Get a reusable connection with health check
     * 
     * @param mysqli $conn Existing connection
     * @return mysqli|null Healthy connection or null if needs recreation
     */
    public static function reuseOrRecreate($conn) {
        if (self::checkHealth($conn)) {
            return $conn; // Connection is healthy
        }
        
        // Connection is stale - close it
        if ($conn) {
            @$conn->close();
        }
        
        return null; // Caller should create new connection
    }
}

/**
 * Lightweight Connection Manager for Campaign Workers
 * 
 * Optimized for background processes that need to minimize
 * database load while maintaining connection efficiency
 */
class CampaignConnectionManager {
    private static $conn_light = null;  // Lightweight connection for stats updates
    private static $conn_heavy = null;  // Heavy connection for main queries
    private static $lastPingTime = 0;
    private static $pingInterval = 30;  // Ping every 30 seconds
    
    /**
     * Get lightweight connection for quick stats updates
     * Reuses existing connection to minimize overhead
     */
    public static function getLightConnection() {
        // Check if existing connection is healthy
        if (self::$conn_light !== null) {
            if (ConnectionPool::checkHealth(self::$conn_light)) {
                return self::$conn_light;
            }
            // Connection is dead - close it
            @self::$conn_light->close();
            self::$conn_light = null;
        }
        
        // Create new lightweight connection
        if (!ConnectionPool::acquire('campaign_light')) {
            throw new Exception("Connection pool exhausted - cannot acquire light connection");
        }
        
        require_once __DIR__ . '/../config/db.php';
        self::$conn_light = $GLOBALS['conn'];
        
        return self::$conn_light;
    }
    
    /**
     * Get heavy connection for complex queries
     * Uses separate connection to avoid blocking lightweight queries
     */
    public static function getHeavyConnection() {
        // Check if existing connection is healthy
        if (self::$conn_heavy !== null) {
            // Periodic ping to keep connection alive
            if (time() - self::$lastPingTime > self::$pingInterval) {
                if (@self::$conn_heavy->ping()) {
                    self::$lastPingTime = time();
                    return self::$conn_heavy;
                }
                // Ping failed - connection is dead
                @self::$conn_heavy->close();
                self::$conn_heavy = null;
            } else {
                // Recent ping, assume healthy
                return self::$conn_heavy;
            }
        }
        
        // Create new heavy connection
        if (!ConnectionPool::acquire('campaign_heavy')) {
            throw new Exception("Connection pool exhausted - cannot acquire heavy connection");
        }
        
        require_once __DIR__ . '/../config/db_campaign.php';
        self::$conn_heavy = $GLOBALS['conn_heavy'];
        self::$lastPingTime = time();
        
        return self::$conn_heavy;
    }
    
    /**
     * Execute query with automatic retry on connection failure
     * 
     * @param string $query SQL query
     * @param string $type 'light' or 'heavy'
     * @param int $maxRetries Maximum retry attempts
     * @return mysqli_result|bool Query result
     */
    public static function executeWithRetry($query, $type = 'light', $maxRetries = 2) {
        $attempts = 0;
        $lastError = null;
        
        while ($attempts < $maxRetries) {
            $attempts++;
            
            try {
                $conn = ($type === 'heavy') ? self::getHeavyConnection() : self::getLightConnection();
                
                // Execute query
                $result = @$conn->query($query);
                
                if ($result !== false) {
                    return $result; // Success
                }
                
                // Query failed - check error
                $lastError = $conn->error;
                $errorCode = $conn->errno;
                
                // If connection error, invalidate and retry
                if ($errorCode == 2006 || $errorCode == 2013) {
                    // MySQL server has gone away / Lost connection
                    error_log("CAMPAIGN DB: Connection lost (error $errorCode), retrying... (attempt $attempts/$maxRetries)");
                    
                    if ($type === 'heavy') {
                        @self::$conn_heavy->close();
                        self::$conn_heavy = null;
                        ConnectionPool::release('campaign_heavy');
                    } else {
                        @self::$conn_light->close();
                        self::$conn_light = null;
                        ConnectionPool::release('campaign_light');
                    }
                    
                    usleep(500000); // Wait 0.5 seconds before retry
                    continue;
                }
                
                // Other error - log and return false
                error_log("CAMPAIGN DB: Query failed - $lastError");
                return false;
                
            } catch (Exception $e) {
                $lastError = $e->getMessage();
                error_log("CAMPAIGN DB: Exception - $lastError");
                
                if ($attempts < $maxRetries) {
                    usleep(500000); // Wait before retry
                    continue;
                }
            }
        }
        
        // All retries failed
        error_log("CAMPAIGN DB: All retries failed - $lastError");
        return false;
    }
    
    /**
     * Close all connections and release pool slots
     */
    public static function closeAll() {
        if (self::$conn_light !== null) {
            @self::$conn_light->close();
            self::$conn_light = null;
            ConnectionPool::release('campaign_light');
        }
        
        if (self::$conn_heavy !== null) {
            @self::$conn_heavy->close();
            self::$conn_heavy = null;
            ConnectionPool::release('campaign_heavy');
        }
    }
    
    /**
     * Register shutdown handler to ensure connections are released
     */
    public static function registerShutdownHandler() {
        register_shutdown_function([__CLASS__, 'closeAll']);
    }
}
