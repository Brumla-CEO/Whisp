FROM php:8.2-cli-alpine

# Instalace systémových závislostí a ovladačů pro Postgres
RUN apk add --no-cache \
    postgresql-dev \
    libzip-dev \
    unzip \
    && docker-php-ext-install pdo pdo_pgsql zip

# Instalace Composeru
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Kopírování definic závislostí
COPY composer.json composer.lock* ./

# Instalace PHP závislostí
RUN composer install --no-scripts --no-autoloader

# Kopírování zbytku aplikace
COPY . .

# Generování autoloaderu
RUN composer dump-autoload --optimize

CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]