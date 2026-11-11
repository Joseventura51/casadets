<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DespachoDetalleCosto extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'despacho_detalle_id',
        'compra_venta_detalle_id',
        'cantidad',
        'created_at',
    ];

    protected $casts = [
        'cantidad' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function despachoDetalle(): BelongsTo
    {
        return $this->belongsTo(DespachoDetalle::class);
    }

    public function asignacionCosto(): BelongsTo
    {
        return $this->belongsTo(CompraVentaDetalle::class, 'compra_venta_detalle_id');
    }
}
