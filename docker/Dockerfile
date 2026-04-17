FROM php:7.4-apache

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

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf \
    /etc/apache2/apache2.conf \
 && sed -ri -e 's/AllowOverride None/AllowOverride All/g' \
    /etc/apache2/apache2.conf

RUN { \
    echo "log_errors=On"; \
    echo "error_log=/proc/self/fd/2"; \
    echo "display_errors=On"; \
    echo "display_startup_errors=On"; \
    echo "error_reporting=E_ALL"; \
} > /usr/local/etc/php/conf.d/docker-php-logging.ini

RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

RUN ln -sf /proc/self/fd/1 /var/log/apache2/access.log \
 && ln -sf /proc/self/fd/2 /var/log/apache2/error.log

COPY crontab /etc/cron.d/seo-cron
RUN chmod 0644 /etc/cron.d/seo-cron && crontab /etc/cron.d/seo-cron

COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

WORKDIR /var/www/html

ENTRYPOINT ["entrypoint.sh"]