<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
        'es_supuesto',
    ];

    protected $casts = [
        'fecha'       => 'date',
        'monto_total' => 'decimal:2',
        'es_supuesto' => 'boolean',
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

    /** El registro de ajuste cuando esta compra es el vale supuesto. */
    public function ajusteSupuesto(): HasOne
    {
        return $this->hasOne(AjustePrecioSupuesto::class, 'compra_supuesta_id');
    }

    /** El registro de ajuste cuando esta compra es la compra real (reconciliación). */
    public function ajusteComoReal(): HasOne
    {
        return $this->hasOne(AjustePrecioSupuesto::class, 'compra_real_id');
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
