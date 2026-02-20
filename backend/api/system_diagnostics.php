<?php
/**
 * System Diagnostics - Find Why Frontend Freezes
 * Access: /api/system_diagnostics.php
 * 
 * This script checks:
 * - PHP-FPM worker status
 * - Database connection pool
 * - Active queries
 * - Server load
 * - Campaign worker count
 * - Lock contention
 */

// Prevent any HTML output
ob_start();

// Set headers immediately
header('Content-Type: application/json');
header('Cache-Control: no-cache');
header('Access-Control-Allow-Origin: *');

error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors as HTML
ini_set('max_execution_time', 5);

$diagnostics = [
    'timestamp' => date('Y-m-d H:i:s'),
    'server' => [],
    'php' => [],
    'database' => [],
    'workers' => [],
    'locks' => [],
    'health' => 'unknown'
];

// ==== SERVER METRICS ====
try {
    // Load average
    if (file_exists('/proc/loadavg')) {
        $load = file_get_contents('/proc/loadavg');
        $loadParts = explode(' ', $load);
        $diagnostics['server']['load_average'] = [
            '1min' => (float)$loadParts[0],
            '5min' => (float)$loadParts[1],
            '15min' => (float)$loadParts[2]
        ];
    }
    
    // Memory usage
    if (file_exists('/proc/meminfo')) {
        $meminfo = file_get_contents('/proc/meminfo');
        preg_match('/MemTotal:\s+(\d+)/', $meminfo, $total);
        preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $available);
        
        $totalMB = round($total[1] / 1024);
        $availableMB = round($available[1] / 1024);
        $usedMB = $totalMB - $availableMB;
        $usedPercent = round(($usedMB / $totalMB) * 100, 1);
        
        $diagnostics['server']['memory'] = [
            'total_mb' => $totalMB,
            'used_mb' => $usedMB,
            'available_mb' => $availableMB,
            'used_percent' => $usedPercent
        ];
    }
    
    // CPU count
    $diagnostics['server']['cpu_count'] = (int)shell_exec('nproc');
    
} catch (Exception $e) {
    $diagnostics['server']['error'] = $e->getMessage();
}

// ==== PHP METRICS ====
$diagnostics['php']['version'] = PHP_VERSION;
$diagnostics['php']['sapi'] = php_sapi_name();
$diagnostics['php']['memory_limit'] = ini_get('memory_limit');
$diagnostics['php']['max_execution_time'] = ini_get('max_execution_time');
$diagnostics['php']['memory_usage_mb'] = round(memory_get_usage(true) / 1024 / 1024, 2);

// Count PHP processes
exec('ps aux | grep php | grep -v grep | wc -l', $phpProcessCount);
$diagnostics['php']['active_processes'] = (int)($phpProcessCount[0] ?? 0);

// PHP-FPM pool status (if available)
$fpmStatus = @file_get_contents('http://localhost/status?json');
if ($fpmStatus) {
    $diagnostics['php']['fpm'] = json_decode($fpmStatus, true);
}

// ==== DATABASE METRICS ====
try {
    $dbPath = __DIR__ . '/../config/db.php';
    if (!file_exists($dbPath)) {
        throw new Exception('Database config not found');
    }
    
    require_once $dbPath;
    
    if (!isset($conn) || !$conn) {
        throw new Exception('Database connection failed');
    }
    
    $diagnostics['database']['connected'] = $conn->ping();
    
    // Get MySQL variables
    $result = $conn->query("SHOW VARIABLES LIKE 'max_connections'");
    if ($result) {
        $row = $result->fetch_assoc();
        $diagnostics['database']['max_connections'] = (int)$row['Value'];
    }
    
    // Get current connections
    $result = $conn->query("SHOW STATUS LIKE 'Threads_connected'");
    if ($result) {
        $row = $result->fetch_assoc();
        $diagnostics['database']['active_connections'] = (int)$row['Value'];
    }
    
    // Get processlist
    $result = $conn->query("SELECT COUNT(*) as cnt FROM information_schema.processlist");
    if ($result) {
        $row = $result->fetch_assoc();
        $diagnostics['database']['processlist_count'] = (int)$row['cnt'];
    }
    
    // Get long-running queries
    $result = $conn->query("
        SELECT 
            ID,
            USER,
            HOST,
            DB,
            COMMAND,
            TIME as duration_sec,
            STATE,
            LEFT(INFO, 100) as query_preview
        FROM information_schema.processlist 
        WHERE COMMAND != 'Sleep' 
        AND TIME > 2
        ORDER BY TIME DESC 
        LIMIT 10
    ");
    
    $longQueries = [];
    while ($result && $row = $result->fetch_assoc()) {
        $longQueries[] = [
            'id' => $row['ID'],
            'user' => $row['USER'],
            'duration' => (int)$row['duration_sec'],
            'state' => $row['STATE'],
            'query' => $row['query_preview']
        ];
    }
    $diagnostics['database']['long_running_queries'] = $longQueries;
    
    // Check for locked tables
    $result = $conn->query("SHOW OPEN TABLES WHERE In_use > 0");
    $lockedTables = [];
    while ($result && $row = $result->fetch_assoc()) {
        $lockedTables[] = [
            'database' => $row['Database'],
            'table' => $row['Table'],
            'in_use' => (int)$row['In_use']
        ];
    }
    $diagnostics['database']['locked_tables'] = $lockedTables;
    
    // Check InnoDB lock waits
    $result = $conn->query("
        SELECT COUNT(*) as cnt 
        FROM information_schema.innodb_trx 
        WHERE trx_state = 'LOCK WAIT'
    ");
    if ($result) {
        $row = $result->fetch_assoc();
        $diagnostics['database']['lock_wait_count'] = (int)$row['cnt'];
    }
    
} catch (Exception $e) {
    $diagnostics['database']['error'] = $e->getMessage();
}

// ==== CAMPAIGN WORKER STATUS ====
try {
    // Count running campaigns
    $result = $conn->query("SELECT COUNT(*) as cnt FROM campaign_status WHERE status = 'running'");
    if ($result) {
        $row = $result->fetch_assoc();
        $diagnostics['workers']['running_campaigns'] = (int)$row['cnt'];
    }
    
    // Count pending emails
    $result = $conn->query("
        SELECT 
            COUNT(DISTINCT campaign_id) as campaigns_with_pending,
            COUNT(*) as total_pending
        FROM mail_blaster 
        WHERE status IN ('pending', 'processing') 
        OR (status = 'failed' AND attempt_count < 5)
    ");
    if ($result) {
        $row = $result->fetch_assoc();
        $diagnostics['workers']['campaigns_with_pending'] = (int)$row['campaigns_with_pending'];
        $diagnostics['workers']['total_pending_emails'] = (int)$row['total_pending'];
    }
    
    // Count background PHP processes
    exec('ps aux | grep "email_blast" | grep -v grep | wc -l', $workerCount);
    $diagnostics['workers']['background_worker_processes'] = (int)($workerCount[0] ?? 0);
    
} catch (Exception $e) {
    $diagnostics['workers']['error'] = $e->getMessage();
}

// ==== LOCK CONTENTION ====
try {
    $result = $conn->query("
        SELECT 
            TABLE_SCHEMA,
            TABLE_NAME,
            TABLE_ROWS,
            UPDATE_TIME
        FROM information_schema.TABLES 
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME IN ('campaign_status', 'mail_blaster', 'smtp_accounts', 'smtp_usage')
        ORDER BY UPDATE_TIME DESC
    ");
    
    $hotTables = [];
    while ($result && $row = $result->fetch_assoc()) {
        $hotTables[] = [
            'table' => $row['TABLE_NAME'],
            'rows' => (int)$row['TABLE_ROWS'],
            'last_update' => $row['UPDATE_TIME']
        ];
    }
    $diagnostics['locks']['hot_tables'] = $hotTables;
    
} catch (Exception $e) {
    $diagnostics['locks']['error'] = $e->getMessage();
}

// ==== HEALTH ASSESSMENT ====
$issues = [];
$warnings = [];

// Check load average
if (isset($diagnostics['server']['load_average']['1min'])) {
    $cpuCount = $diagnostics['server']['cpu_count'] ?? 1;
    $load = $diagnostics['server']['load_average']['1min'];
    if ($load > $cpuCount * 2) {
        $issues[] = "CRITICAL: Load average ($load) is very high for $cpuCount CPUs";
    } elseif ($load > $cpuCount) {
        $warnings[] = "Load average ($load) is high for $cpuCount CPUs";
    }
}

// Check memory
if (isset($diagnostics['server']['memory']['used_percent'])) {
    $memPercent = $diagnostics['server']['memory']['used_percent'];
    if ($memPercent > 95) {
        $issues[] = "CRITICAL: Memory usage at {$memPercent}%";
    } elseif ($memPercent > 85) {
        $warnings[] = "Memory usage at {$memPercent}%";
    }
}

// Check PHP processes
$phpProcs = $diagnostics['php']['active_processes'] ?? 0;
if ($phpProcs > 50) {
    $issues[] = "CRITICAL: Too many PHP processes ($phpProcs)";
} elseif ($phpProcs > 30) {
    $warnings[] = "Many PHP processes ($phpProcs)";
}

// Check database connections
if (isset($diagnostics['database']['active_connections']) && isset($diagnostics['database']['max_connections'])) {
    $active = $diagnostics['database']['active_connections'];
    $max = $diagnostics['database']['max_connections'];
    $percent = ($active / $max) * 100;
    
    if ($percent > 90) {
        $issues[] = "CRITICAL: Database connections at {$percent}% ($active/$max)";
    } elseif ($percent > 75) {
        $warnings[] = "Database connections at {$percent}% ($active/$max)";
    }
}

// Check long queries
$longQueryCount = count($diagnostics['database']['long_running_queries'] ?? []);
if ($longQueryCount > 5) {
    $issues[] = "CRITICAL: $longQueryCount queries running >2 seconds";
} elseif ($longQueryCount > 2) {
    $warnings[] = "$longQueryCount queries running >2 seconds";
}

// Check locks
if (isset($diagnostics['database']['lock_wait_count']) && $diagnostics['database']['lock_wait_count'] > 0) {
    $lockCount = $diagnostics['database']['lock_wait_count'];
    $issues[] = "CRITICAL: $lockCount transactions waiting for locks";
}

// Check pending emails
$pendingCount = $diagnostics['workers']['total_pending_emails'] ?? 0;
if ($pendingCount > 50000) {
    $warnings[] = "Large email queue: " . number_format($pendingCount) . " pending";
}

// Determine overall health
if (count($issues) > 0) {
    $diagnostics['health'] = 'CRITICAL';
    $diagnostics['health_issues'] = $issues;
} elseif (count($warnings) > 0) {
    $diagnostics['health'] = 'WARNING';
    $diagnostics['health_warnings'] = $warnings;
} else {
    $diagnostics['health'] = 'HEALTHY';
}

// Add recommendations
$diagnostics['recommendations'] = [];
if (count($issues) > 0 || count($warnings) > 0) {
    $diagnostics['recommendations'][] = "Check /backend/logs/slow_api.log for slow API calls";
    $diagnostics['recommendations'][] = "Check /backend/logs/api_lock_timeouts.log for database locks";
    
    if ($phpProcs > 30) {
        $diagnostics['recommendations'][] = "Reduce campaign parallel workers";
        $diagnostics['recommendations'][] = "Add delays between email batches (usleep)";
    }
    
    if ($longQueryCount > 2) {
        $diagnostics['recommendations'][] = "Optimize slow queries shown above";
        $diagnostics['recommendations'][] = "Add database indexes if needed";
    }
}

// Clear any accidental output
ob_end_clean();

echo json_encode($diagnostics, JSON_PRETTY_PRINT);
