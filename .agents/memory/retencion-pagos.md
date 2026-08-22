---
name: Retención en pagos
description: Regla de negocio para retenciones del 3% en cobros de ventas y pagos importados.
---

La retención del 3% se calcula sobre el monto bruto del pago: la deuda y el monto aplicado a la venta usan el bruto, mientras que el movimiento de caja usa el neto. El pago conserva ambos importes y el historial muestra la retención.

**Why:** La retención no debe reducir la deuda del cliente; representa un importe descontado al momento de ingresar el dinero a caja.

**How to apply:** Reutilizar el servicio de cobranza para cualquier flujo que registre pagos con retención y mostrar siempre bruto, retención y neto en la interfaz.