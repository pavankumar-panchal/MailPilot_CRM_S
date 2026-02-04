# Production Deployment Guide for payrollsoft.in/emailvalidation

## Pre-Deployment Checklist

- [ ] Backend API is deployed and accessible at `https://payrollsoft.in/emailvalidation/backend/`
- [ ] Database is configured and migrations are run
- [ ] SSL certificate is installed and HTTPS is working
- [ ] `.env.production` file is configured with correct values
- [ ] All dependencies are installed (`npm install`)

## Step-by-Step Deployment

### 1. Build the Application

```bash
cd /opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/frontend

# Use the production build script
npm run build:prod

# Or build manually
npm run build
```

This will:
- Run linting and type checks
- Create optimized production build in `dist/`
- Remove console.logs
- Minify and compress all assets
- Generate hashed filenames for cache busting

### 2. Verify Build Locally

```bash
npm run preview
```

Open `http://localhost:4173` and test:
- [ ] Login/Register works
- [ ] All pages load correctly
- [ ] API calls are successful
- [ ] No console errors

### 3. Deploy to Server

#### Option A: Using rsync (Recommended)

```bash
# From your local machine
rsync -avz --delete \
  --exclude='.env*' \
  --exclude='node_modules' \
  --exclude='.git' \
  dist/ user@payrollsoft.in:/var/www/html/emailvalidation/
```

#### Option B: Using FTP/SFTP

1. Connect to server via FTP/SFTP
2. Navigate to `/var/www/html/emailvalidation/`
3. Upload all files from `dist/` folder
4. Ensure `.htaccess` is uploaded

#### Option C: Manual Upload

1. Compress the dist folder:
   ```bash
   cd dist
   tar -czf frontend-build.tar.gz *
   ```

2. Upload `frontend-build.tar.gz` to server

3. SSH into server and extract:
   ```bash
   cd /var/www/html/emailvalidation
   tar -xzf frontend-build.tar.gz
   rm frontend-build.tar.gz
   ```

### 4. Server Configuration

#### Apache Configuration

1. Copy `.htaccess` to deployment directory:
   ```bash
   cp .htaccess /var/www/html/emailvalidation/.htaccess
   ```

2. Ensure Apache modules are enabled:
   ```bash
   sudo a2enmod rewrite
   sudo a2enmod headers
   sudo a2enmod deflate
   sudo a2enmod expires
   sudo systemctl restart apache2
   ```

3. Update Apache VirtualHost (if needed):
   ```apache
   <VirtualHost *:443>
       ServerName payrollsoft.in
       DocumentRoot /var/www/html
       
       <Directory /var/www/html/emailvalidation>
           AllowOverride All
           Require all granted
           Options -Indexes +FollowSymLinks
       </Directory>
       
       # SSL Configuration
       SSLEngine on
       SSLCertificateFile /path/to/certificate.crt
       SSLCertificateKeyFile /path/to/private.key
   </VirtualHost>
   ```

#### Nginx Configuration (Alternative)

```nginx
server {
    listen 443 ssl;
    server_name payrollsoft.in;
    
    root /var/www/html;
    index index.html;
    
    location /emailvalidation {
        alias /var/www/html/emailvalidation;
        try_files $uri $uri/ /emailvalidation/index.html;
        
        # Security headers
        add_header X-Frame-Options "SAMEORIGIN" always;
        add_header X-XSS-Protection "1; mode=block" always;
        add_header X-Content-Type-Options "nosniff" always;
        add_header Referrer-Policy "strict-origin-when-cross-origin" always;
        
        # Cache static assets
        location ~* \.(js|css|png|jpg|jpeg|gif|svg|ico|woff|woff2)$ {
            expires 1y;
            add_header Cache-Control "public, immutable";
        }
    }
    
    # SSL Configuration
    ssl_certificate /path/to/certificate.crt;
    ssl_certificate_key /path/to/private.key;
}
```

### 5. Set Correct Permissions

```bash
# On the server
cd /var/www/html/emailvalidation
sudo chown -R www-data:www-data .
sudo find . -type f -exec chmod 644 {} \;
sudo find . -type d -exec chmod 755 {} \;
```

### 6. Test Deployment

Visit `https://payrollsoft.in/emailvalidation/` and verify:

- [ ] Homepage loads correctly
- [ ] Login/Register pages work
- [ ] API calls are successful
- [ ] No console errors
- [ ] All routes work (use browser navigation)
- [ ] Images and assets load correctly
- [ ] HTTPS is enforced
- [ ] Security headers are present

### 7. Post-Deployment Verification

#### Check Security Headers

```bash
curl -I https://payrollsoft.in/emailvalidation/
```

Should include:
- `X-Frame-Options: SAMEORIGIN`
- `X-XSS-Protection: 1; mode=block`
- `X-Content-Type-Options: nosniff`
- `Strict-Transport-Security: max-age=31536000`

#### Check Performance

1. Open Chrome DevTools
2. Run Lighthouse audit
3. Verify scores:
   - Performance: > 90
   - Accessibility: > 90
   - Best Practices: > 90
   - SEO: > 80

#### Monitor Errors

```bash
# Check Apache error logs
sudo tail -f /var/log/apache2/error.log

# Check access logs
sudo tail -f /var/log/apache2/access.log
```

## Rollback Procedure

If deployment fails:

1. Keep a backup of previous `dist/` folder
2. Restore previous version:
   ```bash
   cd /var/www/html/emailvalidation
   rm -rf *
   cp -r /backup/emailvalidation/* .
   ```

## Continuous Deployment (Optional)

### GitHub Actions Workflow

Create `.github/workflows/deploy.yml`:

```yaml
name: Deploy to Production

on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      
      - name: Setup Node.js
        uses: actions/setup-node@v2
        with:
          node-version: '18'
      
      - name: Install dependencies
        run: npm ci
        working-directory: ./frontend
      
      - name: Build
        run: npm run build
        working-directory: ./frontend
        env:
          VITE_API_BASE_URL: https://payrollsoft.in/emailvalidation
      
      - name: Deploy via rsync
        uses: burnett01/rsync-deployments@5.2
        with:
          switches: -avzr --delete
          path: frontend/dist/
          remote_path: /var/www/html/emailvalidation/
          remote_host: payrollsoft.in
          remote_user: ${{ secrets.DEPLOY_USER }}
          remote_key: ${{ secrets.DEPLOY_SSH_KEY }}
```

## Troubleshooting

### Issue: Blank page after deployment
**Solution**: Check browser console for errors. Verify base path in `vite.config.js`

### Issue: 404 on page refresh
**Solution**: Ensure `.htaccess` is present and `mod_rewrite` is enabled

### Issue: API calls fail
**Solution**: Check CORS settings in backend, verify `VITE_API_BASE_URL`

### Issue: Assets not loading
**Solution**: Check file permissions and paths in `.htaccess`

## Support

For deployment issues:
1. Check server logs: `/var/log/apache2/error.log`
2. Check browser console for errors
3. Verify environment configuration
4. Contact system administrator

---

**Last Updated**: February 2026
