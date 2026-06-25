<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

class VentaDetalle extends Model
{
    protected $table = 'venta_detalles';

    protected $fillable = [
        'venta_id',
        'producto_id',
        'producto',
        'codigo',
        'cantidad',
        'precio_unitario',
        'subtotal',
    ];

    protected $casts = [
        'cantidad'        => 'decimal:2',
        'precio_unitario' => 'decimal:2',
        'subtotal'        => 'decimal:2',
    ];

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function compras(): BelongsToMany
    {
        return $this->belongsToMany(Compra::class, 'compra_venta_detalle', 'venta_detalle_id', 'compra_id')
            ->withPivot('cantidad', 'compra_linea_id', 'costo_unitario', 'costo_total')
            ->withTimestamps();
    }

    public function cantidadCosteada(): float
    {
        return (float) DB::table('compra_venta_detalle')
            ->where('venta_detalle_id', $this->id)
            ->sum('cantidad');
    }

    public function estadoCosteo(): string
    {
        $costeada = $this->cantidadCosteada();
        if ($costeada <= 0)                              return 'sin_costear';
        if ($costeada >= (float) $this->cantidad)        return 'costeada';
        return 'parcial';
    }
}
