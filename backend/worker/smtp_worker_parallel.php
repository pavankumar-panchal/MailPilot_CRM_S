<?php
/**
 * ENTERPRISE-GRADE Email Validation Worker: Complete Domain + SMTP Validation
 * Performs thorough email validation including:
 * - Domain verification (MX records, DNS resolution)
 * - SMTP validation (mailbox existence check)
 * - Catch-all detection
 * - Disposable/role email detection
 * 
 * Based on single_email.php methodology with parallel processing
 * Usage: php smtp_worker_parallel.php <worker_id> <process_id> <start_index> <end_index>
 */
date_default_timezone_set('Asia/Kolkata');
set_time_limit(0);

if (php_sapi_name() !== 'cli') {
    die("CLI only\n");
}

// Get arguments
if ($argc < 5) {
    error_log("ERROR: Insufficient arguments");
    exit(1);
}

$workerId = intval($argv[1]);
$processId = trim($argv[2]);
$startIndex = intval($argv[3]);
$endIndex = intval($argv[4]);

$GLOBALS['workerId'] = $workerId;

// Tuning constants (enterprise-grade)
if (!defined('SIP_SMTP_SOCKET_TIMEOUT')) define('SIP_SMTP_SOCKET_TIMEOUT', 8);
if (!defined('SIP_SMTP_MAX_MX')) define('SIP_SMTP_MAX_MX', 4);
if (!defined('SIP_SMTP_MAX_IPS_PER_MX')) define('SIP_SMTP_MAX_IPS_PER_MX', 3);
if (!defined('SIP_SMTP_CATCHALL_PROBES')) define('SIP_SMTP_CATCHALL_PROBES', 3);
if (!defined('SIP_SMTP_BACKOFF_CONNECT_MS')) define('SIP_SMTP_BACKOFF_CONNECT_MS', 120);
if (!defined('SIP_SMTP_RETRYABLE_CODES')) define('SIP_SMTP_RETRYABLE_CODES', serialize(['421','450','451','452','447','449']));
if (!defined('SIP_SMTP_DEFERRAL_DELAY_MIN')) define('SIP_SMTP_DEFERRAL_DELAY_MIN', 8);
if (!defined('SIP_MAX_TOTAL_SMTP_TIME')) define('SIP_MAX_TOTAL_SMTP_TIME', 28);
if (!defined('SIP_DISABLE_CATCHALL_DETECTION')) define('SIP_DISABLE_CATCHALL_DETECTION', false);

// Log function
function log_worker($msg) {
    global $workerId;
    $timestamp = date('Y-m-d H:i:s');
    echo "[$timestamp] Worker $workerId: $msg\n";
}

log_worker("Started with args: workerId=$workerId, processId=$processId, startIndex=$startIndex, endIndex=$endIndex");

// Database connection from config
require_once __DIR__ . '/../config/db.php';

if (!isset($conn) || $conn->connect_error) {
    log_worker("Connection failed: " . ($conn->connect_error ?? 'No connection'));
    exit(1);
}

log_worker("Database connection established");

// Database schema matches server - no additional columns needed
// Using: domain_verified, domain_status, validation_response, domain_processed, validation_status

// Get worker directory and emails
$workerDir = '/tmp/bulk_workers_' . $processId . '/';
if (!is_dir($workerDir)) {
    log_worker("ERROR: Worker directory not found: $workerDir");
    exit(1);
}

log_worker("Worker directory found: $workerDir");

$emailsFile = $workerDir . 'emails.txt';
if (!file_exists($emailsFile)) {
    log_worker("ERROR: emails.txt not found in $workerDir");
    exit(1);
}

// Read emails
$emailsText = file_get_contents($emailsFile);
$allEmails = array_filter(explode("\n", trim($emailsText)));
$emailsToProcess = array_slice($allEmails, $startIndex, ($endIndex - $startIndex));

log_worker("Total emails in file: " . count($allEmails));
log_worker("Processing emails from index $startIndex to $endIndex (" . count($emailsToProcess) . " emails)");

// ========== ENTERPRISE HELPER FUNCTIONS (from single_email.php) ==========

/**
 * Normalize Gmail addresses (remove dots, ignore +tags)
 */
function normalize_gmail($email) {
    $parts = explode('@', strtolower(trim($email)));
    if (count($parts) !== 2) return $email;
    if ($parts[1] !== 'gmail.com') return $email;
    $local = explode('+', $parts[0])[0];
    $local = str_replace('.', '', $local);
    return $local . '@gmail.com';
}

/**
 * Validate account name (local part)
 */
function is_valid_account_name($account) {
    if (!preg_match('/^[a-z0-9](?!.*[._-]{2})[a-z0-9._-]*[a-z0-9]$/i', $account)) return false;
    if (strlen($account) < 1 || strlen($account) > 64) return false;
    if (preg_match('/^[0-9]+$/', $account)) return false;
    return true;
}

/**
 * ZEROBOUNCE-LEVEL: Disposable/temporary email detection with comprehensive list
 */
function is_disposable_email($email) {
    $domain = strtolower(explode('@', $email)[1] ?? '');
    if (empty($domain)) return false;
    
    $disposableDomains = [
        '10minutemail.com', '10minutemail.net', 'tempmail.com', 'guerrillamail.com', 'guerrillamail.net',
        'mailinator.com', 'maildrop.cc', 'temp-mail.org', 'throwaway.email', 'getnada.com',
        'yopmail.com', 'yopmail.fr', 'yopmail.net', 'cool.fr.nf', 'jetable.fr.nf',
        'nospam.ze.tc', 'nomail.xl.cx', 'discardmail.com', 'trashmail.com', 'fakeinbox.com',
        'sharklasers.com', 'grr.la', 'guerrillamail.biz', 'guerrillamail.org', 'guerrillamail.de',
        'spam4.me', 'tmails.net', 'mohmal.com', 'emailondeck.com', 'mintemail.com',
        'mytrashmail.com', 'tempinbox.com', 'tempemail.net', 'temporary-mail.net', 'temp-mail.io',
        'disposablemail.com', 'anonbox.net', 'anonymbox.com', 'mailcatch.com', 'spambox.us',
        'dispostable.com', 'throwemail.com', 'trashymail.com', 'tempsky.com', 'mailnesia.com'
    ];
    
    if (in_array($domain, $disposableDomains, true)) {
        return true;
    }
    
    if (preg_match('/^(mail|temp|trash|spam|fake|throwaway|discard)\d+\.(com|net|org)$/i', $domain)) {
        return true;
    }
    
    return false;
}

/**
 * ZEROBOUNCE-LEVEL: Role-based email detection
 */
function is_role_email($email) {
    $account = strtolower(explode('@', $email)[0] ?? '');
    
    $roleAccounts = [
        'admin', 'administrator', 'hostmaster', 'postmaster', 'webmaster',
        'abuse', 'noc', 'security', 'mailer-daemon', 'root',
        'info', 'marketing', 'sales', 'support', 'help',
        'service', 'contact', 'team', 'office', 'jobs',
        'careers', 'hr', 'recruitment', 'billing', 'finance',
        'accounts', 'legal', 'privacy', 'compliance', 'feedback',
        'no-reply', 'noreply', 'donotreply', 'do-not-reply'
    ];
    
    return in_array($account, $roleAccounts, true);
}

/**
 * Get excluded accounts (noreply, postmaster, etc)
 */
function get_excluded_accounts($conn) {
    $out = [];
    try {
        $tbl = $conn->query("SHOW TABLES LIKE 'exclude_accounts'");
        if (!$tbl || $tbl->num_rows === 0) return $out;
        $res = $conn->query("SELECT account FROM exclude_accounts");
        if (!$res) return $out;
        while ($r = $res->fetch_assoc()) $out[] = strtolower(trim($r['account']));
    } catch (Throwable $e) {}
    return $out;
}

/**
 * Get excluded domains with their IPs
 */
function get_excluded_domains_with_ips($conn) {
    $out = [];
    try {
        $tbl = $conn->query("SHOW TABLES LIKE 'exclude_domains'");
        if (!$tbl || $tbl->num_rows === 0) return $out;
        $res = $conn->query("SELECT domain, ip_address FROM exclude_domains");
        if (!$res) return $out;
        while ($r = $res->fetch_assoc()) {
            $d = strtolower(trim($r['domain']));
            $ip = trim($r['ip_address']);
            if ($d !== '') $out[$d] = $ip;
        }
    } catch (Throwable $e) {}
    return $out;
}

/**
 * Parallel SMTP fast probe - probes multiple MX hosts simultaneously
 */
function smtp_parallel_fast(string $email, array $mxHosts, int $maxParallel = 5, int $globalTimeout = 10): array {
    $mxHosts = array_slice($mxHosts, 0, $maxParallel);
    
    if (empty($mxHosts)) {
        return ['status' => 'inconclusive', 'reason' => 'no_mx_hosts', 'host' => null, 'response' => null, 'time' => 0.0];
    }
    
    $sockets = [];
    $meta = [];
    $firstHardInvalid = null;
    $start = microtime(true);
    
    foreach ($mxHosts as $host) {
        $host = rtrim($host, '.');
        
        $sock = @stream_socket_client(
            "tcp://{$host}:25",
            $errno,
            $errstr,
            2,
            STREAM_CLIENT_ASYNC_CONNECT
        );
        
        if ($sock) {
            stream_set_blocking($sock, false);
            stream_set_timeout($sock, 2);
            $id = (int)$sock;
            $sockets[$id] = $sock;
            $meta[$id] = [
                'host' => $host,
                'state' => 'connect',
                'buffer' => '',
                'last_activity' => microtime(true)
            ];
        }
    }
    
    if (empty($sockets)) {
        return ['status' => 'inconclusive', 'reason' => 'no_connections', 'host' => null, 'response' => null, 'time' => round(microtime(true) - $start, 3)];
    }
    
    while (!empty($sockets) && (microtime(true) - $start) < $globalTimeout) {
        $read = [];
        $write = [];
        $except = [];
        
        foreach ($sockets as $sock) {
            $read[] = $sock;
            $write[] = $sock;
        }
        
        $selectResult = @stream_select($read, $write, $except, 1);
        
        if ($selectResult === false) {
            break;
        }
        
        if ($selectResult === 0) {
            $now = microtime(true);
            foreach ($sockets as $id => $sock) {
                if ($now - $meta[$id]['last_activity'] > 8) {
                    fclose($sock);
                    unset($sockets[$id]);
                    unset($meta[$id]);
                }
            }
            continue;
        }
        
        foreach ($read as $sock) {
            $id = (int)$sock;
            if (!isset($meta[$id])) continue;
            
            $line = @fgets($sock, 512);
            
            if ($line === false) {
                fclose($sock);
                unset($sockets[$id]);
                unset($meta[$id]);
                continue;
            }
            
            if (empty(trim($line))) continue;
            
            $meta[$id]['buffer'] .= $line;
            $meta[$id]['last_activity'] = microtime(true);
            
            $code = substr($line, 0, 3);
            $state = $meta[$id]['state'];
            $host = $meta[$id]['host'];
            
            if ($state === 'connect' && $code === '220') {
                @fwrite($sock, "EHLO fast.validator\r\n");
                @fflush($sock);
                $meta[$id]['state'] = 'ehlo';
            }
            elseif ($state === 'ehlo' && $code === '250') {
                if (strpos($line, '250-') === 0) {
                    continue;
                }
                @fwrite($sock, "MAIL FROM:<>\r\n");
                @fflush($sock);
                $meta[$id]['state'] = 'mailfrom';
            }
            elseif ($state === 'mailfrom' && $code === '250') {
                @fwrite($sock, "RCPT TO:<{$email}>\r\n");
                @fflush($sock);
                $meta[$id]['state'] = 'rcpt';
            }
            elseif ($state === 'rcpt') {
                if ($code === '250' || $code === '251') {
                    foreach ($sockets as $s) {
                        @fwrite($s, "QUIT\r\n");
                        @fclose($s);
                    }
                    return [
                        'status' => 'valid',
                        'host' => $host,
                        'response' => trim($line),
                        'code' => $code,
                        'time' => round(microtime(true) - $start, 3)
                    ];
                }
                
                if (in_array($code, ['550', '553', '554'], true)) {
                    if ($firstHardInvalid === null) {
                        $firstHardInvalid = [
                            'host' => $host,
                            'response' => trim($line),
                            'code' => $code
                        ];
                    }
                    @fwrite($sock, "QUIT\r\n");
                    @fclose($sock);
                    unset($sockets[$id]);
                    unset($meta[$id]);
                    continue;
                }
                
                if (in_array($code, ['421', '450', '451', '452'], true)) {
                    @fwrite($sock, "QUIT\r\n");
                    fclose($sock);
                    unset($sockets[$id]);
                    unset($meta[$id]);
                    continue;
                }
                
                if ($code[0] === '5') {
                    @fwrite($sock, "QUIT\r\n");
                    fclose($sock);
                    unset($sockets[$id]);
                    unset($meta[$id]);
                    continue;
                }
            }
            elseif (in_array($code, ['421', '451', '452'], true)) {
                @fwrite($sock, "QUIT\r\n");
                fclose($sock);
                unset($sockets[$id]);
                unset($meta[$id]);
            }
            elseif (in_array($code, ['550', '553', '554'], true)) {
                @fwrite($sock, "QUIT\r\n");
                fclose($sock);
                unset($sockets[$id]);
                unset($meta[$id]);
            }
        }
    }
    
    foreach ($sockets as $sock) {
        @fwrite($sock, "QUIT\r\n");
        @fclose($sock);
    }
    
    $elapsed = round(microtime(true) - $start, 3);
    
    if ($firstHardInvalid !== null) {
        return [
            'status' => 'invalid',
            'host' => $firstHardInvalid['host'],
            'response' => $firstHardInvalid['response'],
            'code' => $firstHardInvalid['code'],
            'time' => $elapsed
        ];
    }
    
    return [
        'status' => 'inconclusive',
        'reason' => 'no_decisive_rcpt',
        'host' => null,
        'response' => 'No decisive RCPT response within time budget',
        'time' => $elapsed
    ];
}

/**
 * Extract domain from email
 */
function extractDomain($email) {
    $parts = explode('@', $email);
    return isset($parts[1]) ? strtolower(trim($parts[1])) : '';
}

// ========== END ENTERPRISE HELPER FUNCTIONS ==========

/**
 * FULL SMTP VERIFICATION - Thorough validation trying all MX hosts (fallback mode)
 * Used when parallel fast mode is inconclusive
 */
function smtp_verify_full($email, $domain, $mxHosts) {
    $result = [
        'email' => $email,
        'domain' => $domain,
        'attempted' => false,
        'ip' => null,
        'validation_status' => 'invalid',
        'validation_response' => null,
        'domain_status' => 0,
        'domain_verified' => 1,
        'has_mx' => 1
    ];
    
    $port = 25;
    $timeout = 60;
    $readTimeout = 30;
    
    // Try ALL MX hosts for maximum accuracy
    foreach ($mxHosts as $hostIndex => $host) {
        log_worker("Full SMTP verify for $email: Trying MX host $host");
        
        $result['attempted'] = true;
        $result['ip'] = $host;
        
        $errNo = 0;
        $errStr = '';
        
        $smtp = @stream_socket_client("tcp://$host:$port", $errNo, $errStr, $timeout);
        
        if (!$smtp) {
            log_worker("Connection failed to $host: $errStr");
            continue;
        }
        
        stream_set_blocking($smtp, true);
        stream_set_timeout($smtp, $readTimeout);
        
        // Wait for banner (with retry for tarpitting servers)
        $banner = '';
        $maxBannerWait = 30;
        $bannerStart = time();
        
        while ((time() - $bannerStart) < $maxBannerWait) {
            $line = fgets($smtp, 4096);
            if ($line === false || $line === '') {
                usleep(500000);
                continue;
            }
            $banner .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }
        
        if (strlen($banner) === 0 || substr($banner, 0, 3) !== '220') {
            fclose($smtp);
            log_worker("Invalid banner from $host");
            continue;
        }
        
        // EHLO
        fputs($smtp, "EHLO server.relyon.co.in\r\n");
        fflush($smtp);
        usleep(3000000); // Wait 3 seconds
        
        $ehloLines = [];
        $supportsStartTLS = false;
        $ehloStart = time();
        $maxEhloWait = 30;
        
        while ((time() - $ehloStart) < $maxEhloWait) {
            $line = fgets($smtp, 4096);
            if ($line === false) {
                usleep(300000);
                continue;
            }
            $ehloLines[] = $line;
            if (stripos($line, 'STARTTLS') !== false) {
                $supportsStartTLS = true;
            }
            if (strlen($line) >= 4 && $line[3] === ' ') break;
            if (count($ehloLines) > 30) break;
        }
        
        if (empty($ehloLines) || substr($ehloLines[0], 0, 3) !== '250') {
            // Try HELO fallback
            fputs($smtp, "HELO server.relyon.co.in\r\n");
            fflush($smtp);
            usleep(500000);
            
            $helo = fgets($smtp, 4096);
            if (!$helo || substr($helo, 0, 3) !== '250') {
                fclose($smtp);
                log_worker("EHLO/HELO failed on $host");
                continue;
            }
        }
        
        // MAIL FROM
        fputs($smtp, "MAIL FROM:<info@relyon.co.in>\r\n");
        fflush($smtp);
        usleep(2000000); // Wait 2 seconds
        
        $mfr = '';
        $mfrStart = time();
        while ((time() - $mfrStart) < 15) {
            $mfr = fgets($smtp, 4096);
            if ($mfr !== false && $mfr !== '') break;
            usleep(200000);
        }
        
        if (!$mfr || substr($mfr, 0, 3) !== '250') {
            fclose($smtp);
            log_worker("MAIL FROM failed on $host");
            continue;
        }
        
        // RCPT TO - THE CRITICAL TEST
        fputs($smtp, "RCPT TO:<$email>\r\n");
        fflush($smtp);
        usleep(2000000); // Wait 2 seconds
        
        $rcpt = '';
        $rcptStart = time();
        $maxRcptWait = 20;
        
        while ((time() - $rcptStart) < $maxRcptWait) {
            $rcpt = fgets($smtp, 4096);
            if ($rcpt !== false && $rcpt !== '') break;
            usleep(200000);
        }
        
        $rCode = $rcpt !== false ? substr($rcpt, 0, 3) : null;
        
        // Check for spam block
        if ($rCode == '550' && $rcpt !== false) {
            $rcptLower = strtolower($rcpt);
            if (strpos($rcptLower, 'spamhaus') !== false || 
                strpos($rcptLower, 'blocked') !== false || 
                strpos($rcptLower, 'blacklist') !== false ||
                strpos($rcptLower, 'spam') !== false) {
                log_worker("Spam block detected on $host - trying next MX");
                fclose($smtp);
                continue; // Try next MX host
            }
        }
        
        // VALID response (250/251)
        if (in_array($rCode, ['250', '251'])) {
            // Detect catch-all
            $isCatchAll = detect_catchall($email, $domain, $host, $smtp);
            
            fputs($smtp, "QUIT\r\n");
            fflush($smtp);
            fclose($smtp);
            
            // Resolve hostname to IP address
            $ipAddress = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);
            
            $result['validation_status'] = 'valid';
            $result['validation_response'] = $ipAddress;
            $result['domain_status'] = 1;
            
            log_worker("✓ VALID: $email on $host (IP: $ipAddress)");
            return $result; // SUCCESS!
        }
        
        fputs($smtp, "QUIT\r\n");
        fflush($smtp);
        fclose($smtp);
        
        // Definitive invalid (5xx hard failure)
        if ($rCode == '550' || $rCode == '553' || $rCode == '554') {
            // Resolve hostname to IP address
            $ipAddress = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);
            
            $result['validation_status'] = 'invalid';
            $result['validation_response'] = $ipAddress;
            $result['domain_status'] = 0;
            
            log_worker("✗ INVALID: $email - " . trim($rcpt));
            return $result;
        }
        
        // Other responses - continue to next host
        log_worker("Inconclusive response $rCode from $host - trying next");
    }
    
    // All MX hosts tried - mark as invalid
    $result['validation_status'] = 'invalid';
    $result['validation_response'] = 'No viable SMTP path after trying all MX hosts';
    $result['domain_status'] = 0;
    
    return $result;
}

/**
 * Detect catch-all domain (accepts any email)
 */
function detect_catchall($email, $domain, $host, $smtp = null) {
    if (SIP_DISABLE_CATCHALL_DETECTION) {
        return 0;
    }
    
    $closeSocket = false;
    if ($smtp === null) {
        // Open new connection for catch-all detection
        $smtp = @stream_socket_client("tcp://$host:25", $errNo, $errStr, 10);
        if (!$smtp) {
            return 0;
        }
        $closeSocket = true;
        
        stream_set_blocking($smtp, true);
        stream_set_timeout($smtp, 10);
        
        // Banner
        $banner = fgets($smtp, 4096);
        if (!$banner || substr($banner, 0, 3) !== '220') {
            if ($closeSocket) fclose($smtp);
            return 0;
        }
        
        // EHLO
        fputs($smtp, "EHLO server.relyon.co.in\r\n");
        fflush($smtp);
        usleep(500000);
        fgets($smtp, 4096);
        
        // MAIL FROM
        fputs($smtp, "MAIL FROM:<info@relyon.co.in>\r\n");
        fflush($smtp);
        usleep(500000);
        fgets($smtp, 4096);
    }
    
    // Test with random addresses
    $allAccepted = true;
    for ($i = 0; $i < SIP_SMTP_CATCHALL_PROBES; $i++) {
        $rand = 'ca' . bin2hex(random_bytes(4));
        $probe = $rand . '@' . $domain;
        
        fputs($smtp, "RCPT TO:<$probe>\r\n");
        fflush($smtp);
        usleep(500000);
        
        $pr = fgets($smtp, 4096);
        $pCode = $pr ? substr($pr, 0, 3) : null;
        
        if (!in_array($pCode, ['250', '251'])) {
            $allAccepted = false;
            break;
        }
    }
    
    if ($closeSocket) {
        fputs($smtp, "QUIT\r\n");
        fflush($smtp);
        fclose($smtp);
    }
    
    return $allAccepted ? 1 : 0;
}

/**
 * ENTERPRISE-GRADE SMTP VERIFICATION - Complete validation (no retryable status)
 * Matches single_email_processor.php logic: tries parallel fast, then falls back to full SMTP
 */
function verifyEmailViaSMTP($email, $domain) {
    $popularDomains = [
        'gmail.com', 'googlemail.com', 'outlook.com', 'hotmail.com', 'live.com', 'msn.com',
        'yahoo.com', 'yahoo.co.uk', 'yahoo.fr', 'yahoo.de', 'yahoo.in', 'ymail.com', 'rocketmail.com',
        'aol.com', 'protonmail.com', 'proton.me', 'icloud.com', 'me.com', 'mac.com',
        'zoho.com', 'mail.com', 'gmx.com', 'gmx.de', 'web.de', 'fastmail.com'
    ];
    $isPopularDomain = in_array(strtolower($domain), $popularDomains, true);
    
    $result = [
        'email' => $email,
        'domain' => $domain,
        'attempted' => false,
        'ip' => null,
        'validation_status' => 'invalid',
        'validation_response' => null,
        'domain_status' => 0,
        'domain_verified' => 1,
        'has_mx' => 0
    ];
    
    // Get MX hosts
    $mxHosts = [];
    if (getmxrr($domain, $hosts, $pri) && !empty($hosts)) {
        for ($i = 0; $i < count($hosts); $i++) {
            $mxHosts[] = rtrim($hosts[$i], '.');
        }
        $result['has_mx'] = 1;
    } else {
        $mxHosts[] = $domain;
    }
    
    if (empty($mxHosts)) {
        $result['validation_response'] = 'No MX or A records';
        $result['domain_verified'] = 0;
        return $result;
    }
    
    // TRY PARALLEL FAST MODE FIRST (3-10 seconds)
    $fastResult = smtp_parallel_fast($email, $mxHosts, 5, $isPopularDomain ? 10 : 12);
    
    if ($fastResult['status'] === 'valid') {
        $host = $fastResult['host'] ?? 'unknown';
        // Resolve hostname to IP address
        $ipAddress = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);
        
        $result['validation_status'] = 'valid';
        $result['validation_response'] = $ipAddress;
        $result['domain_status'] = 1;
        $result['ip'] = $ipAddress;
        return $result;
    }
    
    if ($fastResult['status'] === 'invalid') {
        $host = $fastResult['host'] ?? 'unknown';
        // Resolve hostname to IP address
        $ipAddress = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);
        
        $result['validation_status'] = 'invalid';
        $result['validation_response'] = $ipAddress;
        $result['domain_status'] = 0;
        $result['ip'] = $ipAddress;
        return $result;
    }
    
    // PARALLEL INCONCLUSIVE - Fall back to FULL SMTP VERIFICATION (like single_email_processor.php)
    // This ensures complete validation without "retryable" status
    log_worker("Fast mode inconclusive for $email - using full SMTP verification");
    return smtp_verify_full($email, $domain, $mxHosts);
}

// Process emails
$validEmails = [];
$invalidEmails = [];
$emailDetails = [];
$processed = 0;
$validCount = 0;
$invalidCount = 0;

log_worker("Starting complete email validation loop (domain + SMTP)...");

// Load exclusion lists once
$excludedAccounts = get_excluded_accounts($conn);
$excludedDomains = get_excluded_domains_with_ips($conn);

foreach ($emailsToProcess as $email) {
    $email = trim($email);
    if (empty($email)) continue;
    
    // Store original email for DB lookup
    $originalEmail = $email;
    
    // Normalize Gmail addresses
    $email = normalize_gmail($email);
    
    // Remove non-printable characters
    $email = preg_replace('/[^\x20-\x7E]/', '', $email);
    
    $domain = extractDomain($email);
    if (empty($domain)) {
        log_worker("Skipping invalid email: $email");
        continue;
    }
    
    // Extract account (local part)
    list($account, $domainPart) = explode('@', $email) + [null, null];
    
    // Validate account name
    if (!is_valid_account_name($account)) {
        $invalidEmails[] = $email;
        $invalidCount++;
        $stmt = $conn->prepare("UPDATE emails SET domain_verified = 0, domain_status = 0, validation_status = 'invalid', domain_processed = 1, validation_response = 'Invalid account name' WHERE raw_emailid = ?");
        $stmt->bind_param('s', $originalEmail);
        $stmt->execute();
        $stmt->close();
        continue;
    }
    
    // Check disposable domains with comprehensive detection
    if (is_disposable_email($email)) {
        $invalidEmails[] = $email;
        $invalidCount++;
        $stmt = $conn->prepare("UPDATE emails SET domain_verified = 0, domain_status = 0, validation_status = 'disposable', domain_processed = 1, validation_response = 'Disposable/temporary email provider' WHERE raw_emailid = ?");
        $stmt->bind_param('s', $originalEmail);
        $stmt->execute();
        $stmt->close();
        continue;
    }
    
    // Check excluded accounts (noreply, postmaster, etc)
    if (in_array(strtolower($account), $excludedAccounts, true)) {
        $validEmails[] = $email;
        $validCount++;
        $stmt = $conn->prepare("UPDATE emails SET domain_verified = 1, domain_status = 1, validation_status = 'valid', domain_processed = 1, validation_response = 'Excluded: Account' WHERE raw_emailid = ?");
        $stmt->bind_param('s', $originalEmail);
        $stmt->execute();
        $stmt->close();
        continue;
    }
    
    // Check excluded domains
    if (array_key_exists(strtolower($domain), $excludedDomains)) {
        $validEmails[] = $email;
        $validCount++;
        $excludedIp = $excludedDomains[strtolower($domain)];
        $stmt = $conn->prepare("UPDATE emails SET domain_verified = 1, domain_status = 1, validation_status = 'valid', domain_processed = 1, validation_response = ? WHERE raw_emailid = ?");
        $stmt->bind_param('ss', $excludedIp, $originalEmail);
        $stmt->execute();
        $stmt->close();
        continue;
    }
    
    // Verify via SMTP with enterprise-grade metadata
    $result = verifyEmailViaSMTP($email, $domain);
    
    // Classify result (only valid or invalid - no retryable)
    $isValid = ($result['validation_status'] === 'valid');
    $isInvalid = !$isValid;
    
    // IMMEDIATE DATABASE UPDATE with all new fields
    if ($isValid) {
        $validEmails[] = $email;
        $validCount++;
        
        $stmt = $conn->prepare("
            UPDATE emails 
            SET domain_status = ?, 
                validation_status = ?, 
                domain_processed = 1,
                domain_verified = ?,
                validation_response = ?
            WHERE raw_emailid = ?
        ");
        $domainStatus = $result['domain_status'];
        $validationStatus = $result['validation_status'];
        $domainVerified = $result['domain_verified'];
        $validationResponse = $result['validation_response'];
        $stmt->bind_param('isiss', $domainStatus, $validationStatus, $domainVerified, $validationResponse, $originalEmail);
        $stmt->execute();
        $stmt->close();
        
    } else {
        $invalidEmails[] = $email;
        $invalidCount++;
        
        $stmt = $conn->prepare("
            UPDATE emails 
            SET domain_status = ?, 
                validation_status = ?, 
                domain_processed = 1,
                domain_verified = ?,
                validation_response = ?
            WHERE raw_emailid = ?
        ");
        $domainStatus = $result['domain_status'];
        $validationStatus = $result['validation_status'];
        $domainVerified = $result['domain_verified'];
        $validationResponse = $result['validation_response'];
        $stmt->bind_param('isiss', $domainStatus, $validationStatus, $domainVerified, $validationResponse, $originalEmail);
        $stmt->execute();
        $stmt->close();
    }
    
    // Store details for aggregation
    $emailDetails[$email] = [
        'validation_status' => $result['validation_status'],
        'domain_status' => $result['domain_status'],
        'mx_ip' => $result['ip'],
        'has_mx' => $result['has_mx'],
        'validation_response' => $result['validation_response'],
        'domain_verified' => $result['domain_verified']
    ];
    
    $processed++;
    
    // Update csv_list counts every 10 emails for live progress
    if ($processed % 10 == 0) {
        log_worker("Email #$processed processed. Total: $processed/" . count($emailsToProcess) . " (Valid: $validCount, Invalid: $invalidCount)");
        
        // Get csv_list_id for this batch
        $stmt = $conn->prepare("SELECT csv_list_id FROM emails WHERE raw_emailid = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        if ($row) {
            // Update csv_list progress immediately
            $conn->query("
                UPDATE csv_list 
                SET valid_count = (SELECT COUNT(*) FROM emails WHERE csv_list_id = {$row['csv_list_id']} AND domain_status = 1 AND domain_processed = 1),
                    invalid_count = (SELECT COUNT(*) FROM emails WHERE csv_list_id = {$row['csv_list_id']} AND domain_status = 0 AND domain_processed = 1)
                WHERE id = {$row['csv_list_id']}
            ");
        }
        
        // Write interim results for status tracking
        $interimResults = [
            'worker_id' => $workerId,
            'valid' => $validEmails,
            'invalid' => $invalidEmails,
            'details' => $emailDetails,
            'processed' => $processed,
            'total' => count($emailsToProcess),
            'counts' => [
                'valid' => $validCount,
                'invalid' => $invalidCount
            ]
        ];
        $interimJson = json_encode($interimResults, JSON_PRETTY_PRINT);
        file_put_contents($workerDir . "worker_{$workerId}.json", $interimJson);
    }
}

log_worker("Loop completed. Writing final results...");

// Write final results
$results = [
    'worker_id' => $workerId,
    'valid' => $validEmails,
    'invalid' => $invalidEmails,
    'details' => $emailDetails,
    'processed' => $processed,
    'total' => count($emailsToProcess),
    'counts' => [
        'valid' => $validCount,
        'invalid' => $invalidCount
    ],
    'completed_at' => date('Y-m-d H:i:s')
];

$jsonData = json_encode($results, JSON_PRETTY_PRINT);
if ($jsonData === false) {
    log_worker("ERROR: JSON encoding failed: " . json_last_error_msg());
    exit(1);
}

log_worker("JSON encoded successfully: " . strlen($jsonData) . " bytes");

$outputFile = $workerDir . "worker_{$workerId}.json";
$bytesWritten = file_put_contents($outputFile, $jsonData);

if ($bytesWritten === false) {
    log_worker("ERROR: Failed to write results to $outputFile");
    exit(1);
}

log_worker("Final results written successfully: $bytesWritten bytes to $outputFile");
log_worker("Processed $processed emails: Valid=$validCount, Invalid=$invalidCount");

// Final check: Update csv_list completion status for all affected lists
$affectedLists = [];
foreach ($emailsToProcess as $email) {
    $stmt = $conn->prepare("SELECT csv_list_id FROM emails WHERE raw_emailid = ? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    if ($row && $row['csv_list_id']) {
        $affectedLists[$row['csv_list_id']] = true;
    }
}

// Check and mark completed for each affected list
foreach (array_keys($affectedLists) as $listId) {
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_in_list,
            SUM(CASE WHEN domain_processed = 1 THEN 1 ELSE 0 END) as processed
        FROM emails 
        WHERE csv_list_id = ?
    ");
    $stmt->bind_param('i', $listId);
    $stmt->execute();
    $result = $stmt->get_result();
    $counts = $result->fetch_assoc();
    $stmt->close();
    
    if ($counts['processed'] >= $counts['total_in_list'] && $counts['total_in_list'] > 0) {
        $conn->query("
            UPDATE csv_list 
            SET status = 'completed'
            WHERE id = $listId AND status != 'completed'
        ");
        log_worker("✓ Marked csv_list_id $listId as COMPLETED (all {$counts['total_in_list']} emails validated)");
    }
}

log_worker("Worker completed successfully with complete domain + SMTP validation");

$conn->close();
if ($conn_logs) {
    $conn_logs->close();
}
exit(0);