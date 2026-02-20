#!/bin/bash
# Cleanup old batch directories from previous system versions
# This removes batch directories that don't have user_id.txt or worker_id.txt tracking files

BOLD='\033[1m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${BOLD}Old Batch Directories Cleanup${NC}"
echo "======================================"
echo ""

# Count old directories
OLD_DIRS=$(find /tmp -maxdepth 1 -type d -name 'bulk_workers_*' ! -newer /tmp -exec sh -c '
    for dir; do
        if [ ! -f "$dir/user_id.txt" ] && [ ! -f "$dir/worker_id.txt" ]; then
            echo "$dir"
        fi
    done
' sh {} + 2>/dev/null | wc -l)

if [ "$OLD_DIRS" -eq 0 ]; then
    echo -e "${GREEN}No old batch directories found. System is clean!${NC}"
    exit 0
fi

echo -e "${YELLOW}Found $OLD_DIRS old batch directories without tracking files${NC}"
echo ""
echo "These were created before the multi-user system update."
echo "They are safe to delete as workers have already updated the database."
echo ""
read -p "Delete all old batch directories? (y/n): " confirm

if [ "$confirm" != "y" ] && [ "$confirm" != "Y" ]; then
    echo -e "${YELLOW}Cancelled.${NC}"
    exit 0
fi

echo ""
echo -e "${GREEN}Cleaning up old batches...${NC}"

DELETED=0
FAILED=0

for dir in /tmp/bulk_workers_*; do
    if [ -d "$dir" ]; then
        # Check if it's an old directory (no tracking files)
        if [ ! -f "$dir/user_id.txt" ] && [ ! -f "$dir/worker_id.txt" ]; then
            # Remove all files in directory
            rm -f "$dir"/* 2>/dev/null
            # Remove directory
            if rmdir "$dir" 2>/dev/null; then
                DELETED=$((DELETED + 1))
                # Show progress every 100 directories
                if [ $((DELETED % 100)) -eq 0 ]; then
                    echo "  Deleted $DELETED directories..."
                fi
            else
                FAILED=$((FAILED + 1))
            fi
        fi
    fi
done

echo ""
echo -e "${GREEN}✓ Cleanup complete!${NC}"
echo "  Deleted: $DELETED directories"
if [ "$FAILED" -gt 0 ]; then
    echo -e "  ${RED}Failed: $FAILED directories${NC}"
fi
echo ""
echo "The cron job will now start faster."
