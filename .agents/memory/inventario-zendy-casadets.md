---
name: Inventario ZENDY/CASADETS
description: Durable ownership and document rules for the physical inventory model.
---

Las proformas representan los vales de salida de ZENDY hacia CASADETS. Las boletas, facturas y otros documentos comerciales de CASADETS no descuentan ni devuelven stock físico. Las devoluciones físicas son operaciones explícitas y deben indicar el almacén ZENDY de ingreso.

**Why:** CASADETS vende, pero ZENDY es la propietaria del inventario físico; permitir que cada venta comercial mueva stock duplica salidas y hace que una anulación financiera parezca una devolución física.

**How to apply:** Resolver productos por empresa (`zendy` para vales/compras físicas y `casadets` para ventas no físicas), exigir almacén en cada entrada/salida, y preservar el kardex con movimientos compensatorios en vez de borrar movimientos históricos.