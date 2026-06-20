FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    nginx \
    curl \
    zip \
    unzip \
    git \
    oniguruma-dev \
    libpq-dev

RUN docker-php-ext-install pdo pdo_pgsql mbstring bcmath

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN composer install --no-dev --optimize-autoloader

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

COPY nginx.conf /etc/nginx/nginx.conf

EXPOSE 10000

CMD sh -c "php artisan config:cache && php artisan route:cache && php artisan view:cache && php-fpm -D && nginx -g 'daemon off;'"