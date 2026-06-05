#!/bin/sh
set -e

cd /var/www

# 1. Create .env from the example file when the container starts clean.
if [ ! -f .env ]; then
    cp .env.example .env
fi

# 2. Write Render environment values into .env so cached config uses them.
write_env() {
    local key="$1"
    local val="$2"

    if [ -n "$val" ]; then
        if grep -q "^${key}=" .env 2>/dev/null; then
            sed -i "s|^${key}=.*|${key}=${val}|" .env
        else
            echo "${key}=${val}" >> .env
        fi
    fi
}

write_env APP_NAME         "${APP_NAME:-Casadets}"
write_env APP_ENV          "${APP_ENV:-production}"
write_env APP_DEBUG        "${APP_DEBUG:-false}"
write_env APP_KEY          "$APP_KEY"
write_env APP_URL          "${RENDER_EXTERNAL_URL:-${APP_URL:-http://localhost}}"
write_env DB_CONNECTION    "${DB_CONNECTION:-sqlite}"
write_env SESSION_DRIVER   "${SESSION_DRIVER:-file}"
write_env CACHE_STORE      "${CACHE_STORE:-file}"
write_env QUEUE_CONNECTION "${QUEUE_CONNECTION:-sync}"
write_env LOG_CHANNEL      "${LOG_CHANNEL:-stderr}"
write_env LOG_LEVEL        "${LOG_LEVEL:-error}"

# 3. Prepare runtime directories.
mkdir -p database \
         storage/logs \
         storage/framework/sessions \
         storage/framework/views \
         storage/framework/cache \
         storage/app/public \
         storage/app/imports \
         bootstrap/cache

if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
    touch database/database.sqlite
    chmod 664 database/database.sqlite
fi

chmod -R 775 storage bootstrap/cache

# 4. Clear old caches before Laravel reads database/config values.
php artisan config:clear --quiet 2>/dev/null || true
php artisan route:clear  --quiet 2>/dev/null || true
php artisan view:clear   --quiet 2>/dev/null || true

# 5. Generate APP_KEY when .env does not contain a valid Laravel key.
if ! grep -q "^APP_KEY=base64:" .env; then
    echo "[entrypoint] Generando APP_KEY..."
    php artisan key:generate --force --no-interaction
fi

# 6. Initialize database on every deploy.
echo "[entrypoint] Ejecutando migraciones..."
php artisan migrate --force --no-interaction

echo "[entrypoint] Ejecutando seeders iniciales..."
php artisan db:seed --force --no-interaction

# 7. Rebuild production caches after database initialization.
echo "[entrypoint] Cacheando configuracion..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "[entrypoint] Iniciando servidor en puerto 10000..."
exec php artisan serve --host=0.0.0.0 --port=10000
