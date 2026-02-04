# Relyon CRM - Frontend

Professional email campaign management system with SMTP verification, bulk sending, and real-time monitoring.

## 🚀 Features

- **Campaign Management**: Create, edit, and manage email campaigns with rich text editor
- **SMTP Configuration**: Add and manage multiple SMTP servers and accounts
- **Email Verification**: Verify email addresses before sending campaigns
- **Real-time Monitoring**: Track campaign progress and email delivery status
- **Template System**: Create and manage reusable email templates
- **Worker Management**: Control campaign workers for parallel processing
- **Responsive Design**: Modern, responsive UI built with React and Tailwind CSS

## 📋 Prerequisites

- Node.js 18+ and npm
- Backend API server (PHP/Apache) running at configured base URL
- Modern web browser (Chrome, Firefox, Safari, Edge)

## 🛠️ Installation

1. **Clone the repository**
   ```bash
   cd /path/to/project/frontend
   ```

2. **Install dependencies**
   ```bash
   npm install
   ```

3. **Configure environment**
   
   Copy the example environment file:
   ```bash
   cp .env.example .env.development
   ```
   
   Update `.env.development` with your local settings:
   ```env
   VITE_API_BASE_URL=http://localhost/verify_emails/MailPilot_CRM_S
   VITE_APP_TITLE=Relyon CRM (Dev)
   VITE_ENABLE_CONSOLE_LOGS=true
   ```

4. **Start development server**
   ```bash
   npm run dev
   ```
   
   Open [http://localhost:5173](http://localhost:5173) in your browser.

## 🏗️ Building for Production

### 1. Configure Production Environment

Create `.env.production`:
```env
VITE_API_BASE_URL=https://payrollsoft.in/emailvalidation
VITE_APP_TITLE=Relyon CRM
VITE_ENABLE_CONSOLE_LOGS=false
```

### 2. Build the Application

```bash
npm run build
```

This creates an optimized production build in the `dist/` folder with:
- Minified and compressed code
- Console logs removed
- Source maps disabled
- Code splitting and tree shaking
- Asset optimization

### 3. Preview Production Build Locally

```bash
npm run preview
```

## 📦 Deployment to payrollsoft.in/emailvalidation

### Option 1: Manual Deployment

1. **Build the application**
   ```bash
   npm run build
   ```

2. **Upload `dist/` folder contents to server**
   
   Upload all files from `dist/` to:
   ```
   /var/www/html/emailvalidation/
   ```
   
   Or using rsync:
   ```bash
   rsync -avz --delete dist/ user@payrollsoft.in:/var/www/html/emailvalidation/
   ```

3. **Configure Apache/Nginx**
   
   Add `.htaccess` for React Router (Apache):
   ```apache
   <IfModule mod_rewrite.c>
     RewriteEngine On
     RewriteBase /emailvalidation/
     RewriteRule ^index\.html$ - [L]
     RewriteCond %{REQUEST_FILENAME} !-f
     RewriteCond %{REQUEST_FILENAME} !-d
     RewriteRule . /emailvalidation/index.html [L]
   </IfModule>
   ```

## 🔐 Security Features

- ✅ Content Security Policy (CSP) configured
- ✅ HTML sanitization for user-generated content
- ✅ Token-based authentication with expiry
- ✅ Console logs removed in production
- ✅ XSS protection via input validation

## 📊 Performance Optimizations

- Code splitting by route and vendor
- Lazy loading for heavy components
- Tree shaking to remove unused code
- Minification and compression

## 📝 Available Scripts

| Script | Description |
|--------|-------------|
| `npm run dev` | Start development server |
| `npm run build` | Build for production |
| `npm run preview` | Preview production build |
| `npm run lint` | Run ESLint |
| `npm run lint:fix` | Fix ESLint errors |

---

**Built with** ⚛️ React | ⚡ Vite | 🎨 Tailwind CSS
