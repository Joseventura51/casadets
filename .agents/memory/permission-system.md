---
name: Dynamic permission system
description: How roles/permissions work — catalog, model methods, vendor scoping, middleware
---

## Architecture

- `roles.modulos` (JSON array) — controls sidebar visibility and route access via `puedeVer()`
- `roles.permisos` (JSON array) — controls granular actions via `puedeHacer()`
- `App\Support\PermisoCatalog` — single source of truth for all module keys and permission strings

## Key methods on User model

- `puedeVer($modulo)` — checks `rol->modulos`; has static fallback map if rol has no modulos yet
- `puedeHacer($permiso)` — checks `rol->permisos`; Administrador always returns true as fallback
- `debeRestringirPorVendedor()` — returns true if user has ≥1 vendedor associated (triggers data scoping)
- `vendedorIds()` — returns array of associated vendedor IDs for query filtering

## Middleware

- `rol:modulo` (CheckRol) — checks module-level access
- `permiso:accion` (CheckPermiso) — checks action-level permission

## Vendor scoping

- VentaController already used `esVendedor()` (role name check)
- ClienteController now uses `debeRestringirPorVendedor()` (vendedor association check)
- ReporteController uses `esVendedor()` — still works since seeded roles are named "Vendedor"

**Why:** `debeRestringirPorVendedor()` is more flexible than `esVendedor()` — works for any role that has associated vendedores, not just the role named "Vendedor".

## Default seeded permissions

- Administrador: 14 módulos, 22 permisos (all)
- Supervisor: 12 módulos, 22 permisos (all except admin.*)
- Cajero: 7 módulos, 7 permisos (caja/ventas/clientes limited)
- Vendedor: 5 módulos, 2 permisos (ventas.crear, clientes.crear)
