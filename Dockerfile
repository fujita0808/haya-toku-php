FROM php:8.3-apache

RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pdo_pgsql \
    && a2enmod rewrite headers \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY . /var/www/html/
COPY docker/apache/markdown-charset.conf /etc/apache2/conf-enabled/markdown-charset.conf

WORKDIR /var/www/html

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
