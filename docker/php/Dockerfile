FROM php:8.3-fpm-alpine

RUN docker-php-ext-install pdo_mysql \
    && { \
        echo 'expose_php=Off'; \
        echo 'display_errors=Off'; \
        echo 'log_errors=On'; \
        echo 'allow_url_fopen=Off'; \
    } > "$PHP_INI_DIR/conf.d/zz-security.ini"
WORKDIR /var/www/servidor
