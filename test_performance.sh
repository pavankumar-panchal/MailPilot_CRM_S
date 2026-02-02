#!/bin/bash
# Campaign System Performance Test Script

echo "========================================="
echo "Campaign System Performance Test"
echo "========================================="
echo ""

# Check if PHP CLI is available
if ! command -v php &> /dev/null; then
    echo "❌ PHP CLI not found!"
    exit 1
fi

echo "✓ PHP CLI found: $(which php)"
echo "✓ PHP version: $(php -v | head -n 1)"
echo ""

# Test database connection
echo "Testing database connection..."
php -r "
require '/opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/backend/config/db.php';
if (\$conn->connect_error) {
    echo '❌ Database connection failed: ' . \$conn->connect_error . PHP_EOL;
    exit(1);
}
echo '✓ Database connected successfully' . PHP_EOL;
\$conn->close();
"

echo ""

# Check active campaigns
echo "Checking active campaigns..."
php -r "
require '/opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/backend/config/db.php';
\$result = \$conn->query('SELECT COUNT(*) as cnt FROM campaign_status WHERE status IN (\"running\", \"pending\")');
\$row = \$result->fetch_assoc();
echo '✓ Active campaigns: ' . \$row['cnt'] . PHP_EOL;
\$conn->close();
"

echo ""

# Check SMTP accounts
echo "Checking SMTP accounts..."
php -r "
require '/opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/backend/config/db.php';
\$result = \$conn->query('SELECT COUNT(*) as cnt FROM smtp_accounts WHERE is_active = 1');
\$row = \$result->fetch_assoc();
echo '✓ Active SMTP accounts: ' . \$row['cnt'] . PHP_EOL;

\$result = \$conn->query('SELECT COUNT(DISTINCT smtp_server_id) as cnt FROM smtp_accounts WHERE is_active = 1');
\$row = \$result->fetch_assoc();
echo '✓ Active SMTP servers: ' . \$row['cnt'] . PHP_EOL;
\$conn->close();
"

echo ""

# Check running workers
echo "Checking running workers..."
php -r "
require '/opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/backend/config/db.php';
\$result = \$conn->query('
    SELECT COUNT(DISTINCT smtpid) as workers
    FROM mail_blaster
    WHERE status = \"processing\"
    AND delivery_time >= DATE_SUB(NOW(), INTERVAL 60 SECOND)
');
\$row = \$result->fetch_assoc();
echo '✓ Active workers (last 60 sec): ' . \$row['workers'] . PHP_EOL;
\$conn->close();
"

echo ""

# Check system performance
echo "Checking system performance..."
php -r "
require '/opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/backend/config/db.php';
\$result = \$conn->query('
    SELECT 
        cs.campaign_id,
        cm.mail_subject,
        cs.sent_emails,
        cs.total_emails,
        TIMESTAMPDIFF(SECOND, cs.start_time, NOW()) as elapsed,
        CASE 
            WHEN cs.sent_emails > 0 AND TIMESTAMPDIFF(SECOND, cs.start_time, NOW()) > 0
            THEN cs.sent_emails / TIMESTAMPDIFF(SECOND, cs.start_time, NOW())
            ELSE 0
        END as emails_per_sec
    FROM campaign_status cs
    JOIN campaign_master cm ON cm.campaign_id = cs.campaign_id
    WHERE cs.status = \"running\"
    AND cs.sent_emails > 0
    ORDER BY emails_per_sec DESC
    LIMIT 5
');

if (\$result->num_rows > 0) {
    echo 'Campaign Performance:' . PHP_EOL;
    echo str_repeat('-', 80) . PHP_EOL;
    printf(\"%-8s %-30s %-15s %-15s%s\", 'ID', 'Subject', 'Progress', 'Speed', PHP_EOL);
    echo str_repeat('-', 80) . PHP_EOL;
    
    while (\$row = \$result->fetch_assoc()) {
        \$progress = sprintf('%d/%d', \$row['sent_emails'], \$row['total_emails']);
        \$speed = sprintf('%.1f emails/sec', \$row['emails_per_sec']);
        \$subject = strlen(\$row['mail_subject']) > 28 
            ? substr(\$row['mail_subject'], 0, 25) . '...' 
            : \$row['mail_subject'];
        printf(\"%-8d %-30s %-15s %-15s%s\", 
            \$row['campaign_id'], 
            \$subject,
            \$progress, 
            \$speed,
            PHP_EOL
        );
    }
} else {
    echo '✓ No campaigns currently sending' . PHP_EOL;
}
\$conn->close();
"

echo ""
echo "========================================="
echo "Test Complete!"
echo "========================================="
echo ""
echo "📊 View dashboard: http://your-domain.com/backend/public/campaign_performance.html"
echo "📚 Read docs: /opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/PERFORMANCE_OPTIMIZATIONS.md"
echo ""
