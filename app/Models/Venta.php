<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    /**
     * Compras vinculadas a esta venta (vía sus detalles/productos).
     */
    public function getComprasAttribute(): \Illuminate\Support\Collection
    {
        $this->loadMissing('detalles.compras');
        return collect($this->detalles->flatMap->compras->unique('id')->values()->all());
    }

    public function getTotalCobradoAttribute(): float
    {
        return (float) $this->total + (float) $this->ajuste;
    }
}
