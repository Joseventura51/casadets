<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Movimiento extends Model
{
    protected $fillable = [
        'tipo',
        'subtipo',
        'origen',
        'estado',
        'empresa',
        'caja_id',
        'categoria',
        'metodo_pago',
        'referencia_tipo',
        'referencia_id',
        'cliente_id',
        'vendedor_id',
        'user_id',
        'documento_tipo',
        'documento_numero',
        'monto',
        'fecha',
        'observaciones',
    ];

    protected $casts = [
        'fecha' => 'date',
        'monto' => 'decimal:2',
    ];

    protected $attributes = [
        'estado'  => 'activo',
        'empresa' => 'casadets',
        'origen'  => 'manual',
    ];

    // ── Relaciones ──────────────────────────────────────────────────────────

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(Vendedor::class);
    }

    /**
     * Relación con Pago — solo eager-load cuando referencia_tipo='pago'.
     */
    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class, 'referencia_id');
    }

    // ── Atributos ───────────────────────────────────────────────────────────

    /**
     * Retorna el Pago SOLO cuando este movimiento referencia un pago real.
     * Previene cruce de referencia_id polimórfico.
     */
    public function getPagoDetalleAttribute(): ?Pago
    {
        if ($this->referencia_tipo !== 'pago') {
            return null;
        }
        return $this->getRelationValue('pago');
    }

    /** ¿Este movimiento está anulado? */
    public function getEsAnuladoAttribute(): bool
    {
        return $this->estado === 'anulado';
    }

    /** ¿Afecta el balance de caja? (activo + tipo financiero) */
    public function getAfectaBalanceAttribute(): bool
    {
        return $this->estado === 'activo'
            && in_array($this->tipo, ['ingreso', 'salida']);
    }
}
