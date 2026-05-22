#!/bin/sh
set -e

cd /var/www

# ── 1. Crear .env desde ejemplo si no existe ─────────────────────────────────
if [ ! -f .env ]; then
    cp .env.example .env
fi

# ── 2. Escribir todas las variables de Render al .env ─────────────────────────
# Esto garantiza que config:cache lea los valores correctos de producción
# y no los defaults de .env.example (ej: SESSION_DRIVER=database)
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

write_env APP_NAME        "${APP_NAME:-Casadets}"
write_env APP_ENV         "${APP_ENV:-production}"
write_env APP_DEBUG       "${APP_DEBUG:-false}"
write_env APP_KEY         "$APP_KEY"
write_env APP_URL         "${RENDER_EXTERNAL_URL:-${APP_URL:-http://localhost}}"
write_env DB_CONNECTION   "${DB_CONNECTION:-sqlite}"
write_env SESSION_DRIVER  "${SESSION_DRIVER:-file}"
write_env CACHE_STORE     "${CACHE_STORE:-file}"
write_env QUEUE_CONNECTION "${QUEUE_CONNECTION:-sync}"
write_env LOG_CHANNEL     "${LOG_CHANNEL:-stderr}"
write_env LOG_LEVEL       "${LOG_LEVEL:-error}"

# ── 3. Generar APP_KEY si no hay ninguna válida ───────────────────────────────
if ! grep -q "^APP_KEY=base64:" .env; then
    echo "[entrypoint] Generando APP_KEY..."
    php artisan key:generate --force --no-interaction
fi

# ── 4. Garantizar existencia y permisos de directorios críticos ───────────────
mkdir -p database \
         storage/logs \
         storage/framework/sessions \
         storage/framework/views \
         storage/framework/cache \
         storage/app/public \
         storage/app/imports \
         bootstrap/cache

touch database/database.sqlite
chmod 664 database/database.sqlite
chmod -R 775 storage bootstrap/cache

# ── 5. Ejecutar migraciones ───────────────────────────────────────────────────
echo "[entrypoint] Ejecutando migraciones..."
php artisan migrate --force --no-interaction

# ── 6. Limpiar cachés viejas y regenerar para producción ─────────────────────
# Limpiar primero evita que cachés del build anterior contaminen el arranque
php artisan config:clear  --quiet 2>/dev/null || true
php artisan route:clear   --quiet 2>/dev/null || true
php artisan view:clear    --quiet 2>/dev/null || true

echo "[entrypoint] Cacheando configuración..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# ── 7. Iniciar servidor ───────────────────────────────────────────────────────
echo "[entrypoint] Iniciando servidor en puerto 10000..."
exec php artisan serve --host=0.0.0.0 --port=10000
