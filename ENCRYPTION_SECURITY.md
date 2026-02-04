# Encryption & Security Methods Documentation

## 🔐 Authentication & Encryption Methods Used

### 1. **Password Encryption**

#### Method: **BCrypt (PASSWORD_BCRYPT)**
- **Algorithm**: bcrypt (Blowfish-based)
- **Cost Factor**: 12 (2^12 = 4096 iterations)
- **Location**: `backend/auth/AuthController.php`

```php
// Password Hashing (Registration)
$passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

// Password Verification (Login)
$isValid = password_verify($password, $user['password_hash']);
```

#### **Why BCrypt?**
- ✅ Industry standard for password hashing
- ✅ Built-in salt generation (random 128-bit salt)
- ✅ Adaptive - cost factor can be increased over time
- ✅ Resistant to rainbow table attacks
- ✅ Resistant to brute force (slow by design)
- ✅ Recommended by OWASP

#### **Password Storage Format**
```
$2y$12$[22-character-salt][31-character-hash]
Example: $2y$12$abcdefghijklmnopqrstuv1234567890ABCDEFGHIJKLMNOP
```

---

### 2. **Session Token Encryption**

#### Method: **Cryptographically Secure Random Bytes**
- **Function**: `random_bytes(32)` → `bin2hex()`
- **Length**: 64 hexadecimal characters (32 bytes)
- **Location**: `backend/auth/AuthModel.php`

```php
// Session Token Generation
$token = bin2hex(random_bytes(32));
// Example: 3f2e8b9c1a7d4e5f6g8h9i0j1k2l3m4n5o6p7q8r9s0t1u2v3w4x5y6z7a8b9c0d1e2f
```

#### **Properties**
- ✅ Cryptographically secure (uses OS entropy sources)
- ✅ Unpredictable
- ✅ Unique per session
- ✅ 256-bit security strength
- ✅ Stored in database for verification

---

### 3. **CSRF Token Protection**

#### Method: **Random Bytes with Hash Comparison**
- **Function**: `bin2hex(random_bytes(32))`
- **Comparison**: `hash_equals()` (timing-attack safe)
- **Location**: `backend/auth/AuthMiddleware.php`

```php
// CSRF Token Generation
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// CSRF Token Verification (timing-attack resistant)
if (!hash_equals($sessionToken, $requestToken)) {
    // Invalid token
}
```

#### **CSRF Protection Flow**
1. **Token Generation**: On login, generate random 64-char token
2. **Storage**: Store in PHP session (`$_SESSION['csrf_token']`)
3. **Client Transmission**: Send to frontend in login response
4. **Verification**: Check on POST/PUT/DELETE/PATCH requests
5. **Comparison**: Use `hash_equals()` to prevent timing attacks

#### **Token Sources Checked** (in order of priority)
1. HTTP Header: `X-CSRF-Token`
2. POST data: `$_POST['csrf_token']`
3. JSON body: `$input['csrf_token']`

---

### 4. **Email Verification Token**

#### Method: **Random Bytes**
- **Function**: `bin2hex(random_bytes(32))`
- **Expiry**: 24 hours
- **Location**: `backend/auth/AuthModel.php`

```php
// Email Verification Token
$token = bin2hex(random_bytes(32));
$expiresAt = date('Y-m-d H:i:s', time() + 86400); // 24 hours
```

---

### 5. **Password Reset Token**

#### Method: **Random Bytes with Expiry**
- **Function**: `bin2hex(random_bytes(32))`
- **Expiry**: 1 hour
- **Single Use**: Marked as used after reset
- **Location**: `backend/auth/AuthModel.php`

```php
// Password Reset Token
$token = bin2hex(random_bytes(32));
$expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour
```

---

### 6. **SMTP Password Storage**

#### Method: **Plain Text** ⚠️
- **Location**: `backend/includes/smtp_accounts.php`
- **Storage**: Direct storage in `smtp_accounts.password` field

```php
// SMTP Password Storage (NOT encrypted)
$stmt->bind_param("isssiiii", $smtp_server_id, $email, $from_name, $password, ...);
```

#### **Why Plain Text?** ⚠️
SMTP passwords are stored in plain text because:
- They need to be retrieved to authenticate with SMTP servers
- Email clients require the actual password, not a hash
- Encryption would require a master key, which has its own security issues

#### **Security Recommendations**
1. Use application-specific passwords from email providers
2. Restrict database access with proper permissions
3. Use encrypted database connections
4. Consider envelope encryption for future improvement

---

## 🔒 Security Features Implemented

### 1. **Account Lockout**
```php
// Lock account after 5 failed login attempts
if ($user['failed_login_attempts'] + 1 >= 5) {
    $this->authModel->lockAccount($user['id'], 30); // 30 minutes
}
```

### 2. **Session Management**
- Multiple device support (each gets unique token)
- Session expiry (24 hours default, 30 days with "remember me")
- Auto cleanup of expired sessions
- IP address and user agent tracking

### 3. **Input Validation** (`security_helpers.php`)
- Email validation with length limits
- Integer validation with range checking
- String sanitization with length limits
- Host/port validation
- Boolean normalization
- Encryption type validation

### 4. **Security Headers** (`security_helpers.php`)
```php
// Set in all API responses
X-Frame-Options: DENY
X-Content-Type-Options: nosniff
X-XSS-Protection: 1; mode=block
Strict-Transport-Security: max-age=31536000; includeSubDomains
Content-Security-Policy: [configured]
```

### 5. **CORS Protection**
Allowed origins:
- `http://localhost` (development)
- `http://127.0.0.1` (development)
- `https://payrollsoft.in` (production)
- Local network IPs (192.168.x.x)

### 6. **Rate Limiting**
- Failed login attempts tracking
- Account lockout after threshold
- Activity logging for audit trail

---

## 📊 Encryption Methods Summary

| Component | Method | Strength | Reversible | Location |
|-----------|--------|----------|------------|----------|
| **User Password** | BCrypt (cost 12) | Very High | ❌ No (hash only) | `AuthController.php:68` |
| **Session Token** | random_bytes(32) | High (256-bit) | ❌ No | `AuthModel.php:137` |
| **CSRF Token** | random_bytes(32) | High (256-bit) | ❌ No | `AuthController.php:168` |
| **Email Verify Token** | random_bytes(32) | High (256-bit) | ❌ No | `AuthModel.php:204` |
| **Password Reset Token** | random_bytes(32) | High (256-bit) | ❌ No | `AuthModel.php:236` |
| **SMTP Password** | Plain Text | ⚠️ None | ✅ Yes | `smtp_accounts.php:61` |

---

## 🛡️ Password Policy

### Requirements
1. **Minimum Length**: 8 characters
2. **Complexity**: Must contain:
   - ✅ Uppercase letter (A-Z)
   - ✅ Lowercase letter (a-z)
   - ✅ Number (0-9)
   - ✅ Special character (!@#$%^&*...)

### Password Strength Check
```php
private function isPasswordStrong($password) {
    // At least 8 characters
    if (strlen($password) < 8) return false;
    
    // Must contain uppercase
    if (!preg_match('/[A-Z]/', $password)) return false;
    
    // Must contain lowercase
    if (!preg_match('/[a-z]/', $password)) return false;
    
    // Must contain number
    if (!preg_match('/[0-9]/', $password)) return false;
    
    // Must contain special character
    if (!preg_match('/[^A-Za-z0-9]/', $password)) return false;
    
    return true;
}
```

---

## 🔍 Verification Process

### 1. **Login Password Verification**

```php
Step 1: Get user by email
  ↓
Step 2: Check account status (active, not locked)
  ↓
Step 3: Verify password with password_verify()
  ↓
Step 4: If valid → create session, reset failed attempts
  ↓
Step 5: If invalid → increment failed attempts, check lockout threshold
```

### 2. **Session Token Verification**

```php
Step 1: Check if session token exists in $_SESSION
  ↓
Step 2: Query database for matching session
  ↓
Step 3: Check expiry time
  ↓
Step 4: Update last_activity timestamp
  ↓
Step 5: Return user data or false
```

### 3. **CSRF Token Verification**

```php
Step 1: Check if request method is POST/PUT/DELETE/PATCH
  ↓
Step 2: Get token from session ($_SESSION['csrf_token'])
  ↓
Step 3: Get token from request (header/POST/JSON)
  ↓
Step 4: Compare using hash_equals() (timing-attack safe)
  ↓
Step 5: Accept or reject request
```

---

## 🚨 Security Vulnerabilities & Recommendations

### ⚠️ Current Issues

1. **SMTP Passwords in Plain Text**
   - **Risk**: Database breach exposes SMTP credentials
   - **Recommendation**: Implement AES-256 encryption with key management

2. **No API Rate Limiting**
   - **Risk**: Brute force attacks on API endpoints
   - **Recommendation**: Add Redis-based rate limiting

3. **No 2FA/MFA**
   - **Risk**: Compromised passwords = full access
   - **Recommendation**: Add TOTP-based 2FA

4. **Session Fixation Possible**
   - **Risk**: Session hijacking
   - **Recommendation**: Regenerate session ID on login

### ✅ Security Best Practices Followed

1. ✅ BCrypt for password hashing (not MD5/SHA1)
2. ✅ Cryptographically secure random number generation
3. ✅ Timing-attack resistant comparisons (hash_equals)
4. ✅ Account lockout after failed attempts
5. ✅ Session expiry and cleanup
6. ✅ CSRF protection on state-changing operations
7. ✅ Input validation and sanitization
8. ✅ Security headers configured
9. ✅ CORS properly configured
10. ✅ Activity logging for audit trail

---

## 📖 References

- **BCrypt**: [PHP password_hash() documentation](https://www.php.net/manual/en/function.password-hash.php)
- **OWASP Password Storage**: [OWASP Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Password_Storage_Cheat_Sheet.html)
- **CSRF Protection**: [OWASP CSRF Prevention](https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html)
- **Session Management**: [OWASP Session Management](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html)

---

**Last Updated**: February 3, 2026
**Security Audit Recommended**: Every 6 months
