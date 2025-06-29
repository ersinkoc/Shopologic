# Multi-stage build for Shopologic
FROM php:8.3-fpm-alpine AS base

# Install system dependencies
RUN apk add --no-cache \
    postgresql-dev \
    redis \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    nginx \
    supervisor

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        gd \
        zip \
        opcache \
        pcntl \
        bcmath \
        intl

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install APCu extension
RUN pecl install apcu && docker-php-ext-enable apcu

# Configure PHP
COPY docker/php/php.ini /usr/local/etc/php/php.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf

# Configure OPcache
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.memory_consumption=256" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.interned_strings_buffer=16" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.max_accelerated_files=20000" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.save_comments=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.fast_shutdown=1" >> /usr/local/etc/php/conf.d/opcache.ini

# Configure APCu
RUN echo "apc.enabled=1" >> /usr/local/etc/php/conf.d/apcu.ini \
    && echo "apc.shm_segments=1" >> /usr/local/etc/php/conf.d/apcu.ini \
    && echo "apc.shm_size=256M" >> /usr/local/etc/php/conf.d/apcu.ini

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Create application user
RUN addgroup -g 1000 -S shopologic \
    && adduser -u 1000 -S shopologic -G shopologic

# Set working directory
WORKDIR /var/www/html

# Development stage
FROM base AS development

# Install development tools
RUN apk add --no-cache \
    nodejs \
    npm \
    make

# Enable development PHP settings
RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

# Copy application code
COPY --chown=shopologic:shopologic . .

# Install dependencies
RUN composer install --no-interaction --prefer-dist

# Build assets
RUN cd themes/default && npm install && npm run build

# Set permissions
RUN chown -R shopologic:shopologic storage/ \
    && chmod -R 775 storage/

USER shopologic

# Builder stage
FROM base AS builder

# Copy application code
COPY --chown=shopologic:shopologic . .

# Install production dependencies only
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
    && composer clear-cache

# Install Node.js temporarily for asset building
RUN apk add --no-cache nodejs npm

# Build theme assets
RUN cd themes/default \
    && npm ci --production \
    && npm run build \
    && rm -rf node_modules

# Remove Node.js after building
RUN apk del nodejs npm

# Production stage
FROM base AS production

# Configure Nginx
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf

# Configure Supervisor
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copy built application from builder stage
COPY --from=builder --chown=shopologic:shopologic /var/www/html /var/www/html

# Create necessary directories
RUN mkdir -p /var/www/html/storage/logs \
    && mkdir -p /var/www/html/storage/cache \
    && mkdir -p /var/www/html/storage/sessions \
    && mkdir -p /var/www/html/storage/uploads \
    && mkdir -p /var/log/supervisor \
    && chown -R shopologic:shopologic /var/www/html/storage \
    && chmod -R 775 /var/www/html/storage

# Copy startup script
COPY docker/scripts/startup.sh /usr/local/bin/startup.sh
RUN chmod +x /usr/local/bin/startup.sh

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=60s --retries=3 \
    CMD curl -f http://localhost/health || exit 1

# Expose ports
EXPOSE 80 9000

# Run as non-root user
USER shopologic

# Start services
CMD ["/usr/local/bin/startup.sh"]

# Testing stage
FROM development AS testing

# Install testing dependencies
RUN composer install --no-interaction --prefer-dist

# Run tests
RUN php cli/test.php \
    && php cli/security.php scan

# Lightweight production variant
FROM alpine:3.19 AS production-slim

# Install only runtime dependencies
RUN apk add --no-cache \
    php83 \
    php83-fpm \
    php83-pdo \
    php83-pdo_pgsql \
    php83-gd \
    php83-zip \
    php83-opcache \
    php83-pecl-redis \
    php83-pecl-apcu \
    php83-bcmath \
    php83-intl \
    nginx \
    supervisor \
    curl

# Copy application from production stage
COPY --from=production /var/www/html /var/www/html
COPY --from=production /etc/nginx/http.d/default.conf /etc/nginx/http.d/default.conf
COPY --from=production /etc/supervisor/conf.d/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY --from=production /usr/local/bin/startup.sh /usr/local/bin/startup.sh

# Create user
RUN addgroup -g 1000 -S shopologic \
    && adduser -u 1000 -S shopologic -G shopologic \
    && chown -R shopologic:shopologic /var/www/html

WORKDIR /var/www/html

EXPOSE 80

USER shopologic

CMD ["/usr/local/bin/startup.sh"]