#!/bin/bash
# Multi-User SMTP Validation System - Quick Monitoring Script
# Usage: ./monitor.sh [option]

LOGS_DIR="/opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/backend/logs"
TODAY=$(date +%Y-%m-%d)

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

function show_menu() {
    echo -e "${BLUE}╔═══════════════════════════════════════════════════════════╗${NC}"
    echo -e "${BLUE}║   Multi-User SMTP Validation - Monitoring Dashboard      ║${NC}"
    echo -e "${BLUE}╚═══════════════════════════════════════════════════════════╝${NC}"
    echo ""
    echo "1. View main cron log (real-time)"
    echo "2. View all logs (combined)"
    echo "3. Check active workers"
    echo "4. Show processing statistics"
    echo "5. Monitor specific user (by ID)"
    echo "6. View latest worker logs"
    echo "7. Check system status"
    echo "8. Clear old logs (7+ days)"
    echo "9. Exit"
    echo ""
    read -p "Select option (1-9): " choice
}

function view_cron_log() {
    echo -e "${GREEN}=== Main Cron Log (Press Ctrl+C to exit) ===${NC}"
    tail -f "$LOGS_DIR/smtp_validation_cron_$TODAY.log"
}

function view_all_logs() {
    echo -e "${GREEN}=== All Logs Combined (Press Ctrl+C to exit) ===${NC}"
    tail -f "$LOGS_DIR"/*.log
}

function check_workers() {
    echo -e "${GREEN}=== Active Workers ===${NC}"
    ps aux | grep smtp_worker_parallel.php | grep -v grep
    
    worker_count=$(ps aux | grep smtp_worker_parallel.php | grep -v grep | wc -l)
    echo ""
    echo -e "${YELLOW}Total active workers: $worker_count${NC}"
}

function show_stats() {
    echo -e "${GREEN}=== Processing Statistics (Today) ===${NC}"
    echo ""
    
    # Count processed emails
    valid_count=$(grep -h "✓ EMAIL:" "$LOGS_DIR"/*_$TODAY*.log 2>/dev/null | wc -l)
    invalid_count=$(grep -h "✗ EMAIL:" "$LOGS_DIR"/*_$TODAY*.log 2>/dev/null | wc -l)
    total=$((valid_count + invalid_count))
    
    echo -e "${GREEN}Valid emails:   $valid_count${NC}"
    echo -e "${RED}Invalid emails: $invalid_count${NC}"
    echo -e "${BLUE}Total processed: $total${NC}"
    echo ""
    
    # Active users today
    echo -e "${YELLOW}Active users today:${NC}"
    ls "$LOGS_DIR"/user_*_$TODAY.log 2>/dev/null | sed 's/.*user_//' | sed 's/_.*$//' | sort -u
    echo ""
    
    # Latest cron run
    if [ -f "$LOGS_DIR/smtp_validation_cron_$TODAY.log" ]; then
        echo -e "${YELLOW}Latest cron activity:${NC}"
        tail -5 "$LOGS_DIR/smtp_validation_cron_$TODAY.log"
    fi
}

function monitor_user() {
    read -p "Enter User ID: " user_id
    
    user_log="$LOGS_DIR/user_${user_id}_$TODAY.log"
    
    if [ -f "$user_log" ]; then
        echo -e "${GREEN}=== User $user_id Log (Press Ctrl+C to exit) ===${NC}"
        tail -f "$user_log"
    else
        echo -e "${RED}No log found for User $user_id today${NC}"
        echo ""
        echo "Available user logs today:"
        ls -1 "$LOGS_DIR"/user_*_$TODAY.log 2>/dev/null | sed 's/.*user_/User /' | sed 's/_.*$//'
    fi
}

function view_worker_logs() {
    echo -e "${GREEN}=== Latest Worker Logs ===${NC}"
    echo ""
    
    latest_logs=$(ls -t "$LOGS_DIR"/user_*_worker_*.log 2>/dev/null | head -10)
    
    if [ -z "$latest_logs" ]; then
        echo -e "${RED}No worker logs found${NC}"
        return
    fi
    
    echo "Select a worker log:"
    select log_file in $latest_logs "Cancel"; do
        if [ "$log_file" == "Cancel" ]; then
            break
        elif [ -n "$log_file" ]; then
            echo -e "${GREEN}=== ${log_file##*/} ===${NC}"
            tail -100 "$log_file"
            break
        fi
    done
}

function check_status() {
    echo -e "${BLUE}╔═══════════════════════════════════════════════════════════╗${NC}"
    echo -e "${BLUE}║              System Status Check                          ║${NC}"
    echo -e "${BLUE}╚═══════════════════════════════════════════════════════════╝${NC}"
    echo ""
    
    # Check if cron is running
    cron_running=$(ps aux | grep smtp_validation_cron.php | grep -v grep | wc -l)
    if [ $cron_running -gt 0 ]; then
        echo -e "${GREEN}✓ Cron script: RUNNING${NC}"
    else
        echo -e "${YELLOW}○ Cron script: NOT RUNNING (will start at next minute)${NC}"
    fi
    
    # Check workers
    worker_count=$(ps aux | grep smtp_worker_parallel.php | grep -v grep | wc -l)
    if [ $worker_count -gt 0 ]; then
        echo -e "${GREEN}✓ Workers: $worker_count ACTIVE${NC}"
    else
        echo -e "${YELLOW}○ Workers: IDLE (no processing)${NC}"
    fi
    
    # Check lock file
    if [ -f "/opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/backend/storage/cron.lock" ]; then
        echo -e "${GREEN}✓ Lock file: EXISTS (processing active)${NC}"
    else
        echo -e "${YELLOW}○ Lock file: NOT FOUND (system idle)${NC}"
    fi
    
    # Check logs directory
    if [ -d "$LOGS_DIR" ]; then
        log_count=$(ls -1 "$LOGS_DIR"/*.log 2>/dev/null | wc -l)
        echo -e "${GREEN}✓ Logs directory: OK ($log_count log files)${NC}"
    else
        echo -e "${RED}✗ Logs directory: NOT FOUND${NC}"
    fi
    
    # Database test
    echo ""
    echo -e "${YELLOW}Testing database connection...${NC}"
    php -r "
    require '/opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/backend/config/db.php';
    if (isset(\$conn) && !\$conn->connect_error) {
        echo \"✓ Database: CONNECTED\\n\";
    } else {
        echo \"✗ Database: CONNECTION FAILED\\n\";
    }
    "
    
    echo ""
    echo -e "${BLUE}System check complete!${NC}"
}

function clear_old_logs() {
    echo -e "${YELLOW}=== Clearing logs older than 7 days ===${NC}"
    
    old_logs=$(find "$LOGS_DIR" -name "*.log" -mtime +7)
    count=$(echo "$old_logs" | grep -v '^$' | wc -l)
    
    if [ $count -eq 0 ]; then
        echo -e "${GREEN}No old logs to remove${NC}"
        return
    fi
    
    echo "Found $count log file(s) older than 7 days:"
    echo "$old_logs"
    echo ""
    read -p "Delete these logs? (y/n): " confirm
    
    if [ "$confirm" == "y" ] || [ "$confirm" == "Y" ]; then
        find "$LOGS_DIR" -name "*.log" -mtime +7 -delete
        echo -e "${GREEN}Old logs removed successfully${NC}"
    else
        echo -e "${YELLOW}Cancelled${NC}"
    fi
}

# Main loop
while true; do
    show_menu
    
    case $choice in
        1)
            view_cron_log
            ;;
        2)
            view_all_logs
            ;;
        3)
            check_workers
            ;;
        4)
            show_stats
            ;;
        5)
            monitor_user
            ;;
        6)
            view_worker_logs
            ;;
        7)
            check_status
            ;;
        8)
            clear_old_logs
            ;;
        9)
            echo -e "${GREEN}Goodbye!${NC}"
            exit 0
            ;;
        *)
            echo -e "${RED}Invalid option${NC}"
            ;;
    esac
    
    echo ""
    read -p "Press Enter to continue..."
    clear
done
