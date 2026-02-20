<?php
/**
 * ============================================================================
 * API RATE LIMITING & OPTIMIZATION MIDDLEWARE
 * ============================================================================
 * Protects API from abuse and ensures fair resource allocation
 * Supports 1000+ concurrent users with tiered limits
 * ============================================================================
 */

class RateLimiter
{
    private Redis $redis;
    private const REDIS_DB = 5; // Database 5 for rate limiting
    
    // Rate limit tiers (requests per minute)
    private const RATE_LIMIT_FREE = 60;      // Free tier: 60 req/min
    private const RATE_LIMIT_BASIC = 300;    // Basic: 300 req/min
    private const RATE_LIMIT_PRO = 1000;     // Pro: 1000 req/min
    private const RATE_LIMIT_ENTERPRISE = 5000; // Enterprise: 5000 req/min
    
    // Burst allowance
    private const BURST_MULTIPLIER = 1.5;
    
    // Ban thresholds
    private const BAN_THRESHOLD = 10;        // Violations before ban
    private const BAN_DURATION = 3600;       // 1 hour ban
    
    public function __construct()
    {
        $this->redis = new Redis();
        $this->redis->connect('127.0.0.1', 6379, 2.0);
        $this->redis->select(self::REDIS_DB);
    }
    
    /**
     * Check if request is allowed
     */
    public function checkLimit(string $userId, string $userTier = 'free', string $endpoint = 'general'): array
    {
        // Check if user is banned
        if ($this->isBanned($userId)) {
            return [
                'allowed' => false,
                'reason' => 'rate_limit_ban',
                'retry_after' => $this->getBanTimeRemaining($userId),
                'status_code' => 429
            ];
        }
        
        // Get rate limit for user tier
        $limit = $this->getLimitForTier($userTier);
        $burstLimit = (int)($limit * self::BURST_MULTIPLIER);
        
        // Sliding window rate limiting
        $now = time();
        $window = 60; // 1 minute window
        $key = "rate:$userId:$endpoint";
        
        // Remove old entries outside window
        $this->redis->zRemRangeByScore($key, 0, $now - $window);
        
        // Count requests in current window
        $requestCount = $this->redis->zCard($key);
        
        if ($requestCount >= $burstLimit) {
            // Exceeded burst limit - increment violation counter
            $this->recordViolation($userId);
            
            return [
                'allowed' => false,
                'reason' => 'rate_limit_exceeded',
                'limit' => $limit,
                'burst_limit' => $burstLimit,
                'current' => $requestCount,
                'retry_after' => $this->calculateRetryAfter($key),
                'status_code' => 429
            ];
        }
        
        // Add current request
        $this->redis->zAdd($key, $now, uniqid('', true));
        $this->redis->expire($key, $window * 2); // Expire after 2 windows
        
        return [
            'allowed' => true,
            'limit' => $limit,
            'remaining' => max(0, $limit - $requestCount - 1),
            'reset' => $now + $window
        ];
    }
    
    /**
     * Get limit for user tier
     */
    private function getLimitForTier(string $tier): int
    {
        return match (strtolower($tier)) {
            'enterprise' => self::RATE_LIMIT_ENTERPRISE,
            'pro' => self::RATE_LIMIT_PRO,
            'basic' => self::RATE_LIMIT_BASIC,
            default => self::RATE_LIMIT_FREE
        };
    }
    
    /**
     * Check if user is banned
     */
    private function isBanned(string $userId): bool
    {
        $banKey = "ban:$userId";
        return $this->redis->exists($banKey);
    }
    
    /**
     * Get remaining ban time
     */
    private function getBanTimeRemaining(string $userId): int
    {
        $banKey = "ban:$userId";
        return $this->redis->ttl($banKey);
    }
    
    /**
     * Record rate limit violation
     */
    private function recordViolation(string $userId): void
    {
        $violationKey = "violations:$userId";
        $violations = $this->redis->incr($violationKey);
        $this->redis->expire($violationKey, 3600); // Reset every hour
        
        // Ban if threshold exceeded
        if ($violations >= self::BAN_THRESHOLD) {
            $this->banUser($userId);
        }
    }
    
    /**
     * Ban user temporarily
     */
    private function banUser(string $userId): void
    {
        $banKey = "ban:$userId";
        $this->redis->setex($banKey, self::BAN_DURATION, time());
        
        error_log("[RateLimiter] User $userId banned for " . self::BAN_DURATION . " seconds");
    }
    
    /**
     * Calculate retry after time
     */
    private function calculateRetryAfter(string $key): int
    {
        $oldest = $this->redis->zRange($key, 0, 0, true);
        if (empty($oldest)) {
            return 60;
        }
        
        $oldestTimestamp = (int)array_values($oldest)[0];
        $retryAfter = max(1, 60 - (time() - $oldestTimestamp));
        
        return $retryAfter;
    }
    
    /**
     * Get user's current usage stats
     */
    public function getUsageStats(string $userId, array $endpoints = []): array
    {
        if (empty($endpoints)) {
            $endpoints = ['general'];
        }
        
        $stats = [];
        $now = time();
        $window = 60;
        
        foreach ($endpoints as $endpoint) {
            $key = "rate:$userId:$endpoint";
            $this->redis->zRemRangeByScore($key, 0, $now - $window);
            $count = $this->redis->zCard($key);
            
            $stats[$endpoint] = [
                'requests_last_minute' => $count,
                'requests_last_hour' => $this->getHourlyCount($userId, $endpoint)
            ];
        }
        
        return $stats;
    }
    
    /**
     * Get hourly request count
     */
    private function getHourlyCount(string $userId, string $endpoint): int
    {
        $key = "rate:hourly:$userId:$endpoint";
        $count = $this->redis->get($key);
        return $count !== false ? (int)$count : 0;
    }
}

/**
 * ============================================================================
 * API OPTIMIZATION MIDDLEWARE
 * ============================================================================
 */
class APIOptimization
{
    private Redis $redis;
    private const CACHE_DB = 7; // Database 7 for campaign statistics
    
    public function __construct()
    {
        $this->redis = new Redis();
        $this->redis->connect('127.0.0.1', 6379, 2.0);
        $this->redis->select(self::CACHE_DB);
        $this->redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_JSON);
    }
    
    /**
     * Handle CORS for high-performance APIs
     */
    public static function handleCORS(): void
    {
        // Allow specific origins in production
        $allowedOrigins = [
            'https://yourdomain.com',
            'http://localhost:5173', // Vite dev server
            'http://localhost:3000'
        ];
        
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        if (in_array($origin, $allowedOrigins)) {
            header("Access-Control-Allow-Origin: $origin");
        } else {
            header("Access-Control-Allow-Origin: *"); // For development only
        }
        
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        header("Access-Control-Max-Age: 86400"); // Cache preflight for 24 hours
        header("Access-Control-Allow-Credentials: true");
        
        // Handle OPTIONS preflight
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
    
    /**
     * Enable response compression
     */
    public static function enableCompression(): void
    {
        if (!ob_start('ob_gzhandler')) {
            ob_start();
        }
    }
    
    /**
     * Set cache headers for static content
     */
    public static function setCacheHeaders(int $maxAge = 3600): void
    {
        header("Cache-Control: public, max-age=$maxAge");
        header("Expires: " . gmdate('D, d M Y H:i:s', time() + $maxAge) . ' GMT');
        header("Pragma: public");
    }
    
    /**
     * Set no-cache headers for dynamic content
     */
    public static function setNoCacheHeaders(): void
    {
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Pragma: no-cache");
        header("Expires: 0");
    }
    
    /**
     * Send JSON response with proper headers
     */
    public static function sendJSON(array $data, int $statusCode = 200, bool $compress = true): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        
        if ($compress && extension_loaded('zlib')) {
            header('Content-Encoding: gzip');
            echo gzencode(json_encode($data, JSON_UNESCAPED_UNICODE));
        } else {
            echo json_encode($data, JSON_UNESCAPED_UNICODE);
        }
        
        exit;
    }
    
    /**
     * Cache API response in Redis
     */
    public function cacheResponse(string $cacheKey, array $data, int $ttl = 300): void
    {
        $this->redis->setex($cacheKey, $ttl, $data);
    }
    
    /**
     * Get cached API response
     */
    public function getCachedResponse(string $cacheKey): ?array
    {
        $cached = $this->redis->get($cacheKey);
        return $cached !== false ? $cached : null;
    }
    
    /**
     * Generate cache key from request
     */
    public static function generateCacheKey(string $endpoint, array $params = []): string
    {
        ksort($params);
        return 'api:' . md5($endpoint . serialize($params));
    }
    
    /**
     * Invalidate cache by pattern
     */
    public function invalidateCache(string $pattern): int
    {
        $keys = $this->redis->keys("api:*$pattern*");
        if (!empty($keys)) {
            return $this->redis->del($keys);
        }
        return 0;
    }
}

/**
 * ============================================================================
 * REQUEST LOGGER (for analytics and monitoring)
 * ============================================================================
 */
class RequestLogger
{
    private static Redis $redis;
    private const LOG_DB = 6; // Database 6 for coordination
    
    /**
     * Log API request
     */
    public static function log(string $endpoint, string $userId, float $duration, int $statusCode): void
    {
        if (!isset(self::$redis)) {
            self::$redis = new Redis();
            self::$redis->connect('127.0.0.1', 6379, 1.0);
            self::$redis->select(self::LOG_DB);
        }
        
        $logEntry = [
            'endpoint' => $endpoint,
            'user_id' => $userId,
            'duration' => round($duration, 4),
            'status' => $statusCode,
            'timestamp' => time()
        ];
        
        // Store in sorted set for time-based queries
        $key = 'logs:' . date('Y-m-d');
        self::$redis->zAdd($key, time(), json_encode($logEntry));
        self::$redis->expire($key, 86400 * 7); // Keep for 7 days
        
        // Update metrics
        self::updateMetrics($endpoint, $duration, $statusCode);
    }
    
    /**
     * Update endpoint metrics
     */
    private static function updateMetrics(string $endpoint, float $duration, int $statusCode): void
    {
        $metricsKey = "metrics:$endpoint";
        
        $metrics = self::$redis->get($metricsKey);
        if ($metrics === false) {
            $metrics = [
                'total_requests' => 0,
                'total_duration' => 0,
                'success_count' => 0,
                'error_count' => 0,
                'avg_duration' => 0
            ];
        }
        
        $metrics['total_requests']++;
        $metrics['total_duration'] += $duration;
        $metrics['avg_duration'] = $metrics['total_duration'] / $metrics['total_requests'];
        
        if ($statusCode >= 200 && $statusCode < 300) {
            $metrics['success_count']++;
        } else {
            $metrics['error_count']++;
        }
        
        self::$redis->setex($metricsKey, 3600, $metrics);
    }
    
    /**
     * Get metrics for endpoint
     */
    public static function getMetrics(string $endpoint): array
    {
        if (!isset(self::$redis)) {
            self::$redis = new Redis();
            self::$redis->connect('127.0.0.1', 6379, 1.0);
            self::$redis->select(self::LOG_DB);
        }
        
        $metricsKey = "metrics:$endpoint";
        $metrics = self::$redis->get($metricsKey);
        
        return $metrics !== false ? $metrics : [];
    }
}

/**
 * ============================================================================
 * API MIDDLEWARE WRAPPER
 * ============================================================================
 */
class APIMiddleware
{
    private RateLimiter $rateLimiter;
    private APIOptimization $optimizer;
    private float $requestStartTime;
    
    public function __construct()
    {
        $this->rateLimiter = new RateLimiter();
        $this->optimizer = new APIOptimization();
        $this->requestStartTime = microtime(true);
    }
    
    /**
     * Initialize all middleware
     */
    public function initialize(): void
    {
        // CORS
        APIOptimization::handleCORS();
        
        // Compression
        APIOptimization::enableCompression();
        
        // No cache for APIs
        APIOptimization::setNoCacheHeaders();
        
        // Security headers
        $this->setSecurityHeaders();
    }
    
    /**
     * Check rate limit before processing request
     */
    public function checkRateLimit(string $userId, string $tier = 'free', string $endpoint = 'general'): bool
    {
        $result = $this->rateLimiter->checkLimit($userId, $tier, $endpoint);
        
        // Add rate limit headers
        if ($result['allowed']) {
            header("X-RateLimit-Limit: {$result['limit']}");
            header("X-RateLimit-Remaining: {$result['remaining']}");
            header("X-RateLimit-Reset: {$result['reset']}");
            return true;
        } else {
            header("X-RateLimit-Limit: {$result['limit']}");
            header("X-RateLimit-Remaining: 0");
            header("Retry-After: {$result['retry_after']}");
            
            APIOptimization::sendJSON([
                'success' => false,
                'error' => $result['reason'],
                'message' => 'Rate limit exceeded',
                'retry_after' => $result['retry_after']
            ], $result['status_code']);
            
            return false;
        }
    }
    
    /**
     * Get cached response or execute callback
     */
    public function cached(string $cacheKey, callable $callback, int $ttl = 300): array
    {
        // Try cache first
        $cached = $this->optimizer->getCachedResponse($cacheKey);
        if ($cached !== null) {
            header('X-Cache: HIT');
            return $cached;
        }
        
        // Execute callback
        header('X-Cache: MISS');
        $data = $callback();
        
        // Cache result
        $this->optimizer->cacheResponse($cacheKey, $data, $ttl);
        
        return $data;
    }
    
    /**
     * Log request and send response
     */
    public function respond(array $data, int $statusCode = 200, string $userId = '', string $endpoint = ''): void
    {
        // Calculate request duration
        $duration = microtime(true) - $this->requestStartTime;
        
        // Add performance header
        header("X-Response-Time: " . round($duration * 1000, 2) . "ms");
        
        // Log request
        if ($userId && $endpoint) {
            RequestLogger::log($endpoint, $userId, $duration, $statusCode);
        }
        
        // Send response
        APIOptimization::sendJSON($data, $statusCode);
    }
    
    /**
     * Set security headers
     */
    private function setSecurityHeaders(): void
    {
        header("X-Content-Type-Options: nosniff");
        header("X-Frame-Options: DENY");
        header("X-XSS-Protection: 1; mode=block");
        header("Referrer-Policy: strict-origin-when-cross-origin");
        header("Content-Security-Policy: default-src 'self'");
    }
}

/**
 * ============================================================================
 * USAGE EXAMPLE
 * ============================================================================
 */
if (basename(__FILE__) === 'api_middleware.php' && isset($_GET['example'])) {
    // Example usage
    $middleware = new APIMiddleware();
    $middleware->initialize();
    
    // Get user info from token/session
    $userId = $_SESSION['user_id'] ?? 'anonymous';
    $userTier = $_SESSION['user_tier'] ?? 'free';
    $endpoint = $_SERVER['REQUEST_URI'] ?? '/api/test';
    
    // Check rate limit
    if (!$middleware->checkRateLimit($userId, $userTier, $endpoint)) {
        exit;
    }
    
    // Use caching for expensive operations
    $cacheKey = APIOptimization::generateCacheKey($endpoint, $_GET);
    
    $data = $middleware->cached($cacheKey, function() {
        // Expensive operation here
        return [
            'success' => true,
            'message' => 'This response is cached',
            'timestamp' => time()
        ];
    }, 300);
    
    // Send response
    $middleware->respond($data, 200, $userId, $endpoint);
}
