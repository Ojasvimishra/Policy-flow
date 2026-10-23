FROM php:8.2-cli-alpine

# Install system dependencies and build libraries
RUN apk add --no-cache \
    curl \
    git \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    postgresql-dev \
    libpq-dev \
    oniguruma-dev \
    linux-headers \
    $PHPIZE_DEPS

# Install PHP extensions required for Laravel, PostgreSQL, MySQL, and decimal arithmetic
RUN docker-php-ext-install pdo pdo_pgsql pdo_mysql bcmath pcntl

# Install Redis extension via PECL
RUN pecl install redis && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Expose Laravel default port
EXPOSE 8000

# Default command
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
