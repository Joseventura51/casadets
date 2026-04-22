<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

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

    public function detalles(): BelongsToMany
    {
        return $this->belongsToMany(VentaDetalle::class, 'compra_venta_detalle', 'compra_id', 'venta_detalle_id')
            ->withTimestamps();
    }

    /**
     * Ventas únicas vinculadas a esta compra (derivadas de los detalles).
     */
    public function getVentasAttribute(): Collection
    {
        $this->loadMissing('detalles.venta.vendedor');
        return collect($this->detalles->pluck('venta')->filter()->unique('id')->values()->all());
    }
}
