#!/bin/sh
set -e

cd /var/www

# Copiar .env si no existe
if [ ! -f .env ]; then
    cp .env.example .env
fi

# Generar APP_KEY solo si no está definida
if [ -z "$APP_KEY" ]; then
    php artisan key:generate --force --no-interaction
fi

# Asegurarse que la base de datos existe
touch database/database.sqlite

# Ejecutar migraciones
php artisan migrate --force --no-interaction

# Limpiar y cachear configuración
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Iniciar servidor en el puerto que Render espera (10000)
exec php artisan serve --host=0.0.0.0 --port=10000
