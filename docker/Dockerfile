FROM php:8.0-apache

ARG UID=1000

# Приводим www-data к UID хоста
RUN usermod -u ${UID} www-data

RUN a2enmod rewrite

RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    libzip-dev zip \
    libxml2-dev \
    poppler-utils \
    antiword \
    imagemagick libmagickwand-dev \
    cron \
    && docker-php-ext-install curl mysqli pdo pdo_mysql zip \
    && pecl install imagick && docker-php-ext-enable imagick \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

ENV APACHE_DOCUMENT_ROOT=/var/www/html

RUN rm -f /var/log/apache2/*.log \
 && ln -sf /proc/self/fd/1 /var/log/apache2/access.log \
 && ln -sf /proc/self/fd/2 /var/log/apache2/error.log \
 && chown -R 1000:1000 /var/log/apache2

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf \
    /etc/apache2/apache2.conf \
 && sed -ri -e 's/AllowOverride None/AllowOverride All/g' \
    /etc/apache2/apache2.conf

RUN sed -i 's|ErrorLog .*|ErrorLog /proc/self/fd/2|g' /etc/apache2/apache2.conf \
 && sed -i 's|CustomLog .*|CustomLog /proc/self/fd/1 combined|g' /etc/apache2/sites-available/*.conf

RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Cron файл
COPY crontab /etc/cron.d/seo-cron
RUN chmod 0644 /etc/cron.d/seo-cron

WORKDIR /var/www/html

EXPOSE 80