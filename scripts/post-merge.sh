#!/bin/bash
set -e

composer install --no-interaction --prefer-dist --optimize-autoloader

php artisan migrate --force

php artisan config:clear
php artisan route:clear
php artisan view:clear
