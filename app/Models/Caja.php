<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Caja extends Model
{
    protected $fillable = [
        'codigo',
        'nombre',
        'empresa',
        'activa',
        'esta_abierta',
    ];

    protected $casts = [
        'activa'       => 'boolean',
        'esta_abierta' => 'boolean',
    ];

    public function sesiones(): HasMany
    {
        return $this->hasMany(CajaSesion::class);
    }

    public function series(): HasMany
    {
        return $this->hasMany(Serie::class);
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function usuariosAsignados(): HasMany
    {
        return $this->hasMany(\App\Models\User::class, 'usuario_caja', 'caja_id');
    }

    public function sesionAbierta(): ?CajaSesion
    {
        return $this->sesiones()
            ->where('estado', 'abierta')
            ->latest()
            ->first();
    }

    public function estaAbierta(): bool
    {
        // Lee el booleano de la columna — sin consultar caja_sesiones
        return (bool) $this->esta_abierta;
    }

    /** @deprecated Usar sesionAbierta() */
    public function sesionAbiertaHoy(): ?CajaSesion
    {
        return $this->sesionAbierta();
    }

    /** @deprecated Usar estaAbierta() */
    public function estaAbiertaHoy(): bool
    {
        return $this->estaAbierta();
    }
}
