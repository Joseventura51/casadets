<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PagoMetodo extends Model
{
    protected $table = 'pago_metodos';

    protected $fillable = [
        'pago_id',
        'metodo',
        'monto',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
    ];

    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class);
    }
}
