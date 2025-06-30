# Shopologic Deployment Guide

This guide covers deploying Shopologic to various environments including development, staging, and production.

**🎯 ENHANCED PLUGIN ECOSYSTEM - PRODUCTION READY**

Shopologic now includes a comprehensive plugin ecosystem with 47 advanced models, cross-plugin integration, real-time events, performance monitoring, and automated testing. This deployment guide covers the enhanced system capabilities.

## 📋 Prerequisites

- PHP 8.3+ with required extensions
- PostgreSQL 15+
- Redis 7+
- Node.js 18+ and npm
- Git
- Composer 2+
- SSL certificate (for production)

## 🚀 Quick Start with Docker

### Development Environment

```bash
# Clone repository
git clone https://github.com/shopologic/shopologic.git
cd shopologic

# Copy environment file
cp .env.example .env

# Start services
docker-compose up -d

# Install dependencies
docker-compose exec app composer install
docker-compose exec app npm install --prefix themes/default

# Run migrations
docker-compose exec app php cli/migrate.php up

# Seed database (optional)
docker-compose exec app php cli/seed.php run

# Access application
open http://localhost
```

### Production with Docker

```bash
# Build production image
docker build -t shopologic:latest --target production .

# Run with docker-compose
docker-compose -f docker-compose.yml up -d

# Or run standalone
docker run -d \
  --name shopologic \
  -p 80:80 \
  -e DB_HOST=postgres \
  -e DB_PASSWORD=secret \
  -e REDIS_HOST=redis \
  shopologic:latest
```

## 🛠️ Manual Deployment

### 1. Server Requirements

```bash
# Check PHP version and extensions
php -v
php -m

# Required extensions:
# - pdo, pdo_pgsql, redis, gd, zip, opcache, intl, bcmath
```

### 2. Clone Repository

```bash
cd /var/www
git clone https://github.com/shopologic/shopologic.git shopologic
cd shopologic
```

### 3. Install Dependencies

```bash
# PHP dependencies
composer install --no-dev --optimize-autoloader

# Node dependencies and build assets
cd themes/default
npm ci --production
npm run build
cd ../..
```

### 4. Configure Environment

```bash
# Copy and edit environment file
cp .env.example .env
nano .env

# Generate application key
php cli/install.php generate-key
```

### 5. Set Permissions

```bash
# Set ownership
chown -R www-data:www-data .

# Set directory permissions
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;

# Make storage writable
chmod -R 775 storage
chmod -R 775 public/uploads
```

### 6. Configure Web Server

#### Nginx Configuration

```nginx
server {
    listen 80;
    server_name shopologic.com;
    root /var/www/shopologic/public;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

#### Apache Configuration

```apache
<VirtualHost *:80>
    ServerName shopologic.com
    DocumentRoot /var/www/shopologic/public

    <Directory /var/www/shopologic/public>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/shopologic-error.log
    CustomLog ${APACHE_LOG_DIR}/shopologic-access.log combined
</VirtualHost>
```

### 7. Database Setup

```bash
# Run migrations
php cli/migrate.php up --env=production

# Create admin user
php cli/user.php create --admin
```

### 8. Configure Services

#### Supervisor for Queue Workers

```ini
[program:shopologic-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/shopologic/cli/queue.php work --daemon
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
numprocs=4
redirect_stderr=true
stdout_logfile=/var/www/shopologic/storage/logs/worker.log
```

#### Cron for Scheduler

```cron
* * * * * cd /var/www/shopologic && php cli/schedule.php run >> /dev/null 2>&1
```

### 9. SSL Certificate

```bash
# Install certbot
apt-get install certbot python3-certbot-nginx

# Get certificate
certbot --nginx -d shopologic.com -d www.shopologic.com
```

### 10. Final Steps

```bash
# Clear and warm caches
php cli/cache.php clear
php cli/cache.php warm

# Run health check
php cli/deploy.php health production

# Monitor logs
tail -f storage/logs/app.log
```

## 🔄 CI/CD Deployment

### GitHub Actions

The repository includes GitHub Actions workflows for CI/CD:

- `.github/workflows/ci.yml` - Continuous Integration
- `.github/workflows/deploy.yml` - Deployment workflow

### Deployment Commands

```bash
# Check deployment readiness
php cli/deploy.php check

# Create deployment package
php cli/deploy.php prepare production

# Deploy to environment
php cli/deploy.php deploy production

# Rollback if needed
php cli/deploy.php rollback
```

### Environment-Specific Configuration

Create environment-specific config files:

- `deployment/config.staging.json`
- `deployment/config.production.json`

## 📊 Monitoring

### Health Checks

```bash
# Check system health
curl https://shopologic.com/health

# Detailed health check
php cli/monitor.php health production
```

### Metrics Collection

```bash
# Setup monitoring
php cli/monitor.php setup

# View metrics
curl https://shopologic.com/metrics
```

### Log Monitoring

```bash
# Application logs
tail -f storage/logs/app.log

# Error logs
tail -f storage/logs/error.log

# Queue worker logs
tail -f storage/logs/worker.log
```

## 🔧 Maintenance

### Enable Maintenance Mode

```bash
# Enable with default message
php cli/maintenance.php enable

# Enable with custom message
php cli/maintenance.php enable "System upgrade in progress"

# Allow specific IPs
php cli/maintenance.php allow 192.168.1.100
```

### Disable Maintenance Mode

```bash
php cli/maintenance.php disable
```

## 🚨 Troubleshooting

### Common Issues

1. **Permission Errors**
   ```bash
   chmod -R 775 storage
   chown -R www-data:www-data storage
   ```

2. **Database Connection Failed**
   ```bash
   # Test connection
   psql -h localhost -U shopologic -d shopologic
   ```

3. **Redis Connection Failed**
   ```bash
   # Test connection
   redis-cli ping
   ```

4. **Assets Not Loading**
   ```bash
   # Rebuild assets
   cd themes/default
   npm run build
   ```

### Debug Mode

```bash
# Enable debug mode (development only)
APP_DEBUG=true
APP_ENV=local

# Check logs
tail -f storage/logs/debug.log
```

## 🔐 Security

### Post-Deployment Security

```bash
# Run security scan
php cli/security.php scan

# Harden installation
php cli/security.php harden

# Check file permissions
find . -type f -perm 0777 -exec ls -la {} \;
find . -type d -perm 0777 -exec ls -la {} \;
```

### Backup and Disaster Recovery

#### Manual Backup

```bash
# Create full backup
php cli/backup.php create --type=full --encrypt

# Create incremental backup
php cli/backup.php create --type=incremental

# List available backups
php cli/backup.php list --details

# Verify backup integrity
php cli/backup.php verify backup-20240115-123456
```

#### Automated Backups

```bash
# Add to crontab for automated backups
# Daily incremental backup at 2 AM
0 2 * * * /var/www/shopologic/scripts/backup.sh incremental local

# Weekly full backup on Sunday at 3 AM
0 3 * * 0 /var/www/shopologic/scripts/backup.sh full local

# Monthly offsite backup on 1st at 4 AM
0 4 1 * * /var/www/shopologic/scripts/backup.sh full s3

# Or use the built-in scheduler
php cli/backup.php schedule add "0 2 * * *" --type=incremental
php cli/backup.php schedule add "0 3 * * 0" --type=full
```

#### Disaster Recovery

```bash
# Restore from backup
php cli/backup.php restore backup-20240115-123456

# Test restore in isolated environment
php cli/backup.php test backup-20240115-123456

# Create restore point before major changes
php cli/backup.php create --type=restore_point

# Export backup for migration
php cli/backup.php export backup-20240115-123456 --output=./export.tar

# Import backup from another system
php cli/backup.php import ./export.tar --storage=local
```

#### Backup Configuration

Edit `.env` for backup settings:

```env
# Backup Storage
BACKUP_STORAGE=local
BACKUP_S3_ENABLED=false
BACKUP_FTP_ENABLED=false

# Retention Policy
BACKUP_RETENTION_DAYS=30
BACKUP_RETENTION_COUNT=10

# Encryption
BACKUP_ENCRYPTION_ENABLED=true

# Notifications
BACKUP_NOTIFICATIONS_ENABLED=true
BACKUP_NOTIFY_SUCCESS=false
BACKUP_NOTIFY_FAILURE=true
BACKUP_EMAIL=admin@shopologic.com
```

#### Recovery Procedures

1. **Database Corruption**
   ```bash
   # Stop application
   php cli/maintenance.php enable
   
   # Restore database only
   php cli/backup.php restore backup-id --database-only
   
   # Verify and restart
   php cli/deploy.php health production
   php cli/maintenance.php disable
   ```

2. **File System Issues**
   ```bash
   # Restore files only
   php cli/backup.php restore backup-id --files-only
   
   # Fix permissions
   chown -R www-data:www-data .
   find . -type d -exec chmod 755 {} \;
   find . -type f -exec chmod 644 {} \;
   ```

3. **Complete System Recovery**
   ```bash
   # Fresh installation
   cd /var/www
   git clone https://github.com/shopologic/shopologic.git shopologic-new
   cd shopologic-new
   
   # Restore from backup
   php cli/backup.php restore backup-id
   
   # Update configuration
   cp /var/www/shopologic/.env .env
   
   # Switch to new installation
   mv /var/www/shopologic /var/www/shopologic-old
   mv /var/www/shopologic-new /var/www/shopologic
   ```

## 📈 Scaling

### Horizontal Scaling

1. **Load Balancer**: Configure nginx/HAProxy
2. **Multiple App Servers**: Share sessions via Redis
3. **Database Replication**: PostgreSQL streaming replication
4. **CDN**: Configure CloudFlare or similar

### Performance Optimization

```bash
# Enable all caches
php cli/cache.php optimize

# Optimize database
php cli/database.php optimize

# Minify assets
cd themes/default
npm run build:production
```

## 📝 Additional Resources

- [Docker Documentation](https://docs.docker.com/)
- [Kubernetes Deployment](./k8s/README.md)
- [Ansible Playbooks](./ansible/README.md)
- [Monitoring Setup](./docs/monitoring.md)

For more help, visit our documentation at https://docs.shopologic.com or contact support@shopologic.com.