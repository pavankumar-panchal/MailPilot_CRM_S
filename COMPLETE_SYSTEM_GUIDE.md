# Relyon CRM - Complete System Guide

## 🎯 System Overview

Relyon CRM is a full-stack email verification and campaign management system with:
- **Frontend**: React 18 + Vite + TailwindCSS (Port 5174)
- **Backend**: PHP 8+ + MariaDB (Apache on Port 80)
- **Authentication**: Token-based with 24-hour sessions
- **User Isolation**: Each user sees only their own data (admins see all)

---

## 🏗️ Architecture

### Frontend (React)
```
frontend/
├── src/
│   ├── App.jsx               # Main app with routing & auth
│   ├── components/
│   │   ├── Login.jsx         # Login form
│   │   ├── Register.jsx      # Registration form
│   │   └── Navbar.jsx        # Navigation bar
│   ├── pages/
│   │   ├── Home.jsx          # Dashboard
│   │   ├── EmailVerification.jsx
│   │   ├── Smtp.jsx
│   │   ├── Campaigns.jsx
│   │   ├── Master.jsx
│   │   └── MailTemplates.jsx
│   ├── utils/
│   │   └── authFetch.js      # Authenticated API calls
│   └── config.js             # API endpoints & URLs
├── vite.config.js            # Vite configuration with proxy
└── index.html                # Entry point (CSP removed)
```

### Backend (PHP)
```
backend/
├── app/
│   ├── login.php             # POST /api/login.php
│   ├── logout.php            # POST /api/logout.php
│   └── register.php          # POST /api/register.php
├── includes/
│   ├── auth_helper.php       # Authentication functions
│   ├── security_helpers.php  # CORS & validation
│   ├── session_config.php    # Session management
│   ├── get_csv_list.php      # GET email lists
│   ├── smtp_accounts.php     # SMTP management
│   └── campaign.php          # Campaign management
├── config/
│   └── db.php                # Database connection
└── database/
    ├── user_tokens_table.sql # Token storage schema
    └── auth_schema.sql       # Users table schema
```

---

## 🔐 Authentication System

### Token-Based Authentication

**How it works:**

1. **Login** (`/api/login.php`)
   - User submits email + password
   - Backend verifies credentials
   - Generates 64-character secure token
   - Stores token in `user_tokens` table with 24h expiry
   - Returns token + user data to frontend
   - Frontend stores in localStorage

2. **Authenticated Requests**
   - Frontend includes `Authorization: Bearer <token>` header
   - Backend validates token against database
   - Loads user context (ID, role, email, name)
   - Applies user filtering automatically

3. **Logout** (`/api/logout.php`)
   - Deletes token from database
   - Clears PHP session
   - Frontend removes localStorage data

### Key Functions

#### Backend: `auth_helper.php`

```php
// Get authenticated user (checks session + token)
$user = getAuthenticatedUser();

// Require authentication (401 if not logged in)
$user = requireAuth();

// Get current user ID
$userId = getCurrentUserId();

// Check if admin
$isAdmin = isAuthenticatedAdmin();

// Get SQL WHERE clause for user filtering
$where = getAuthFilterWhere();      // "WHERE user_id = 4"
$and = getAuthFilterAnd();          // "AND user_id = 4"
```

#### Frontend: `authFetch.js`

```javascript
import { authFetch, authGet, authPost } from './utils/authFetch';

// Automatically includes Authorization header
const response = await authFetch('/backend/includes/get_csv_list.php');

// Shorthand methods
const data = await authGet('/backend/includes/smtp_accounts.php');
await authPost('/backend/app/logout.php', {});
```

---

## 🗄️ Database Schema

### users
```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    role ENUM('user', 'admin') DEFAULT 'user',
    permissions JSON DEFAULT '[]',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### user_tokens
```sql
CREATE TABLE user_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) UNIQUE NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used_at TIMESTAMP NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (expires_at)
);
```

### All Tables with user_id
- csv_uploads
- csv_records
- smtp_accounts
- master_smtps
- campaigns
- campaign_status
- mail_blaster
- mail_templates
- email_responses
- ...and 12 more tables

---

## 🔄 User Data Filtering

### Regular Users
- See **only** their own data
- `user_id` column must match their ID
- Enforced in SQL queries using `getAuthFilterWhere()`

### Admin Users
- See **all** data across all users
- No filtering applied
- Full system access

### Implementation Example

```php
// In get_csv_list.php
$currentUser = requireAuth();

// Build query with user filtering
$whereClause = getAuthFilterWhere('c');  // 'c' is table alias

$sql = "SELECT * FROM csv_uploads c $whereClause";
// Regular user: "SELECT * FROM csv_uploads c WHERE c.user_id = 4"
// Admin user:   "SELECT * FROM csv_uploads c"

$result = $conn->query($sql);
```

---

## 🌐 CORS Configuration

### Backend: `security_helpers.php`

```php
function handleCors() {
    // Whitelist of allowed origins
    $allowedOrigins = [
        'http://localhost:5173',
        'http://localhost:5174',
        'http://localhost:5175',
        'http://localhost:5176',
        'https://payrollsoft.in'
    ];
    
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    
    if (in_array($origin, $allowedOrigins)) {
        header("Access-Control-Allow-Origin: $origin");
    }
    
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header("Access-Control-Allow-Credentials: true");
    
    // Handle preflight
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }
}
```

### Critical: Call Order

```php
// ✅ CORRECT ORDER
require_once 'security_helpers.php';
handleCors();  // BEFORE session_start()
require_once 'session_config.php';  // Calls session_start()

// ❌ WRONG ORDER (CORS headers won't send)
require_once 'session_config.php';  // session_start() happens here
handleCors();  // Too late! Headers already sent
```

---

## 🚀 Vite Dev Server Configuration

### vite.config.js

```javascript
export default defineConfig({
  server: {
    port: 5174,
    proxy: {
      // Proxy /api/* to backend
      '/api': {
        target: 'http://localhost',
        changeOrigin: true,
        rewrite: (path) => path.replace(/^\/api/, '/verify_emails/MailPilot_CRM_S/backend/app')
      },
      // Proxy /verify_emails/* to Apache
      '/verify_emails': {
        target: 'http://localhost',
        changeOrigin: true
      }
    }
  }
});
```

### How Proxying Works

```
Frontend Request:     /api/login.php
   ↓
Vite Proxy:          http://localhost/verify_emails/MailPilot_CRM_S/backend/app/login.php
   ↓
Apache PHP:          Processes request
   ↓
Response:            JSON with token
   ↓
Frontend:            Stores token in localStorage
```

---

## 📦 API Endpoints

### Authentication

| Endpoint | Method | Description | Auth Required |
|----------|--------|-------------|---------------|
| `/api/login.php` | POST | User login | No |
| `/api/logout.php` | POST | User logout | Yes |
| `/api/register.php` | POST | User registration | No |

### Email Lists

| Endpoint | Method | Description | Auth Required |
|----------|--------|-------------|---------------|
| `/backend/includes/get_csv_list.php` | GET | Get user's email lists | Yes |
| `/backend/includes/import_data.php` | POST | Upload new list | Yes |
| `/backend/includes/get_results.php` | GET | Get verification results | Yes |

### SMTP Accounts

| Endpoint | Method | Description | Auth Required |
|----------|--------|-------------|---------------|
| `/backend/includes/smtp_accounts.php` | GET | Get SMTP accounts | Yes |
| `/backend/includes/smtp_accounts.php` | POST | Add SMTP account | Yes |
| `/backend/includes/verify_smtp.php` | POST | Verify SMTP credentials | Yes |

### Campaigns

| Endpoint | Method | Description | Auth Required |
|----------|--------|-------------|---------------|
| `/backend/includes/campaign.php` | GET | Get campaigns | Yes |
| `/backend/includes/campaign.php` | POST | Create campaign | Yes |
| `/backend/includes/start_campaign.php` | POST | Start campaign | Yes |

### Mail Templates

| Endpoint | Method | Description | Auth Required |
|----------|--------|-------------|---------------|
| `/backend/includes/mail_templates.php?action=list` | GET | Get templates | Yes |
| `/backend/includes/mail_templates.php?action=create` | POST | Create template | Yes |
| `/backend/includes/mail_templates.php?action=update` | POST | Update template | Yes |
| `/backend/includes/mail_templates.php?action=delete` | POST | Delete template | Yes |

---

## 🧪 Testing

### Integration Test Page

Open: `http://localhost/verify_emails/MailPilot_CRM_S/test_integration.html`

Features:
- ✅ Test login/logout flow
- ✅ Test authenticated API calls
- ✅ Verify user data filtering
- ✅ Check CORS headers
- ✅ Test complete flow (login → fetch data → logout)

### Manual Testing

```bash
# Test login
curl -X POST http://localhost/verify_emails/MailPilot_CRM_S/backend/app/login.php \
  -H "Content-Type: application/json" \
  -d '{"email":"panchalpavan800@gmail.com","password":"Pavan@786"}' | jq

# Test authenticated request
curl -X GET http://localhost/verify_emails/MailPilot_CRM_S/backend/includes/get_csv_list.php \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" | jq

# Test logout
curl -X POST http://localhost/verify_emails/MailPilot_CRM_S/backend/app/logout.php \
  -b "MAILPILOT_SESSION=YOUR_SESSION_ID"
```

---

## 🐛 Common Issues & Fixes

### 1. Blank Frontend Page

**Problem**: React app doesn't load, blank white screen

**Solution**: Check [frontend/index.html](frontend/index.html) - Remove restrictive CSP meta tag

```html
<!-- ❌ REMOVE THIS -->
<meta http-equiv="Content-Security-Policy" content="script-src 'self'...">

<!-- ✅ OR USE THIS (development-friendly) -->
<meta http-equiv="Content-Security-Policy" content="default-src * 'unsafe-inline' 'unsafe-eval' data: blob:;">
```

### 2. CORS Errors

**Problem**: "Access-Control-Allow-Origin" header missing

**Solution**: Ensure `handleCors()` is called BEFORE `session_start()`

```php
// ✅ CORRECT
require_once 'security_helpers.php';
handleCors();
require_once 'session_config.php';

// ❌ WRONG
require_once 'session_config.php';
handleCors();  // Too late!
```

### 3. Authentication Not Working

**Problem**: 401 Unauthorized on all requests

**Solutions**:
- Check token in localStorage: `localStorage.getItem('mailpilot_token')`
- Verify token in database: `SELECT * FROM user_tokens WHERE token = 'YOUR_TOKEN' AND expires_at > NOW()`
- Check Authorization header: Open DevTools → Network → Request Headers
- Ensure `auth_helper.php` is included: `require_once __DIR__ . '/auth_helper.php';`

### 4. User Sees No Data

**Problem**: User logged in but lists/campaigns empty

**Solutions**:
- Check user_id: `SELECT id FROM users WHERE email = 'user@example.com'`
- Verify data exists: `SELECT * FROM csv_uploads WHERE user_id = 4`
- Check SQL query: Look for `WHERE user_id = ?` clause
- Verify `getAuthFilterWhere()` is used in endpoint

### 5. Dev Server Not Starting

**Problem**: `npm run dev` fails or port already in use

**Solutions**:
```bash
# Kill existing processes
pkill -9 node
pkill -9 vite

# Clear node_modules and reinstall
cd frontend
rm -rf node_modules package-lock.json
npm install

# Start dev server
npm run dev
```

### 6. API Requests Return Empty Response

**Problem**: HTTP 200 but empty body

**Solutions**:
- Check PHP error log: `tail -f /opt/lampp/logs/php_error_log`
- Enable error display:
```php
ini_set('display_errors', 1);
error_reporting(E_ALL);
```
- Check if `exit()` is called too early
- Verify `Content-Type: application/json` header is set

---

## 🔧 Development Workflow

### Starting the System

```bash
# 1. Start Apache + MySQL
sudo /opt/lampp/lampp start

# 2. Start Vite dev server
cd /opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/frontend
npm run dev

# 3. Open in browser
http://localhost:5174
```

### Stopping the System

```bash
# Stop Vite dev server
# Press Ctrl+C in the terminal running npm

# Stop Apache + MySQL
sudo /opt/lampp/lampp stop
```

### Making Changes

**Frontend Changes:**
- Edit files in `frontend/src/`
- Vite hot-reloads automatically
- No rebuild needed

**Backend Changes:**
- Edit PHP files in `backend/`
- Refresh browser to see changes
- Check `/opt/lampp/logs/php_error_log` for errors

**Database Changes:**
- Use phpMyAdmin: `http://localhost/phpmyadmin`
- Or MySQL CLI: `/opt/lampp/bin/mysql -u root -p`

---

## 📊 User Example: panchalpavan800@gmail.com

### Credentials
- **Email**: panchalpavan800@gmail.com
- **Password**: Pavan@786
- **User ID**: 4
- **Role**: user (not admin)

### Data Owned
- **Email List**: "asdf" (ID: 12)
  - Total emails: 100
  - Valid: 66
  - Invalid: 34
  - Created: 2026-01-21

### Expected Behavior
1. Login → Receives token
2. Navigate to Email Verification → Sees ONLY list "asdf"
3. Navigate to SMTP → Sees only their SMTP accounts
4. Navigate to Campaigns → Sees only their campaigns
5. Logout → Token deleted, redirected to login

---

## 🚀 Production Deployment

### Frontend Build

```bash
cd frontend
npm run build
# Creates optimized build in frontend/dist/
```

### Backend Configuration

1. Update `frontend/src/config.js`:
```javascript
const PRODUCTION_BASE = 'https://your-domain.com/path';
```

2. Update CORS in `backend/includes/security_helpers.php`:
```php
$allowedOrigins = [
    'https://your-domain.com'
];
```

3. Set secure session cookies in `backend/includes/session_config.php`:
```php
session_set_cookie_params([
    'lifetime' => 86400,
    'path' => '/',
    'domain' => 'your-domain.com',
    'secure' => true,  // HTTPS only
    'httponly' => true,
    'samesite' => 'Strict'
]);
```

### Deployment Steps

1. Upload `frontend/dist/` → Web root
2. Upload `backend/` → Server
3. Import database schema
4. Set file permissions: `chmod 755 backend/storage/`
5. Update environment-specific configs
6. Test authentication flow
7. Test API endpoints

---

## 📚 Additional Resources

### Files to Read
- [backend/AUTH_SYSTEM_GUIDE.md](backend/AUTH_SYSTEM_GUIDE.md) - Auth system details
- [backend/docs/CAMPAIGN_FLOW_EXPLAINED.md](backend/docs/CAMPAIGN_FLOW_EXPLAINED.md) - Campaign workflow
- [backend/docs/CAMPAIGN_EXCEL_TEMPLATE_GUIDE.md](backend/docs/CAMPAIGN_EXCEL_TEMPLATE_GUIDE.md) - Excel import

### Key Technologies
- **Frontend**: React 18, React Router 6, TailwindCSS 4, Vite 6
- **Backend**: PHP 8+, MariaDB 10.4+, Apache 2.4+
- **Authentication**: Token-based (64-char secure tokens)
- **Session Management**: PHP sessions + database tokens

---

## 🆘 Getting Help

### Check Logs

```bash
# PHP errors
tail -f /opt/lampp/logs/php_error_log

# Custom debug log
tail -f /tmp/get_csv_list_debug.log

# Apache errors
tail -f /opt/lampp/logs/error_log

# Browser console
Open DevTools → Console
```

### Debug Authentication

```javascript
// In browser console
console.log('Token:', localStorage.getItem('mailpilot_token'));
console.log('User:', localStorage.getItem('mailpilot_user'));
console.log('Expiry:', localStorage.getItem('mailpilot_token_expiry'));

// Check if token expired
const expiry = new Date(localStorage.getItem('mailpilot_token_expiry'));
console.log('Expired?', expiry < new Date());
```

### Database Queries

```sql
-- Check user exists
SELECT * FROM users WHERE email = 'panchalpavan800@gmail.com';

-- Check active tokens
SELECT t.*, u.email 
FROM user_tokens t 
JOIN users u ON t.user_id = u.id 
WHERE t.expires_at > NOW();

-- Check user's data
SELECT * FROM csv_uploads WHERE user_id = 4;
SELECT * FROM smtp_accounts WHERE user_id = 4;
SELECT * FROM campaigns WHERE user_id = 4;

-- Clean expired tokens
DELETE FROM user_tokens WHERE expires_at < NOW();
```

---

## ✅ System Status Checklist

- [x] Database migration completed (21 tables with user_id)
- [x] user_tokens table created
- [x] Token-based authentication implemented
- [x] User data filtering enforced
- [x] CORS headers configured correctly
- [x] Frontend CSP removed
- [x] Vite proxy configured
- [x] authFetch() helper created
- [x] Login/logout flow working
- [x] Integration test page created

---

**Last Updated**: January 21, 2026
**Version**: 2.0 (Token-based authentication)
**Status**: ✅ Production Ready
