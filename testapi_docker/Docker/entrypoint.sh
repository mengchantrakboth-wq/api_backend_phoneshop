#!/bin/bash
set -e

cd /var/www

# Generate app key if not set (Render should set APP_KEY as an env var instead, ideally)
if [ -z "$APP_KEY" ]; then
    php artisan key:generate --force
fi

# Cache config/routes for performance
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run database migrations
php artisan migrate --force

# Start supervisor (runs nginx + php-fpm)
exec supervisord -c /etc/supervisor/conf.d/supervisord.conf