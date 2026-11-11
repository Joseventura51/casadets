<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompraVentaDetalle extends Model
{
    protected $table = 'compra_venta_detalle';

    protected $fillable = [
        'compra_id',
        'venta_detalle_id',
        'compra_linea_id',
        'cantidad',
        'costo_unitario',
        'costo_total',
    ];

    protected $casts = [
        'cantidad' => 'decimal:2',
        'costo_unitario' => 'decimal:4',
        'costo_total' => 'decimal:4',
    ];

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class);
    }

    public function ventaDetalle(): BelongsTo
    {
        return $this->belongsTo(VentaDetalle::class);
    }

    public function regularizaciones(): HasMany
    {
        return $this->hasMany(RegularizacionCosto::class, 'compra_venta_detalle_id');
    }

    public function despachoDetalleCostos(): HasMany
    {
        return $this->hasMany(DespachoDetalleCosto::class, 'compra_venta_detalle_id');
    }
}
