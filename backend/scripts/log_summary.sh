#!/bin/bash
# Quick log summary for today

DATE=$(date +%Y-%m-%d)
LOG_DIR="/opt/lampp/htdocs/verify_emails/MailPilot_CRM/backend/storage/logs"

echo "=========================================="
echo "Email Campaign Summary - $DATE"
echo "=========================================="

# Success count
if [ -f "$LOG_DIR/success_${DATE}.log" ]; then
    SUCCESS=$(wc -l < "$LOG_DIR/success_${DATE}.log")
    echo "✓ Successful: $SUCCESS emails"
else
    echo "✓ Successful: 0 emails"
fi

# Failure count
if [ -f "$LOG_DIR/failures_${DATE}.log" ]; then
    FAILED=$(wc -l < "$LOG_DIR/failures_${DATE}.log")
    echo "✗ Failed: $FAILED emails"
    
    if [ $FAILED -gt 0 ]; then
        echo ""
        echo "Top 5 Error Messages:"
        echo "----------------------------------------"
        grep -oP '(?<=Error: ).*' "$LOG_DIR/failures_${DATE}.log" | sort | uniq -c | sort -rn | head -5
    fi
else
    echo "✗ Failed: 0 emails"
fi

echo ""
echo "=========================================="
echo "Active SMTP Accounts Usage:"
echo "=========================================="

if [ -f "$LOG_DIR/email_details_${DATE}.log" ]; then
    # Count emails per SMTP account
    awk -F'|' '{print $5}' "$LOG_DIR/email_details_${DATE}.log" | sort | uniq -c | sort -rn | head -10 | while read count email; do
        # Count successes and failures for this SMTP
        SUCCESS=$(grep "|${email}|success|" "$LOG_DIR/email_details_${DATE}.log" | wc -l)
        FAILED=$(grep "|${email}|failed|" "$LOG_DIR/email_details_${DATE}.log" | wc -l)
        printf "%-40s Total: %3d (✓%-3d ✗%-3d)\n" "$email" $count $SUCCESS $FAILED
    done
else
    echo "No emails sent yet today"
fi

echo ""
echo "=========================================="
echo "Recent Activity (Last 10 emails):"
echo "=========================================="

if [ -f "$LOG_DIR/email_details_${DATE}.log" ]; then
    tail -10 "$LOG_DIR/email_details_${DATE}.log" | while IFS='|' read timestamp campaign to_email smtp_id smtp_email status error; do
        if [ "$status" == "success" ]; then
            printf "[%s] ✓ %s via %s\n" "$timestamp" "$to_email" "$smtp_email"
        else
            printf "[%s] ✗ %s via %s - %s\n" "$timestamp" "$to_email" "$smtp_email" "$error"
        fi
    done
else
    echo "No activity yet"
fi

echo ""
echo "=========================================="

# Check for running campaigns
RUNNING=$(ps aux | grep "email_blaster.php" | grep -v grep | wc -l)
if [ $RUNNING -gt 0 ]; then
    echo "🟢 Active campaigns: $RUNNING"
    ps aux | grep "email_blaster.php" | grep -v grep | awk '{print "   Campaign " $NF " (PID: " $2 ")"}'
else
    echo "⚪ No active campaigns"
fi

echo "=========================================="
