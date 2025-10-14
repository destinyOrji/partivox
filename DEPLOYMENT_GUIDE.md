# 🚀 PARTIVOX Deployment Guide

## 📋 Prerequisites

### System Requirements
- **PHP 8.0+** with extensions:
  - `mongodb`
  - `curl`
  - `json`
  - `openssl`
- **MongoDB 4.4+**
- **Web Server** (Apache/Nginx) or PHP built-in server
- **Composer** for dependency management

### Environment Setup
1. **Install MongoDB PHP Driver**
   ```bash
   composer require mongodb/mongodb
   ```

2. **Install JWT Library**
   ```bash
   composer require firebase/php-jwt
   ```

---

## 🔧 Configuration

### 1. Database Configuration
Edit `/api/config/db.php`:
```php
<?php
class Database {
    private static $connection_string = 'mongodb://localhost:27017';
    private static $database_name = 'partivox_production';
    // ... rest of configuration
}
```

### 2. Environment Variables
Create `.env` file in root directory:
```env
# Database
MONGODB_URI=mongodb://localhost:27017
DATABASE_NAME=partivox_production

# JWT
JWT_SECRET=your_super_secret_jwt_key_here
JWT_EXPIRY=86400

# Twitter API
TWITTER_CLIENT_ID=your_twitter_client_id
TWITTER_CLIENT_SECRET=your_twitter_client_secret
TWITTER_REDIRECT_URI=https://yourdomain.com/api/twitter/callback.php

# Google OAuth
GOOGLE_CLIENT_ID=your_google_client_id
GOOGLE_CLIENT_SECRET=your_google_client_secret
GOOGLE_REDIRECT_URI=https://yourdomain.com/api/google/callback.php

# Email Configuration
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=your_email@gmail.com
SMTP_PASSWORD=your_app_password

# Application
APP_URL=https://yourdomain.com
APP_NAME=PARTIVOX
DEBUG=false
```

### 3. Twitter API Setup
1. Create Twitter Developer Account
2. Create new App in Twitter Developer Portal
3. Get Client ID and Client Secret
4. Set callback URL: `https://yourdomain.com/api/twitter/callback.php`

### 4. Google OAuth Setup
1. Go to Google Cloud Console
2. Create new project or select existing
3. Enable Google+ API
4. Create OAuth 2.0 credentials
5. Set redirect URI: `https://yourdomain.com/api/google/callback.php`

---

## 🗄️ Database Setup

### 1. Create MongoDB Database
```javascript
// Connect to MongoDB
use partivox_production

// Create collections with indexes
db.users.createIndex({ "email": 1 }, { unique: true })
db.users.createIndex({ "twitter_id": 1 }, { sparse: true })
db.users.createIndex({ "created_at": 1 })

db.campaigns.createIndex({ "user_id": 1 })
db.campaigns.createIndex({ "status": 1 })
db.campaigns.createIndex({ "created_at": 1 })

db.tasks.createIndex({ "campaign_id": 1 })
db.tasks.createIndex({ "user_id": 1 })
db.tasks.createIndex({ "status": 1 })

db.transactions.createIndex({ "user_id": 1 })
db.transactions.createIndex({ "type": 1 })
db.transactions.createIndex({ "status": 1 })
db.transactions.createIndex({ "created_at": 1 })

db.settings.createIndex({ "user_id": 1 }, { unique: true })
```

### 2. Create Admin User
```javascript
// Insert default admin user
db.users.insertOne({
  name: "Admin",
  email: "admin@partivox.com",
  password: "$2y$10$hashed_password_here", // Use password_hash() in PHP
  role: "admin",
  is_verified: true,
  auth_provider: "email",
  created_at: new Date(),
  updated_at: new Date()
})
```

---

## 🌐 Web Server Configuration

### Apache (.htaccess)
Create `/api/.htaccess`:
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

# CORS Headers
Header always set Access-Control-Allow-Origin "*"
Header always set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS"
Header always set Access-Control-Allow-Headers "Content-Type, Authorization"
Header always set Access-Control-Allow-Credentials "true"

# Handle preflight requests
RewriteCond %{REQUEST_METHOD} OPTIONS
RewriteRule ^(.*)$ $1 [R=200,L]
```

### Nginx Configuration
```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /path/to/partivox;
    index index.html index.php;

    # Handle API routes
    location /api/ {
        try_files $uri $uri/ /api/index.php?$query_string;
    }

    # PHP processing
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # CORS headers
    add_header Access-Control-Allow-Origin *;
    add_header Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS";
    add_header Access-Control-Allow-Headers "Content-Type, Authorization";
}
```

---

## 🔒 Security Hardening

### 1. File Permissions
```bash
# Set proper permissions
chmod 755 /path/to/partivox
chmod 644 /path/to/partivox/api/config/*.php
chmod 600 /path/to/partivox/.env
```

### 2. Hide Sensitive Files
Add to `.htaccess`:
```apache
# Deny access to sensitive files
<Files ".env">
    Order allow,deny
    Deny from all
</Files>

<Files "*.md">
    Order allow,deny
    Deny from all
</Files>
```

### 3. Enable HTTPS
```bash
# Install SSL certificate (Let's Encrypt example)
certbot --apache -d yourdomain.com
```

---

## 📦 Production Deployment Steps

### 1. Upload Files
```bash
# Upload to server (example with rsync)
rsync -avz --exclude='.git' --exclude='node_modules' /local/partivox/ user@server:/var/www/partivox/
```

### 2. Install Dependencies
```bash
cd /var/www/partivox
composer install --no-dev --optimize-autoloader
```

### 3. Set Environment
```bash
# Copy and configure environment
cp .env.example .env
nano .env  # Edit with production values
```

### 4. Configure Database
```bash
# Import database structure
mongoimport --db partivox_production --collection users --file users.json
```

### 5. Set Permissions
```bash
chown -R www-data:www-data /var/www/partivox
chmod -R 755 /var/www/partivox
chmod 600 /var/www/partivox/.env
```

---

## 🔍 Testing Deployment

### 1. Health Check Endpoints
Test these URLs:
- `https://yourdomain.com/` - Main page
- `https://yourdomain.com/api/` - API status
- `https://yourdomain.com/pages/userDashboard.html` - User interface
- `https://yourdomain.com/pages/admin_dashboard/login.html` - Admin interface

### 2. API Testing
```bash
# Test user registration
curl -X POST https://yourdomain.com/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"name":"Test User","email":"test@example.com","password":"password123"}'

# Test authentication
curl -X POST https://yourdomain.com/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"password123"}'
```

### 3. Database Connectivity
```php
<?php
// Test database connection
require_once '/var/www/partivox/api/config/db.php';
try {
    $db = Database::getDB();
    echo "Database connection successful!";
} catch (Exception $e) {
    echo "Database connection failed: " . $e->getMessage();
}
?>
```

---

## 📊 Monitoring & Maintenance

### 1. Log Files
Monitor these logs:
- `/var/log/apache2/error.log` (Apache)
- `/var/log/nginx/error.log` (Nginx)
- `/var/log/mongodb/mongod.log` (MongoDB)
- Application logs in `/var/www/partivox/logs/`

### 2. Database Backup
```bash
# Daily backup script
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
mongodump --db partivox_production --out /backups/partivox_$DATE
tar -czf /backups/partivox_$DATE.tar.gz /backups/partivox_$DATE
rm -rf /backups/partivox_$DATE
```

### 3. Performance Monitoring
- Monitor MongoDB performance
- Check API response times
- Monitor server resources (CPU, RAM, Disk)
- Set up alerts for critical errors

---

## 🚨 Troubleshooting

### Common Issues

1. **Database Connection Failed**
   - Check MongoDB service: `systemctl status mongod`
   - Verify connection string in `db.php`
   - Check firewall settings

2. **API Returns 500 Error**
   - Check PHP error logs
   - Verify file permissions
   - Check missing dependencies

3. **Authentication Not Working**
   - Verify JWT secret in `.env`
   - Check token expiry settings
   - Validate API endpoints

4. **CORS Issues**
   - Check CORS headers configuration
   - Verify domain whitelist
   - Test with browser dev tools

### Debug Mode
Enable debug mode in `.env`:
```env
DEBUG=true
```

---

## 🎯 Performance Optimization

### 1. PHP Optimization
```ini
; php.ini optimizations
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
```

### 2. MongoDB Optimization
```javascript
// Create compound indexes for better performance
db.users.createIndex({ "email": 1, "is_verified": 1 })
db.campaigns.createIndex({ "user_id": 1, "status": 1, "created_at": -1 })
db.transactions.createIndex({ "user_id": 1, "type": 1, "created_at": -1 })
```

### 3. Caching
Implement Redis caching for:
- User sessions
- API responses
- Database query results

---

**🎉 Your PARTIVOX application is now ready for production deployment!**

For support and updates, refer to the API documentation and maintain regular backups of your database and application files.
