<?php
/**
 * ============================================================================
 * DATABASE CONNECTION POOL FOR HIGH CONCURRENCY
 * ============================================================================
 * Implements connection pooling to handle 1000+ concurrent users
 * Reduces connection overhead and improves performance
 * ============================================================================
 */

class DatabaseConnectionPool
{
    private static ?DatabaseConnectionPool $instance = null;
    private array $pool = [];
    private array $inUse = [];
    private int $maxConnections;
    private int $minConnections;
    private array $config;
    
    // Performance tracking
    private int $totalConnectionsCreated = 0;
    private int $totalConnectionsReused = 0;
    private array $stats = [];
    
    private const MAX_POOL_SIZE = 100;
    private const MIN_POOL_SIZE = 10;
    private const CONNECTION_TIMEOUT = 60; // seconds
    private const MAX_CONNECTION_AGE = 3600; // 1 hour
    private const IDLE_TIMEOUT = 300; // 5 minutes
    
    private function __construct(array $config)
    {
        $this->config = $config;
        $this->maxConnections = $config['max_connections'] ?? self::MAX_POOL_SIZE;
        $this->minConnections = $config['min_connections'] ?? self::MIN_POOL_SIZE;
        
        // Pre-create minimum connections
        for ($i = 0; $i < $this->minConnections; $i++) {
            $this->createConnection();
        }
    }
    
    public static function getInstance(array $config = []): DatabaseConnectionPool
    {
        if (self::$instance === null) {
            if (empty($config)) {
                // Load from default config
                require_once __DIR__ . '/../config/db.php';
                global $servername, $username, $password, $dbname;
                
                $config = [
                    'host' => $servername,
                    'username' => $username,
                    'password' => $password,
                    'database' => $dbname,
                    'charset' => 'utf8mb4',
                    'max_connections' => self::MAX_POOL_SIZE,
                    'min_connections' => self::MIN_POOL_SIZE
                ];
            }
            
            self::$instance = new self($config);
        }
        
        return self::$instance;
    }
    
    /**
     * Get a connection from the pool
     */
    public function getConnection(): PDO
    {
        // Try to get an available connection from pool
        foreach ($this->pool as $id => $connInfo) {
            if (!$this->isConnectionValid($connInfo)) {
                // Remove invalid connection
                unset($this->pool[$id]);
                continue;
            }
            
            // Move to in-use
            $this->inUse[$id] = $connInfo;
            unset($this->pool[$id]);
            
            $this->totalConnectionsReused++;
            $connInfo['last_used'] = time();
            $connInfo['reuse_count']++;
            
            return $connInfo['pdo'];
        }
        
        // No available connections, create new one if under limit
        if ((count($this->pool) + count($this->inUse)) < $this->maxConnections) {
            $connInfo = $this->createConnection();
            $id = $connInfo['id'];
            $this->inUse[$id] = $connInfo;
            
            return $connInfo['pdo'];
        }
        
        // Pool is full, wait for a connection to be released
        $timeout = microtime(true) + self::CONNECTION_TIMEOUT;
        while (microtime(true) < $timeout) {
            usleep(10000); // 10ms
            
            // Check if any connection became available
            foreach ($this->pool as $id => $connInfo) {
                if ($this->isConnectionValid($connInfo)) {
                    $this->inUse[$id] = $connInfo;
                    unset($this->pool[$id]);
                    $this->totalConnectionsReused++;
                    $connInfo['last_used'] = time();
                    return $connInfo['pdo'];
                }
            }
        }
        
        throw new RuntimeException("Connection pool timeout: no available connections");
    }
    
    /**
     * Release a connection back to the pool
     */
    public function releaseConnection(PDO $pdo): void
    {
        // Find the connection in inUse
        foreach ($this->inUse as $id => $connInfo) {
            if ($connInfo['pdo'] === $pdo) {
                // Move back to pool
                $connInfo['last_released'] = time();
                $this->pool[$id] = $connInfo;
                unset($this->inUse[$id]);
                return;
            }
        }
    }
    
    /**
     * Create a new database connection
     */
    private function createConnection(): array
    {
        $dsn = sprintf(
            "mysql:host=%s;dbname=%s;charset=%s",
            $this->config['host'],
            $this->config['database'],
            $this->config['charset'] ?? 'utf8mb4'
        );
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false, // Don't use persistent in pool
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            PDO::MYSQL_ATTR_FOUND_ROWS => true,
            PDO::ATTR_TIMEOUT => 10,
        ];
        
        $pdo = new PDO($dsn, $this->config['username'], $this->config['password'], $options);
        
        // Optimize for high concurrency
        $pdo->exec("SET SESSION sql_mode = ''");
        $pdo->exec("SET SESSION transaction_isolation = 'READ-COMMITTED'");
        $pdo->exec("SET SESSION autocommit = 1");
        $pdo->exec("SET SESSION wait_timeout = 300");
        $pdo->exec("SET SESSION interactive_timeout = 300");
        
        $id = uniqid('conn_', true);
        $this->totalConnectionsCreated++;
        
        return [
            'id' => $id,
            'pdo' => $pdo,
            'created' => time(),
            'last_used' => time(),
            'last_released' => null,
            'reuse_count' => 0
        ];
    }
    
    /**
     * Check if a connection is still valid
     */
    private function isConnectionValid(array $connInfo): bool
    {
        $now = time();
        
        // Check age
        if (($now - $connInfo['created']) > self::MAX_CONNECTION_AGE) {
            return false;
        }
        
        // Check idle time
        if ($connInfo['last_released'] && 
            ($now - $connInfo['last_released']) > self::IDLE_TIMEOUT) {
            return false;
        }
        
        // Ping the connection
        try {
            $connInfo['pdo']->query('SELECT 1');
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Clean up old or idle connections
     */
    public function cleanup(): void
    {
        $removed = 0;
        
        foreach ($this->pool as $id => $connInfo) {
            if (!$this->isConnectionValid($connInfo)) {
                unset($this->pool[$id]);
                $removed++;
            }
        }
        
        // Maintain minimum pool size
        $currentSize = count($this->pool) + count($this->inUse);
        while ($currentSize < $this->minConnections) {
            $this->createConnection();
            $currentSize++;
        }
        
        if ($removed > 0) {
            error_log("[ConnectionPool] Cleaned up $removed stale connections");
        }
    }
    
    /**
     * Get pool statistics
     */
    public function getStats(): array
    {
        return [
            'pool_size' => count($this->pool),
            'in_use' => count($this->inUse),
            'total_created' => $this->totalConnectionsCreated,
            'total_reused' => $this->totalConnectionsReused,
            'max_connections' => $this->maxConnections,
            'min_connections' => $this->minConnections,
            'reuse_ratio' => $this->totalConnectionsCreated > 0 
                ? round($this->totalConnectionsReused / $this->totalConnectionsCreated, 2)
                : 0
        ];
    }
    
    /**
     * Close all connections
     */
    public function closeAll(): void
    {
        foreach ($this->pool as $id => $connInfo) {
            unset($this->pool[$id]);
        }
        
        foreach ($this->inUse as $id => $connInfo) {
            unset($this->inUse[$id]);
        }
        
        error_log("[ConnectionPool] Closed all connections");
    }
    
    public function __destruct()
    {
        $this->closeAll();
    }
}

/**
 * ============================================================================
 * OPTIMIZED DATABASE HELPER CLASS
 * ============================================================================
 */
class OptimizedDB
{
    private static ?PDO $connection = null;
    private static ?DatabaseConnectionPool $pool = null;
    private static bool $usePooling = true;
    
    /**
     * Get database connection (with or without pooling)
     */
    public static function getConnection(bool $usePool = true): PDO
    {
        if (!$usePool || !self::$usePooling) {
            // Direct connection for long-running processes
            if (self::$connection === null) {
                self::$connection = self::createDirectConnection();
            }
            return self::$connection;
        }
        
        // Use connection pool for high concurrency
        if (self::$pool === null) {
            self::$pool = DatabaseConnectionPool::getInstance();
        }
        
        return self::$pool->getConnection();
    }
    
    /**
     * Release connection back to pool
     */
    public static function releaseConnection(PDO $pdo): void
    {
        if (self::$pool !== null) {
            self::$pool->releaseConnection($pdo);
        }
    }
    
    /**
     * Create direct connection
     */
    private static function createDirectConnection(): PDO
    {
        require_once __DIR__ . '/../config/db.php';
        global $servername, $username, $password, $dbname;
        
        $dsn = "mysql:host=$servername;dbname=$dbname;charset=utf8mb4";
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => true,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ];
        
        $pdo = new PDO($dsn, $username, $password, $options);
        
        // Optimizations
        $pdo->exec("SET SESSION sql_mode = ''");
        $pdo->exec("SET SESSION transaction_isolation = 'READ-COMMITTED'");
        
        return $pdo;
    }
    
    /**
     * Execute query with automatic connection management
     */
    public static function query(string $sql, array $params = [], bool $usePool = true): array
    {
        $pdo = self::getConnection($usePool);
        
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetchAll();
            
            if ($usePool) {
                self::releaseConnection($pdo);
            }
            
            return $result;
        } catch (PDOException $e) {
            if ($usePool) {
                self::releaseConnection($pdo);
            }
            throw $e;
        }
    }
    
    /**
     * Execute single row query
     */
    public static function queryOne(string $sql, array $params = [], bool $usePool = true): ?array
    {
        $result = self::query($sql, $params, $usePool);
        return $result[0] ?? null;
    }
    
    /**
     * Execute insert/update/delete
     */
    public static function execute(string $sql, array $params = [], bool $usePool = true): int
    {
        $pdo = self::getConnection($usePool);
        
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $affected = $stmt->rowCount();
            
            if ($usePool) {
                self::releaseConnection($pdo);
            }
            
            return $affected;
        } catch (PDOException $e) {
            if ($usePool) {
                self::releaseConnection($pdo);
            }
            throw $e;
        }
    }
    
    /**
     * Batch insert with transaction
     */
    public static function batchInsert(string $table, array $columns, array $rows, bool $usePool = true): int
    {
        if (empty($rows)) {
            return 0;
        }
        
        $pdo = self::getConnection($usePool);
        
        try {
            $pdo->beginTransaction();
            
            $placeholders = '(' . implode(',', array_fill(0, count($columns), '?')) . ')';
            $sql = "INSERT INTO $table (" . implode(',', $columns) . ") VALUES $placeholders";
            
            $stmt = $pdo->prepare($sql);
            $total = 0;
            
            foreach ($rows as $row) {
                $stmt->execute(array_values($row));
                $total++;
            }
            
            $pdo->commit();
            
            if ($usePool) {
                self::releaseConnection($pdo);
            }
            
            return $total;
        } catch (PDOException $e) {
            $pdo->rollBack();
            
            if ($usePool) {
                self::releaseConnection($pdo);
            }
            
            throw $e;
        }
    }
    
    /**
     * Get pool statistics
     */
    public static function getPoolStats(): array
    {
        if (self::$pool === null) {
            return ['error' => 'Pool not initialized'];
        }
        
        return self::$pool->getStats();
    }
    
    /**
     * Cleanup pool connections
     */
    public static function cleanupPool(): void
    {
        if (self::$pool !== null) {
            self::$pool->cleanup();
        }
    }
}

/**
 * ============================================================================
 * CACHED QUERY HELPER (with Redis)
 * ============================================================================
 */
class CachedQuery
{
    private static ?Redis $redis = null;
    private const CACHE_PREFIX = 'query:';
    private const DEFAULT_TTL = 300; // 5 minutes
    
    /**
     * Execute query with caching
     */
    public static function query(string $sql, array $params = [], int $ttl = self::DEFAULT_TTL): array
    {
        $cacheKey = self::generateCacheKey($sql, $params);
        
        // Try cache first
        $cached = self::getFromCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        // Execute query
        $result = OptimizedDB::query($sql, $params);
        
        // Store in cache
        self::storeInCache($cacheKey, $result, $ttl);
        
        return $result;
    }
    
    /**
     * Invalidate cache by pattern
     */
    public static function invalidate(string $pattern): void
    {
        $redis = self::getRedis();
        
        $keys = $redis->keys(self::CACHE_PREFIX . $pattern . '*');
        if (!empty($keys)) {
            $redis->del($keys);
        }
    }
    
    private static function getRedis(): Redis
    {
        if (self::$redis === null) {
            self::$redis = new Redis();
            self::$redis->connect('127.0.0.1', 6379);
            self::$redis->select(8); // Database 8 for user data cache
            self::$redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_JSON);
        }
        
        return self::$redis;
    }
    
    private static function generateCacheKey(string $sql, array $params): string
    {
        return self::CACHE_PREFIX . md5($sql . serialize($params));
    }
    
    private static function getFromCache(string $key): ?array
    {
        $redis = self::getRedis();
        $cached = $redis->get($key);
        
        return $cached !== false ? $cached : null;
    }
    
    private static function storeInCache(string $key, array $data, int $ttl): void
    {
        $redis = self::getRedis();
        $redis->setex($key, $ttl, $data);
    }
}

// Background cleanup task (run via cron every 5 minutes)
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    $pool = DatabaseConnectionPool::getInstance();
    $pool->cleanup();
    echo "Pool cleanup completed\n";
    print_r($pool->getStats());
}
