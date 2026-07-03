# Registro de Cambios — casadets ERP/POS

Historial de cambios implementados por sesión de trabajo.
Formato: `[YYYY-MM-DD] Área — Descripción`

---

## 2026-07-03 — Restauración de saldo a favor al anular movimiento

### Corrección: anulación de movimiento saldo_favor_usado

**Problema:** Al anular un movimiento de tipo `saldo_favor_usado` (uso de saldo a favor para pagar un vale):
1. El saldo a favor no se restauraba — permanecía como `usado` con `monto_disponible=0`.
2. Se creaba una contrapartida `tipo='ingreso'` visible en la lista de movimientos (confuso para el usuario).
3. El pago (Pago con `metodo_pago='saldo_favor'`) no se marcaba como anulado.
4. El campo `pagado` de la venta no se reducía.

**Archivos modificados:**
- `app/Http/Controllers/MovimientoController.php`:
  - Import: `use App\Models\SaldoFavor;`
  - Paso 2.5 nuevo: cuando `referencia_tipo === 'saldo_favor'`, se restaura `monto_disponible` del SaldoFavor, se fija el `estado` a `disponible` o `parcialmente_usado` según el monto original, se busca el Pago asociado por `observacion LIKE "Uso de saldo a favor SF#N%"` + `monto_total`, se revierte el `pagado` de la venta (solo el monto del saldo, no el total del vale), y se marca el pago como `anulado`.
  - Paso 3 modificado: si `subtipo === 'saldo_favor_usado'`, la contrapartida se crea con `tipo='contable'` en vez de `tipo='ingreso'`, manteniéndose excluida del balance por `SUBTIPOS_NO_BALANCE`.

**Reglas preservadas:**
- `SUBTIPOS_NO_BALANCE = ['anulacion', 'saldo_favor_usado']` — ya excluía ambos del balance de caja.
- Solo el monto del saldo a favor se revierte en la venta. Si el vale tenía otros pagos en efectivo, esos se mantienen.
- Las ventas `anulado` y `anulado_nc` se saltan al revertir el pagado.

---

## 2026-07-03 — NC "Usar para anular vale"

### Nueva funcionalidad: Anular vale con nota de crédito

**Descripción:** Nueva acción en el modal de notas de crédito que permite usar una NC para anular un vale pendiente. El vale pasa al estado `anulado_nc`.

**Controller nuevo método:**
- `SaldoFavorController::anularValeConNC()` — valida NC, valida vale, verifica mismo cliente, verifica que NC cubre el saldo, crea `NotaCreditoAplicacion`, actualiza estado del vale a `anulado_nc`.
- `SaldoFavorController::validarNotaCreditoAnulable()` — validación privada: NC no usada, tiene cliente, monto correcto.
- `SaldoFavorController::idsNotasCreditoConvertidas()` — actualizado para incluir NCs usadas en `nota_credito_aplicaciones`.

**Modelo:**
- `Venta::recalcularEstado()` — guard añadido: `anulado_nc` es inmutable como `anulado`.

**Rutas:**
- `POST /casadets/saldos-favor/nc/{venta}/anular-vale` → `anularValeConNC`

**Vistas:**
- `saldos_favor/index.blade.php` — botón "Anular vale" junto a "Convertir" en cada fila NC. Sub-fila expandible con select de vales pendientes del cliente, confirmación y manejo de errores.
- `ventas/index.blade.php` — filtro `anulado_nc` = "Anulado x NC", fila estilo rojo (`fila-anulado`), badge estático "✕ Anulado x NC", sin botón de pago, JS mapa actualizado.

**Reportes y cobranza:**
- `ReporteController.php` — todas las 10 exclusiones `!= 'anulado'` cambiadas a `whereNotIn(['anulado','anulado_nc'])`.
- `VentaController.php` — `pago()`, `updatePago()`, `updateEstado()` bloquean `anulado_nc` igual que `anulado`.

---

## 2026-07-03 — Sesión anterior

### Prioridad 1: Restricción de datos por vendedor (VendedorScope)

**Archivos nuevos:**
- `app/Services/VendedorScope.php` — Servicio centralizado de restricción por vendedor. Métodos: `activo()`, `ids()`, `aplicar()`, `aplicarCompras()`, `aplicarSaldos()`, `aplicarMovimientos()`.

**Controladores actualizados:**
- `app/Http/Controllers/VentaController.php` — Reemplazado `esVendedor()` → `debeRestringirPorVendedor()` en 9 lugares.
- `app/Http/Controllers/ReporteController.php` — Reemplazado `esVendedor()` → `debeRestringirPorVendedor()` en 4 lugares. Añadida exclusión de referencias fiscales en `qVentas()` y `qDetalles()`.
- `app/Http/Controllers/CompraController.php` — Añadido `VendedorScope::aplicarCompras()` en `index()`.
- `app/Http/Controllers/SaldoFavorController.php` — Añadido `VendedorScope::aplicarSaldos()` en `index()`.
- `app/Http/Controllers/MovimientoController.php` — Añadido `VendedorScope::aplicarMovimientos()` en `index()`. Actualizado `create()` para pasar vendedores. Actualizado `store()` con validación `vendedor_id` requerido para salidas.

---

### Prioridad 2: Referencias fiscales (es_referencia_fiscal)

**Migración nueva:**
- `database/migrations/2026_05_30_000001_add_es_referencia_fiscal_to_ventas.php` — Agrega columna `es_referencia_fiscal` (boolean, default false) a tabla `ventas`.

**Modelo actualizado:**
- `app/Models/Venta.php` — Añadido `es_referencia_fiscal` a `$fillable` y `$casts`. Scopes: `scopeNoFiscal()`, `scopeEsFiscal()`. `getSaldoPendienteAttribute()` retorna 0 para referencias fiscales. `recalcularEstado()` fuerza estado `pagado` para referencias fiscales.

**Vistas actualizadas:**
- `resources/views/casadets/ventas/index.blade.php` — `$esRefFiscal` usa nuevo campo (`es_referencia_fiscal`). Botón "Verificar pago" oculto para referencias fiscales.
- `resources/views/casadets/ventas/show.blade.php` — `$esCanjeadaFiscal` usa nuevo campo. Botón "Verificar pago" oculto para referencias fiscales.

**Exclusión en reportes:**
- `ReporteController::qVentas()` — Añadido `->where('ventas.es_referencia_fiscal', false)`.
- `ReporteController::qDetalles()` — Añadido `->where('v.es_referencia_fiscal', false)`.

---

### Prioridad 3: Vendedor en movimientos

**Migración nueva:**
- `database/migrations/2026_05_30_000002_add_vendedor_id_to_movimientos.php` — Agrega FK `vendedor_id` (nullable) a tabla `movimientos`.

**Modelo actualizado:**
- `app/Models/Movimiento.php` — Añadido `vendedor_id` a `$fillable`. Añadida relación `vendedor()`.

**Vista actualizada:**
- `resources/views/movimientos/create.blade.php` — Selector de vendedor visible solo cuando `$tipo === 'salida'`, marcado como requerido.

---

### Prioridad 4: Seguridad visual (puedeHacer)

**Vistas actualizadas:**
- `resources/views/casadets/ventas/index.blade.php` — Botones "Importar", "Nueva venta", "Editar", "Verificar pago", "Eliminar" protegidos con `puedeHacer()`.
- `resources/views/casadets/ventas/show.blade.php` — Botones "Verificar pago" y "Editar" protegidos con `puedeHacer()`.

**Pendiente (no implementado aún):**
- Proteger botones en compras, productos, vendedores, movimientos, clientes.

---

## Sesiones anteriores (resumen)

### 2026-07-03 (sesión previa) — Notas de crédito aplicadas a ventas

**Archivos nuevos:**
- `app/Models/NotaCreditoAplicacion.php` — Modelo para tabla `nota_credito_aplicaciones`. Relaciones: `notaCredito()`, `venta()`, `registradoPor()`.
- `database/migrations/2026_07_03_000001_nc_aplicaciones_y_nc_aplicado_ventas.php` — Agrega columna `nc_aplicado` a `ventas` y crea tabla `nota_credito_aplicaciones`.

**Modelo actualizado:**
- `app/Models/Venta.php` — Añadido `nc_aplicado` a `$fillable` y `$casts`.

---

### Sistema de roles y permisos dinámicos

**Archivos creados:**
- `app/Support/PermisoCatalog.php` — Catálogo estático de módulos y permisos. 14 módulos, 22+ permisos.
- `app/Http/Middleware/CheckPermiso.php` — Middleware de verificación de permisos.
- `database/seeders/RolSeeder.php` — 4 roles por defecto: Admin, Supervisor, Cajero, Vendedor.

**Modelos actualizados:**
- `app/Models/User.php` — Métodos: `puedeVer()`, `puedeHacer()`, `esVendedor()`, `debeRestringirPorVendedor()`, `vendedorIds()`.
- `app/Models/Rol.php` — Columnas JSON: `modulos`, `permisos`.

---

## Convenciones del sistema

| Elemento | Valor |
|---|---|
| Auth dev | `admin@sistema.com` / `12345678` |
| DB dev | SQLite (Replit) |
| DB prod | MySQL `bd_casadets` |
| Vendor scope | `debeRestringirPorVendedor()` (no `esVendedor()`) |
| Fiscal refs | `es_referencia_fiscal = true` → excluir de KPIs, reportes, deuda |
| NC aplicadas | `nc_aplicado` en ventas + tabla `nota_credito_aplicaciones` |
| Permiso check | `$user->puedeHacer('modulo.accion')` (string con punto) |

---

## Pendiente por implementar

- [ ] **P4**: Proteger botones en compras, productos, vendedores, movimientos
- [ ] **P5**: Dashboard gerencial con Chart.js (7 KPIs + 10 gráficos)
- [ ] Excluir `es_referencia_fiscal` en CobranzaService y cálculo de deuda de clientes
- [ ] Campo `vendedor_id` en el formulario de edición de movimientos
- [ ] Aplicar NC desde interfaz (formulario + controller)
