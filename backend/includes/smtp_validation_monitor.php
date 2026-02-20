#!/usr/bin/env php
<?php
/**
 * SMTP Validation System Monitor
 * Real-time monitoring for high-performance email validation
 * Usage: php smtp_validation_monitor.php [--json] [--watch]
 */

date_default_timezone_set('Asia/Kolkata');

if (php_sapi_name() !== 'cli') {
    die("CLI only\n");
}

require_once __DIR__ . '/../config/db.php';

// Parse command-line arguments
$options = getopt('', ['json', 'watch', 'worker-id:']);
$jsonOutput = isset($options['json']);
$watchMode = isset($options['watch']);
$workerId = $options['worker-id'] ?? null;

// ANSI color codes for terminal output
define('COLOR_RESET', "\033[0m");
define('COLOR_GREEN', "\033[32m");
define('COLOR_YELLOW', "\033[33m");
define('COLOR_RED', "\033[31m");
define('COLOR_BLUE', "\033[34m");
define('COLOR_CYAN', "\033[36m");

/**
 * Get overall system statistics
 */
function get_system_stats($conn, $workerId = null) {
    $whereClause = $workerId ? "WHERE worker_id = " . intval($workerId) : "";
    
    // Total emails statistics
    $query = "
        SELECT 
            COUNT(*) as total_emails,
            SUM(CASE WHEN domain_processed = 1 THEN 1 ELSE 0 END) as processed,
            SUM(CASE WHEN domain_processed = 0 THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN domain_status = 1 AND domain_processed = 1 THEN 1 ELSE 0 END) as valid,
            SUM(CASE WHEN domain_status = 0 AND domain_processed = 1 THEN 1 ELSE 0 END) as invalid
        FROM emails
        $whereClause
    ";
    
    $result = $conn->query($query);
    return $result ? $result->fetch_assoc() : null;
}

/**
 * Get per-user statistics
 */
function get_user_stats($conn, $workerId = null) {
    $whereClause = $workerId ? "AND e.worker_id = " . intval($workerId) : "";
    
    $query = "
        SELECT 
            COALESCE(e.user_id, cl.user_id) as user_id,
            COUNT(*) as total,
            SUM(CASE WHEN e.domain_processed = 1 THEN 1 ELSE 0 END) as processed,
            SUM(CASE WHEN e.domain_processed = 0 THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN e.domain_status = 1 AND e.domain_processed = 1 THEN 1 ELSE 0 END) as valid,
            SUM(CASE WHEN e.domain_status = 0 AND e.domain_processed = 1 THEN 1 ELSE 0 END) as invalid
        FROM emails e
        LEFT JOIN csv_list cl ON e.csv_list_id = cl.id
        WHERE 1=1 $whereClause
        GROUP BY COALESCE(e.user_id, cl.user_id)
        HAVING user_id IS NOT NULL
        ORDER BY pending DESC, total DESC
    ";
    
    $result = $conn->query($query);
    $users = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    }
    
    return $users;
}

/**
 * Get CSV list statistics
 */
function get_csv_list_stats($conn) {
    $query = "
        SELECT 
            status,
            COUNT(*) as count,
            SUM(total_emails) as total_emails
        FROM csv_list
        GROUP BY status
    ";
    
    $result = $conn->query($query);
    $stats = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $stats[$row['status']] = [
                'count' => $row['count'],
                'emails' => $row['total_emails']
            ];
        }
    }
    
    return $stats;
}

/**
 * Get running worker processes
 */
function get_running_workers() {
    $output = [];
    exec('ps aux | grep "smtp_worker_parallel.php" | grep -v grep', $output);
    
    $workers = [];
    foreach ($output as $line) {
        if (preg_match('/php.*smtp_worker_parallel\.php\s+(\d+)\s+(\S+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)/', $line, $matches)) {
            $workers[] = [
                'worker_id' => $matches[1],
                'batch_id' => $matches[2],
                'start_index' => $matches[3],
                'end_index' => $matches[4],
                'user_id' => $matches[5],
                'server_worker_id' => $matches[6]
            ];
        }
    }
    
    return $workers;
}

/**
 * Get batch directories status
 */
function get_batch_status() {
    $batchDirs = glob('/tmp/bulk_workers_*/');
    $batches = [];
    
    foreach ($batchDirs as $dir) {
        $batchId = basename($dir);
        $resultFiles = glob($dir . 'worker_*.json');
        $expectedFile = $dir . 'worker_count.txt';
        $expected = file_exists($expectedFile) ? intval(file_get_contents($expectedFile)) : 0;
        
        $batches[] = [
            'batch_id' => $batchId,
            'completed_workers' => count($resultFiles),
            'expected_workers' => $expected,
            'is_complete' => count($resultFiles) >= $expected && $expected > 0
        ];
    }
    
    return $batches;
}

/**
 * Get database connection statistics
 */
function get_db_connection_stats($conn) {
    $query = "SHOW STATUS LIKE 'Threads_%'";
    $result = $conn->query($query);
    
    $stats = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $stats[$row['Variable_name']] = $row['Value'];
        }
    }
    
    return $stats;
}

/**
 * Calculate processing speed
 */
function calculate_speed($conn, $minutes = 5) {
    $query = "
        SELECT COUNT(*) as recent_processed
        FROM emails
        WHERE domain_processed = 1
        AND updated_at >= DATE_SUB(NOW(), INTERVAL $minutes MINUTE)
    ";
    
    $result = $conn->query($query);
    if ($result) {
        $row = $result->fetch_assoc();
        $processed = $row['recent_processed'];
        return [
            'emails_per_minute' => round($processed / $minutes, 2),
            'emails_per_hour' => round(($processed / $minutes) * 60, 2),
            'period_minutes' => $minutes
        ];
    }
    
    return null;
}

/**
 * Format output as table
 */
function print_table($title, $headers, $rows) {
    echo "\n" . COLOR_CYAN . "=== $title ===" . COLOR_RESET . "\n";
    
    // Print headers
    echo COLOR_YELLOW;
    foreach ($headers as $header) {
        echo str_pad($header, 20) . " ";
    }
    echo COLOR_RESET . "\n";
    
    // Print separator
    echo str_repeat("-", count($headers) * 21) . "\n";
    
    // Print rows
    foreach ($rows as $row) {
        foreach ($row as $cell) {
            echo str_pad($cell, 20) . " ";
        }
        echo "\n";
    }
}

/**
 * Display monitoring dashboard
 */
function display_dashboard($conn, $workerId) {
    // Clear screen in watch mode
    if (isset($GLOBALS['watchMode']) && $GLOBALS['watchMode']) {
        system('clear');
    }
    
    echo COLOR_BLUE . "\n╔══════════════════════════════════════════════════════════════╗\n";
    echo "║     HIGH-PERFORMANCE SMTP VALIDATION MONITOR v2.0           ║\n";
    echo "╚══════════════════════════════════════════════════════════════╝\n" . COLOR_RESET;
    echo "Time: " . date('Y-m-d H:i:s') . "\n";
    if ($workerId) {
        echo "Worker ID Filter: $workerId\n";
    }
    
    // System statistics
    $systemStats = get_system_stats($conn, $workerId);
    if ($systemStats) {
        $total = $systemStats['total_emails'];
        $processed = $systemStats['processed'];
        $pending = $systemStats['pending'];
        $progressPct = $total > 0 ? round(($processed / $total) * 100, 2) : 0;
        
        echo "\n" . COLOR_CYAN . "OVERALL SYSTEM STATUS:" . COLOR_RESET . "\n";
        echo "Total Emails    : " . number_format($total) . "\n";
        echo "Processed       : " . COLOR_GREEN . number_format($processed) . COLOR_RESET . " ($progressPct%)\n";
        echo "Pending         : " . COLOR_YELLOW . number_format($pending) . COLOR_RESET . "\n";
        echo "Valid           : " . COLOR_GREEN . number_format($systemStats['valid']) . COLOR_RESET . "\n";
        echo "Invalid         : " . COLOR_RED . number_format($systemStats['invalid']) . COLOR_RESET . "\n";
        
        // Progress bar
        $barWidth = 50;
        $filledWidth = round(($processed / max($total, 1)) * $barWidth);
        echo "\nProgress: [";
        echo COLOR_GREEN . str_repeat("█", $filledWidth) . COLOR_RESET;
        echo str_repeat("░", $barWidth - $filledWidth);
        echo "] $progressPct%\n";
    }
    
    // Processing speed
    $speed = calculate_speed($conn, 5);
    if ($speed) {
        echo "\n" . COLOR_CYAN . "PROCESSING SPEED (Last 5 min):" . COLOR_RESET . "\n";
        echo "Emails/minute   : " . number_format($speed['emails_per_minute']) . "\n";
        echo "Emails/hour     : " . number_format($speed['emails_per_hour']) . "\n";
        
        // ETA calculation
        if ($systemStats && $speed['emails_per_minute'] > 0) {
            $remaining = $systemStats['pending'];
            $etaMinutes = $remaining / $speed['emails_per_minute'];
            $etaHours = floor($etaMinutes / 60);
            $etaMins = round($etaMinutes % 60);
            echo "ETA             : ~{$etaHours}h {$etaMins}m\n";
        }
    }
    
    // Per-user statistics
    $userStats = get_user_stats($conn, $workerId);
    if (!empty($userStats)) {
        $rows = [];
        foreach ($userStats as $user) {
            $pct = $user['total'] > 0 ? round(($user['processed'] / $user['total']) * 100, 1) : 0;
            $rows[] = [
                "User {$user['user_id']}",
                number_format($user['total']),
                number_format($user['processed']),
                number_format($user['pending']),
                "$pct%"
            ];
        }
        print_table("USER STATISTICS", ["User", "Total", "Processed", "Pending", "Progress"], $rows);
    }
    
    // CSV List statistics
    $csvStats = get_csv_list_stats($conn);
    if (!empty($csvStats)) {
        echo "\n" . COLOR_CYAN . "CSV LIST STATUS:" . COLOR_RESET . "\n";
        foreach ($csvStats as $status => $data) {
            $color = $status == 'completed' ? COLOR_GREEN : ($status == 'running' ? COLOR_YELLOW : COLOR_RESET);
            echo $color . ucfirst($status) . ": " . number_format($data['count']) . " lists (" . number_format($data['emails']) . " emails)" . COLOR_RESET . "\n";
        }
    }
    
    // Running workers
    $workers = get_running_workers();
    if (!empty($workers)) {
        echo "\n" . COLOR_CYAN . "ACTIVE WORKERS: " . count($workers) . COLOR_RESET . "\n";
        foreach (array_slice($workers, 0, 10) as $worker) {
            echo "  Worker {$worker['worker_id']} | User {$worker['user_id']} | Emails {$worker['start_index']}-{$worker['end_index']}\n";
        }
        if (count($workers) > 10) {
            echo "  ... and " . (count($workers) - 10) . " more\n";
        }
    }
    
    // Batch status
    $batches = get_batch_status();
    if (!empty($batches)) {
        echo "\n" . COLOR_CYAN . "BATCH STATUS: " . count($batches) . " active batches" . COLOR_RESET . "\n";
        foreach (array_slice($batches, 0, 5) as $batch) {
            $status = $batch['is_complete'] ? COLOR_GREEN . "COMPLETE" : COLOR_YELLOW . "PROCESSING";
            echo "  " . substr($batch['batch_id'], 0, 20) . "... : {$batch['completed_workers']}/{$batch['expected_workers']} workers $status" . COLOR_RESET . "\n";
        }
    }
    
    // Database connections
    $dbStats = get_db_connection_stats($conn);
    if (!empty($dbStats)) {
        echo "\n" . COLOR_CYAN . "DATABASE CONNECTIONS:" . COLOR_RESET . "\n";
        echo "Connected       : " . ($dbStats['Threads_connected'] ?? 'N/A') . "\n";
        echo "Running         : " . ($dbStats['Threads_running'] ?? 'N/A') . "\n";
        echo "Created         : " . ($dbStats['Threads_created'] ?? 'N/A') . "\n";
    }
    
    // Memory usage
    $memUsage = round(memory_get_usage() / 1024 / 1024, 2);
    $memPeak = round(memory_get_peak_usage() / 1024 / 1024, 2);
    echo "\n" . COLOR_CYAN . "MONITOR MEMORY:" . COLOR_RESET . " Current: {$memUsage}MB | Peak: {$memPeak}MB\n";
    
    if ($GLOBALS['watchMode']) {
        echo "\n" . COLOR_YELLOW . "[Press Ctrl+C to exit watch mode]" . COLOR_RESET . "\n";
    }
}

// Main execution
try {
    if ($watchMode) {
        echo "Starting watch mode (updates every 5 seconds)...\n";
        sleep(1);
        
        while (true) {
            display_dashboard($conn, $workerId);
            sleep(5);
        }
    } elseif ($jsonOutput) {
        // JSON output for API/programmatic use
        $data = [
            'timestamp' => date('Y-m-d H:i:s'),
            'system_stats' => get_system_stats($conn, $workerId),
            'user_stats' => get_user_stats($conn, $workerId),
            'csv_stats' => get_csv_list_stats($conn),
            'workers' => get_running_workers(),
            'batches' => get_batch_status(),
            'speed' => calculate_speed($conn, 5),
            'db_connections' => get_db_connection_stats($conn)
        ];
        
        echo json_encode($data, JSON_PRETTY_PRINT) . "\n";
    } else {
        // Single display
        display_dashboard($conn, $workerId);
    }
} catch (Exception $e) {
    echo COLOR_RED . "ERROR: " . $e->getMessage() . COLOR_RESET . "\n";
    exit(1);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
