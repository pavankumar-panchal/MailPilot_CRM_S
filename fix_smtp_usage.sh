#!/bin/bash
# Script to fix smtp_usage duplicates and verify the fix

echo "========================================"
echo "Fixing smtp_usage duplicate rows"
echo "========================================"
echo ""

# Path to MySQL
MYSQL_CMD="/opt/lampp/bin/mysql"
DB_NAME="your_database_name"  # UPDATE THIS
DB_USER="root"
DB_PASS=""  # UPDATE THIS

echo "Step 1: Backing up smtp_usage table..."
$MYSQL_CMD -u$DB_USER -p$DB_PASS $DB_NAME -e "CREATE TABLE IF NOT EXISTS smtp_usage_backup_$(date +%Y%m%d) AS SELECT * FROM smtp_usage;"
echo "✓ Backup created"
echo ""

echo "Step 2: Checking for duplicate rows..."
DUPLICATES=$($MYSQL_CMD -u$DB_USER -p$DB_PASS $DB_NAME -N -e "SELECT COUNT(*) FROM (SELECT smtp_id, date, hour, COALESCE(user_id,1) as uid, COUNT(*) as cnt FROM smtp_usage GROUP BY smtp_id, date, hour, uid HAVING cnt > 1) as dups;")
echo "Found $DUPLICATES duplicate row groups"
echo ""

if [ "$DUPLICATES" -gt 0 ]; then
    echo "Step 3: Applying fix..."
    $MYSQL_CMD -u$DB_USER -p$DB_PASS $DB_NAME < $(dirname $0)/database/fix_smtp_usage_duplicates.sql
    echo "✓ Fix applied"
    echo ""
    
    echo "Step 4: Verifying fix..."
    REMAINING=$($MYSQL_CMD -u$DB_USER -p$DB_PASS $DB_NAME -N -e "SELECT COUNT(*) FROM (SELECT smtp_id, date, hour, user_id, COUNT(*) as cnt FROM smtp_usage GROUP BY smtp_id, date, hour, user_id HAVING cnt > 1) as dups;")
    
    if [ "$REMAINING" -eq 0 ]; then
        echo "✅ SUCCESS! No duplicate rows remaining"
    else
        echo "⚠️  WARNING: Still have $REMAINING duplicate groups"
    fi
else
    echo "✓ No duplicates found - table is clean"
fi

echo ""
echo "Step 5: Table statistics..."
$MYSQL_CMD -u$DB_USER -p$DB_PASS $DB_NAME -e "
    SELECT 
        COUNT(*) as total_rows,
        COUNT(DISTINCT smtp_id) as unique_smtp_accounts,
        COUNT(DISTINCT date) as unique_dates,
        MIN(date) as earliest_date,
        MAX(date) as latest_date
    FROM smtp_usage;
"

echo ""
echo "========================================"
echo "Fix completed!"
echo "========================================"
