#!/bin/bash

cat <<'EOF'

╔═══════════════════════════════════════════════════════════════╗
║  🚨 FOUND THE PROBLEM - DNS/SPF CONFIGURATION ISSUE 🚨      ║
╚═══════════════════════════════════════════════════════════════╝

WHY EMAILS DON'T ARRIVE AT pavankumar.c@relyonsoft.com:
═══════════════════════════════════════════════════════════════

Your relyonsoft.com domain has RESTRICTIVE SPF record that 
BLOCKS emails from your external SMTP servers!

CURRENT SPF RECORD:
-------------------------------------------------------------------
v=spf1 ip4:109.203.124.121 +a +mx +ip4:213.229.120.7 ~all
-------------------------------------------------------------------
This ONLY allows 2 specific IPs. Your SMTP servers 
(relyonmail.xyz, payrollsoft.in, etc.) are BLOCKED!

CURRENT DMARC POLICY:
-------------------------------------------------------------------
p=reject - REJECTS all emails that fail SPF/DKIM
-------------------------------------------------------------------

═══════════════════════════════════════════════════════════════
✅ SOLUTION - UPDATE YOUR DNS RECORDS:
═══════════════════════════════════════════════════════════════

Option 1: ALLOW YOUR SMTP DOMAINS (Recommended)
-------------------------------------------------------------------
Update SPF record to include your SMTP sending domains:

v=spf1 ip4:109.203.124.121 +a +mx +ip4:213.229.120.7 include:relyonmail.xyz include:payrollsoft.in include:relyonmails1.com include:relyonmails2.com include:relyonmails3.com include:relyonmail.online ~all

This allows emails from:
✓ Your 2 existing IPs
✓ All your SMTP sending domains

Option 2: GET ALL SMTP SERVER IPs (More specific)
-------------------------------------------------------------------
Get IPs of all your SMTP servers and add them:

v=spf1 ip4:109.203.124.121 +a +mx +ip4:213.229.120.7 ip4:xxx.xxx.xxx.xxx ip4:yyy.yyy.yyy.yyy ~all

Option 3: RELAX DMARC (Temporary test)
-------------------------------------------------------------------
Change DMARC from p=reject to p=quarantine or p=none:

v=DMARC1;p=quarantine;sp=quarantine;...

This will send failed emails to spam instead of rejecting.

═══════════════════════════════════════════════════════════════
📋 HOW TO UPDATE DNS:
═══════════════════════════════════════════════════════════════

1. Login to your DNS provider (where relyonsoft.com is hosted)
   - GoDaddy, Namecheap, Cloudflare, etc.

2. Find DNS Management / DNS Zone Editor

3. Update TXT record for @ (root domain):
   - Find: v=spf1 ip4:109.203.124.121...
   - Replace with Option 1 above

4. Update TXT record for _dmarc:
   - Find: v=DMARC1;p=reject...
   - Change p=reject to p=quarantine (optional)

5. Wait 5-15 minutes for DNS propagation

6. Test again!

═══════════════════════════════════════════════════════════════
🧪 QUICK TEST:
═══════════════════════════════════════════════════════════════

After updating DNS, send a test email:

php backend/scripts/send_test_smtp.php pavankumar.c@relyonsoft.com

Check if it arrives in inbox or spam folder.

═══════════════════════════════════════════════════════════════
💡 WHY GMAIL WORKS:
═══════════════════════════════════════════════════════════════

Gmail (panchalpavan7090@gmail.com) receives emails because:
✓ Gmail has less restrictive SPF checking
✓ Gmail uses intelligent spam filtering
✓ Our SMTP servers are reputable enough for Gmail

Your domain (relyonsoft.com) blocks them because:
✗ SPF says "ONLY these 2 IPs allowed"
✗ DMARC says "REJECT anything that fails SPF"
✗ Your sending SMTPs are not in the allowed list

═══════════════════════════════════════════════════════════════
📞 WHO CAN HELP:
═══════════════════════════════════════════════════════════════

- Domain Administrator (manages relyonsoft.com DNS)
- Hosting Provider support
- IT Department managing your email server

═══════════════════════════════════════════════════════════════
✅ AFTER DNS UPDATE:
═══════════════════════════════════════════════════════════════

Emails will arrive at pavankumar.c@relyonsoft.com just like 
they arrive at panchalpavan7090@gmail.com!

═══════════════════════════════════════════════════════════════

EOF
