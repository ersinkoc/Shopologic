# Shopologic Installation Guide

This guide walks you through installing Shopologic on your server for production use.

**🎯 ENHANCED PLUGIN ECOSYSTEM - PRODUCTION READY**

This installation guide covers the enhanced Shopologic platform with 47 advanced models, cross-plugin integration, real-time events, performance monitoring, and automated testing capabilities.

## 🚀 Quick Start with Enhanced Ecosystem

After installation, initialize the enhanced plugin ecosystem:

```bash
# Initialize complete plugin ecosystem
php bootstrap_plugins.php

# Run with integration demonstration
php bootstrap_plugins.php --demo
```

## 🎯 System Requirements

### Server Requirements
- **PHP 8.3+** with required extensions
- **PostgreSQL 13+** database server
- **Web server** (Apache 2.4+ or Nginx 1.18+)
- **Redis** (recommended for caching and sessions)
- **SSL certificate** (required for production)

### PHP Extensions
Required extensions:
```bash
# Verify extensions are installed
php -m | grep -E "(pdo|pdo_pgsql|json|mbstring|openssl|curl|gd|intl|zip|redis)"
```

- `pdo` and `pdo_pgsql` - Database connectivity
- `json` - JSON processing
- `mbstring` - Multi-byte string support
- `openssl` - Encryption and SSL
- `curl` - HTTP client functionality
- `gd` or `imagick` - Image processing
- `intl` - Internationalization
- `zip` - Archive support
- `redis` - Redis connectivity (if using Redis)

### Hardware Recommendations

#### Minimum Requirements
- **CPU**: 1 vCPU
- **RAM**: 2 GB
- **Storage**: 10 GB SSD
- **Bandwidth**: 100 Mbps

#### Recommended for Production
- **CPU**: 2+ vCPUs
- **RAM**: 4+ GB
- **Storage**: 50+ GB SSD
- **Bandwidth**: 1 Gbps

#### High-Traffic Production
- **CPU**: 4+ vCPUs
- **RAM**: 8+ GB
- **Storage**: 100+ GB SSD
- **Database**: Dedicated server
- **Cache**: Dedicated Redis cluster

## 📥 Installation Methods

### Method 1: Manual Installation

#### 1. Download Shopologic
```bash
# Download latest release
wget https://github.com/shopologic/shopologic/releases/latest/download/shopologic.tar.gz

# Extract files
tar -xzf shopologic.tar.gz
cd shopologic

# Or clone from repository
git clone https://github.com/shopologic/shopologic.git
cd shopologic
```

#### 2. Set File Permissions
```bash
# Set ownership (replace www-data with your web server user)
sudo chown -R www-data:www-data /path/to/shopologic

# Set permissions
find /path/to/shopologic -type f -exec chmod 644 {} \;
find /path/to/shopologic -type d -exec chmod 755 {} \;

# Make storage writable
chmod -R 775 storage/
chmod -R 775 database/

# Secure sensitive files
chmod 600 .env
```

#### 3. Database Setup
```bash
# Connect to PostgreSQL
sudo -u postgres psql

# Create database and user
CREATE DATABASE shopologic;
CREATE USER shopologic WITH PASSWORD 'secure_password';
GRANT ALL PRIVILEGES ON DATABASE shopologic TO shopologic;
\q
```

#### 4. Environment Configuration
```bash
# Copy environment template
cp .env.example .env

# Edit configuration
nano .env
```

Configure your `.env` file:
```env
# Application
APP_NAME="Your Store Name"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
APP_TIMEZONE=UTC

# Database
DB_CONNECTION=pgsql
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=shopologic
DB_USERNAME=shopologic
DB_PASSWORD=secure_password

# Cache & Sessions
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_DRIVER=redis

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=
REDIS_DATABASE=0

# Security (leave empty - will be generated)
ENCRYPTION_KEY=
JWT_SECRET=

# Email
MAIL_DRIVER=smtp
MAIL_HOST=smtp.your-provider.com
MAIL_PORT=587
MAIL_USERNAME=your-email@domain.com
MAIL_PASSWORD=your-email-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@your-domain.com
MAIL_FROM_NAME="${APP_NAME}"
```

#### 5. Run Installation
```bash
# Install Shopologic
php cli/install.php

# Run database migrations
php cli/migrate.php up

# Seed with sample data (optional)
php cli/seed.php run
```

### Method 2: Docker Installation

#### 1. Docker Compose Setup
Create `docker-compose.yml`:
```yaml
version: '3.8'

services:
  app:
    image: shopologic/shopologic:latest
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./storage:/var/www/html/storage
      - ./uploads:/var/www/html/public/uploads
      - ./.env:/var/www/html/.env
    depends_on:
      - database
      - redis
    environment:
      - DB_HOST=database
      - REDIS_HOST=redis

  database:
    image: postgres:15
    environment:
      POSTGRES_DB: shopologic
      POSTGRES_USER: shopologic
      POSTGRES_PASSWORD: secure_password
    volumes:
      - postgres_data:/var/lib/postgresql/data
    ports:
      - "5432:5432"

  redis:
    image: redis:7-alpine
    ports:
      - "6379:6379"
    volumes:
      - redis_data:/data

volumes:
  postgres_data:
  redis_data:
```

#### 2. Deploy with Docker
```bash
# Start services
docker-compose up -d

# Run installation
docker-compose exec app php cli/install.php

# Run migrations
docker-compose exec app php cli/migrate.php up
```

## 🌐 Web Server Configuration

### Apache Configuration
Create virtual host configuration:
```apache
<VirtualHost *:80>
    ServerName your-domain.com
    DocumentRoot /path/to/shopologic/public
    
    # Redirect to HTTPS
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</VirtualHost>

<VirtualHost *:443>
    ServerName your-domain.com
    DocumentRoot /path/to/shopologic/public
    
    # SSL Configuration
    SSLEngine on
    SSLCertificateFile /path/to/certificate.crt
    SSLCertificateKeyFile /path/to/private.key
    SSLCertificateChainFile /path/to/ca-bundle.crt
    
    # Security Headers
    Header always set Strict-Transport-Security "max-age=63072000; includeSubDomains; preload"
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    
    # Directory Configuration
    <Directory /path/to/shopologic/public>
        AllowOverride All
        Require all granted
        
        # URL Rewriting
        RewriteEngine On
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule ^(.*)$ index.php [QSA,L]
    </Directory>
    
    # Protect sensitive files
    <FilesMatch "\.(env|md|json|lock)$">
        Require all denied
    </FilesMatch>
    
    # Custom Error Pages
    ErrorDocument 404 /404.html
    ErrorDocument 500 /500.html
    
    # Logging
    CustomLog ${APACHE_LOG_DIR}/shopologic_access.log combined
    ErrorLog ${APACHE_LOG_DIR}/shopologic_error.log
</VirtualHost>
```

### Nginx Configuration
Create server configuration:
```nginx
server {
    listen 80;
    server_name your-domain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name your-domain.com;
    root /path/to/shopologic/public;
    index index.php;

    # SSL Configuration
    ssl_certificate /path/to/certificate.crt;
    ssl_certificate_key /path/to/private.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;

    # Security Headers
    add_header Strict-Transport-Security "max-age=63072000; includeSubDomains; preload" always;
    add_header X-Content-Type-Options nosniff always;
    add_header X-Frame-Options DENY always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Main location
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Admin panel
    location /admin {
        try_files $uri $uri/ /admin.php?$query_string;
    }

    # API endpoints
    location /api {
        try_files $uri $uri/ /api.php?$query_string;
    }

    # GraphQL endpoint
    location /graphql {
        try_files $uri /api.php?$query_string;
    }

    # PHP handling
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        
        # Security
        fastcgi_hide_header X-Powered-By;
        fastcgi_buffer_size 128k;
        fastcgi_buffers 4 256k;
        fastcgi_busy_buffers_size 256k;
    }

    # Deny access to sensitive files
    location ~ /\.(env|git|svn) {
        deny all;
    }

    location ~ \.(md|json|lock)$ {
        deny all;
    }

    # Static assets
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        add_header Vary Accept-Encoding;
        access_log off;
    }

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types
        text/plain
        text/css
        text/xml
        text/javascript
        application/javascript
        application/xml+rss
        application/json;

    # Logging
    access_log /var/log/nginx/shopologic_access.log;
    error_log /var/log/nginx/shopologic_error.log;
}
```

## 🔒 SSL Certificate Setup

### Let's Encrypt (Certbot)
```bash
# Install Certbot
sudo apt update
sudo apt install certbot python3-certbot-apache

# Generate certificate
sudo certbot --apache -d your-domain.com

# Auto-renewal
sudo crontab -e
# Add: 0 12 * * * /usr/bin/certbot renew --quiet
```

### Custom SSL Certificate
```bash
# If you have your own SSL certificate
sudo cp your-certificate.crt /etc/ssl/certs/
sudo cp your-private.key /etc/ssl/private/
sudo chmod 600 /etc/ssl/private/your-private.key
```

## ⚙️ Performance Optimization

### PHP Configuration
Edit `php.ini`:
```ini
# Memory and execution
memory_limit = 256M
max_execution_time = 300
max_input_time = 300

# File uploads
upload_max_filesize = 64M
post_max_size = 64M
max_file_uploads = 20

# Session settings
session.cookie_secure = 1
session.cookie_httponly = 1
session.cookie_samesite = "Strict"

# OPcache settings
opcache.enable = 1
opcache.memory_consumption = 128
opcache.interned_strings_buffer = 8
opcache.max_accelerated_files = 4000
opcache.revalidate_freq = 60
opcache.fast_shutdown = 1
```

### Database Optimization
PostgreSQL configuration (`postgresql.conf`):
```ini
# Memory settings
shared_buffers = 256MB
effective_cache_size = 1GB
work_mem = 4MB
maintenance_work_mem = 64MB

# Connection settings
max_connections = 100

# Logging
log_statement = 'mod'
log_min_duration_statement = 1000
```

### Redis Configuration
Redis configuration (`redis.conf`):
```ini
# Memory settings
maxmemory 256mb
maxmemory-policy allkeys-lru

# Persistence
save 900 1
save 300 10
save 60 10000

# Security
requirepass your-redis-password
```

## 🔧 Post-Installation Setup

### 1. Create Admin User
```bash
# Access admin creation
php cli/user.php create-admin
```

### 2. Configure Store Settings
Access admin panel at `https://your-domain.com/admin` and configure:
- Store information
- Payment gateways
- Shipping methods
- Tax settings
- Email templates

### 3. Set Up Cron Jobs
```bash
# Edit crontab
crontab -e

# Add Shopologic scheduled tasks
* * * * * cd /path/to/shopologic && php cli/schedule.php run >> /dev/null 2>&1
0 2 * * * cd /path/to/shopologic && php cli/cache.php warm >> /dev/null 2>&1
0 3 * * * cd /path/to/shopologic && php cli/backup.php run >> /dev/null 2>&1
```

### 4. Configure Backups
```bash
# Set up automated backups
php cli/backup.php configure

# Test backup
php cli/backup.php run --test
```

## 🛡️ Security Hardening

### 1. Run Security Scan
```bash
php cli/security.php scan
```

### 2. Apply Security Hardening
```bash
php cli/security.php harden
```

### 3. Set Up Monitoring
```bash
# Configure log monitoring
php cli/monitor.php setup

# Set up alerts
php cli/monitor.php alerts configure
```

## ✅ Installation Verification

### 1. Health Checks
```bash
# Check system health
curl https://your-domain.com/health/live
curl https://your-domain.com/health/ready

# Check API
curl https://your-domain.com/api/v1/health
```

### 2. Performance Test
```bash
# Run performance tests
php cli/test.php --performance

# Load test (if you have Apache Bench)
ab -n 1000 -c 10 https://your-domain.com/
```

### 3. Security Verification
```bash
# Security scan
php cli/security.php scan

# SSL test
curl -I https://your-domain.com
```

## 🚨 Troubleshooting

### Common Issues

#### Database Connection Errors
```bash
# Check PostgreSQL status
sudo systemctl status postgresql

# Test connection
php -r "new PDO('pgsql:host=localhost;dbname=shopologic', 'user', 'pass');"
```

#### File Permission Issues
```bash
# Fix permissions
sudo chown -R www-data:www-data /path/to/shopologic
chmod -R 755 storage/
```

#### Cache Issues
```bash
# Clear all caches
php cli/cache.php clear

# Check Redis
redis-cli ping
```

#### SSL Certificate Issues
```bash
# Check certificate
openssl x509 -in /path/to/cert.crt -text -noout

# Test SSL
openssl s_client -connect your-domain.com:443
```

### Log Files
Monitor these log files for issues:
- `/var/log/nginx/shopologic_error.log` (Nginx)
- `/var/log/apache2/shopologic_error.log` (Apache)
- `storage/logs/shopologic.log` (Application)
- `/var/log/postgresql/postgresql.log` (Database)

## 📞 Getting Help

- **Documentation**: [Read the full documentation](../README.md)
- **Community**: [Join our community forum](https://community.shopologic.com)
- **Support**: [Contact professional support](mailto:support@shopologic.com)
- **Issues**: [Report installation issues](https://github.com/shopologic/shopologic/issues)

---

Congratulations! Your Shopologic installation is now complete. 🎉

Next steps:
1. [Configure your store](./configuration.md)
2. [Set up payment gateways](./payment-gateways.md)
3. [Configure shipping methods](./shipping.md)