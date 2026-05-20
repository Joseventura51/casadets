<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompraLinea extends Model
{
    protected $table = 'compra_lineas';

    protected $fillable = [
        'compra_id',
        'producto',
        'cantidad',
        'monto_unitario',
        'monto_total',
    ];

    protected $casts = [
        'cantidad'       => 'decimal:2',
        'monto_unitario' => 'decimal:2',
        'monto_total'    => 'decimal:2',
    ];

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class);
    }
}
