#!/bin/bash
# Deployment Script for Production Server
# Run this script ON THE PRODUCTION SERVER (payrollsoft.in)

set -e  # Exit on any error

echo "=========================================="
echo "🚀 MailPilot CRM Deployment Script"
echo "=========================================="
echo ""

# Check if we're in the right directory
if [ ! -f "package.json" ] && [ ! -d "backend" ]; then
    echo "❌ Error: Not in the application root directory"
    echo "Please navigate to the application directory first"
    exit 1
fi

# Backup current state
echo "📦 Creating backup..."
BACKUP_DIR="../emailvalidation_backup_$(date +%Y%m%d_%H%M%S)"
cp -r . "$BACKUP_DIR"
echo "✅ Backup created at: $BACKUP_DIR"
echo ""

# Pull latest code
echo "📥 Pulling latest code from Git..."
git fetch origin
git pull origin master
echo "✅ Code updated"
echo ""

# Navigate to frontend and rebuild
echo "🔨 Building frontend..."
cd frontend

# Check if node_modules exists
if [ ! -d "node_modules" ]; then
    echo "📦 Installing npm dependencies..."
    npm install
fi

# Build the frontend
npm run build

cd ..
echo "✅ Frontend built successfully"
echo ""

# Set proper permissions (adjust username as needed)
echo "🔒 Setting permissions..."
# Uncomment and modify the next line with your hosting username
# chown -R username:username .
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;
echo "✅ Permissions set"
echo ""

echo "=========================================="
echo "✅ Deployment Complete!"
echo "=========================================="
echo ""
echo "Next steps:"
echo "1. Clear your browser cache (Ctrl+Shift+R)"
echo "2. Visit: https://payrollsoft.in/emailvalidation/"
echo "3. Login and check Email Verification page"
echo ""
echo "If issues persist, check:"
echo "  - Browser console (F12)"
echo "  - Server error logs"
echo "  - File permissions"
echo ""
