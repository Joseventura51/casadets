<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use App\Models\ConciliacionAuditoria;

class Compra extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'empresa',
        'caja_id',
        'documento_tipo',
        'documento_numero',
        'fecha',
        'monto_total',
        'metodo_pago',
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
            ->withPivot('cantidad', 'compra_linea_id', 'costo_unitario', 'costo_total')
            ->withTimestamps();
    }

    public function stockMovimientos(): HasMany
    {
        return $this->hasMany(StockMovimiento::class, 'referencia_id')
                    ->where('referencia_tipo', 'compra');
    }

    public function auditorias(): HasMany
    {
        return $this->hasMany(ConciliacionAuditoria::class)->orderBy('created_at', 'desc');
    }

    /**
     * Ventas vinculadas a esta compra (vía detalles).
     * Requiere haber precargado: with('detalles.venta.vendedor') en el controller.
     */
    public function getVentasAttribute(): Collection
    {
        if (!$this->relationLoaded('detalles')) {
            return collect();
        }
        return $this->detalles->pluck('venta')->filter()->unique('id')->values();
    }
}
