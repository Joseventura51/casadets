<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venta extends Model
{
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

    public function pagos(): HasMany
    {
        return $this->hasMany(VentaPago::class);
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
     * Compras vinculadas a esta venta (vía sus detalles/productos).
     */
    public function getComprasAttribute(): \Illuminate\Support\Collection
    {
        $this->loadMissing('detalles.compras');
        return collect($this->detalles->flatMap->compras->unique('id')->values()->all());
    }

    /**
     * Total cobrado real = suma de pagos aplicados desde detalle_pago_factura.
     * Fallback a columna `pagado` si las relaciones no están cargadas.
     */
    public function getTotalCobradoAttribute(): float
    {
        // Si ya tenemos la columna pagado actualizada, la usamos directamente
        return (float) ($this->attributes['pagado'] ?? 0);
    }

    /**
     * Saldo pendiente de cobro.
     */
    public function getSaldoPendienteAttribute(): float
    {
        return max(0, (float) $this->total - $this->total_cobrado);
    }

    /**
     * Recalcula y guarda el estado según lo pagado.
     * pendiente | parcial | pagado | anulado
     */
    public function recalcularEstado(): void
    {
        if ($this->estado === 'anulado') return;

        $pagado = (float) $this->pagado;
        $total  = (float) $this->total;

        if ($pagado <= 0) {
            $estado = 'pendiente';
        } elseif ($pagado >= $total - 0.005) {
            $estado = 'pagado';
        } else {
            $estado = 'parcial';
        }

        $this->update(['estado' => $estado]);
    }
}
