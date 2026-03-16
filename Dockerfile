FROM php:8.4-fpm

RUN apt-get update && apt-get install -y \
    git curl unzip libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    libonig-dev libxml2-dev libicu-dev libsqlite3-dev sqlite3 libpq-dev \
    libcurl4-openssl-dev pkg-config libssl-dev default-mysql-client \
    nodejs npm \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql pdo_sqlite mbstring exif pcntl bcmath gd zip intl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . /var/www/html

RUN composer install --no-interaction --prefer-dist --optimize-autoloader \
    && npm ci \
    && npm run build \
    && chown -R www-data:www-data /var/www/html

CMD sh -lc 'php artisan key:generate --force --no-interaction || true && php artisan migrate --force && php artisan db:seed --class=Database\\Seeders\\DatabaseSeeder --force && php artisan db:seed --class=Database\\Seeders\\InitialSetupSeeder --force && php artisan db:seed --class=Database\\Seeders\\PaymentMethodSeeder --force && php artisan db:seed --class=Database\\Seeders\\SmsProviderSeeder --force && php artisan serve --host=0.0.0.0 --port=8000'
