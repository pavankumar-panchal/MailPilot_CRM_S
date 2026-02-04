# Quick Deployment Reference

## 🚀 Deploy to payrollsoft.in/emailvalidation in 3 Steps

### 1️⃣ Build
```bash
cd /opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/frontend
npm run build
```

### 2️⃣ Deploy
```bash
# Upload dist/ contents to server:
# /var/www/html/emailvalidation/

# Using rsync:
rsync -avz --delete dist/ user@payrollsoft.in:/var/www/html/emailvalidation/
```

### 3️⃣ Verify
Visit: https://payrollsoft.in/emailvalidation/

---

## 📝 Important Files

| File | Purpose |
|------|---------|
| `.env.production` | Production environment config |
| `dist/` | Build output (upload this) |
| `.htaccess` | Apache configuration (include in dist/) |
| `DEPLOYMENT.md` | Full deployment guide |

## 🔧 Environment Variables

```env
VITE_API_BASE_URL=https://payrollsoft.in/emailvalidation
VITE_APP_TITLE=Relyon CRM
VITE_ENABLE_CONSOLE_LOGS=false
```

## ⚡ Quick Commands

```bash
# Development
npm run dev              # Start dev server

# Production
npm run build            # Build for production
npm run build:prod       # Build with verification
npm run preview          # Test build locally

# Quality
npm run lint             # Check code quality
npm run typecheck        # Check types
npm run format           # Format code
```

## 🆘 Troubleshooting

**Blank page?**
- Check `.env.production` has correct URL
- Verify `.htaccess` is uploaded
- Check browser console for errors

**404 on refresh?**
- Enable `mod_rewrite` on Apache
- Verify `.htaccess` is present

**API calls fail?**
- Check CORS settings in backend
- Verify `VITE_API_BASE_URL` is correct
- Check browser Network tab

## 📊 Build Output

Expected build size: ~700 KB (gzipped)
Expected chunks: 20+ files
Build time: 15-25 seconds

## ✅ Pre-Deployment Checklist

- [ ] `.env.production` configured
- [ ] `npm run build` successful
- [ ] `npm run preview` tested locally
- [ ] Backend API accessible
- [ ] Team notified

## 🔄 Rollback

```bash
# Keep backup before deploying
cp -r dist/ dist-backup/

# To rollback
cp -r dist-backup/* dist/
# Re-upload to server
```

---

For detailed instructions, see: `DEPLOYMENT.md`
