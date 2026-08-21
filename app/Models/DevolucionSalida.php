<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DevolucionSalida extends Model
{
    protected $table = 'devoluciones_salida';

    protected $fillable = [
        'salida_almacen_id', 'documento', 'fecha', 'motivo',
        'usuario_id', 'almacen_recepcion_id', 'estado',
    ];

    protected $casts = ['fecha' => 'date'];

    public function salida(): BelongsTo
    {
        return $this->belongsTo(SalidaAlmacen::class, 'salida_almacen_id');
    }

    public function almacenRecepcion(): BelongsTo
    {
        return $this->belongsTo(Almacen::class, 'almacen_recepcion_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DevolucionSalidaDetalle::class);
    }
}