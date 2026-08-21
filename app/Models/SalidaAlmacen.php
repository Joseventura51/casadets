<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalidaAlmacen extends Model
{
    public const REALIZADA = 'realizada';
    public const PARCIALMENTE_DEVUELTA = 'parcialmente_devuelta';
    public const DEVUELTA_COMPLETAMENTE = 'devuelta_completamente';
    public const ANULADA = 'anulada';

    protected $table = 'salidas_almacen';

    protected $fillable = [
        'documento', 'fecha', 'almacen_origen_id', 'almacen_destino_id',
        'estado', 'observaciones', 'usuario_id', 'archivo_referencia',
    ];

    protected $casts = ['fecha' => 'date'];

    public function origen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class, 'almacen_origen_id');
    }

    public function destino(): BelongsTo
    {
        return $this->belongsTo(Almacen::class, 'almacen_destino_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(SalidaAlmacenDetalle::class);
    }

    public function devoluciones(): HasMany
    {
        return $this->hasMany(DevolucionSalida::class);
    }
}