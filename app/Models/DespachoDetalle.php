<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DespachoDetalle extends Model
{
    protected $fillable = ['despacho_id', 'venta_detalle_id', 'cantidad'];

    protected $casts = ['cantidad' => 'decimal:2'];

    public function despacho(): BelongsTo
    {
        return $this->belongsTo(Despacho::class);
    }

    public function ventaDetalle(): BelongsTo
    {
        return $this->belongsTo(VentaDetalle::class);
    }

    public function costos(): HasMany
    {
        return $this->hasMany(DespachoDetalleCosto::class);
    }
}
