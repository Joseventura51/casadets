<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CajaSesion extends Model
{
    protected $table = 'caja_sesiones';

    protected $fillable = [
        'empresa',
        'caja_id',
        'fecha',
        'monto_apertura',
        'monto_cierre',
        'estado',
        'observaciones',
    ];

    protected $casts = [
        'fecha'          => 'date',
        'monto_apertura' => 'decimal:2',
        'monto_cierre'   => 'decimal:2',
    ];

    public function estaAbierta(): bool
    {
        return $this->estado === 'abierta';
    }

    public function estaCerrada(): bool
    {
        return $this->estado === 'cerrada';
    }

    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class);
    }
}
