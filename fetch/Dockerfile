FROM php:8.2-cli-alpine

# Install dependencies: PostgreSQL + pcntl
# NOTE: pdo and openssl are already built-in on php:8.2-cli-alpine,
#       installing them via docker-php-ext-install causes "Cannot find config.m4"
# NOTE: postgresql-libs is kept for runtime (pdo_pgsql needs libpq.so.5)
RUN apk add --no-cache --virtual .build-deps postgresql-dev \
    && apk add --no-cache postgresql-libs \
    && docker-php-ext-install pdo_pgsql pcntl \
    && apk del .build-deps

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy dependency files first for better caching
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-interaction --no-progress --optimize-autoloader

# Copy application code
COPY pipeline.php ./

CMD ["php", "-d", "memory_limit=256M", "pipeline.php"]
