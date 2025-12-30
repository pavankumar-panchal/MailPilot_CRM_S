<?php
/**
 * ProcessManager - Lightweight process and lock management
 * Combines BackgroundProcess + CronLockManager functionality
 */
class ProcessManager
{
    private static $lockDir = null;
    private $lockFile = null;
    private $jobName = null;
    
    public function __construct($jobName = null, $maxTime = 300) {
        if ($jobName) {
            $this->jobName = preg_replace('/[^a-z0-9_-]/i', '_', $jobName);
            self::$lockDir = __DIR__ . '/../tmp/cron_locks';
            if (!is_dir(self::$lockDir)) @mkdir(self::$lockDir, 0775, true);
            $this->lockFile = self::$lockDir . '/' . $this->jobName . '.lock';
            register_shutdown_function([$this, 'release']);
        }
    }
    
    // Lock Management
    public function acquire() {
        if (!$this->lockFile) return true;
        
        if (file_exists($this->lockFile)) {
            $data = @json_decode(file_get_contents($this->lockFile), true);
            if ($data && file_exists('/proc/' . $data['pid'])) {
                return false; // Already running
            }
            @unlink($this->lockFile); // Stale lock
        }
        
        return (bool)file_put_contents($this->lockFile, json_encode([
            'pid' => getmypid(),
            'time' => time(),
            'job' => $this->jobName
        ]));
    }
    
    public function release() {
        if ($this->lockFile && file_exists($this->lockFile)) {
            $data = @json_decode(file_get_contents($this->lockFile), true);
            if ($data && $data['pid'] == getmypid()) @unlink($this->lockFile);
        }
    }
    
    // Background Process Execution
    public static function execute($phpBin, $script, $args = [], $logFile = '/dev/null') {
        $argStr = '';
        foreach ($args as $key => $val) {
            if (is_numeric($key)) {
                $argStr .= ' ' . escapeshellarg($val);
            } else {
                $argStr .= ' --' . $key . '=' . escapeshellarg((string)$val);
            }
        }
        
        $cmd = sprintf(
            'nohup %s %s%s > %s 2>&1 & echo $!',
            escapeshellarg($phpBin),
            escapeshellarg($script),
            $argStr,
            escapeshellarg((string)$logFile)
        );
        
        $pid = trim(shell_exec($cmd));
        return (int)$pid;
    }
    
    public static function closeConnections(...$connections) {
        foreach ($connections as $conn) {
            if ($conn && method_exists($conn, 'close')) {
                $conn->close();
            }
        }
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
    }
    
    public static function isRunning($pid) {
        return $pid > 0 && file_exists('/proc/' . (int)$pid);
    }
    
    // Monitor all locks
    public static function getAllLocks() {
        $locks = [];
        $dir = __DIR__ . '/../tmp/cron_locks';
        if (!is_dir($dir)) return $locks;
        
        foreach (glob($dir . '/*.lock') as $file) {
            $data = @json_decode(file_get_contents($file), true);
            if ($data) {
                $data['running'] = self::isRunning($data['pid'] ?? 0);
                $data['age'] = time() - ($data['time'] ?? 0);
                $locks[] = $data;
            }
        }
        return $locks;
    }
    
    // Clean stale locks
    public static function cleanStale() {
        $cleaned = 0;
        $dir = __DIR__ . '/../tmp/cron_locks';
        if (!is_dir($dir)) return $cleaned;
        
        foreach (glob($dir . '/*.lock') as $file) {
            $data = @json_decode(file_get_contents($file), true);
            if ($data && !self::isRunning($data['pid'] ?? 0)) {
                @unlink($file);
                $cleaned++;
            }
        }
        return $cleaned;
    }
}
