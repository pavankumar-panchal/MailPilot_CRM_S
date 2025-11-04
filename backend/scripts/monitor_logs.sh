#!/bin/bash
# Real-time log monitoring for email campaigns
# Usage: ./monitor_logs.sh [campaign_id]

CAMPAIGN_ID=$1
DATE=$(date +%Y-%m-%d)
LOG_DIR="/opt/lampp/htdocs/verify_emails/MailPilot_CRM/backend/storage/logs"

if [ -z "$CAMPAIGN_ID" ]; then
    echo "Monitoring all campaigns..."
    echo "=========================================="
    echo "Press Ctrl+C to stop"
    echo "=========================================="
    
    # Monitor all detail logs
    tail -f "$LOG_DIR/email_details_${DATE}.log" 2>/dev/null | while IFS='|' read timestamp campaign to_email smtp_id smtp_email status error; do
        if [ "$status" == "success" ]; then
            echo -e "\033[32m[✓]\033[0m [$timestamp] Campaign $campaign: $to_email via $smtp_email"
        else
            echo -e "\033[31m[✗]\033[0m [$timestamp] Campaign $campaign: $to_email via $smtp_email - $error"
        fi
    done
else
    echo "Monitoring Campaign #$CAMPAIGN_ID..."
    echo "=========================================="
    echo "Press Ctrl+C to stop"
    echo "=========================================="
    
    # Monitor specific campaign log
    tail -f "$LOG_DIR/campaign_${CAMPAIGN_ID}.log" 2>/dev/null | while read line; do
        if echo "$line" | grep -q "SUCCESS"; then
            echo -e "\033[32m$line\033[0m"
        elif echo "$line" | grep -q "ERROR"; then
            echo -e "\033[31m$line\033[0m"
        else
            echo "$line"
        fi
    done
fi
