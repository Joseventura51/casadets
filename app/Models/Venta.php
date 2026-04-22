<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venta extends Model
{
    protected $fillable = [
        'vendedor_id',
        'total',
        'ajuste',
        'metodo_pago',
        'documento_tipo',
        'documento_numero',
        'observaciones',
        'fecha',
    ];

    protected $casts = [
        'fecha' => 'date',
        'total' => 'decimal:2',
        'ajuste' => 'decimal:2',
    ];

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(Vendedor::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(VentaDetalle::class);
    }

    public function compras(): BelongsToMany
    {
        return $this->belongsToMany(Compra::class, 'compra_venta')->withTimestamps();
    }

    public function getTotalCobradoAttribute(): float
    {
        return (float) $this->total + (float) $this->ajuste;
    }
}
