#!/bin/bash
# Test orchestrator startup for campaign #65

echo "🚀 Starting orchestrator test for campaign #65..."
echo "=========================================="

cd /var/www/vhosts/relyonmail.xyz/httpdocs/emailvalidation/backend/includes

# Run orchestrator and capture output
php email_blast_parallel.php 65 2>&1 | head -100

echo "=========================================="
echo "✅ Test complete - check output above"
