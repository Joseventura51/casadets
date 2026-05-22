FROM php:8.2-cli

# Extensiones del sistema necesarias
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libxml2-dev \
    libonig-dev \
    libicu-dev \
    zip \
    unzip \
    curl \
    git \
    sqlite3 \
    libsqlite3-dev \
    && rm -rf /var/lib/apt/lists/*

# Extensiones PHP requeridas por Laravel + phpspreadsheet
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo \
        pdo_sqlite \
        pdo_mysql \
        zip \
        gd \
        xml \
        mbstring \
        intl \
        exif \
        bcmath \
        opcache

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Copiar archivos de dependencias primero (mejor cache de layers Docker)
COPY composer.json composer.lock ./

# Instalar dependencias sin dev en producción
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Copiar el resto de la aplicación
COPY . .

# Ejecutar scripts post-install ahora que el código está disponible
RUN composer run-script post-autoload-dump --no-interaction 2>/dev/null || true

# Crear todas las carpetas necesarias con permisos correctos
RUN mkdir -p \
        storage/logs \
        storage/framework/sessions \
        storage/framework/views \
        storage/framework/cache \
        storage/app/public \
        storage/app/imports \
        bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Crear base de datos SQLite vacía (las migraciones se ejecutan en el entrypoint)
RUN mkdir -p database \
    && touch database/database.sqlite \
    && chmod 664 database/database.sqlite

# Copiar y configurar entrypoint
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 10000

ENTRYPOINT ["docker-entrypoint.sh"]
