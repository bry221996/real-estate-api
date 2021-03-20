FROM php:7.2-fpm

# Install git | composer
RUN apt-get update \
    && apt-get install -y \ 
        git \
        zlib1g-dev \
        libpng-dev \
        libjpeg-dev \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin/ --filename=composer \
    && composer self-update

RUN docker-php-ext-configure mysqli --with-mysqli=mysqlnd \
    && docker-php-ext-configure gd --with-png-dir=/usr --with-jpeg-dir=/usr \
    && docker-php-ext-configure pdo_mysql --with-pdo-mysql=mysqlnd \
    && docker-php-ext-install mysqli pdo_mysql bcmath zip gd \
    && pecl install redis \
    && docker-php-ext-enable redis
