#!/bin/bash

# Deployment script for MailPilot CRM Frontend
# This script copies the built frontend files to the production server

echo "========================================"
echo "MailPilot CRM - Production Deployment"
echo "========================================"
echo ""

# Configuration
PRODUCTION_SERVER="payrollsoft.in"
PRODUCTION_PATH="/home/payrolls/public_html/emailvalidation"
PRODUCTION_USER="payrolls"  # Change this to your SSH username

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if dist folder exists
if [ ! -d "dist" ]; then
    echo -e "${RED}Error: dist folder not found!${NC}"
    echo "Please run 'npm run build' first"
    exit 1
fi

echo -e "${YELLOW}Building fresh production bundle...${NC}"
npm run build

if [ $? -ne 0 ]; then
    echo -e "${RED}Build failed! Aborting deployment.${NC}"
    exit 1
fi

echo -e "${GREEN}Build successful!${NC}"
echo ""

# Create a tarball of the dist folder
echo -e "${YELLOW}Creating deployment package...${NC}"
cd dist
tar -czf ../frontend-dist.tar.gz .
cd ..

if [ $? -ne 0 ]; then
    echo -e "${RED}Failed to create deployment package!${NC}"
    exit 1
fi

echo -e "${GREEN}Package created: frontend-dist.tar.gz${NC}"
echo ""

# Display deployment instructions
echo -e "${YELLOW}========================================"
echo "DEPLOYMENT INSTRUCTIONS"
echo "========================================${NC}"
echo ""
echo "Option 1: Manual Upload via FTP/SFTP"
echo "1. Upload 'frontend-dist.tar.gz' to your server"
echo "2. SSH into your server: ssh ${PRODUCTION_USER}@${PRODUCTION_SERVER}"
echo "3. Navigate to: cd ${PRODUCTION_PATH}"
echo "4. Backup current files: mv dist dist.backup.$(date +%Y%m%d_%H%M%S)"
echo "5. Create new dist directory: mkdir -p dist"
echo "6. Extract: tar -xzf frontend-dist.tar.gz -C dist/"
echo "7. Set permissions: chmod -R 755 dist/"
echo ""
echo "Option 2: Automatic Deployment via SSH (if configured)"
echo "Run the following commands:"
echo ""
echo "# Upload the package"
echo "scp frontend-dist.tar.gz ${PRODUCTION_USER}@${PRODUCTION_SERVER}:${PRODUCTION_PATH}/"
echo ""
echo "# SSH and deploy"
echo "ssh ${PRODUCTION_USER}@${PRODUCTION_SERVER} << 'ENDSSH'"
echo "cd ${PRODUCTION_PATH}"
echo "mv dist dist.backup.\$(date +%Y%m%d_%H%M%S) 2>/dev/null || true"
echo "mkdir -p dist"
echo "tar -xzf frontend-dist.tar.gz -C dist/"
echo "chmod -R 755 dist/"
echo "rm frontend-dist.tar.gz"
echo "echo 'Deployment complete!'"
echo "ENDSSH"
echo ""
echo -e "${GREEN}========================================"
echo "Package ready for deployment!"
echo "========================================${NC}"
echo ""
echo "Location: $(pwd)/frontend-dist.tar.gz"
echo "Size: $(du -h frontend-dist.tar.gz | cut -f1)"
echo ""

# Clean up old tarballs older than 7 days
find . -name "frontend-dist*.tar.gz" -mtime +7 -delete 2>/dev/null
