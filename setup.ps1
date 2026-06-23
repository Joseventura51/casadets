Write-Host "=== SETUP CASADETS ===" -ForegroundColor Cyan

if (-Not (Test-Path ".env")) {
    Copy-Item .env.example .env
    Write-Host "[OK] .env creado" -ForegroundColor Green
} else {
    Write-Host "[OK] .env ya existe" -ForegroundColor Yellow
}

if (-Not (Test-Path "database\database.sqlite")) {
    New-Item -Path "database" -ItemType Directory -Force | Out-Null
    New-Item -Path "database\database.sqlite" -ItemType File -Force | Out-Null
    Write-Host "[OK] Base de datos SQLite creada" -ForegroundColor Green
} else {
    Write-Host "[OK] Base de datos ya existe" -ForegroundColor Yellow
}

Write-Host "Generando clave de aplicacion..." -ForegroundColor Cyan
php artisan key:generate

Write-Host "Limpiando cache..." -ForegroundColor Cyan
php artisan config:clear
php artisan route:clear

Write-Host "Corriendo migraciones..." -ForegroundColor Cyan
php artisan migrate --force

Write-Host ""
Write-Host "=== LISTO! ===" -ForegroundColor Green
Write-Host "Ejecuta: php artisan serve" -ForegroundColor White
Write-Host "Luego abre: http://127.0.0.1:8000" -ForegroundColor White
