#!/usr/bin/env php
<?php
/**
 * Simple Process Monitor
 * Usage: php monitor.php [status|clean]
 */

require_once __DIR__ . '/../includes/ProcessManager.php';

$cmd = $argv[1] ?? 'status';

if ($cmd === 'status') {
    echo "Active Cron Locks:\n";
    echo str_repeat("=", 50) . "\n";
    
    $locks = ProcessManager::getAllLocks();
    
    if (empty($locks)) {
        echo "✓ No active locks\n";
    } else {
        foreach ($locks as $lock) {
            $status = $lock['running'] ? '🟢 RUNNING' : '🔴 STALE';
            echo "Job: {$lock['job']}\n";
            echo "PID: {$lock['pid']}\n";
            echo "Status: $status\n";
            echo "Age: {$lock['age']}s\n";
            echo str_repeat("-", 50) . "\n";
        }
    }
} elseif ($cmd === 'clean') {
    $cleaned = ProcessManager::cleanStale();
    echo "✓ Cleaned $cleaned stale lock(s)\n";
} else {
    echo "Usage: php monitor.php [status|clean]\n";
}
