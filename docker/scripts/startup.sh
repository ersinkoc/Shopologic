#!/bin/sh

set -e

echo "Starting Shopologic..."

# Wait for database
echo "Waiting for database..."
until PGPASSWORD=$DB_PASSWORD psql -h "$DB_HOST" -U "$DB_USERNAME" -d "$DB_DATABASE" -c '\q' 2>/dev/null; do
  echo "Database is unavailable - sleeping"
  sleep 1
done

echo "Database is up"

# Wait for Redis
echo "Waiting for Redis..."
until redis-cli -h "$REDIS_HOST" ping 2>/dev/null; do
  echo "Redis is unavailable - sleeping"
  sleep 1
done

echo "Redis is up"

# Run migrations if in development mode
if [ "$APP_ENV" = "development" ] || [ "$APP_ENV" = "local" ]; then
    echo "Running migrations..."
    php cli/migrate.php up
fi

# Clear and warm cache
echo "Preparing cache..."
php cli/cache.php clear
php cli/cache.php warm

# Set permissions
chown -R shopologic:shopologic /var/www/html/storage
chmod -R 775 /var/www/html/storage

# Create required directories
mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/storage/cache
mkdir -p /var/www/html/storage/sessions
mkdir -p /var/www/html/storage/uploads

# Start supervisord
echo "Starting services..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf