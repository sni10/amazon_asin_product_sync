FROM php:8.1-fpm

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
    apt-transport-https  \
    ca-certificates  \
    gnupg \
    git \
    nano \
    mc \
    curl \
    libpq-dev \
    rsync \
    supervisor \
    && docker-php-ext-install mbstring exif pcntl bcmath gd pdo pdo_pgsql zip sockets

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

USER root

RUN pecl install xdebug && docker-php-ext-enable xdebug

RUN pecl install rdkafka && docker-php-ext-enable rdkafka

RUN pecl install amqp  && docker-php-ext-enable amqp

COPY docker/configs-data/php.ini /usr/local/etc/php/conf.d/custom-php.ini
COPY ./config/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf
COPY ./docker/supervisor/supervisord.conf /etc/supervisord.conf
COPY ./docker/supervisor/*.conf /etc/supervisor/conf.d/

RUN curl -1sLf 'https://dl.cloudsmith.io/public/symfony/stable/setup.deb.sh' | bash \
&& apt-get install -y symfony-cli \
&& apt-get clean \
&& rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/amazon_asin_product_sync

COPY . .

#COPY ./token_LOCK.json token.json.example
#COPY ./credentials.json_LOCK credentials.json.example

# Настройка прав для файлов и директорий
RUN chown -R www-data:www-data /var/www \
    && chmod -R 775 /var/www \
    && mkdir -p /var/www/.composer/cache \
    && chown -R www-data:www-data /var/www/.composer

# Настройка прав для supervisor и временных файлов
RUN mkdir -p /var/run/supervisor /var/log/supervisor && \
    chown -R www-data:www-data /var/run/supervisor /var/log/supervisor && \
    chmod -R 775 /var/run/supervisor /var/log/supervisor

EXPOSE 9003
EXPOSE 9000


