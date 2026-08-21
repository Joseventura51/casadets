---
name: Almacenes e inventario
description: Arquitectura de stock por almacén y compatibilidad con el Kardex global existente.
---

El sistema mantiene `productos.stock_actual` como stock global derivado de `stock_movimientos`. El módulo de almacenes agrega `producto_almacen` y movimientos explícitos con `almacen_id`; los movimientos antiguos sin almacén se imputan al almacén PRINCIPAL mediante eventos del modelo.

**Why:** La aplicación ya tenía compras, ventas y devoluciones de venta funcionando sobre un Kardex global. Cambiar esas operaciones de golpe habría arriesgado el inventario histórico y la caja.

**How to apply:** Las salidas entre almacenes deben usar `InventarioAlmacenService` dentro de transacciones y bloqueo de filas. No reutilizar `devoluciones` de ventas; usar `devoluciones_salida`.