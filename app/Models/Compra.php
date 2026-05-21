<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Compra extends Model
{
    protected $fillable = [
        'empresa',
        'documento_tipo',
        'documento_numero',
        'fecha',
        'monto_total',
        'observaciones',
    ];

    protected $casts = [
        'fecha'       => 'date',
        'monto_total' => 'decimal:2',
    ];

    public function lineas(): HasMany
    {
        return $this->hasMany(CompraLinea::class);
    }

    public function detalles(): BelongsToMany
    {
        return $this->belongsToMany(VentaDetalle::class, 'compra_venta_detalle', 'compra_id', 'venta_detalle_id')
            ->withPivot('cantidad', 'compra_linea_id')
            ->withTimestamps();
    }

    public function getVentasAttribute(): Collection
    {
        $this->loadMissing('detalles.venta.vendedor');
        return collect($this->detalles->pluck('venta')->filter()->unique('id')->values()->all());
    }
}
