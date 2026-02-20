#!/bin/bash

# Campaign System Cleanup Script
# Removes unused, backup, and duplicate files
# Run with: bash backend/scripts/cleanup_unused_files.sh

echo "========================================="
echo "Campaign System File Cleanup"
echo "========================================="
echo ""

# Set script directory
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_ROOT="$(dirname "$(dirname "$SCRIPT_DIR")")"

cd "$PROJECT_ROOT" || exit 1

echo "Project root: $PROJECT_ROOT"
echo ""

# Count files to be removed
total_size=0
file_count=0

echo "Files marked for removal:"
echo "-----------------------------------------"

# Old backups
if [ -f "backend/includes/email_blast_worker.php.backup_1771221558" ]; then
    size=$(du -h "backend/includes/email_blast_worker.php.backup_1771221558" | cut -f1)
    echo "✗ backend/includes/email_blast_worker.php.backup_1771221558 ($size)"
    file_count=$((file_count + 1))
fi

# Old frontend archive
if [ -f "frontend_old.zip" ]; then
    size=$(du -h "frontend_old.zip" | cut -f1)
    echo "✗ frontend_old.zip ($size)"
    file_count=$((file_count + 1))
fi

# Test files (not needed in production)
test_files=(
    "backend/test_orchestrator.sh"
    "backend/test_master_endpoint.php"
    "backend/test_db_connections.php"
    "backend/includes/test_worker2_db.php"
    "backend/includes/test_cron_debug.php"
    "backend/test_smtp_accounts.php"
    "backend/test_localhost_db.php"
    "backend/test_server2_connection.php"
)

for file in "${test_files[@]}"; do
    if [ -f "$file" ]; then
        size=$(du -h "$file" | cut -f1)
        echo "✗ $file ($size)"
        file_count=$((file_count + 1))
    fi
done

# PID files (stale process identifiers)
if [ -f "backend/tmp/email_blaster_16.pid" ]; then
    size=$(du -h "backend/tmp/email_blaster_16.pid" | cut -f1)
    echo "✗ backend/tmp/email_blaster_16.pid ($size)"
    file_count=$((file_count + 1))
fi

echo "-----------------------------------------"
echo "Total files to remove: $file_count"
echo ""

# Ask for confirmation
read -p "Do you want to remove these files? (y/N): " -n 1 -r
echo ""

if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Cleanup cancelled."
    exit 0
fi

echo ""
echo "Removing files..."
echo "-----------------------------------------"

# Remove old backups
if [ -f "backend/includes/email_blast_worker.php.backup_1771221558" ]; then
    rm -f "backend/includes/email_blast_worker.php.backup_1771221558"
    echo "✓ Removed email_blast_worker.php.backup_1771221558"
fi

# Remove old frontend archive
if [ -f "frontend_old.zip" ]; then
    rm -f "frontend_old.zip"
    echo "✓ Removed frontend_old.zip"
fi

# Remove test files
for file in "${test_files[@]}"; do
    if [ -f "$file" ]; then
        rm -f "$file"
        echo "✓ Removed $file"
    fi
done

# Remove stale PID files
if [ -f "backend/tmp/email_blaster_16.pid" ]; then
    rm -f "backend/tmp/email_blaster_16.pid"
    echo "✓ Removed email_blaster_16.pid"
fi

echo "-----------------------------------------"
echo "✅ Cleanup complete!"
echo ""

# Show disk space saved
echo "Disk space analysis:"
du -sh backend/includes/ 2>/dev/null | sed 's/^/  backend/includes: /'
du -sh backend/tmp/ 2>/dev/null | sed 's/^/  backend/tmp: /'
du -sh . | sed 's/^/  Total project: /'

echo ""
echo "========================================="
echo "Remaining important files:"
echo "========================================="
echo ""
echo "✅ email_blast_parallel (1).php - Main orchestrator (OPTIMIZED)"
echo "✅ email_blast_worker (4).php - Email worker (ACTIVE)"
echo "✅ backend/includes/connection_pool.php - Connection pooling (NEW)"
echo "✅ backend/scripts/campaign_health_check.php - Health monitoring (NEW)"
echo ""
echo "Note: The files in backend/includes/ are older versions."
echo "The active worker files are in the project root (with version numbers)."
echo ""
