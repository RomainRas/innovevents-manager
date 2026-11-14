FROM composer:2@sha256:8de2184c6de224f4689e8f80ed5f5edaf0030b6b8b7839a22a2bcb6722ac87d0 AS composer
FROM php:8.4-cli-bookworm@sha256:b3e907a1108629c562c4bb313ce3449e34d49991f6bb2487913f6146f47200fe
RUN apt-get update && apt-get install -y --no-install-recommends libssl-dev pkg-config unzip libpng-dev libjpeg62-turbo-dev \
    && docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install pdo_mysql gd \
    && pecl install mongodb-2.5.7 \
    && docker-php-ext-enable mongodb \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
RUN apt-get update && apt-get install -y --no-install-recommends libxml2-dev libonig-dev \
    && docker-php-ext-install dom mbstring && rm -rf /var/lib/apt/lists/*
WORKDIR /app
CMD ["php", "-d", "display_errors=0", "-d", "upload_max_filesize=5M", "-d", "post_max_size=6M", "-S", "0.0.0.0:8080", "-t", "public", "public/index.php"]
