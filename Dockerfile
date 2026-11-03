FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev libzip-dev mariadb-client \
    && docker-php-ext-install mbstring pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod headers rewrite

COPY docker/apache-allowoverride.conf /etc/apache2/conf-enabled/allowoverride.conf
COPY . /var/www/html
COPY config/config.example.php /var/www/html/config/config.php

RUN mkdir -p /var/www/html/assets/img/uploads /var/www/html/backups \
    && chown -R www-data:www-data /var/www/html/assets/img/uploads /var/www/html/backups

EXPOSE 80
