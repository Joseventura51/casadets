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
        'caja_id',
        'cliente_id',
        'total',
        'ajuste',
        'nc_aplicado',
        'pagado',
        'metodo_pago',
        'documento_tipo',
        'documento_numero',
        'observaciones',
        'fecha',
        'estado',
        'es_referencia_fiscal',
        'igv_incluido',
        'igv_porcentaje',
    ];

    protected $casts = [
        'fecha'                => 'date',
        'total'                => 'decimal:2',
        'ajuste'               => 'decimal:2',
        'nc_aplicado'          => 'decimal:2',
        'pagado'               => 'decimal:2',
        'es_referencia_fiscal' => 'boolean',
        'igv_incluido'         => 'boolean',
        'igv_porcentaje'       => 'decimal:2',
    ];

    // ── Relaciones ──────────────────────────────────────────────────────────

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(Vendedor::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(VentaDetalle::class);
    }

    public function pagoFacturas(): HasMany
    {
        return $this->hasMany(DetallePagoFactura::class);
    }

    public function nubefactComprobante(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Models\NubefactComprobante::class)->latestOfMany();
    }

    public function devoluciones(): HasMany
    {
        return $this->hasMany(Devolucion::class);
    }

    public function pagosAplicados()
    {
        return $this->belongsToMany(Pago::class, 'detalle_pago_factura')
                    ->withPivot('monto_aplicado')
                    ->withTimestamps();
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    /** Excluye referencias fiscales de cualquier cálculo financiero */
    public function scopeNoFiscal($query)
    {
        return $query->where('es_referencia_fiscal', false);
    }

    /** Solo referencias fiscales */
    public function scopeEsFiscal($query)
    {
        return $query->where('es_referencia_fiscal', true);
    }

    // ── Atributos calculados ─────────────────────────────────────────────────

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

    /**
     * Total a cobrar = Total original + ajuste.
     * Este es el importe efectivo que se le cobra al cliente.
     * Si no hay ajuste, coincide con el total original.
     */
    public function getTotalACobrarAttribute(): float
    {
        return round((float) $this->total + (float) $this->ajuste, 2);
    }

    /**
     * Total cobrado = importe ya recibido en pagos.
     * @deprecated En nuevas vistas usar pagado directamente.
     */
    public function getTotalCobradoAttribute(): float
    {
        return (float) ($this->attributes['pagado'] ?? 0);
    }

    /**
     * Saldo pendiente = lo que falta por pagar sobre el Total a Cobrar.
     */
    public function getSaldoPendienteAttribute(): float
    {
        if ($this->es_referencia_fiscal) {
            return 0.0;
        }
        return max(0, $this->total_a_cobrar - (float) $this->pagado);
    }

    // ── Lógica de negocio ────────────────────────────────────────────────────

    /**
     * Recalcula y persiste el estado según lo pagado vs. el total a cobrar.
     */
    public function recalcularEstado(): void
    {
        if (in_array($this->estado, ['anulado', 'anulado_nc'])) {
            return;
        }

        if ($this->es_referencia_fiscal) {
            $this->update(['estado' => 'pagado']);
            return;
        }

        if ($this->documento_tipo === 'nota_credito') {
            $this->update(['estado' => 'pagado']);
            return;
        }

        $pagado       = round((float) $this->pagado, 2);
        $totalCobrar  = round($this->total_a_cobrar, 2);

        if ($pagado <= 0) {
            $estado = 'pendiente';
        } elseif ($pagado >= $totalCobrar) {
            $estado = 'pagado';
        } else {
            $estado = 'parcial';
        }

        $this->update(['estado' => $estado]);
    }
}
