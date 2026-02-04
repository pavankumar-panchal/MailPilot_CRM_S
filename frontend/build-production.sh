#!/bin/bash

# Production Build and Verification Script
# This script builds the frontend for production and verifies the build

set -e  # Exit on error

echo "🚀 Starting Production Build Process..."
echo "========================================"

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Check if .env.production exists
if [ ! -f ".env.production" ]; then
    echo -e "${RED}❌ Error: .env.production file not found!${NC}"
    echo "Creating .env.production from .env.example..."
    cp .env.example .env.production
    echo -e "${YELLOW}⚠️  Please update .env.production with production values${NC}"
    exit 1
fi

echo -e "${GREEN}✓ Environment file found${NC}"

# Verify Node.js version
NODE_VERSION=$(node -v | cut -d'v' -f2 | cut -d'.' -f1)
if [ "$NODE_VERSION" -lt 18 ]; then
    echo -e "${RED}❌ Error: Node.js 18+ required. Current version: $(node -v)${NC}"
    exit 1
fi
echo -e "${GREEN}✓ Node.js version: $(node -v)${NC}"

# Check if node_modules exists
if [ ! -d "node_modules" ]; then
    echo -e "${YELLOW}⚠️  node_modules not found. Running npm install...${NC}"
    npm install
fi

# Run linting
echo ""
echo "🔍 Running ESLint..."
if npm run lint; then
    echo -e "${GREEN}✓ Linting passed${NC}"
else
    echo -e "${YELLOW}⚠️  Linting issues found (continuing anyway)${NC}"
fi

# Run type checking
echo ""
echo "🔍 Running TypeScript type check..."
if npm run typecheck; then
    echo -e "${GREEN}✓ Type checking passed${NC}"
else
    echo -e "${YELLOW}⚠️  Type checking issues found (continuing anyway)${NC}"
fi

# Clean previous build
echo ""
echo "🧹 Cleaning previous build..."
rm -rf dist
echo -e "${GREEN}✓ Cleaned${NC}"

# Build for production
echo ""
echo "🏗️  Building for production..."
if npm run build; then
    echo -e "${GREEN}✓ Build successful${NC}"
else
    echo -e "${RED}❌ Build failed${NC}"
    exit 1
fi

# Verify build output
echo ""
echo "🔍 Verifying build output..."

if [ ! -d "dist" ]; then
    echo -e "${RED}❌ Error: dist folder not created${NC}"
    exit 1
fi

if [ ! -f "dist/index.html" ]; then
    echo -e "${RED}❌ Error: index.html not found in dist${NC}"
    exit 1
fi

# Count files
FILE_COUNT=$(find dist -type f | wc -l)
echo -e "${GREEN}✓ Build contains $FILE_COUNT files${NC}"

# Check bundle sizes
echo ""
echo "📊 Bundle Sizes:"
echo "==============="
find dist/assets -name "*.js" -exec du -h {} \; | sort -hr | head -10
echo ""
find dist/assets -name "*.css" -exec du -h {} \; | sort -hr | head -5

# Calculate total size
TOTAL_SIZE=$(du -sh dist | cut -f1)
echo ""
echo -e "${GREEN}✓ Total build size: $TOTAL_SIZE${NC}"

# Check for source maps (should not exist in production)
if find dist -name "*.map" | grep -q .; then
    echo -e "${YELLOW}⚠️  Warning: Source maps found in production build${NC}"
fi

# Verify no console.logs in production bundle (basic check)
if grep -r "console.log" dist/assets/*.js 2>/dev/null | grep -v "//"; then
    echo -e "${YELLOW}⚠️  Warning: console.log statements found in bundle${NC}"
else
    echo -e "${GREEN}✓ No console.log statements found${NC}"
fi

echo ""
echo "========================================"
echo -e "${GREEN}✅ Production build completed successfully!${NC}"
echo ""
echo "📦 Build location: ./dist"
echo ""
echo "Next steps:"
echo "1. Test the build locally: npm run preview"
echo "2. Upload dist/ contents to: https://payrollsoft.in/emailvalidation/"
echo "3. Verify the deployment at: https://payrollsoft.in/emailvalidation/"
echo ""
