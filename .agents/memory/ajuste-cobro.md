---
name: Ajuste Manual de Cobro
description: How the manual billing adjustment feature works across the ventas module.
---

## Rule
- `ventas.total` = Total Original (sum of products, IMMUTABLE once saved)
- `ventas.ajuste` = delta (negative = discount, positive = surcharge)
- `total_a_cobrar` = computed accessor: `total + ajuste`
- `pagado` = payments received so far
- `saldo_pendiente` = computed accessor: `max(0, total_a_cobrar - pagado)`

## Forms
- Create/edit forms submit `total_cobrar` (the desired billing amount), NOT `ajuste`
- The controller calculates: `ajuste = totalCobrar - total`
- The JS in create.blade.php and edit.blade.php shows real-time ajuste feedback
- When products change (recalcular fires), totalACobrar resets to match new total (ajuste → 0)

## CobranzaService
- All debt calculations use `$venta->total_a_cobrar` not `$venta->total`
- Auto-pay in store() uses `$totalCobrar` (not `$total`)

## Reportes
- `comision_porcentaje` column added to vendedores (decimal 5,2, default 0)
- comision_total = SUM((total + ajuste) * comision_porcentaje / 100) per period
- por_dia entries now include `costo` key for the per-day table with totals row

**Why:** Customers occasionally pay a slightly different amount due to rounding or manual negotiation. The ajuste records the delta for full audit trail without corrupting the original product-based total.

**How to apply:** Always access `$venta->total_a_cobrar` (accessor) in payment logic, never `$venta->total` directly for balance calculations.
