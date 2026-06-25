---
name: ConciliacionService concurrency model
description: Locking, validation, and audit strategy for compra_venta_detalle assignments.
---

## Rule
All assign/re-assign operations on `compra_venta_detalle` MUST go through `ConciliacionService::sincronizar()`. Never call `$compra->detalles()->sync()` directly from a controller.

**Why:** Direct sync() calls have a TOCTOU race: validate → sync can be interleaved concurrently. The service acquires `lockForUpdate()` on venta_detalle rows before validating, collapsing check-then-act into an atomic unit.

**How to apply:**
1. `CompraController::buildSyncData()` — only builds the pivot array (resolves linea IDs from form, computes frozen costs). No validation here.
2. `ConciliacionService::sincronizar($compra, $syncData, $compraIdExcluir)` — wraps in `DB::transaction()`, locks rows, validates, syncs, writes audit.
3. Nested transactions: the service's inner `DB::transaction()` uses SAVEPOINTs inside the controller's outer transaction. Works on both SQLite and PostgreSQL.

## Locking semantics
- PostgreSQL: `lockForUpdate()` → `SELECT ... FOR UPDATE` on venta_detalle rows.
- SQLite: `lockForUpdate()` is a no-op, but the surrounding transaction serializes all writes (WAL mode).

## Audit table: `conciliacion_auditorias`
- Append-only (`created_at` only, no `updated_at`, `$timestamps = false`).
- Captures: compra_id, venta_detalle_id, accion (crear/actualizar/eliminar), cantidad/costo antes y después, compra_linea_id antes y después, producto_nombre (denormalized for display), usuario_id, ip.
- Comparison uses `capturarEstado()` (snapshot before sync) vs new state after sync.

## Validation exclusion logic
- For venta_detalle saldo: exclude `compra_id = $compraIdExcluir` (the compra being updated still has old CVD records at validation time).
- For compra_linea saldo: no exclusion needed — update always deletes old lineas and creates new ones with fresh IDs, so no prior CVD records point to the new linea IDs.
