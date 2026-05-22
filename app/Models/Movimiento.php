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
        'categoria',
        'metodo_pago',
        'referencia_tipo',
        'referencia_id',
        'cliente_id',
        'user_id',
        'documento_tipo',
        'documento_numero',
        'monto',
        'fecha',
        'observaciones',
    ];

    protected $casts = [
        'fecha' => 'date',
        'monto' => 'float',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * Relación con Pago usada para eager loading en el ledger.
     * Para acceso seguro usa getPagoDetalleAttribute().
     */
    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class, 'referencia_id');
    }

    /**
     * Retorna el Pago relacionado SOLO cuando este movimiento es de tipo referencia='pago'.
     * Evita cruce de referencia_id entre distintos tipos de referencia.
     */
    public function getPagoDetalleAttribute(): ?Pago
    {
        if ($this->referencia_tipo !== 'pago') {
            return null;
        }

        return $this->getRelationValue('pago');
    }
}
