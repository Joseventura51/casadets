#!/bin/bash
set -e

# Generar .env desde variables de entorno de Replit si no existe o está incompleto
if [ -z "$APP_KEY" ]; then
  echo "ERROR: APP_KEY no está definida en las variables de entorno."
  exit 1
fi

cat > .env << EOF
APP_NAME=${APP_NAME:-casadets}
APP_ENV=${APP_ENV:-local}
APP_KEY=${APP_KEY}
APP_DEBUG=${APP_DEBUG:-true}
APP_URL=${APP_URL:-http://localhost:5000}

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US
APP_MAINTENANCE_DRIVER=file

BCRYPT_ROUNDS=12

LOG_CHANNEL=${LOG_CHANNEL:-stack}
LOG_STACK=${LOG_STACK:-single}
LOG_LEVEL=${LOG_LEVEL:-debug}

DB_CONNECTION=${DB_CONNECTION:-sqlite}

SESSION_DRIVER=${SESSION_DRIVER:-database}
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=${QUEUE_CONNECTION:-database}

CACHE_STORE=${CACHE_STORE:-database}

MAIL_MAILER=log
MAIL_FROM_ADDRESS="hello@casadets.com"
MAIL_FROM_NAME="casadets"

VITE_APP_NAME="casadets"
EOF

# Crear base de datos SQLite si no existe
touch database/database.sqlite

# Crear directorios de storage necesarios
mkdir -p storage/app/reportes_caja
mkdir -p storage/logs

# Instalar / sincronizar dependencias PHP (detecta paquetes nuevos)
composer install --no-interaction --prefer-dist --optimize-autoloader

# Correr migraciones
php artisan migrate --force

# Sembrar datos iniciales (caja, series FFF1/BBB1, roles, admin) — idempotente
php artisan db:seed --force

# Limpiar caché
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Iniciar servidor
php -d upload_max_filesize=100M -d post_max_size=100M -d max_input_vars=10000 artisan serve --host=0.0.0.0 --port=5000
