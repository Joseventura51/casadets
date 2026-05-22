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
}
