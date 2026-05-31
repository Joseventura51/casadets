---
name: VendedorScope centralization
description: All vendedor-based filtering must go through VendedorScope service; inline checks are banned.
---

## Rule
Use `VendedorScope::aplicar($query)`, `::aplicarMovimientos()`, `::aplicarSaldos()`, `::aplicarCompras()` in every controller.
Never write inline `if ($user->debeRestringirPorVendedor()) { $query->whereIn(...) }` blocks in controllers.

**Why:** Inline checks were inconsistent across 8+ call sites in VentaController alone, and HomeController/CajaController had no scoping at all. Centralizing ensures any future change to the scoping logic propagates everywhere.

**How to apply:**
- Query with direct `vendedor_id` column → `VendedorScope::aplicar($query)`
- Single-record authorization → `$ids = VendedorScope::ids(); if ($ids !== null && !in_array($model->vendedor_id, $ids)) abort(403)`
- Movimientos → `VendedorScope::aplicarMovimientos($query)`
- SaldoFavor → `VendedorScope::aplicarSaldos($query)`
- Compras → `VendedorScope::aplicarCompras($query)`
