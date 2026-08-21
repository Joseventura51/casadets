<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalidaAlmacenDetalle extends Model
{
    protected $table = 'salida_almacen_detalles';

    protected $fillable = [
        'salida_almacen_id', 'producto_id', 'codigo_producto',
        'descripcion_original', 'unidad_medida', 'cantidad',
        'precio_unitario', 'monto', 'lote',
    ];

    protected $casts = [
        'cantidad' => 'decimal:2',
        'precio_unitario' => 'decimal:2',
        'monto' => 'decimal:2',
    ];

    public function salida(): BelongsTo
    {
        return $this->belongsTo(SalidaAlmacen::class, 'salida_almacen_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}