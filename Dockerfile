FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    zip \
    libzip-dev \
    && docker-php-ext-install pdo pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY . /app

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && rm composer-setup.php \
    && composer install --no-dev --optimize-autoloader || true

EXPOSE 8080

CMD ["sh", "-lc", "php -d display_errors=1 -d display_startup_errors=1 -d log_errors=1 -d error_log=/proc/self/fd/2 -S 0.0.0.0:${PORT} -t /app/vente_entre_particuliers"]