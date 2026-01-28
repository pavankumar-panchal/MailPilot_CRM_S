<?php
/**
 * Security Helper Functions
 * Provides input validation and sanitization for the entire application
 */

/**
 * Validate and sanitize integer input
 * @param mixed $value The value to validate
 * @param int $min Minimum allowed value
 * @param int $max Maximum allowed value
 * @return int Validated integer
 * @throws InvalidArgumentException
 */
function validateInteger($value, $min = 0, $max = PHP_INT_MAX) {
    $val = filter_var($value, FILTER_VALIDATE_INT);
    if ($val === false || $val < $min || $val > $max) {
        throw new InvalidArgumentException("Invalid integer value. Expected between $min and $max.");
    }
    return $val;
}

/**
 * Validate email address
 * @param string $email Email to validate
 * @return string Validated and sanitized email
 * @throws InvalidArgumentException
 */
function validateEmail($email) {
    $email = trim($email);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException("Invalid email format: $email");
    }
    if (strlen($email) > 254) {
        throw new InvalidArgumentException("Email address too long (max 254 characters)");
    }
    return strtolower($email);
}

/**
 * Sanitize string input
 * @param string $value String to sanitize
 * @param int $maxLength Maximum length
 * @return string Sanitized string
 */
function sanitizeString($value, $maxLength = 255) {
    $value = trim($value);
    if (strlen($value) > $maxLength) {
        $value = substr($value, 0, $maxLength);
    }
    // Remove null bytes
    $value = str_replace("\0", "", $value);
    return $value;
}

/**
 * Validate and sanitize hostname
 * @param string $host Hostname to validate
 * @return string Validated hostname
 * @throws InvalidArgumentException
 */
function validateHost($host) {
    $host = trim($host);
    // Allow IP addresses and domain names
    if (!filter_var($host, FILTER_VALIDATE_IP) && !filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
        throw new InvalidArgumentException("Invalid host: $host");
    }
    return $host;
}

/**
 * Validate port number
 * @param mixed $port Port number to validate
 * @return int Validated port
 * @throws InvalidArgumentException
 */
function validatePort($port) {
    return validateInteger($port, 1, 65535);
}

/**
 * Validate boolean value
 * @param mixed $value Value to validate
 * @return int Returns 1 or 0
 */
function validateBoolean($value) {
    return !empty($value) ? 1 : 0;
}

/**
 * Validate encryption type
 * @param string $encryption Encryption type (ssl, tls, or empty)
 * @return string Validated encryption
 * @throws InvalidArgumentException
 */
function validateEncryption($encryption) {
    $encryption = strtolower(trim($encryption));
    $allowed = ['', 'ssl', 'tls', 'none'];
    if (!in_array($encryption, $allowed)) {
        throw new InvalidArgumentException("Invalid encryption type. Allowed: ssl, tls, none");
    }
    return $encryption === 'none' ? '' : $encryption;
}

/**
 * Validate campaign status
 * @param string $status Status to validate
 * @return string Validated status
 * @throws InvalidArgumentException
 */
function validateCampaignStatus($status) {
    $status = strtolower(trim($status));
    $allowed = ['pending', 'running', 'paused', 'completed', 'failed'];
    if (!in_array($status, $allowed)) {
        throw new InvalidArgumentException("Invalid campaign status. Allowed: " . implode(', ', $allowed));
    }
    return $status;
}

/**
 * Validate file path to prevent directory traversal
 * @param string $path Path to validate
 * @param string $baseDir Base directory that path must be within
 * @return string Validated path
 * @throws InvalidArgumentException
 */
function validateFilePath($path, $baseDir) {
    $realPath = realpath($path);
    $realBase = realpath($baseDir);
    
    if ($realPath === false || $realBase === false) {
        throw new InvalidArgumentException("Invalid file path");
    }
    
    if (strpos($realPath, $realBase) !== 0) {
        throw new InvalidArgumentException("Path traversal attempt detected");
    }
    
    return $realPath;
}

/**
 * Set security headers for all responses
 */
function setSecurityHeaders() {
    // Prevent clickjacking
    header("X-Frame-Options: DENY");
    
    // Enable XSS protection
    header("X-XSS-Protection: 1; mode=block");
    
    // Prevent MIME type sniffing
    header("X-Content-Type-Options: nosniff");
    
    // Referrer policy
    header("Referrer-Policy: strict-origin-when-cross-origin");
    
    // Content Security Policy - allow localhost dev servers for API calls
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self' http://localhost:* http://127.0.0.1:* https://payrollsoft.in");
    
    // Remove PHP version disclosure
    header_remove("X-Powered-By");
}

/**
 * Validate and sanitize JSON input
 * @return array|null Decoded JSON data or null on failure
 */
function getValidatedJsonInput() {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return null;
    }
    
    return $data;
}

/**
 * Handle CORS securely with whitelist
 * @param array $allowedOrigins Array of allowed origins
 */
function handleCors($allowedOrigins = []) {
    // Default allowed origins for production
    if (empty($allowedOrigins)) {
        $allowedOrigins = [
            'http://localhost:5173',
            'http://localhost:5174',
            'http://localhost:5175',
            'http://localhost:5176',
            'http://localhost',
            'http://127.0.0.1',
            'https://payrollsoft.in',
            'http://payrollsoft.in'
        ];
    }
    
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowOrigin = false;
    
    // Check if origin is in whitelist
    if (in_array($origin, $allowedOrigins)) {
        $allowOrigin = $origin;
    } 
    // Allow any localhost port for development
    elseif (preg_match('/^https?:\/\/(localhost|127\.0\.0\.1)(:\d+)?$/i', $origin)) {
        $allowOrigin = $origin;
    } 
    // Allow local network IPs for development
    elseif (preg_match('/^https?:\/\/192\.168\.\d+\.\d+(:\d+)?$/i', $origin)) {
        $allowOrigin = $origin;
    }
    // Allow same-origin requests (when no Origin header - direct server access)
    elseif (empty($origin)) {
        // For same-origin requests, we don't need CORS headers
        $allowOrigin = false;
    }
    
    // Set CORS headers
    if ($allowOrigin) {
        header("Access-Control-Allow-Origin: $allowOrigin");
        header("Access-Control-Allow-Credentials: true");
    }
    
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept");
    header("Access-Control-Max-Age: 3600");
    header("Vary: Origin");
    
    // Handle preflight OPTIONS request
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Content-Type: application/json');
        http_response_code(200);
        exit();
    }
}

/**
 * Sanitize array of integers
 * @param array $array Array to sanitize
 * @return array Array of integers
 */
function sanitizeIntArray($array) {
    if (!is_array($array)) {
        return [];
    }
    return array_map('intval', array_filter($array, 'is_numeric'));
}

/**
 * Rate limiting helper
 * @param string $identifier Unique identifier (IP, user, etc.)
 * @param int $maxRequests Maximum requests allowed
 * @param int $timeWindow Time window in seconds
 * @return bool True if allowed, false if rate limit exceeded
 */
function checkRateLimit($identifier, $maxRequests = 100, $timeWindow = 60) {
    $cacheFile = sys_get_temp_dir() . '/ratelimit_' . md5($identifier) . '.txt';
    
    $now = time();
    $requests = [];
    
    if (file_exists($cacheFile)) {
        $data = file_get_contents($cacheFile);
        $requests = json_decode($data, true) ?: [];
    }
    
    // Remove old requests outside time window
    $requests = array_filter($requests, function($timestamp) use ($now, $timeWindow) {
        return ($now - $timestamp) < $timeWindow;
    });
    
    // Check if limit exceeded
    if (count($requests) >= $maxRequests) {
        return false;
    }
    
    // Add current request
    $requests[] = $now;
    file_put_contents($cacheFile, json_encode($requests));
    
    return true;
}

/**
 * Log security events
 * @param string $event Event description
 * @param array $context Additional context
 */
function logSecurityEvent($event, $context = []) {
    $logFile = __DIR__ . '/../logs/security_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    $logEntry = [
        'timestamp' => $timestamp,
        'ip' => $ip,
        'user_agent' => $userAgent,
        'event' => $event,
        'context' => $context
    ];
    
    $logLine = json_encode($logEntry) . PHP_EOL;
    file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
}
