---
name: SaldoFavor FK architecture
description: saldos_favor.venta_origen_id is the canonical FK to ventas; no string matching on descripcion for business logic.
---

## Rule
`saldos_favor.venta_origen_id` (nullable FK → ventas.id, ON DELETE SET NULL) is the single source of truth for which venta/documento originated a saldo.

## Why
Previously, duplicate-conversion detection and NC filtering used `descripcion LIKE '%NC #X%'` — fragile, O(N) queries, collision-prone.

## How to apply
- `notasCreditoDisponibles()`: filter already-converted NCs via `whereNotIn('id', SaldoFavor::whereNotNull('venta_origen_id')->pluck('venta_origen_id'))`.
- `convertirNC()`: check duplicate via `SaldoFavor::where('venta_origen_id', $venta->id)->exists()`.
- `registrarPago()` excedente: always set `venta_origen_id = $venta->id` when creating SaldoFavor.
- `saldosCliente` JSON: derive `tipo_origen` from `venta_origen_id` + `ventaOrigen->documento_tipo`, not from description string.
- Model has `ventaOrigen()` BelongsTo and `esDeNC()` helper.
