FROM php:8.4-fpm-alpine

# 安裝系統依賴與 PHP 擴展安裝工具
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

# 安裝 EIP 系統所需的 PHP 擴展
RUN apk add --no-cache bash curl git \
    && install-php-extensions \
        pdo_pgsql \
        pdo_mysql \
        zip \
        bcmath \
        pcntl \
        exif \
        gd \
        redis \
        opcache

# 引入 Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

EXPOSE 9000
CMD ["php-fpm"]
