FROM php:8.2-fpm-alpine

# Instalace systémových závislostí a ovladačů pro Postgres
RUN apk add --no-cache \
    postgresql-dev \
    && docker-php-ext-install pdo pdo_pgsql

# Instalace Composeru
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Nejprve kopírujeme definice závislostí (lepší caching)
COPY composer.json ./

# Instalace PHP závislostí (pokud composer.lock neexistuje, nevadí)
RUN composer install --no-scripts --no-autoloader

# Kopírování zbytku aplikace
COPY . .

# Generování autoloaderu
RUN composer dump-autoload --optimize

CMD ["php-fpm"]