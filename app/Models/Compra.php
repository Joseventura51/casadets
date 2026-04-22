<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Compra extends Model
{
    protected $fillable = [
        'empresa',
        'documento_tipo',
        'documento_numero',
        'fecha',
        'producto',
        'cantidad',
        'monto_unitario',
        'monto_total',
        'observaciones',
    ];

    protected $casts = [
        'fecha' => 'date',
        'cantidad' => 'decimal:2',
        'monto_unitario' => 'decimal:2',
        'monto_total' => 'decimal:2',
    ];

    public function ventas(): BelongsToMany
    {
        return $this->belongsToMany(Venta::class, 'compra_venta')->withTimestamps();
    }
}
