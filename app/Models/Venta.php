<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Venta extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'vendedor_id',
        'cliente_id',
        'total',
        'pagado',
        'ajuste',
        'metodo_pago',
        'documento_tipo',
        'documento_numero',
        'observaciones',
        'fecha',
        'estado',
    ];

    protected $casts = [
        'fecha'  => 'date',
        'total'  => 'decimal:2',
        'pagado' => 'decimal:2',
        'ajuste' => 'decimal:2',
    ];

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(Vendedor::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(VentaDetalle::class);
    }

    public function pagoFacturas(): HasMany
    {
        return $this->hasMany(DetallePagoFactura::class);
    }

    public function pagosAplicados()
    {
        return $this->belongsToMany(Pago::class, 'detalle_pago_factura')
                    ->withPivot('monto_aplicado')
                    ->withTimestamps();
    }

    /**
     * Compras vinculadas a esta venta (vía detalles).
     * Requiere haber precargado: with('detalles.compras') en el controller.
     */
    public function getComprasAttribute(): \Illuminate\Support\Collection
    {
        if (!$this->relationLoaded('detalles')) {
            return collect();
        }
        return $this->detalles->flatMap->compras->unique('id')->values();
    }

    public function getTotalCobradoAttribute(): float
    {
        return (float) ($this->attributes['pagado'] ?? 0);
    }

    public function getSaldoPendienteAttribute(): float
    {
        return max(0, (float) $this->total - $this->total_cobrado);
    }

    /**
     * Recalcula y persiste el estado según lo pagado.
     * Usa round() en lugar de offset manual para evitar errores de punto flotante.
     */
    public function recalcularEstado(): void
    {
        if ($this->estado === 'anulado') {
            return;
        }

        $pagado = round((float) $this->pagado, 2);
        $total  = round((float) $this->total, 2);

        if ($pagado <= 0) {
            $estado = 'pendiente';
        } elseif ($pagado >= $total) {
            $estado = 'pagado';
        } else {
            $estado = 'parcial';
        }

        $this->update(['estado' => $estado]);
    }
}
