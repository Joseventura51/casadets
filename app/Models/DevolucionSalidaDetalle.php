<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DevolucionSalidaDetalle extends Model
{
    protected $table = 'devolucion_salida_detalles';

    protected $fillable = [
        'devolucion_salida_id', 'salida_almacen_detalle_id',
        'producto_id', 'cantidad_devuelta', 'precio_unitario', 'monto',
    ];

    protected $casts = [
        'cantidad_devuelta' => 'decimal:2',
        'precio_unitario' => 'decimal:2',
        'monto' => 'decimal:2',
    ];

    public function devolucion(): BelongsTo
    {
        return $this->belongsTo(DevolucionSalida::class, 'devolucion_salida_id');
    }

    public function detalleSalida(): BelongsTo
    {
        return $this->belongsTo(SalidaAlmacenDetalle::class, 'salida_almacen_detalle_id');
    }
}