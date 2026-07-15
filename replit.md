# casadets — Sistema de Gestión

Aplicación de gestión empresarial construida con Laravel (PHP) y SQLite.

## Stack
- **Backend:** Laravel 11 (PHP)
- **Base de datos:** SQLite (archivo `database/database.sqlite`)
- **Frontend:** Blade templates + Vite (CSS/JS)
- **PDF:** barryvdh/laravel-dompdf

## Cómo correr el proyecto
El workflow **"Start application"** ejecuta `bash scripts/start.sh`, que:
1. Instala dependencias PHP (`composer install`)
2. Corre migraciones (`php artisan migrate --force`)
3. Limpia caché de config/rutas/vistas
4. Levanta el servidor en el puerto 5000

## Módulos principales
- **Ventas / Cobros** — gestión de vales, pagos, saldos a favor, notas de crédito
- **Compras** — facturas de proveedores, conciliación CVD
- **Clientes / Vendedores** — ABM con restricciones por vendedor
- **Cajas / Series** — multi-caja con sesiones y series de comprobantes
- **Reportes** — reportes semanales, por caja, PDF exportable
- **Productos / Stock** — inventario con movimientos
- **Usuarios / Roles** — permisos dinámicos por módulo

## Variables de entorno requeridas
- `APP_KEY` — clave de cifrado Laravel (ya configurada en `.env`)

## Preferencias del usuario
- Hablar en español
