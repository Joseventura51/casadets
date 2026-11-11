<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegularizacionCosto extends Model
{
    public $timestamps = false;

    protected $table = 'regularizaciones_costo';

    protected $fillable = [
        'compra_venta_detalle_id',
        'user_id',
        'idempotency_key',
        'costo_anterior',
        'costo_nuevo',
        'diferencia_unitaria',
        'referencia',
        'motivo',
        'created_at',
    ];

    protected $casts = [
        'costo_anterior' => 'decimal:4',
        'costo_nuevo' => 'decimal:4',
        'diferencia_unitaria' => 'decimal:4',
        'created_at' => 'datetime',
    ];

    public function asignacionCosto(): BelongsTo
    {
        return $this->belongsTo(CompraVentaDetalle::class, 'compra_venta_detalle_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function movimientosComision(): HasMany
    {
        return $this->hasMany(ComisionMovimiento::class);
    }
}
