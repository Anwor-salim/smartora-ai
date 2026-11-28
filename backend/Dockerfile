FROM composer:2 AS vendor
WORKDIR /app
COPY . .
RUN rm -f bootstrap/cache/*.php \
    && composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts --ignore-platform-reqs

FROM php:8.4-cli
RUN apt-get update && apt-get install -y --no-install-recommends \
    libsqlite3-dev \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libicu-dev \
    unzip \
    poppler-utils \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_sqlite mbstring zip gd xml dom simplexml xmlreader xmlwriter intl \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY --from=vendor /app /var/www/html
COPY docker/run.sh /usr/local/bin/start-app

RUN sed -i 's/\r$//' /usr/local/bin/start-app \
    && chmod +x /usr/local/bin/start-app \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/knowledge_documents storage/app/private/knowledge_documents database \
    && touch database/database.sqlite \
    && chown -R www-data:www-data storage database

EXPOSE 8000
CMD ["/usr/local/bin/start-app"]
