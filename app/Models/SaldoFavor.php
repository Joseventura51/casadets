<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaldoFavor extends Model
{
    protected $table = 'saldos_favor';

    protected $fillable = [
        'cliente_id',
        'pago_id',
        'venta_origen_id',
        'monto_original',
        'monto_disponible',
        'estado',
        'descripcion',
        'fecha',
    ];

    protected $casts = [
        'fecha'            => 'date',
        'monto_original'   => 'decimal:2',
        'monto_disponible' => 'decimal:2',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class);
    }

    /**
     * Venta o documento que originó este saldo.
     * Puede ser una nota de crédito o la venta donde hubo excedente de pago.
     */
    public function ventaOrigen(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_origen_id');
    }

    /**
     * ¿Este saldo proviene de una nota de crédito?
     */
    public function esDeNC(): bool
    {
        return $this->venta_origen_id !== null
            && optional($this->ventaOrigen)->documento_tipo === 'nota_credito';
    }
}
