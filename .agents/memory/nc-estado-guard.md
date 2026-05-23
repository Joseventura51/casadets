---
name: NC recalcularEstado guard
description: nota_credito documents must be explicitly guarded in recalcularEstado() to always remain 'pagado'.
---

## Rule
At the top of `Venta::recalcularEstado()`, after the `anulado` guard, add:

```php
if ($this->documento_tipo === 'nota_credito') {
    $this->update(['estado' => 'pagado']);
    return;
}
```

## Why
NC total is stored as negative (e.g. -100) and pagado=0. The expression `0 >= -100` evaluates to `true` giving `pagado` — correct but only by mathematical coincidence. Any future change to how NC totals are stored would silently break state calculation.

## How to apply
Always add this guard whenever `recalcularEstado()` is modified. NC documents represent credits/returns, not collectible debts.
