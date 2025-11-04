<?php
/**
 * Log Viewer Script
 * Usage: php view_logs.php [options]
 * 
 * Options:
 *   --type=TYPE         Log type: success, failures, smtp, details, campaign (default: all)
 *   --date=DATE         Date filter (Y-m-d format, default: today)
 *   --smtp=ID           SMTP account ID filter (only for smtp logs)
 *   --campaign=ID       Campaign ID filter (only for campaign logs)
 *   --tail=N            Show last N lines (default: 50)
 *   --search=TEXT       Search for text in logs
 */

$logDir = __DIR__ . '/../storage/logs';

// Parse command line arguments
$options = getopt('', [
    'type::',
    'date::',
    'smtp::',
    'campaign::',
    'tail::',
    'search::',
    'help'
]);

if (isset($options['help'])) {
    echo file_get_contents(__FILE__);
    echo "\n\nExample usage:\n";
    echo "  php view_logs.php --type=failures --date=2025-11-03\n";
    echo "  php view_logs.php --type=smtp --smtp=29 --tail=100\n";
    echo "  php view_logs.php --type=campaign --campaign=11\n";
    echo "  php view_logs.php --search='SMTP error'\n";
    exit(0);
}

$type = $options['type'] ?? 'all';
$date = $options['date'] ?? date('Y-m-d');
$smtp_id = $options['smtp'] ?? null;
$campaign_id = $options['campaign'] ?? null;
$tail = isset($options['tail']) ? (int)$options['tail'] : 50;
$search = $options['search'] ?? null;

echo "========================================\n";
echo "Email Campaign Logs Viewer\n";
echo "========================================\n";
echo "Date: $date\n";
echo "Type: $type\n";
if ($search) echo "Search: $search\n";
echo "========================================\n\n";

function readLogFile($file, $tail = 50, $search = null) {
    if (!file_exists($file)) {
        echo "Log file not found: $file\n\n";
        return;
    }
    
    $lines = file($file);
    if ($search) {
        $lines = array_filter($lines, function($line) use ($search) {
            return stripos($line, $search) !== false;
        });
    }
    
    if (count($lines) > $tail) {
        $lines = array_slice($lines, -$tail);
    }
    
    echo "File: " . basename($file) . " (" . count($lines) . " lines)\n";
    echo str_repeat("-", 80) . "\n";
    echo implode("", $lines);
    echo "\n";
}

function listLogFiles($dir, $pattern) {
    $files = glob("$dir/$pattern");
    return $files ?: [];
}

// Show logs based on type
switch ($type) {
    case 'success':
        $file = "$logDir/success_{$date}.log";
        readLogFile($file, $tail, $search);
        break;
        
    case 'failures':
        $file = "$logDir/failures_{$date}.log";
        readLogFile($file, $tail, $search);
        break;
        
    case 'details':
        $file = "$logDir/email_details_{$date}.log";
        echo "Format: Timestamp|Campaign|To|SMTP_ID|SMTP_Email|Status|Error\n\n";
        readLogFile($file, $tail, $search);
        break;
        
    case 'smtp':
        if ($smtp_id) {
            $file = "$logDir/smtp_{$smtp_id}_{$date}.log";
            readLogFile($file, $tail, $search);
        } else {
            $files = listLogFiles($logDir, "smtp_*_{$date}.log");
            if (empty($files)) {
                echo "No SMTP logs found for $date\n";
            } else {
                echo "Available SMTP logs:\n";
                foreach ($files as $file) {
                    if (preg_match('/smtp_(\d+)_/', basename($file), $matches)) {
                        echo "  SMTP ID {$matches[1]}: " . basename($file) . "\n";
                    }
                }
                echo "\nUse --smtp=ID to view specific SMTP logs\n";
            }
        }
        break;
        
    case 'campaign':
        if ($campaign_id) {
            echo "=== Campaign $campaign_id Main Log ===\n";
            readLogFile("$logDir/campaign_{$campaign_id}.log", $tail, $search);
            
            echo "\n=== Campaign $campaign_id Errors ===\n";
            readLogFile("$logDir/campaign_{$campaign_id}_errors.log", $tail, $search);
        } else {
            $files = listLogFiles($logDir, "campaign_*.log");
            if (empty($files)) {
                echo "No campaign logs found\n";
            } else {
                echo "Available campaign logs:\n";
                foreach ($files as $file) {
                    if (preg_match('/campaign_(\d+)\.log$/', basename($file), $matches)) {
                        echo "  Campaign ID {$matches[1]}: " . basename($file) . "\n";
                    }
                }
                echo "\nUse --campaign=ID to view specific campaign logs\n";
            }
        }
        break;
        
    case 'all':
    default:
        echo "=== Success Logs (Today) ===\n";
        readLogFile("$logDir/success_{$date}.log", 20, $search);
        
        echo "\n=== Failure Logs (Today) ===\n";
        readLogFile("$logDir/failures_{$date}.log", 20, $search);
        
        echo "\n=== Email Details (Today) ===\n";
        echo "Format: Timestamp|Campaign|To|SMTP_ID|SMTP_Email|Status|Error\n\n";
        readLogFile("$logDir/email_details_{$date}.log", 20, $search);
        
        echo "\nUse --type=TYPE for specific log type (success, failures, smtp, details, campaign)\n";
        break;
}

echo "\n========================================\n";
echo "Summary Statistics for $date\n";
echo "========================================\n";

// Count successes and failures
$successFile = "$logDir/success_{$date}.log";
$failureFile = "$logDir/failures_{$date}.log";

if (file_exists($successFile)) {
    $successCount = count(file($successFile));
    echo "✓ Successful sends: $successCount\n";
}

if (file_exists($failureFile)) {
    $failureCount = count(file($failureFile));
    echo "✗ Failed sends: $failureCount\n";
}

// Show most used SMTP accounts
$detailsFile = "$logDir/email_details_{$date}.log";
if (file_exists($detailsFile)) {
    $smtpStats = [];
    $lines = file($detailsFile);
    foreach ($lines as $line) {
        $parts = explode('|', $line);
        if (count($parts) >= 5) {
            $smtpEmail = trim($parts[4]);
            $status = trim($parts[5]);
            
            if (!isset($smtpStats[$smtpEmail])) {
                $smtpStats[$smtpEmail] = ['success' => 0, 'failed' => 0];
            }
            $smtpStats[$smtpEmail][$status]++;
        }
    }
    
    if (!empty($smtpStats)) {
        echo "\nSMTP Account Usage:\n";
        arsort($smtpStats);
        foreach (array_slice($smtpStats, 0, 10) as $smtp => $stats) {
            $total = $stats['success'] + $stats['failed'];
            echo "  $smtp: $total emails (✓{$stats['success']} ✗{$stats['failed']})\n";
        }
    }
}

echo "========================================\n";
