<?php
error_reporting(0);

// Use Indian Standard Time across all PHP processes
date_default_timezone_set('Asia/Kolkata');

// Auto-detect environment based on server name or hostname
$isProduction = false;

// Check SERVER_NAME (for web requests)
if (isset($_SERVER['SERVER_NAME']) && strpos($_SERVER['SERVER_NAME'], 'payrollsoft.in') !== false) {
    $isProduction = true;
}                           

// Check hostname (for CLI/cron jobs)
if (!$isProduction && php_sapi_name() === 'cli') {
    $hostname = gethostname();
    if ($hostname && strpos($hostname, 'payrollsoft') !== false) {
        $isProduction = true;
    }
    // Also check if we can detect production by file path
    if (!$isProduction && strpos(__DIR__, 'httpdocs') !== false) {
        $isProduction = true;
    }
}

// Detect which physical server we're on
$server1_ip = '174.141.233.174';
$server2_ip = '207.244.80.245';

// Check if we're running on Server 1 or Server 2
$currentServerIp = gethostbyname(gethostname());
$isServer1 = ($currentServerIp === $server1_ip || @file_exists('/var/www/vhosts/payrollsoft.in'));
$isServer2 = ($currentServerIp === $server2_ip || @file_exists('/var/www/vhosts/relyonmail.xyz'));

// Auto-detect: If running locally (XAMPP), default to Server 1 behavior
if (!$isServer1 && !$isServer2) {
    $isServer1 = true; // Local dev connects to remote Server 1
}

// Log server detection for verification
if (php_sapi_name() === 'cli') {
    $server_location = $isServer1 ? 'SERVER 1' : ($isServer2 ? 'SERVER 2' : 'UNKNOWN');
    error_log("[DB] Running on: $server_location (IP: $currentServerIp)");
}

if ($isProduction) {
    // PRODUCTION: Determine DB host based on which server we're on
    if ($isServer1) {
        // Running ON Server 1 - use localhost for Server 1 DB
        $dbConfig = [
            'host' => '127.0.0.1',  // Local Server 1 DB
            'username' => 'email_id',
            'password' => '55y60jgW*',
            'name' => 'email_id',
            'port' => 3306
        ];
    } else {
        // Running ON Server 2 - connect remotely to Server 1 DB
        $dbConfig = [
            'host' => '174.141.233.174',  // Remote Server 1 DB
            'username' => 'email_id',
            'password' => '55y60jgW*',
            'name' => 'email_id',
            'port' => 3306
        ];
        if (php_sapi_name() === 'cli') {
            error_log("[DB] SERVER 2 detected → Connecting to SERVER 1 remotely at 174.141.233.174");
        }
    }
} else {
    // LOCAL DEVELOPMENT (XAMPP) - connect remotely to Server 1 DB
    $dbConfig = [
        'host' => '174.141.233.174',  // Remote Server 1 DB
        'username' => 'email_id',
        'password' => '55y60jgW*',
        'name' => 'email_id',
        'port' => 3306
    ];
}

// error_log("Database config loaded for: " . ($isProduction ? 'PRODUCTION' : 'LOCALHOST') . " - DB: " . $dbConfig['name'] . " (CLI: " . (php_sapi_name() === 'cli' ? 'YES' : 'NO') . ")");

// Determine if this is a campaign background process
$phpSelf = isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : '';
$scriptFilename = isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : '';
$isCampaignProcess = php_sapi_name() === 'cli' && (
    strpos($phpSelf, 'campaign_cron.php') !== false ||
    strpos($phpSelf, 'email_blast') !== false ||
    strpos($phpSelf, 'worker') !== false ||
    basename($scriptFilename, '.php') === 'campaign_cron'
);

// DO NOT use persistent connections for campaign processes
// This prevents them from exhausting the connection pool
// Web processes get their own connection pool
$host = $dbConfig['host'];
// Campaign processes use regular connections to avoid pool exhaustion
// if ($isCampaignProcess) {
//     $host = 'p:' . $host; // DISABLED - causes web slowdown
// }

// Initialize connection with options
$conn = mysqli_init();
if (!$conn) {
    die('mysqli_init failed');
}

// Set connection timeout BEFORE connecting
if ($isCampaignProcess) {
    $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3); // Faster timeout for background
} else {
    $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5); // Normal for web
}

// Now connect
if (!$conn->real_connect($host, $dbConfig['username'], $dbConfig['password'], $dbConfig['name'], $dbConfig['port'])) {
    die("Connection failed: " . mysqli_connect_error());
}

// Check connection with detailed error logging
if ($conn->connect_error) {
    $error_msg = date('[Y-m-d H:i:s] ') . "Database Connection Failed\n";
    $error_msg .= "Error: " . $conn->connect_error . "\n";
    $error_msg .= "Host: " . $dbConfig['host'] . "\n";
    $error_msg .= "Username: " . $dbConfig['username'] . "\n";
    $error_msg .= "Database: " . $dbConfig['name'] . "\n";
    $error_msg .= str_repeat('-', 80) . "\n";

    // Log to file
    @error_log($error_msg, 3, __DIR__ . '/../logs/db_error.log');

    // Return JSON error for API calls
    $requestUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    if (strpos($requestUri, '/api/') !== false) {
        header('Content-Type: application/json');
        die(json_encode([
            'success' => false,
            'message' => 'Database connection failed',
            'error' => $conn->connect_error
        ]));
    } else {
        die("Database connection failed: " . $conn->connect_error);
    }
}

// Set proper character set for utf8mb4 support
$conn->set_charset("utf8mb4");

// Log slow/locked queries for debugging frontend freezes
$GLOBALS['db_query_start_time'] = microtime(true);
register_shutdown_function(function() use ($conn, $isCampaignProcess) {
    if (!$isCampaignProcess && isset($GLOBALS['db_query_start_time'])) {
        $duration = microtime(true) - $GLOBALS['db_query_start_time'];
        if ($duration > 3) { // Log queries taking > 3 seconds
            $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'unknown';
            $log = date('[Y-m-d H:i:s]') . " SLOW API: {$uri} took " . number_format($duration, 2) . "s\n";
            // @file_put_contents(__DIR__ . '/../logs/slow_api.log', $log, FILE_APPEND); // Disabled
        }
    }
});

// === RESOURCE OPTIMIZATION: Prevent campaign processes from blocking frontend ===
if ($isCampaignProcess) {
    // Campaign processes: LOWEST priority, fail fast to never block web queries
    try {
        $conn->query("SET SESSION sql_low_priority_updates = 1"); // Low priority for writes
    } catch (mysqli_sql_exception $e) {
        // Ignore if not supported
    }
    
    // INCREASED to 10 seconds to handle high concurrency (was 1 second - too short!)
    try {
        $conn->query("SET SESSION innodb_lock_wait_timeout = 10"); // Wait up to 10 seconds with retry logic
    } catch (mysqli_sql_exception $e) {
        // Ignore if not supported
    }
    
    // FIX: transaction_isolation vs tx_isolation (version compatibility)
    // MySQL >= 5.7.20, MariaDB >= 10.2.2: transaction_isolation
    // Older versions: tx_isolation
    // PHP 8.1+ throws exceptions, so use try-catch
    try {
        $conn->query("SET SESSION transaction_isolation = 'READ-COMMITTED'");
    } catch (mysqli_sql_exception $e) {
        // Fallback for older MySQL/MariaDB versions (pre-5.7.20)
        try {
            $conn->query("SET SESSION tx_isolation = 'READ-COMMITTED'");
        } catch (mysqli_sql_exception $e2) {
            // Ignore if both fail - not critical
        }
    }
    
    try {
        $conn->query("SET SESSION wait_timeout = 300"); // 5 minute idle timeout
    } catch (mysqli_sql_exception $e) {
        // Ignore if not supported
    }
    
    try {
        $conn->query("SET SESSION interactive_timeout = 300");
    } catch (mysqli_sql_exception $e) {
        // Ignore if not supported
    }
    
    // max_execution_time is MySQL 5.7+ only, not available in MariaDB
    try {
        $conn->query("SET SESSION max_execution_time = 30000"); // 30 second query timeout
    } catch (mysqli_sql_exception $e) {
        // Ignore if not supported (MariaDB, MySQL < 5.7)
    }
    
    // Force campaign queries to yield to web queries
    try {
        $conn->query("SET SESSION sql_buffer_result = 1");
    } catch (mysqli_sql_exception $e) {
        // Ignore if not supported
    }
} else {
    // Web/API requests: HIGH priority, SHORT timeout to prevent frontend freeze
    try {
        // CRITICAL: 2 second timeout prevents frontend freezing when workers hold locks
        $conn->query("SET SESSION innodb_lock_wait_timeout = 2"); // Wait max 2 seconds (was 15 - too long!)
    } catch (mysqli_sql_exception $e) {
        // Ignore if not supported
    }
    
    try {
        $conn->query("SET SESSION wait_timeout = 300"); // 5 minute idle timeout
    } catch (mysqli_sql_exception $e) {
        // Ignore if not supported
    }
    
    try {
        $conn->query("SET SESSION interactive_timeout = 300");
    } catch (mysqli_sql_exception $e) {
        // Ignore if not supported
    }
    
    // Web queries get priority
    try {
        $conn->query("SET SESSION sql_buffer_result = 0");
    } catch (mysqli_sql_exception $e) {
        // Ignore if not supported
    }
}

// Ensure MySQL uses IST for CURDATE()/NOW()/CURTIME()
try {
    $conn->query("SET time_zone = '+05:30'");
} catch (mysqli_sql_exception $e) {
    // Ignore if not supported
}
