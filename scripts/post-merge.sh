#!/bin/bash
set -e

composer install --no-interaction --prefer-dist --optimize-autoloader

touch database/database.sqlite

# Generate .env from environment variables set in Replit
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

php artisan migrate --force

npm install

npm run build

php artisan config:clear
php artisan route:clear
php artisan view:clear
