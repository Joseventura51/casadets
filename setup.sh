#!/bin/bash
echo "=== SETUP CASADETS ==="

if [ ! -f ".env" ]; then
    cp .env.example .env
    echo "[OK] .env creado"
else
    echo "[OK] .env ya existe"
fi

mkdir -p database
touch database/database.sqlite
echo "[OK] Base de datos SQLite lista"

php artisan key:generate
php artisan config:clear
php artisan route:clear
php artisan migrate:fresh --seed

echo ""
echo "=== LISTO! ==="
echo "Ejecuta: php artisan serve"
echo "Luego abre: http://127.0.0.1:8000"
echo ""
echo "Usuario: admin@sistema.com"
echo "Contraseña: 12345678"
