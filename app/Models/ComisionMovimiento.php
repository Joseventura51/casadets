<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComisionMovimiento extends Model
{
    public $timestamps = false;

    protected $table = 'comision_movimientos';

    protected $fillable = [
        'vendedor_id',
        'venta_id',
        'venta_detalle_id',
        'despacho_id',
        'despacho_detalle_costo_id',
        'regularizacion_costo_id',
        'movimiento_original_id',
        'user_id',
        'tipo',
        'importe',
        'porcentaje',
        'utilidad_base',
        'fecha_origen',
        'fecha_efectiva',
        'origen_clave',
        'observaciones',
        'created_at',
    ];

    protected $casts = [
        'importe' => 'decimal:2',
        'porcentaje' => 'decimal:2',
        'utilidad_base' => 'decimal:2',
        'fecha_origen' => 'date',
        'fecha_efectiva' => 'date',
        'created_at' => 'datetime',
    ];

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(Vendedor::class);
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function ventaDetalle(): BelongsTo
    {
        return $this->belongsTo(VentaDetalle::class);
    }

    public function despacho(): BelongsTo
    {
        return $this->belongsTo(Despacho::class);
    }

    public function original(): BelongsTo
    {
        return $this->belongsTo(self::class, 'movimiento_original_id');
    }
}
