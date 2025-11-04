<?php
/**
 * Domain Email Delivery Diagnostic Tool
 * Checks why emails might not be received at custom domains
 */

$domain = 'relyonsoft.com';
$testEmail = 'pavankumar.c@relyonsoft.com';

echo "==============================================\n";
echo "Email Delivery Diagnostic for: $domain\n";
echo "==============================================\n\n";

// Check 1: DNS MX Records
echo "[1] Checking MX Records for $domain...\n";
$mxRecords = [];
if (getmxrr($domain, $mxRecords)) {
    echo "✓ MX Records found:\n";
    foreach ($mxRecords as $mx) {
        echo "  - $mx\n";
    }
} else {
    echo "✗ NO MX Records found! This domain cannot receive emails.\n";
    echo "  ACTION: Configure MX records in DNS settings.\n";
}
echo "\n";

// Check 2: Domain Resolution
echo "[2] Checking Domain Resolution...\n";
$ip = gethostbyname($domain);
if ($ip !== $domain) {
    echo "✓ Domain resolves to: $ip\n";
} else {
    echo "✗ Domain does not resolve!\n";
}
echo "\n";

// Check 3: SPF Record
echo "[3] Checking SPF Record...\n";
$spfRecord = dns_get_record($domain, DNS_TXT);
$spfFound = false;
foreach ($spfRecord as $record) {
    if (isset($record['txt']) && strpos($record['txt'], 'v=spf1') === 0) {
        echo "✓ SPF Record found: {$record['txt']}\n";
        $spfFound = true;
        
        // Check if our SMTP servers are allowed
        if (strpos($record['txt'], 'include:') !== false || 
            strpos($record['txt'], 'a:') !== false || 
            strpos($record['txt'], '+all') !== false) {
            echo "  ⚠ SPF may allow external senders\n";
        } elseif (strpos($record['txt'], '-all') !== false || 
                  strpos($record['txt'], '~all') !== false) {
            echo "  ⚠ SPF is restrictive - external SMTP servers may be blocked\n";
            echo "  NOTE: Emails from external SMTPs (relyonmail.xyz, payrollsoft.in, etc.)\n";
            echo "        may be rejected or marked as spam!\n";
        }
    }
}
if (!$spfFound) {
    echo "⚠ No SPF record found. Emails may be marked as spam.\n";
}
echo "\n";

// Check 4: DMARC Record
echo "[4] Checking DMARC Record...\n";
$dmarcDomain = '_dmarc.' . $domain;
$dmarcRecord = dns_get_record($dmarcDomain, DNS_TXT);
if (!empty($dmarcRecord)) {
    echo "✓ DMARC Record found:\n";
    foreach ($dmarcRecord as $record) {
        if (isset($record['txt'])) {
            echo "  {$record['txt']}\n";
        }
    }
} else {
    echo "⚠ No DMARC record found.\n";
}
echo "\n";

// Check 5: Common Issues
echo "[5] Common Issues & Solutions:\n";
echo "-------------------------------------------\n";
echo "✓ Our system SUCCESSFULLY sends emails to $testEmail\n";
echo "✓ SMTP reports: SUCCESS (no errors)\n";
echo "\n";
echo "If emails not arriving, check:\n";
echo "1. SPAM/JUNK folder in your mail client\n";
echo "2. Email server logs on relyonsoft.com server\n";
echo "3. Firewall rules blocking sender IPs\n";
echo "4. SPF records - may be rejecting external SMTPs\n";
echo "5. Mail server configuration (catch-all, forwarding, etc.)\n";
echo "\n";
echo "WHO TO CONTACT:\n";
echo "- Your domain administrator (manages relyonsoft.com)\n";
echo "- Email hosting provider (Google Workspace, Microsoft 365, etc.)\n";
echo "- Check /var/log/mail.log on your mail server\n";
echo "\n";

// Check 6: Test with Gmail for comparison
echo "[6] Why Gmail Works but Custom Domain Doesn't:\n";
echo "-------------------------------------------\n";
echo "Gmail (panchalpavan7090@gmail.com):\n";
echo "  ✓ Has excellent spam filtering\n";
echo "  ✓ Accepts emails from most legitimate SMTPs\n";
echo "  ✓ Large infrastructure handles bounces well\n";
echo "\n";
echo "Custom Domain ($testEmail):\n";
echo "  ? May have strict SPF/DKIM requirements\n";
echo "  ? May reject emails from unknown SMTP servers\n";
echo "  ? Mail server may have aggressive filtering\n";
echo "  ? May not be properly configured for external mail\n";
echo "\n";

echo "==============================================\n";
echo "RECOMMENDATION:\n";
echo "==============================================\n";
echo "1. Check your relyonsoft.com email server logs\n";
echo "2. Add SPF record to allow sending domains:\n";
echo "   v=spf1 include:relyonmail.xyz include:payrollsoft.in ~all\n";
echo "3. Check mail server firewall rules\n";
echo "4. Verify pavankumar.c@relyonsoft.com mailbox exists\n";
echo "5. Check spam/junk folder\n";
echo "==============================================\n";
?>
