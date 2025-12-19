<?php
declare(strict_types=1);

/* ==========================
   SAFE RUNTIME SETTINGS
   ========================== */
error_reporting(E_ALL);
ini_set('display_errors', '1');
set_time_limit(0);
ini_set('max_execution_time', '0');
ini_set('default_socket_timeout', '15');
date_default_timezone_set('Asia/Kolkata');

/* Disable buffering for live output */
while (ob_get_level()) ob_end_flush();
ob_implicit_flush(true);

/* ==========================
   DB CONNECTION (CRM)
   ========================== */
$conn = new mysqli('127.0.0.1', 'root', '', 'CRM', 3306);
$conn->set_charset('utf8mb4');

if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

/* ==========================
   TEST EMAIL RECIPIENT
   ========================== */
define('TEST_RECIPIENT', 'panchalpavan800@gmail.com');

/* ==========================
   SMTP FULL SEND HEALTH CHECK
   ========================== */
function smtpFullSendCheck(
    string $host,
    $port,
    string $encryption,
    string $username,
    string $password
): array {
    
    $timeout = 10;
    $port = (int)$port;
    
    if ($port <= 0 || $port > 65535) {
        return ['DEAD', 'Invalid port number'];
    }
    
    $remote_host = $host;
    if ($encryption === 'ssl') {
        $remote_host = 'ssl://' . $host;
    }
    
    $errno = 0;
    $errstr = '';
    $sock = @fsockopen($remote_host, $port, $errno, $errstr, $timeout);
    
    if (!$sock) {
        return ['DEAD', "Connection failed: $errstr (errno: $errno)"];
    }
    
    stream_set_timeout($sock, $timeout);
    
    $read = function() use ($sock) {
        $line = @fgets($sock, 1024);
        return $line !== false ? trim($line) : '';
    };
    
    $send = function(string $cmd) use ($sock) {
        @fwrite($sock, $cmd . "\r\n");
    };
    
    $expect = function(string $code) use ($read) {
        $response = $read();
        return strpos($response, $code) === 0 ? $response : false;
    };
    
    // Read banner
    if ($expect('220') === false) {
        fclose($sock);
        return ['BLOCKED', 'No valid SMTP banner'];
    }
    
    // EHLO
    $send('EHLO localhost');
    $ehlo_lines = '';
    while (($line = $read()) !== '' && strpos($line, '250-') === 0 || strpos($line, '250 ') === 0) {
        $ehlo_lines .= $line . "\n";
        if (strpos($line, '250 ') === 0) break;
    }
    
    // STARTTLS if needed
    if ($encryption === 'tls' && stripos($ehlo_lines, 'STARTTLS') !== false) {
        $send('STARTTLS');
        if ($expect('220') === false) {
            fclose($sock);
            return ['BLOCKED', 'STARTTLS failed'];
        }
        if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($sock);
            return ['BLOCKED', 'TLS handshake failed'];
        }
        // Re-EHLO after TLS
        $send('EHLO localhost');
        while (($line = $read()) !== '' && (strpos($line, '250-') === 0 || strpos($line, '250 ') === 0)) {
            if (strpos($line, '250 ') === 0) break;
        }
    }
    
    // AUTH LOGIN
    $send('AUTH LOGIN');
    if ($expect('334') === false) {
        fclose($sock);
        return ['BLOCKED', 'AUTH LOGIN not supported'];
    }
    
    $send(base64_encode($username));
    if ($expect('334') === false) {
        fclose($sock);
        return ['AUTH FAILED', 'Username rejected'];
    }
    
    $send(base64_encode($password));
    if ($expect('235') === false) {
        $resp = $read();
        fclose($sock);
        return ['AUTH FAILED', 'Password rejected: ' . $resp];
    }
    
    // === NOW ATTEMPT TO SEND A REAL TEST EMAIL ===
    $send('MAIL FROM:<' . $username . '>');
    if ($expect('250') === false) {
        $send('RSET');
        $send('QUIT');
        fclose($sock);
        return ['BLOCKED', 'MAIL FROM rejected (possible sending disabled)'];
    }
    
    $send('RCPT TO:<' . TEST_RECIPIENT . '>');
    if ($expect('250') === false && $expect('251') === false) {
        $send('RSET');
        $send('QUIT');
        fclose($sock);
        return ['BLOCKED', 'RCPT TO rejected (recipient blocked?)'];
    }
    
    $send('DATA');
    if ($expect('354') === false) {
        $send('RSET');
        $send('QUIT');
        fclose($sock);
        return ['BLOCKED', 'DATA command rejected'];
    }
    
    // Minimal valid email headers + body
    $message = "From: {$username}\r\n";
    $message .= "To: " . TEST_RECIPIENT . "\r\n";
    $message .= "Subject: SMTP Health Test - " . date('Y-m-d H:i:s') . "\r\n";
    $message .= "Date: " . date('r') . "\r\n";
    $message .= "Message-ID: <" . uniqid() . "@test>\r\n";
    $message .= "Content-Type: text/plain; charset=utf-8\r\n";
    $message .= "\r\n";
    $message .= "This is an automated SMTP health check test email.\r\n";
    $message .= "If you received this, the SMTP account is fully working.\r\n";
    $message .= "\r\n";
    
    $send($message . ".");
    
    $final = $expect('250');
    $send('QUIT');
    fclose($sock);
    
    if ($final !== false && strpos($final, '250') === 0) {
        return ['ACTIVE', 'Test email accepted for delivery (250 Queued)'];
    } else {
        return ['BLOCKED', 'Message accepted but no final 250 (rare error)'];
    }
}

/* ==========================
   FETCH SMTP ACCOUNTS
   ========================== */
$sql = "
SELECT 
    a.id,
    a.email,
    a.password,
    a.is_active AS account_active,
    s.name AS server_name,
    s.host,
    s.port,
    s.encryption,
    s.is_active AS server_active
FROM smtp_accounts a
JOIN smtp_servers s ON s.id = a.smtp_server_id
ORDER BY s.id ASC, a.id ASC
";

$res = $conn->query($sql);
if (!$res) die("Database query failed: " . $conn->error);

$total = $res->num_rows;
$now = date('d-m-Y h:i:s A');
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>SMTP Health Monitor (100% Accurate)</title>
<style>
body { font-family: Arial; background:#f4f6f8; margin:20px; }
table { width:100%; border-collapse: collapse; background:#fff; margin-top:20px; }
th, td { padding:12px; border:1px solid #ddd; font-size:13px; text-align:left; }
th { background:#2c3e50; color:#fff; position:sticky; top:0; }
.ACTIVE { color:green; font-weight:bold; }
.BLOCKED { color:orange; font-weight:bold; }
.AUTHFAILED { color:red; font-weight:bold; }
.DEAD { color:#555; font-weight:bold; }
.DISABLED { color:#999; font-weight:bold; }
.status-cell { text-align: center; font-size:15px; }
.info-cell { max-width: 400px; word-wrap: break-word; font-size:12px; }
</style>
</head>
<body>

<h2>📧 SMTP Health Monitor (Real Send Test)</h2>
<p><b>Total SMTP Accounts:</b> <?= $total ?></p>
<p><b>Checked At:</b> <?= $now ?> (IST)</p>
<p><b>Test Recipient:</b> <?= TEST_RECIPIENT ?> <small>(Check inbox/spam for test emails)</small></p>

<table>
<tr>
    <th>#</th>
    <th>ID</th>
    <th>Email</th>
    <th>Server</th>
    <th>Host:Port</th>
    <th>Enc</th>
    <th>Status</th>
    <th>Details</th>
</tr>

<?php
$i = 1;
$active_count = $blocked_count = $auth_failed_count = $dead_count = $disabled_count = 0;

while ($row = $res->fetch_assoc()) {
    
    $status = $info = '';
    
    if ($row['account_active'] && $row['server_active']) {
        [$status, $info] = smtpFullSendCheck(
            $row['host'],
            $row['port'],
            $row['encryption'],
            $row['email'],
            $row['password']
        );
        
        match($status) {
            'ACTIVE' => $active_count++,
            'BLOCKED' => $blocked_count++,
            'AUTH FAILED' => $auth_failed_count++,
            'DEAD' => $dead_count++,
            default => null
        };
    } else {
        $status = 'DISABLED';
        $info = 'Account or server disabled';
        $disabled_count++;
    }
    
    $class = str_replace(' ', '', $status);
    
    echo "<tr>
        <td>{$i}</td>
        <td>{$row['id']}</td>
        <td>{$row['email']}</td>
        <td>{$row['server_name']}</td>
        <td>{$row['host']}:{$row['port']}</td>
        <td>{$row['encryption']}</td>
        <td class='status-cell {$class}'>{$status}</td>
        <td class='info-cell'>{$info}</td>
    </tr>";
    
    $i++;
    
    // Be gentle: 1 second delay every 3 accounts to avoid rate limits
    if ($i % 3 == 0) {
        sleep(1);
    }
}
?>

</table>

<div style="margin-top: 20px; padding: 15px; background: #fff; border: 1px solid #ddd;">
    <h3>Summary</h3>
    <p><span class="ACTIVE">ACTIVE (Can Send):</span> <?= $active_count ?></p>
    <p><span class="BLOCKED">BLOCKED:</span> <?= $blocked_count ?></p>
    <p><span class="AUTHFAILED">AUTH FAILED:</span> <?= $auth_failed_count ?></p>
    <p><span class="DEAD">DEAD:</span> <?= $dead_count ?></p>
    <p><span class="DISABLED">DISABLED:</span> <?= $disabled_count ?></p>
</div>

<p><small><strong>Note:</strong> ACTIVE means the server fully accepted a real test email for delivery → 100% confirmation it can send mail right now.</small></p>

</body>
</html>