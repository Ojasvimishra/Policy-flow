#!/bin/sh
set -e

# PolicyFlow Production Container Entrypoint
echo "Starting PolicyFlow service: $1"

# Verify storage and cache directory permissions
mkdir -p /var/www/html/storage/framework/cache/data \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/logs \
         /var/www/html/bootstrap/cache

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# If command is php-fpm or worker in production, optimize caches if APP_KEY is provided
if [ "$1" = "php-fpm" ] && [ "$APP_ENV" = "production" ] && [ -n "$APP_KEY" ]; then
    echo "Optimizing Laravel configuration and route caches..."
    php artisan config:cache || echo "Config caching skipped"
    php artisan route:cache || echo "Route caching skipped"
    php artisan view:cache || echo "View caching skipped"
fi

# Execute main process
exec "$@"
