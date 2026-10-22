# Includes the fixes for CVE-2026-109618 and CVE-2026-109619.
FROM php:8.4.27-apache

# Record the runtime version in deployment logs and reject an unpatched build.
RUN php -v && php -r 'exit(version_compare(PHP_VERSION, "8.4.27", ">=") ? 0 : 1);'

RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev libpq-dev libzip-dev unzip \
    && docker-php-ext-install intl pdo_pgsql zip \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY . .
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader

COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf

RUN chown -R www-data:www-data logs tmp

EXPOSE 10000
CMD ["sh", "-c", "sed -i \"s/^Listen 80$/Listen ${PORT:-10000}/\" /etc/apache2/ports.conf && sed -i \"s/:80>/:${PORT:-10000}>/\" /etc/apache2/sites-enabled/000-default.conf && apache2-foreground"]
