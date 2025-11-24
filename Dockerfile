FROM php:8.2-fpm

ARG APP_ENV

RUN apt-get update && apt-get install -y \
    libpng-dev \
    librdkafka-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    librabbitmq-dev \
    zip \
    unzip \
    procps \
    net-tools \
    lsof \
    libfreetype6-dev \
    apt-transport-https \
    ca-certificates \
    gnupg \
    git \
    nano \
    mc \
    curl \
    libpq-dev \
    rsync \
    supervisor \
    && docker-php-ext-install mbstring exif pcntl bcmath gd pdo pdo_pgsql zip sockets \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN pecl install rdkafka && docker-php-ext-enable rdkafka
RUN pecl install amqp && docker-php-ext-enable amqp

RUN if [ "$APP_ENV" = "test" ]; then \
        pecl install xdebug && docker-php-ext-enable xdebug; \
    fi

RUN curl -1sLf 'https://dl.cloudsmith.io/public/symfony/stable/setup.deb.sh' | bash \
    && apt-get install -y symfony-cli \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/amazon_asin_product_sync

COPY . /var/www/amazon_asin_product_sync

RUN if [ "$APP_ENV" = "test" ]; then \
        composer install --no-interaction --prefer-dist --optimize-autoloader --no-scripts; \
    else \
        composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts; \
    fi


COPY docker/configs-data/php.ini /usr/local/etc/php/conf.d/custom-php.ini
COPY ./docker/supervisor/supervisord.conf /etc/supervisord.conf
COPY ./docker/supervisor/*.conf /etc/supervisor/conf.d/

RUN composer dump-autoload --optimize

RUN chown -R www-data:www-data /var/www \
    && chmod -R 775 /var/www \
    && mkdir -p /var/www/.composer/cache \
    && chown -R www-data:www-data /var/www/.composer

RUN mkdir -p /var/run/supervisor /var/log/supervisor && \
    chown -R www-data:www-data /var/run/supervisor /var/log/supervisor && \
    chmod -R 775 /var/run/supervisor /var/log/supervisor

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9003
EXPOSE 9000

USER www-data

#ENTRYPOINT ["entrypoint.sh"]
