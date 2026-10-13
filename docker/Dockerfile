FROM php:8.4-cli

RUN apt-get update && apt-get install -y \
        libpq-dev \
        libsqlite3-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        fonts-dejavu-core \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_pgsql pdo_sqlite gd \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
