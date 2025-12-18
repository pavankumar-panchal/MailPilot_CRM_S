<?php
/**
 * PHP Function Compatibility Checker for MailPilot CRM
 * 
 * This script checks if all required PHP functions, extensions, and configurations
 * are available on the server for the MailPilot CRM project to work properly.
 */

header('Content-Type: text/plain; charset=utf-8');

echo "=================================================================\n";
echo "   MailPilot CRM - PHP Compatibility Check\n";
echo "=================================================================\n\n";

echo "Server: " . $_SERVER['SERVER_SOFTWARE'] . "\n";
echo "PHP Version: " . phpversion() . "\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

$errors = [];
$warnings = [];
$success = [];

// ===== REQUIRED PHP EXTENSIONS =====
echo "--- PHP Extensions ---\n";
$required_extensions = array(
    'mysqli' => 'Database connection',
    'json' => 'JSON encoding/decoding',
    'mbstring' => 'Multibyte string handling',
    'imap' => 'Email processing (IMAP)',
    'openssl' => 'SSL/TLS encryption',
    'curl' => 'HTTP requests',
    'fileinfo' => 'File type detection',
    'zip' => 'Archive handling'
);

foreach ($required_extensions as $ext => $purpose) {
    if (extension_loaded($ext)) {
        $success[] = "✓ $ext - $purpose";
        echo "✓ $ext - $purpose\n";
    } else {
        $errors[] = "✗ $ext - $purpose (REQUIRED)";
        echo "✗ $ext - $purpose (REQUIRED)\n";
    }
}
echo "\n";

// ===== CORE PHP FUNCTIONS =====
echo "--- Core PHP Functions ---\n";
$core_functions = array(
    // String functions
    'strlen', 'substr', 'strpos', 'str_replace', 'trim', 'explode', 'implode',
    'mb_convert_encoding', 'mb_substr', 'strip_tags', 'preg_split', 'preg_replace', 'preg_match',
    
    // Array functions
    'array_slice', 'array_merge', 'count', 'in_array', 'array_filter',
    
    // File functions
    'file_exists', 'is_dir', 'mkdir', 'file_get_contents', 'file_put_contents',
    'move_uploaded_file', 'unlink', 'chmod', 'basename', 'dirname', 'pathinfo',
    
    // JSON functions
    'json_encode', 'json_decode',
    
    // Time functions
    'time', 'date', 'microtime', 'strtotime', 'sleep', 'usleep',
    
    // Math functions
    'round', 'ceil', 'floor', 'max', 'min', 'intval', 'floatval',
    
    // Variable functions
    'isset', 'empty', 'is_numeric', 'is_array', 'is_string', 'gettype',
    'filter_var',
    
    // Output functions
    'header', 'http_response_code', 'echo', 'exit',
    
    // Error handling
    'error_log', 'set_error_handler', 'set_exception_handler',
    'error_reporting', 'ini_set', 'ini_get',
    
    // Execution functions
    'shell_exec', 'exec', 'escapeshellarg', 'escapeshellcmd'
);

$missing_functions = [];
foreach ($core_functions as $func) {
    if (!function_exists($func)) {
        $missing_functions[] = $func;
    }
}

if (empty($missing_functions)) {
    echo "✓ All core PHP functions available\n";
} else {
    $errors[] = "✗ Missing functions: " . implode(', ', $missing_functions);
    echo "✗ Missing functions: " . implode(', ', $missing_functions) . "\n";
}
echo "\n";

// ===== MYSQLI FUNCTIONS =====
echo "--- MySQLi Functions ---\n";
$mysqli_functions = array(
    'mysqli_connect', 'mysqli_query', 'mysqli_fetch_assoc', 'mysqli_fetch_all',
    'mysqli_num_rows', 'mysqli_affected_rows', 'mysqli_insert_id',
    'mysqli_real_escape_string', 'mysqli_set_charset', 'mysqli_close',
    'mysqli_begin_transaction', 'mysqli_commit', 'mysqli_rollback'
);

$missing_mysqli = [];
foreach ($mysqli_functions as $func) {
    if (!function_exists($func)) {
        $missing_mysqli[] = $func;
    }
}

if (empty($missing_mysqli)) {
    echo "✓ All MySQLi functions available\n";
} else {
    $errors[] = "✗ Missing MySQLi: " . implode(', ', $missing_mysqli);
    echo "✗ Missing MySQLi: " . implode(', ', $missing_mysqli) . "\n";
}
echo "\n";

// ===== IMAP FUNCTIONS (for email processing) =====
echo "--- IMAP Functions (Email Processing) ---\n";
$imap_functions = array(
    'imap_open', 'imap_close', 'imap_search', 'imap_fetch_overview',
    'imap_body', 'imap_headerinfo', 'imap_delete', 'imap_expunge',
    'imap_setflag_full', 'imap_clearflag_full', 'imap_uid'
);

$missing_imap = [];
foreach ($imap_functions as $func) {
    if (!function_exists($func)) {
        $missing_imap[] = $func;
    }
}

if (empty($missing_imap)) {
    echo "✓ All IMAP functions available\n";
} else {
    $errors[] = "✗ Missing IMAP: " . implode(', ', $missing_imap);
    echo "✗ Missing IMAP: " . implode(', ', $missing_imap) . "\n";
}
echo "\n";

// ===== NETWORK FUNCTIONS (for SMTP verification) =====
echo "--- Network Functions (SMTP Verification) ---\n";
$network_functions = array(
    'stream_socket_client', 'stream_set_timeout', 'fgets', 'fputs', 'fclose',
    'gethostbyname', 'getmxrr', 'dns_get_record'
);

$missing_network = [];
foreach ($network_functions as $func) {
    if (!function_exists($func)) {
        $missing_network[] = $func;
    }
}

if (empty($missing_network)) {
    echo "✓ All network functions available\n";
} else {
    $errors[] = "✗ Missing network: " . implode(', ', $missing_network);
    echo "✗ Missing network: " . implode(', ', $missing_network) . "\n";
}
echo "\n";

// ===== OPTIONAL SYSTEM FUNCTIONS =====
echo "--- Optional System Functions (Process Management) ---\n";
$optional_functions = array(
    'posix_kill' => 'Process signaling',
    'proc_open' => 'Process execution',
    'pcntl_fork' => 'Process forking'
);

foreach ($optional_functions as $func => $purpose) {
    if (function_exists($func)) {
        $success[] = "✓ $func - $purpose (available)";
        echo "✓ $func - $purpose (available)\n";
    } else {
        $warnings[] = "⚠ $func - $purpose (optional, not critical)";
        echo "⚠ $func - $purpose (optional, not critical)\n";
    }
}
echo "\n";

// ===== PHP CONFIGURATION =====
echo "--- PHP Configuration ---\n";

$config_checks = array(
    'file_uploads' => array('required' => true, 'expected' => '1', 'name' => 'File uploads'),
    'max_execution_time' => array('required' => false, 'expected' => '0', 'name' => 'Max execution time (0=unlimited)'),
    'memory_limit' => array('required' => false, 'expected' => '512M', 'name' => 'Memory limit'),
    'post_max_size' => array('required' => false, 'expected' => '64M', 'name' => 'POST max size'),
    'upload_max_filesize' => array('required' => false, 'expected' => '64M', 'name' => 'Upload max filesize'),
    'display_errors' => array('required' => false, 'expected' => '0', 'name' => 'Display errors (should be Off in production)'),
    'log_errors' => array('required' => false, 'expected' => '1', 'name' => 'Log errors')
);

foreach ($config_checks as $key => $check) {
    $value = ini_get($key);
    $status = $value ? $value : 'Not set';
    
    if ($check['required'] && ($value === false || $value === '')) {
        $errors[] = "✗ $key: $status (REQUIRED)";
        echo "✗ {$check['name']}: $status (REQUIRED)\n";
    } else {
        echo "  {$check['name']}: $status (recommended: {$check['expected']})\n";
    }
}
echo "\n";

// ===== FILE PERMISSIONS =====
echo "--- File Permissions ---\n";
$writable_dirs = array(
    __DIR__ . '/../logs',
    __DIR__ . '/../tmp',
    __DIR__ . '/../storage/images',
    __DIR__ . '/../storage/attachments'
);

foreach ($writable_dirs as $dir) {
    if (file_exists($dir)) {
        if (is_writable($dir)) {
            echo "✓ $dir (writable)\n";
        } else {
            $errors[] = "✗ $dir (NOT writable)";
            echo "✗ $dir (NOT writable)\n";
        }
    } else {
        $warnings[] = "⚠ $dir (does not exist, will be created automatically)";
        echo "⚠ $dir (does not exist, will be created automatically)\n";
    }
}
echo "\n";

// ===== DATABASE CONNECTION TEST =====
echo "--- Database Connection Test ---\n";
try {
    require_once __DIR__ . '/../config/db.php';
    if (isset($conn) && $conn instanceof mysqli) {
        if ($conn->connect_error) {
            $errors[] = "✗ Database connection failed: " . $conn->connect_error;
            echo "✗ Database connection failed: " . $conn->connect_error . "\n";
        } else {
            echo "✓ Database connection successful\n";
            echo "  - Database: " . ($conn->query("SELECT DATABASE()")->fetch_row()[0] ?? 'unknown') . "\n";
            echo "  - MySQL Version: " . $conn->server_info . "\n";
            $conn->close();
        }
    } else {
        $warnings[] = "⚠ Could not test database connection (config may be missing)";
        echo "⚠ Could not test database connection\n";
    }
} catch (Exception $e) {
    $errors[] = "✗ Database test failed: " . $e->getMessage();
    echo "✗ Database test failed: " . $e->getMessage() . "\n";
}
echo "\n";

// ===== SUMMARY =====
echo "=================================================================\n";
echo "   SUMMARY\n";
echo "=================================================================\n\n";

if (empty($errors)) {
    echo "✓✓✓ ALL CRITICAL REQUIREMENTS MET! ✓✓✓\n";
    echo "Your server is fully compatible with MailPilot CRM.\n\n";
} else {
    echo "✗✗✗ CRITICAL ISSUES FOUND ✗✗✗\n\n";
    echo "The following issues must be resolved:\n";
    foreach ($errors as $error) {
        echo "$error\n";
    }
    echo "\n";
}

if (!empty($warnings)) {
    echo "⚠ WARNINGS (Optional/Non-Critical):\n";
    foreach ($warnings as $warning) {
        echo "$warning\n";
    }
    echo "\n";
}

echo "Total Errors: " . count($errors) . "\n";
echo "Total Warnings: " . count($warnings) . "\n";
echo "\n";

if (!empty($errors)) {
    echo "Please contact your hosting provider to enable the missing features.\n";
    exit(1);
} else {
    echo "System is ready for deployment!\n";
    exit(0);
}
?>
