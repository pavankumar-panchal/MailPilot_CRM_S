#!/bin/bash
# Test the Send button flow

echo "=== Testing Send Button Flow ==="
echo ""

# 1. Check if campaigns exist
echo "1. Checking campaigns..."
curl -s -X POST "http://localhost/verify_emails/MailPilot_CRM/backend/routes/api.php/api/master/campaigns_master" \
  -H "Content-Type: application/json" \
  -d '{"action":"list"}' | jq -r '.data.campaigns[] | "\(.campaign_id)\t\(.description)\t\(.campaign_status // "pending")"' | head -3

echo ""
echo "2. Testing Start Campaign (campaign_id=9)..."
curl -s -X POST "http://localhost/verify_emails/MailPilot_CRM/backend/routes/api.php/api/master/campaigns_master" \
  -H "Content-Type: application/json" \
  -d '{"action":"start_campaign","campaign_id":9}' | jq '.'

echo ""
echo "3. Checking if email_blaster process started..."
sleep 2
ps aux | grep "email_blaster.php 9" | grep -v grep

echo ""
echo "4. Checking campaign status after 3 seconds..."
sleep 1
curl -s -X POST "http://localhost/verify_emails/MailPilot_CRM/backend/routes/api.php/api/master/campaigns_master" \
  -H "Content-Type: application/json" \
  -d '{"action":"list"}' | jq -r '.data.campaigns[] | select(.campaign_id == 9) | "Status: \(.campaign_status) | Sent: \(.sent_emails)/\(.total_emails)"'

echo ""
echo "=== Test Complete ==="
