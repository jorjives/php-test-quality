FROM php:8.4-cli-alpine

# Install composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy composer files first for layer caching
COPY composer.json composer.lock* ./

# Install dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copy application code
COPY . .

# OCI labels
LABEL org.opencontainers.image.source="https://github.com/jorjives/php-test-quality"
LABEL org.opencontainers.image.description="AST-based test quality analyser for PHPUnit tests"
LABEL org.opencontainers.image.licenses="MIT"

# Set entrypoint
ENTRYPOINT ["php", "bin/tq"]
