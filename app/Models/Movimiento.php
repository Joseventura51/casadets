<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Movimiento extends Model
{
    protected $fillable = [
        'tipo',
        'subtipo',
        'categoria',
        'metodo_pago',
        'referencia_tipo',
        'referencia_id',
        'cliente_id',
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
}
