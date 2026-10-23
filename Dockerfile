# syntax=docker/dockerfile:1

# ==============================================================================
# Stage 1: Composer Build & Dependency Optimization
# ==============================================================================
FROM composer:2 AS composer-stage

WORKDIR /var/www/html

# Copy composer dependency specifications
COPY composer.json composer.lock ./

# Install production dependencies without dev packages or scripts
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --no-progress \
    --optimize-autoloader \
    --no-scripts

# Copy application source code for autoloader optimization
COPY app/ app/
COPY bootstrap/ bootstrap/
COPY config/ config/
COPY database/ database/
COPY public/ public/
COPY resources/ resources/
COPY routes/ routes/
COPY artisan artisan

# Generate optimized autoloader with discovered packages
RUN composer dump-autoload --optimize --no-dev

# ==============================================================================
# Stage 2: Production PHP-FPM Runtime Image
# ==============================================================================
FROM php:8.2-fpm-alpine AS production

LABEL maintainer="PolicyFlow DevOps Team <devops@trippersshield.internal>"
LABEL description="PolicyFlow Hardened Production Backend (PHP 8.2-FPM Alpine)"

# Install runtime dependencies and compilation tools
RUN apk add --no-cache \
    curl \
    fcgi \
    libpq \
    libpng \
    libxml2 \
    oniguruma \
    && apk add --no-cache --virtual .build-deps \
    $PHPIZE_DEPS \
    postgresql-dev \
    libpng-dev \
    libxml2-dev \
    oniguruma-dev \
    linux-headers \
    && docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_pgsql \
    pdo_mysql \
    bcmath \
    pcntl \
    opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /tmp/pear ~/.pearrc

WORKDIR /var/www/html

# Copy production PHP and PHP-FPM pool configurations
COPY docker/php/php.ini /usr/local/etc/php/conf.d/policyflow.ini
COPY docker/php/zz-docker.conf /usr/local/etc/php-fpm.d/zz-docker.conf

# Copy application code and vendor from composer-stage
COPY --from=composer-stage /var/www/html/vendor ./vendor
COPY . .

# Copy and set executable permissions for the container entrypoint
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Configure directory ownership and permissions for secure non-root operation
RUN mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache

# Expose standard FastCGI port
EXPOSE 9000

# Health check to ensure PHP-FPM responder is active
HEALTHCHECK --interval=20s --timeout=5s --start-period=10s --retries=3 \
    CMD cgi-fcgi -bind -connect 127.0.0.1:9000 || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

CMD ["php-fpm"]
