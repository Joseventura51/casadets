<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Producto extends Model
{
    protected $fillable = [
        'nombre',
        'codigo',
        'empresa',
        'precio_venta',
        'precio_costo',
        'stock_actual',
        'activo',
    ];

    protected $casts = [
        'precio_venta' => 'decimal:2',
        'precio_costo' => 'decimal:2',
        'stock_actual' => 'decimal:2',
        'activo'       => 'boolean',
    ];

    public function detallesVenta(): HasMany
    {
        return $this->hasMany(VentaDetalle::class);
    }

    public function lineasCompra(): HasMany
    {
        return $this->hasMany(CompraLinea::class);
    }

    public function stockMovimientos(): HasMany
    {
        return $this->hasMany(StockMovimiento::class);
    }

    /**
     * Stock calculado desde el kardex (fuente de verdad).
     * Usar para verificación; para display usar stock_actual.
     */
    public function stockDesdeKardex(): float
    {
        return (float) ($this->stockMovimientos()
            ->selectRaw("SUM(CASE WHEN tipo = 'entrada' THEN cantidad ELSE -cantidad END) as total")
            ->value('total') ?? 0.0);
    }

    /**
     * Recalcula y persiste stock_actual desde el kardex.
     */
    public function recalcularStock(): void
    {
        $this->update(['stock_actual' => $this->stockDesdeKardex()]);
    }

    /**
     * Margen de ganancia estimado (%).
     */
    public function getMargenAttribute(): ?float
    {
        $pv = (float) $this->precio_venta;
        $pc = (float) $this->precio_costo;
        if ($pv <= 0) return null;
        return round(($pv - $pc) / $pv * 100, 1);
    }

    /**
     * Tiene stock bajo (≤ 0).
     */
    public function getStockBajoAttribute(): bool
    {
        return (float) $this->stock_actual <= 0;
    }
}
