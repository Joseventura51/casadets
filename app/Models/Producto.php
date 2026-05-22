<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Producto extends Model
{
    protected $fillable = [
        'nombre',
        'codigo',
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
     * Usar solo cuando se necesite precisión absoluta; para listados usar stock_actual.
     */
    public function stockDesdeKardex(): float
    {
        return (float) $this->stockMovimientos()
            ->selectRaw("SUM(CASE WHEN tipo = 'entrada' THEN cantidad ELSE -cantidad END) as total")
            ->value('total') ?? 0.0;
    }

    /**
     * Recalcula y persiste stock_actual desde el kardex.
     */
    public function recalcularStock(): void
    {
        $this->update(['stock_actual' => $this->stockDesdeKardex()]);
    }
}
