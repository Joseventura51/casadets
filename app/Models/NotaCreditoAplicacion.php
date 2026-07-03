<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotaCreditoAplicacion extends Model
{
    protected $table = 'nota_credito_aplicaciones';

    protected $fillable = [
        'nota_credito_id',
        'venta_id',
        'monto',
        'registrado_por_id',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
    ];

    public function notaCredito(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'nota_credito_id');
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_id');
    }
}
