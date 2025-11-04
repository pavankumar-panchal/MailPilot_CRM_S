#!/bin/bash
# View logs.log - Shows which SMTP sent which email

LOG_FILE="/opt/lampp/htdocs/verify_emails/MailPilot_CRM/backend/storage/logs.log"

if [ ! -f "$LOG_FILE" ]; then
    echo "❌ No logs.log file found yet."
    echo "📧 The file will be created when you send emails from a campaign."
    exit 1
fi

# Check if argument is provided
if [ "$1" == "tail" ]; then
    echo "=========================================="
    echo "📧 LIVE EMAIL SENDING LOGS (Last 50)"
    echo "=========================================="
    tail -50 "$LOG_FILE"
elif [ "$1" == "follow" ]; then
    echo "=========================================="
    echo "📧 MONITORING LIVE EMAIL SENDS"
    echo "Press Ctrl+C to stop"
    echo "=========================================="
    tail -f "$LOG_FILE"
elif [ "$1" == "success" ]; then
    echo "=========================================="
    echo "✅ SUCCESSFUL EMAIL SENDS"
    echo "=========================================="
    grep "Status: SUCCESS" "$LOG_FILE"
elif [ "$1" == "failed" ]; then
    echo "=========================================="
    echo "❌ FAILED EMAIL SENDS"
    echo "=========================================="
    grep "Status: FAILED" "$LOG_FILE"
elif [ "$1" == "smtp" ] && [ -n "$2" ]; then
    echo "=========================================="
    echo "📬 EMAILS SENT BY SMTP ID: $2"
    echo "=========================================="
    grep "SMTP.*ID: $2" "$LOG_FILE"
elif [ "$1" == "campaign" ] && [ -n "$2" ]; then
    echo "=========================================="
    echo "📋 EMAILS SENT IN CAMPAIGN: $2"
    echo "=========================================="
    grep "Campaign: $2" "$LOG_FILE"
elif [ "$1" == "stats" ]; then
    echo "=========================================="
    echo "📊 EMAIL SENDING STATISTICS"
    echo "=========================================="
    echo ""
    TOTAL=$(wc -l < "$LOG_FILE")
    SUCCESS=$(grep -c "Status: SUCCESS" "$LOG_FILE")
    FAILED=$(grep -c "Status: FAILED" "$LOG_FILE")
    
    echo "Total Emails: $TOTAL"
    echo "✅ Success: $SUCCESS"
    echo "❌ Failed: $FAILED"
    echo ""
    echo "Top SMTP Accounts Used:"
    grep -oP 'SMTP: \K[^ ]+' "$LOG_FILE" | sort | uniq -c | sort -rn | head -10
else
    echo "=========================================="
    echo "📧 EMAIL SENDING LOGS"
    echo "=========================================="
    echo ""
    cat "$LOG_FILE"
    echo ""
    echo "=========================================="
    echo "📊 Quick Stats:"
    echo "Total: $(wc -l < "$LOG_FILE") | Success: $(grep -c "Status: SUCCESS" "$LOG_FILE") | Failed: $(grep -c "Status: FAILED" "$LOG_FILE")"
    echo "=========================================="
    echo ""
    echo "Usage:"
    echo "  ./view_main_log.sh          - View all logs"
    echo "  ./view_main_log.sh tail     - Last 50 entries"
    echo "  ./view_main_log.sh follow   - Watch live (real-time)"
    echo "  ./view_main_log.sh success  - Only successful sends"
    echo "  ./view_main_log.sh failed   - Only failed sends"
    echo "  ./view_main_log.sh smtp 29  - Emails sent by SMTP ID 29"
    echo "  ./view_main_log.sh campaign 11 - Emails from campaign 11"
    echo "  ./view_main_log.sh stats    - Statistics"
fi
