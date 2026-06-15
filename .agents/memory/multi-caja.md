---
name: Multi-Caja architecture
description: How the multi-caja, series, and user assignment system works across the app.
---

## Core tables
- `cajas` — physical cash registers (codigo, nombre, empresa, activa)
- `series` — document series with auto-correlativo (codigo, tipo_documento, correlativo_actual, caja_id, activa)
- `usuario_caja` — pivot: user ↔ caja, with `principal` flag
- `caja_id` column added (nullable) to: `caja_sesiones`, `ventas`, `movimientos`, `compras`, `saldos_favor`, `users`

## Session mechanic
- Active caja is stored in `session('caja_id')`
- `CajaService::cajasUsuario()` — Admin sees all cajas; others see their assigned ones
- `CajaService::cajaSeleccionada()` — reads session('caja_id')
- `CajaSelectorController::seleccionar()` — POST /caja/seleccionar, validates permission then writes to session
- On login: `AuthController` auto-selects `$user->cajaPrincipal()` if exists

## caja_id propagation rule
Every `Movimiento::create()` must include `'caja_id' => $venta->caja_id ?? session('caja_id')` (or just `session('caja_id')` for non-venta context). Files covered:
- CobranzaService: registrarPago, registrarPagoMultiple, reducirSaldo, aplicarSaldoFavor
- MovimientoController::store
- CompraController::store, update (new movimiento branch)
- VentaController::store (via venta creation with caja_id)
- SaldoFavorController::crear

## Import by serie
VentaImportController::confirm detects caja_id from serie column:
```php
$serieModel = Serie::where('codigo', strtoupper(trim($g['serie'])))->first();
if ($serieModel && $serieModel->caja_id) { $cajaIdVenta = $serieModel->caja_id; }
```

## Multi-session per caja per day
CajaSesion no longer has unique constraint on [empresa, fecha].
Multiple open/close cycles per day are allowed. Only restriction: cannot open if already `estado = 'abierta'`.

## CajaAbierta middleware
Checks `session('caja_id')` first; falls back to empresa-level check for historical data compatibility.
Redirect to `/casadets/caja` on failure.

## New admin routes
- GET/POST /admin/cajas — CajaAdminController (CRUD)
- GET/POST /admin/series — SerieController (CRUD + DELETE)
- GET /admin/series/caja/{caja}.json — porCaja API
- POST /caja/seleccionar — CajaSelectorController
- GET /caja/disponibles.json — CajaSelectorController

## Permissions
PermisoCatalog: added `admin.cajas` and `admin.series` to MODULOS, PERMISOS, and DEFAULTS['Administrador'].
Sidebar shows Cajas/Series links when user has those permissions.

## Layout selector
layouts/app.blade.php renders a caja selector bar (dropdown + estado badge) above content when user has cajas assigned. Auto-submits on change.

**Why:** Needed to support multiple physical cash registers per company, with document series tracking, per-caja financial reporting, and user access control per register.
