# Localhost Setup Guide

## ✅ Database Configuration Fixed!

Your database configuration has been updated to work correctly on **localhost (XAMPP)**.

---

## 📋 Quick Setup Steps

### 1. **Start XAMPP**
   - Open XAMPP Control Panel
   - Start **Apache** and **MySQL**

### 2. **Create Databases**

Open phpMyAdmin: http://localhost/phpmyadmin

#### Create Database 1: `email_id`
1. Click **"New"** to create a database
2. Database name: `email_id`
3. Collation: `utf8mb4_unicode_ci`
4. Click **"Create"**
5. Import the first SQL dump you provided (email_id database)

#### Create Database 2: `CRM`
1. Click **"New"** to create a database
2. Database name: `CRM`
3. Collation: `utf8mb4_general_ci`
4. Click **"Create"**
5. Import the second SQL dump you provided (CRM database)

### 3. **Test Database Connections**

Visit this URL in your browser:
```
http://localhost/verify_emails/MailPilot_CRM_S/backend/test_localhost_db.php
```

You should see:
- ✅ Server 1 Database (email_id) - Connected
- ✅ Server 2 Database (CRM) - Connected
- ✅ All required tables listed

---

## 🗄️ Database Architecture

### Server 1 Database: `email_id`
**Tables:**
- `campaign_master` - Campaign definitions
- `campaign_status` - Campaign progress tracking
- `imported_recipients` - Excel import data
- `emails` - CSV email lists
- `users` - User accounts
- `mail_templates` - Email templates

### Server 2 Database: `CRM`
**Tables:**
- `mail_blaster` - Email sending queue
- `smtp_accounts` - SMTP credentials
- `smtp_servers` - SMTP server configs
- `smtp_health` - Health monitoring
- `smtp_rotation` - Load balancing
- `smtp_usage` - Usage tracking

---

## 🔧 Configuration Details

### Modified Files:
1. **`backend/config/db.php`** - Server 1 database (email_id)
   - Localhost: Connects to `127.0.0.1`
   - Username: `root`
   - Password: `` (empty)
   - Database: `email_id`

2. **`backend/config/db_campaign.php`** - Server 2 database (CRM)
   - Localhost: Connects to `127.0.0.1`
   - Username: `root`
   - Password: `` (empty)
   - Database: `CRM`

### How It Detects Localhost:
- Checks if `SERVER_NAME` is `localhost` or `127.0.0.1`
- Checks if path contains `/opt/lampp/` or `/xampp/`
- Checks if path contains `C:\xampp\` (Windows)

---

## 🚀 Next Steps After Database Setup

### 1. Create a Test User
```sql
-- In email_id database
INSERT INTO users (email, password_hash, role, name, is_active) 
VALUES ('admin@test.com', '$2y$10$YourHashedPasswordHere', 'admin', 'Admin User', 1);
```

### 2. Add SMTP Server
```sql
-- In CRM database
INSERT INTO smtp_servers (name, host, port, encryption, received_email, is_active, user_id) 
VALUES ('Gmail', 'smtp.gmail.com', 587, 'tls', 'your-email@gmail.com', 1, 1);
```

### 3. Add SMTP Account
```sql
-- In CRM database
INSERT INTO smtp_accounts (smtp_server_id, email, password, daily_limit, hourly_limit, is_active, user_id) 
VALUES (1, 'your-email@gmail.com', 'your-app-password', 500, 50, 1, 1);
```

### 4. Start the Frontend
```bash
cd frontend
npm install
npm run dev
```

Visit: http://localhost:5173

---

## 🔍 Troubleshooting

### Problem: "Connection failed" error
**Solution:**
1. Make sure MySQL is running in XAMPP
2. Check databases exist in phpMyAdmin
3. Verify database names are exactly `email_id` and `CRM`

### Problem: "Access denied for user 'root'"
**Solution:**
1. In XAMPP, root usually has no password
2. If you set a password, update both config files:
   - `backend/config/db.php` (line ~40)
   - `backend/config/db_campaign.php` (line ~40)

### Problem: "Table doesn't exist"
**Solution:**
1. Import the SQL dumps into the correct databases
2. Make sure table names match exactly (case-sensitive on Linux)

### Problem: Campaign not sending emails
**Solution:**
1. Check `smtp_servers` table has entries
2. Check `smtp_accounts` table has active accounts
3. Verify `user_id` matches between users and SMTP accounts

---

## 📊 Test Database Connection

Run the test script:
```
http://localhost/verify_emails/MailPilot_CRM_S/backend/test_localhost_db.php
```

This will show:
- ✅ Which databases are connected
- ✅ Which tables exist
- ✅ How many records in each table
- ❌ Any missing tables or connection errors

---

## 🌐 Environment Detection

The system automatically detects:

| Environment | Detection Method | Server 1 (email_id) | Server 2 (CRM) |
|-------------|------------------|---------------------|----------------|
| **Localhost** | Path contains `/opt/lampp/` or `/xampp/` | 127.0.0.1 (local) | 127.0.0.1 (local) |
| **Server 1** | File exists `/var/www/vhosts/payrollsoft.in` | 127.0.0.1 (local) | 207.244.80.245 (remote) |
| **Server 2** | File exists `/var/www/vhosts/relyonmail.xyz` | 174.141.233.174 (remote) | 127.0.0.1 (local) |

---

## ✅ Verification Checklist

Before using the system:

- [ ] MySQL is running in XAMPP
- [ ] Database `email_id` exists
- [ ] Database `CRM` exists
- [ ] All tables imported successfully
- [ ] Test script shows green checkmarks
- [ ] At least one user created
- [ ] At least one SMTP server configured
- [ ] At least one SMTP account added
- [ ] Frontend is running (npm run dev)

---

## 📞 Support

If you encounter issues:
1. Check the test script output
2. Review XAMPP error logs
3. Check browser console for errors
4. Verify all databases and tables exist

---

Generated: 2026-02-20
Updated for: Localhost (XAMPP/LAMPP) Support
