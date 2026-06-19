<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Devolucion extends Model
{
    protected $table = 'devoluciones';

    protected $fillable = [
        'venta_id',
        'user_id',
        'tipo',
        'monto_devuelto',
        'saldo_generado',
        'motivo',
        'fecha',
        'empresa',
        'caja_id',
    ];

    protected $casts = [
        'fecha'          => 'date',
        'monto_devuelto' => 'decimal:2',
        'saldo_generado' => 'decimal:2',
    ];

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DevolucionDetalle::class);
    }
}
