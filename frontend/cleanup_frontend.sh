#!/bin/bash
echo "=== Cleaning Frontend ==="
echo ""

# 1. Remove build artifacts
echo "1. Cleaning build artifacts..."
rm -rf dist/ 2>/dev/null
rm -rf .vite/ 2>/dev/null
rm -rf node_modules/.cache/ 2>/dev/null
echo "   ✓ Build artifacts cleaned"

# 2. Remove reports
echo "2. Removing old reports..."
rm -f *.report.html 2>/dev/null
echo "   ✓ Reports removed"

# 3. Remove log files
echo "3. Cleaning log files..."
rm -f *.log 2>/dev/null
echo "   ✓ Log files cleaned"

# 4. Remove backup files
echo "4. Removing backup files..."
find . -name "*.bak" -delete 2>/dev/null
find . -name "*~" -delete 2>/dev/null
echo "   ✓ Backup files removed"

echo ""
echo "=== Frontend Cleanup Summary ==="
echo "Source files: $(find src/ -type f | wc -l)"
echo "Public assets: $(find public/ -type f | wc -l)"
echo ""
echo "✅ Frontend cleaned successfully!"
