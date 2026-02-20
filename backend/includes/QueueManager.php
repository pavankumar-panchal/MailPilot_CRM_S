<?php
/**
 * ============================================================================
 * DISTRIBUTED QUEUE MANAGEMENT SYSTEM
 * ============================================================================
 * Redis-backed queue for distributed email campaign processing
 * Handles 500k+ emails/hour with priority queuing and retry logic
 * ============================================================================
 */

class QueueManager
{
    private Redis $redis;
    private const REDIS_DB = 2; // High priority queue database
    
    // Queue names
    public const QUEUE_EMAIL_HIGH = 'email:queue:high';
    public const QUEUE_EMAIL_LOW = 'email:queue:low';
    public const QUEUE_SMTP_VALIDATION = 'email:queue:smtp_validation';
    public const QUEUE_RETRY = 'email:queue:retry';
    public const QUEUE_DEAD_LETTER = 'email:queue:dead';
    public const QUEUE_SCHEDULED = 'email:queue:scheduled';
    
    // Job status tracking
    private const JOB_PROCESSING = 'jobs:processing';
    private const JOB_COMPLETED = 'jobs:completed';
    private const JOB_FAILED = 'jobs:failed';
    
    // Configuration
    private const MAX_RETRY_ATTEMPTS = 3;
    private const RETRY_DELAY_BASE = 60; // seconds
    private const JOB_TIMEOUT = 300; // 5 minutes
    private const DEAD_LETTER_TTL = 86400 * 7; // 7 days
    
    public function __construct()
    {
        $this->redis = new Redis();
        $this->redis->connect('127.0.0.1', 6379, 2.0);
        $this->redis->select(self::REDIS_DB);
        $this->redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_JSON);
    }
    
    /**
     * ========================================================================
     * JOB SUBMISSION
     * ========================================================================
     */
    
    /**
     * Add email job to queue
     */
    public function enqueueEmail(array $jobData, string $priority = 'normal'): string
    {
        $jobId = $this->generateJobId();
        
        $job = [
            'id' => $jobId,
            'type' => 'email',
            'data' => $jobData,
            'priority' => $priority,
            'attempts' => 0,
            'created_at' => time(),
            'queued_at' => time()
        ];
        
        $queue = ($priority === 'high') ? self::QUEUE_EMAIL_HIGH : self::QUEUE_EMAIL_LOW;
        $this->redis->lPush($queue, $job);
        
        return $jobId;
    }
    
    /**
     * Bulk enqueue for campaign launches
     */
    public function bulkEnqueueEmails(array $jobsData, string $priority = 'normal'): array
    {
        $jobIds = [];
        $queue = ($priority === 'high') ? self::QUEUE_EMAIL_HIGH : self::QUEUE_EMAIL_LOW;
        
        $pipeline = $this->redis->multi(Redis::PIPELINE);
        
        foreach ($jobsData as $jobData) {
            $jobId = $this->generateJobId();
            
            $job = [
                'id' => $jobId,
                'type' => 'email',
                'data' => $jobData,
                'priority' => $priority,
                'attempts' => 0,
                'created_at' => time(),
                'queued_at' => time()
            ];
            
            $pipeline->lPush($queue, $job);
            $jobIds[] = $jobId;
        }
        
        $pipeline->exec();
        
        return $jobIds;
    }
    
    /**
     * Schedule job for future execution
     */
    public function scheduleEmail(array $jobData, int $executeAt, string $priority = 'normal'): string
    {
        $jobId = $this->generateJobId();
        
        $job = [
            'id' => $jobId,
            'type' => 'email',
            'data' => $jobData,
            'priority' => $priority,
            'attempts' => 0,
            'created_at' => time(),
            'execute_at' => $executeAt
        ];
        
        // Use sorted set with execution time as score
        $this->redis->zAdd(self::QUEUE_SCHEDULED, $executeAt, $job);
        
        return $jobId;
    }
    
    /**
     * Add SMTP validation job
     */
    public function enqueueSMTPValidation(array $jobData): string
    {
        $jobId = $this->generateJobId();
        
        $job = [
            'id' => $jobId,
            'type' => 'smtp_validation',
            'data' => $jobData,
            'attempts' => 0,
            'created_at' => time(),
            'queued_at' => time()
        ];
        
        $this->redis->lPush(self::QUEUE_SMTP_VALIDATION, $job);
        
        return $jobId;
    }
    
    /**
     * ========================================================================
     * JOB CONSUMPTION
     * ========================================================================
     */
    
    /**
     * Get next job from queue (blocking)
     */
    public function dequeueJob(array $queues = null, int $timeout = 1): ?array
    {
        if ($queues === null) {
            $queues = [self::QUEUE_EMAIL_HIGH, self::QUEUE_EMAIL_LOW];
        }
        
        $result = $this->redis->brPop($queues, $timeout);
        
        if (!$result) {
            return null;
        }
        
        $job = $result[1]; // [0] = queue name, [1] = job data
        
        // Mark as processing
        $this->markJobProcessing($job['id'], $job);
        
        return $job;
    }
    
    /**
     * Get batch of jobs (non-blocking)
     */
    public function dequeueBatch(string $queue, int $batchSize = 50): array
    {
        $jobs = [];
        
        $pipeline = $this->redis->multi(Redis::PIPELINE);
        
        for ($i = 0; $i < $batchSize; $i++) {
            $pipeline->rPop($queue);
        }
        
        $results = $pipeline->exec();
        
        foreach ($results as $job) {
            if ($job !== false && $job !== null) {
                $this->markJobProcessing($job['id'], $job);
                $jobs[] = $job;
            }
        }
        
        return $jobs;
    }
    
    /**
     * ========================================================================
     * JOB STATUS MANAGEMENT
     * ========================================================================
     */
    
    /**
     * Mark job as processing
     */
    public function markJobProcessing(string $jobId, array $job): void
    {
        $key = self::JOB_PROCESSING . ":$jobId";
        $job['started_at'] = time();
        $job['timeout_at'] = time() + self::JOB_TIMEOUT;
        
        $this->redis->setex($key, self::JOB_TIMEOUT * 2, $job);
    }
    
    /**
     * Mark job as completed
     */
    public function markJobCompleted(string $jobId, array $result = []): void
    {
        // Remove from processing
        $this->redis->del(self::JOB_PROCESSING . ":$jobId");
        
        // Add to completed (with short TTL)
        $key = self::JOB_COMPLETED . ":$jobId";
        $data = [
            'id' => $jobId,
            'completed_at' => time(),
            'result' => $result
        ];
        
        $this->redis->setex($key, 3600, $data); // Keep for 1 hour
    }
    
    /**
     * Mark job as failed and handle retry
     */
    public function markJobFailed(string $jobId, string $error, array $jobData = null): void
    {
        // Remove from processing
        $processingKey = self::JOB_PROCESSING . ":$jobId";
        $job = $this->redis->get($processingKey);
        
        if (!$job && $jobData) {
            $job = $jobData;
        }
        
        if (!$job) {
            error_log("[QueueManager] Job $jobId not found for failure handling");
            return;
        }
        
        $this->redis->del($processingKey);
        
        $job['attempts'] = ($job['attempts'] ?? 0) + 1;
        $job['last_error'] = $error;
        $job['failed_at'] = time();
        
        if ($job['attempts'] < self::MAX_RETRY_ATTEMPTS) {
            // Retry with exponential backoff
            $retryDelay = self::RETRY_DELAY_BASE * pow(2, $job['attempts'] - 1);
            $job['retry_after'] = time() + $retryDelay;
            
            $this->redis->lPush(self::QUEUE_RETRY, $job);
            
            error_log("[QueueManager] Job $jobId failed, retry " . $job['attempts'] . "/" . self::MAX_RETRY_ATTEMPTS);
        } else {
            // Move to dead letter queue
            $job['final_error'] = $error;
            $this->redis->lPush(self::QUEUE_DEAD_LETTER, $job);
            
            // Also track in failed jobs
            $failedKey = self::JOB_FAILED . ":$jobId";
            $this->redis->setex($failedKey, self::DEAD_LETTER_TTL, $job);
            
            error_log("[QueueManager] Job $jobId moved to dead letter queue");
        }
    }
    
    /**
     * ========================================================================
     * QUEUE MONITORING
     * ========================================================================
     */
    
    /**
     * Get queue sizes
     */
    public function getQueueSizes(): array
    {
        return [
            'high_priority' => $this->redis->lLen(self::QUEUE_EMAIL_HIGH),
            'low_priority' => $this->redis->lLen(self::QUEUE_EMAIL_LOW),
            'smtp_validation' => $this->redis->lLen(self::QUEUE_SMTP_VALIDATION),
            'retry' => $this->redis->lLen(self::QUEUE_RETRY),
            'dead_letter' => $this->redis->lLen(self::QUEUE_DEAD_LETTER),
            'scheduled' => $this->redis->zCard(self::QUEUE_SCHEDULED),
            'processing' => $this->countProcessingJobs()
        ];
    }
    
    /**
     * Count jobs currently being processed
     */
    private function countProcessingJobs(): int
    {
        $pattern = self::JOB_PROCESSING . ":*";
        $keys = $this->redis->keys($pattern);
        return count($keys);
    }
    
    /**
     * Get job status
     */
    public function getJobStatus(string $jobId): array
    {
        // Check if processing
        $processingKey = self::JOB_PROCESSING . ":$jobId";
        $job = $this->redis->get($processingKey);
        
        if ($job) {
            return [
                'status' => 'processing',
                'data' => $job
            ];
        }
        
        // Check if completed
        $completedKey = self::JOB_COMPLETED . ":$jobId";
        $job = $this->redis->get($completedKey);
        
        if ($job) {
            return [
                'status' => 'completed',
                'data' => $job
            ];
        }
        
        // Check if failed
        $failedKey = self::JOB_FAILED . ":$jobId";
        $job = $this->redis->get($failedKey);
        
        if ($job) {
            return [
                'status' => 'failed',
                'data' => $job
            ];
        }
        
        // Check queues
        $queues = [
            self::QUEUE_EMAIL_HIGH,
            self::QUEUE_EMAIL_LOW,
            self::QUEUE_SMTP_VALIDATION,
            self::QUEUE_RETRY
        ];
        
        foreach ($queues as $queue) {
            $jobs = $this->redis->lRange($queue, 0, -1);
            foreach ($jobs as $job) {
                if ($job['id'] === $jobId) {
                    return [
                        'status' => 'queued',
                        'queue' => $queue,
                        'data' => $job
                    ];
                }
            }
        }
        
        return [
            'status' => 'not_found',
            'data' => null
        ];
    }
    
    /**
     * ========================================================================
     * MAINTENANCE OPERATIONS
     * ========================================================================
     */
    
    /**
     * Process retry queue
     */
    public function processRetryQueue(): int
    {
        $now = time();
        $processed = 0;
        $maxBatch = 100;
        
        for ($i = 0; $i < $maxBatch; $i++) {
            $job = $this->redis->rPop(self::QUEUE_RETRY);
            
            if (!$job) {
                break;
            }
            
            $retryAfter = $job['retry_after'] ?? 0;
            
            if ($retryAfter <= $now) {
                // Ready to retry - add back to appropriate queue
                $queue = ($job['priority'] === 'high') ? self::QUEUE_EMAIL_HIGH : self::QUEUE_EMAIL_LOW;
                $job['queued_at'] = time();
                $this->redis->lPush($queue, $job);
                $processed++;
            } else {
                // Not ready yet - put back
                $this->redis->lPush(self::QUEUE_RETRY, $job);
            }
        }
        
        return $processed;
    }
    
    /**
     * Process scheduled jobs
     */
    public function processScheduledJobs(): int
    {
        $now = time();
        $processed = 0;
        
        // Get jobs ready to execute
        $jobs = $this->redis->zRangeByScore(self::QUEUE_SCHEDULED, 0, $now);
        
        foreach ($jobs as $job) {
            $queue = ($job['priority'] === 'high') ? self::QUEUE_EMAIL_HIGH : self::QUEUE_EMAIL_LOW;
            $job['queued_at'] = time();
            
            $this->redis->lPush($queue, $job);
            $this->redis->zRem(self::QUEUE_SCHEDULED, $job);
            
            $processed++;
        }
        
        return $processed;
    }
    
    /**
     * Clean up stuck jobs (timeout recovery)
     */
    public function cleanupStuckJobs(): int
    {
        $now = time();
        $cleaned = 0;
        $pattern = self::JOB_PROCESSING . ":*";
        
        $keys = $this->redis->keys($pattern);
        
        foreach ($keys as $key) {
            $job = $this->redis->get($key);
            
            if (!$job) {
                continue;
            }
            
            $timeoutAt = $job['timeout_at'] ?? 0;
            
            if ($timeoutAt > 0 && $timeoutAt < $now) {
                // Job timed out - move to retry
                error_log("[QueueManager] Job {$job['id']} timed out, moving to retry");
                
                $this->redis->del($key);
                $this->markJobFailed($job['id'], 'Job timeout', $job);
                
                $cleaned++;
            }
        }
        
        return $cleaned;
    }
    
    /**
     * Purge old dead letter jobs
     */
    public function purgeDeadLetterQueue(int $olderThanDays = 7): int
    {
        $cutoff = time() - (86400 * $olderThanDays);
        $purged = 0;
        
        $length = $this->redis->lLen(self::QUEUE_DEAD_LETTER);
        
        for ($i = 0; $i < $length; $i++) {
            $job = $this->redis->rPop(self::QUEUE_DEAD_LETTER);
            
            if (!$job) {
                break;
            }
            
            $failedAt = $job['failed_at'] ?? 0;
            
            if ($failedAt < $cutoff) {
                // Old enough to purge
                $purged++;
            } else {
                // Keep it
                $this->redis->lPush(self::QUEUE_DEAD_LETTER, $job);
            }
        }
        
        return $purged;
    }
    
    /**
     * Get queue statistics
     */
    public function getStatistics(): array
    {
        $sizes = $this->getQueueSizes();
        
        return [
            'queues' => $sizes,
            'total_pending' => $sizes['high_priority'] + $sizes['low_priority'],
            'health' => [
                'retry_queue_size' => $sizes['retry'],
                'dead_letter_size' => $sizes['dead_letter'],
                'processing' => $sizes['processing']
            ],
            'timestamp' => time()
        ];
    }
    
    /**
     * ========================================================================
     * UTILITY METHODS
     * ========================================================================
     */
    
    /**
     * Generate unique job ID
     */
    private function generateJobId(): string
    {
        return uniqid('job_', true) . '_' . bin2hex(random_bytes(4));
    }
    
    /**
     * Clear all queues (use with caution!)
     */
    public function clearAllQueues(): array
    {
        $queues = [
            self::QUEUE_EMAIL_HIGH,
            self::QUEUE_EMAIL_LOW,
            self::QUEUE_SMTP_VALIDATION,
            self::QUEUE_RETRY,
            self::QUEUE_SCHEDULED
        ];
        
        $cleared = [];
        
        foreach ($queues as $queue) {
            $size = $this->redis->lLen($queue);
            $this->redis->del($queue);
            $cleared[$queue] = $size;
        }
        
        return $cleared;
    }
}

/**
 * ============================================================================
 * QUEUE MAINTENANCE DAEMON (run as cron job)
 * ============================================================================
 */
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    echo "[QueueMaintenance] Starting maintenance tasks...\n";
    
    $qm = new QueueManager();
    
    // Process retry queue
    $retried = $qm->processRetryQueue();
    echo "[QueueMaintenance] Processed $retried retry jobs\n";
    
    // Process scheduled jobs
    $scheduled = $qm->processScheduledJobs();
    echo "[QueueMaintenance] Processed $scheduled scheduled jobs\n";
    
    // Clean up stuck jobs
    $cleaned = $qm->cleanupStuckJobs();
    echo "[QueueMaintenance] Cleaned $cleaned stuck jobs\n";
    
    // Purge old dead letters (older than 7 days)
    $purged = $qm->purgeDeadLetterQueue(7);
    echo "[QueueMaintenance] Purged $purged old dead letter jobs\n";
    
    // Show statistics
    echo "\n[QueueMaintenance] Current statistics:\n";
    print_r($qm->getStatistics());
    
    echo "\n[QueueMaintenance] Maintenance completed\n";
}
